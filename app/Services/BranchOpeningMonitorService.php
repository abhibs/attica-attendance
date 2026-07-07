<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\BranchOpeningAdminAlert;
use App\Models\BranchOpeningAssignment;
use App\Models\BranchOpeningDailyActivity;
use App\Models\BranchOpeningNotificationLog;
use App\Models\BranchOpeningSetting;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BranchOpeningMonitorService
{
    private const ADMIN_PHONE = '9686266994';
    private const NIGHT_BEFORE_NOTIFICATION_TIME = '21:00';
    private const REMINDER_START_MINUTES = 120;
    private const REMINDER_INTERVAL_MINUTES = 15;

    public function __construct(
        private readonly EmployeeNotificationDispatchService $notificationDispatchService,
        private readonly BranchOpeningAnalyticsService $branchOpeningAnalyticsService
    ) {
    }

    public function run(?Carbon $now = null): void
    {
        $timezone = config('app.timezone', 'Asia/Kolkata');
        $now = ($now ? $now->copy() : Carbon::now($timezone))
            ->setTimezone($timezone)
            ->second(0);

        $this->branchOpeningAnalyticsService->syncDate($now, $now);

        $branches = Branch::query()
            ->where('status', 1)
            ->orderBy('branchName')
            ->get(['branchId', 'branchName'])
            ->keyBy(fn (Branch $branch): string => $this->clean($branch->branchId));

        if ($branches->isEmpty()) {
            return;
        }

        $settings = BranchOpeningSetting::query()
            ->whereIn('branch_id', $branches->keys()->all())
            ->get();

        if ($settings->isEmpty()) {
            return;
        }

        $assignments = BranchOpeningAssignment::query()
            ->whereIn('branch_id', $settings->pluck('branch_id')->map(fn ($value): string => $this->clean($value))->all())
            ->where('assignment_type', BranchOpeningAssignment::TYPE_OPENER)
            ->get(['branch_id', 'employee_id'])
            ->groupBy(fn (BranchOpeningAssignment $assignment): string => $this->clean($assignment->branch_id));

        $employeeIds = $assignments
            ->flatten(1)
            ->pluck('employee_id')
            ->map(fn ($value): int => (int) $value)
            ->filter()
            ->unique()
            ->values();

        if ($employeeIds->isEmpty()) {
            return;
        }

        $employeesById = Employee::query()
            ->whereIn('id', $employeeIds->all())
            ->get(['id', 'empId', 'name', 'status'])
            ->keyBy('id');

        $branchOpeningIndex = $this->todayBranchOpeningIndex(
            $now,
            $settings->pluck('branch_id')
        );

        $activityByBranch = BranchOpeningDailyActivity::query()
            ->whereDate('attendance_date', $now->toDateString())
            ->get([
                'branch_id',
                'opened_at',
                'opened_by_employee_id',
                'opened_by_emp_id',
                'opened_by_name',
            ])
            ->keyBy(fn (BranchOpeningDailyActivity $activity): string => $this->clean($activity->branch_id));

        foreach ($settings as $setting) {
            $branchId = $this->clean($setting->branch_id);
            $branch = $branches->get($branchId);

            if (! $branch instanceof Branch) {
                continue;
            }

            $openingTime = $this->normalizeTime($setting->opening_time);
            if ($openingTime === '') {
                continue;
            }

            $openers = collect($assignments->get($branchId, []))
                ->map(fn (BranchOpeningAssignment $assignment) => $employeesById->get((int) $assignment->employee_id))
                ->filter(fn ($employee): bool => $employee instanceof Employee && $this->isEligibleEmployee($employee))
                ->values();

            if ($openers->isEmpty()) {
                continue;
            }

            $openingToday = Carbon::createFromFormat(
                'Y-m-d H:i',
                $now->toDateString().' '.$openingTime,
                $timezone
            )->second(0);

            $adminPhone = $this->resolveAdminPhone($setting->admin_phone);
            /** @var BranchOpeningDailyActivity|null $activity */
            $activity = $activityByBranch->get($branchId);

            $this->sendNightBeforeReminder($branch, $openers, $openingToday->copy()->addDay(), $now);
            $this->sendCountdownReminder($branch, $openers, $openingToday, $now, $branchOpeningIndex, $activity, $adminPhone);
            $this->sendOverdueReminder($branch, $openers, $openingToday, $now, $branchOpeningIndex, $activity, $adminPhone);
            $this->syncAdminAlert($branch, $openers, $openingToday, $now, $branchOpeningIndex, $activity);
        }
    }

    private function sendNightBeforeReminder(Branch $branch, Collection $openers, Carbon $openingTomorrow, Carbon $now): void
    {
        $nightBefore = $openingTomorrow->copy()
            ->subDay()
            ->setTimeFromTimeString(self::NIGHT_BEFORE_NOTIFICATION_TIME)
            ->second(0);

        if (! $now->isSameDay($nightBefore) || $now->lt($nightBefore)) {
            return;
        }

        $title = 'Branch opening tomorrow';
        $body = sprintf(
            'You have branch opening tomorrow at %s for %s.',
            $openingTomorrow->format('H:i'),
            $this->branchLabel($branch)
        );

        $this->sendLoggedNotifications(
            $branch,
            $openers,
            'night_before',
            $openingTomorrow->toDateString(),
            $title,
            $body
        );
    }

    private function sendCountdownReminder(
        Branch $branch,
        Collection $openers,
        Carbon $openingToday,
        Carbon $now,
        array $branchOpeningIndex,
        ?BranchOpeningDailyActivity $activity,
        string $adminPhone
    ): void
    {
        $windowStart = $openingToday->copy()->subMinutes(self::REMINDER_START_MINUTES);

        if ($now->lt($windowStart) || $now->gt($openingToday)) {
            return;
        }

        $offset = $this->latestDueCountdownOffset($openingToday, $now);
        if ($offset === null) {
            return;
        }

        if ($this->branchOpenedAt($this->clean($branch->branchId), $openingToday, $branchOpeningIndex, $activity) instanceof Carbon) {
            return;
        }

        $title = 'Branch opening reminder';
        $body = implode("\n", [
            'Branch needs to be opened at the given time: '.$openingToday->format('h:i A').'.',
            'You need to reach in : '.$this->notificationDuration($offset).'.',
            'If you are late call admin now at: '.$adminPhone.'.',
        ]);

        $this->sendLoggedNotifications(
            $branch,
            $openers,
            'countdown',
            $openingToday->toDateString().'|'.$offset,
            $title,
            $body
        );
    }

    private function sendOverdueReminder(
        Branch $branch,
        Collection $openers,
        Carbon $openingToday,
        Carbon $now,
        array $branchOpeningIndex,
        ?BranchOpeningDailyActivity $activity,
        string $adminPhone
    ): void
    {
        if ($now->lte($openingToday)) {
            return;
        }

        $overdueBucket = $this->latestDueOverdueBucket($openingToday, $now);
        if ($overdueBucket === null) {
            return;
        }

        if ($this->branchOpenedAt($this->clean($branch->branchId), $openingToday, $branchOpeningIndex, $activity) instanceof Carbon) {
            return;
        }

        $overdueMinutes = max(1, $openingToday->diffInMinutes($now));
        $title = 'Branch opening reminder';
        $body = implode("\n", [
            'Branch needs to be opened at the given time: '.$openingToday->format('h:i A').'.',
            'You are late by : '.$this->notificationDuration($overdueMinutes).'.',
            'If you are late call admin now at: '.$adminPhone.'.',
        ]);

        $this->sendLoggedNotifications(
            $branch,
            $openers,
            'overdue',
            $openingToday->toDateString().'|'.$overdueBucket,
            $title,
            $body
        );
    }

    private function syncAdminAlert(
        Branch $branch,
        Collection $openers,
        Carbon $openingToday,
        Carbon $now,
        array $branchOpeningIndex,
        ?BranchOpeningDailyActivity $activity
    ): void
    {
        if ($now->lte($openingToday)) {
            return;
        }

        $firstLogin = $this->branchOpenedAt(
            $this->clean($branch->branchId),
            $openingToday,
            $branchOpeningIndex,
            $activity
        );

        /** @var BranchOpeningAdminAlert $alert */
        $alert = BranchOpeningAdminAlert::query()->firstOrNew([
            'branch_id' => $this->clean($branch->branchId),
            'opening_date' => $openingToday->toDateString(),
        ]);

        $alert->branch_name = $this->clean($branch->branchName);
        $alert->opening_time = $openingToday->format('H:i:s');

        if ($firstLogin instanceof Carbon) {
            if ($firstLogin->lte($openingToday)) {
                if ($alert->exists && $alert->status === BranchOpeningAdminAlert::STATUS_OVERDUE) {
                    $alert->status = BranchOpeningAdminAlert::STATUS_RESOLVED_ON_TIME;
                    $alert->opened_at = $firstLogin;
                    $alert->resolved_at = $now;
                    $alert->overdue_minutes = 0;
                    $this->fillOpenerFields($alert, $firstLogin, $branch, $openingToday, $branchOpeningIndex, $activity);
                    $alert->save();
                }

                return;
            }

            $alert->status = BranchOpeningAdminAlert::STATUS_RESOLVED_LATE;
            $alert->notified_at ??= $openingToday;
            $alert->opened_at = $firstLogin;
            $alert->resolved_at = $now;
            $alert->overdue_minutes = $openingToday->diffInMinutes($firstLogin);
            $this->fillOpenerFields($alert, $firstLogin, $branch, $openingToday, $branchOpeningIndex, $activity);
            $alert->save();

            return;
        }

        $alert->status = BranchOpeningAdminAlert::STATUS_OVERDUE;
        $alert->notified_at ??= $openingToday;
        $alert->resolved_at = null;
        $alert->opened_at = null;
        $alert->overdue_minutes = $openingToday->diffInMinutes($now);
        $alert->save();
    }

    private function fillOpenerFields(
        BranchOpeningAdminAlert $alert,
        Carbon $loginTime,
        Branch $branch,
        Carbon $openingToday,
        array $branchOpeningIndex,
        ?BranchOpeningDailyActivity $activity
    ): void {
        if (
            $activity instanceof BranchOpeningDailyActivity &&
            $activity->opened_at instanceof Carbon &&
            $activity->opened_at->equalTo($loginTime)
        ) {
            $alert->opener_employee_id = $activity->opened_by_employee_id;
            $alert->opener_emp_id = $this->clean($activity->opened_by_emp_id);
            $alert->opener_name = $this->clean($activity->opened_by_name);

            return;
        }

        $alert->opener_employee_id = null;
        $alert->opener_emp_id = null;
        $alert->opener_name = null;

        $branchId = $this->clean($branch->branchId);
        $row = $branchOpeningIndex[$branchId] ?? null;

        if (! is_array($row)) {
            return;
        }

        $loggedAt = $row['at'] ?? null;
        if (! $loggedAt instanceof Carbon || ! $loggedAt->equalTo($loginTime)) {
            return;
        }

        $alert->opener_employee_id = $row['employee_id'] ?? null;
        $alert->opener_emp_id = $this->clean($row['emp_id'] ?? null);
        $alert->opener_name = $this->clean($row['employee_name'] ?? null);
    }

    private function resolveAdminPhone($value): string
    {
        $phone = preg_replace('/[^0-9+\-\s]/', '', trim((string) $value));
        $phone = trim((string) $phone);

        return $phone !== '' ? $phone : self::ADMIN_PHONE;
    }

    private function sendLoggedNotifications(
        Branch $branch,
        Collection $employees,
        string $type,
        string $key,
        string $title,
        string $body
    ): void {
        $branchId = $this->clean($branch->branchId);
        $pendingEmployees = $employees
            ->filter(function (Employee $employee) use ($branchId, $type, $key): bool {
                return ! BranchOpeningNotificationLog::query()
                    ->where('branch_id', $branchId)
                    ->where('employee_id', (int) $employee->id)
                    ->where('notification_type', $type)
                    ->where('notification_key', $key)
                    ->exists();
            })
            ->values();

        if ($pendingEmployees->isEmpty()) {
            return;
        }

        $this->notificationDispatchService->sendToEmployees(
            $pendingEmployees,
            $title,
            $body,
            null,
            'branch_opening',
            $branchId
        );

        $now = now();
        $rows = $pendingEmployees
            ->map(fn (Employee $employee): array => [
                'branch_id' => $branchId,
                'employee_id' => (int) $employee->id,
                'notification_type' => $type,
                'notification_key' => $key,
                'title' => $title,
                'body' => $body,
                'sent_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($rows !== []) {
            BranchOpeningNotificationLog::query()->insertOrIgnore($rows);
        }
    }

    private function latestDueCountdownOffset(Carbon $openingToday, Carbon $now): ?int
    {
        for ($offset = 0; $offset <= self::REMINDER_START_MINUTES; $offset += self::REMINDER_INTERVAL_MINUTES) {
            if ($now->gte($openingToday->copy()->subMinutes($offset))) {
                return $offset;
            }
        }

        return null;
    }

    private function latestDueOverdueBucket(Carbon $openingToday, Carbon $now): ?int
    {
        if ($now->lte($openingToday)) {
            return null;
        }

        $elapsed = $openingToday->diffInMinutes($now);

        return intdiv($elapsed, self::REMINDER_INTERVAL_MINUTES) * self::REMINDER_INTERVAL_MINUTES;
    }

    private function notificationDuration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d hrs', $hours, $remainingMinutes);
        }

        return $remainingMinutes.' mins';
    }

    private function branchLabel(Branch $branch): string
    {
        return trim($this->clean($branch->branchId).' - '.$this->clean($branch->branchName), ' -');
    }

    private function branchOpenedAt(
        string $branchId,
        Carbon $openingToday,
        array $branchOpeningIndex,
        ?BranchOpeningDailyActivity $activity
    ): ?Carbon
    {
        $row = $branchOpeningIndex[$branchId] ?? null;
        $attendanceOpenedAt = is_array($row) && ($row['at'] ?? null) instanceof Carbon
            ? $row['at']->copy()->setTimezone($openingToday->timezone)
            : null;

        $manualOpenedAt = $activity?->opened_at instanceof Carbon
            ? $activity->opened_at->copy()->setTimezone($openingToday->timezone)
            : null;

        if ($manualOpenedAt instanceof Carbon && $attendanceOpenedAt instanceof Carbon) {
            return $manualOpenedAt->lte($attendanceOpenedAt)
                ? $manualOpenedAt
                : $attendanceOpenedAt;
        }

        return $manualOpenedAt ?? $attendanceOpenedAt;
    }

    private function todayBranchOpeningIndex(Carbon $now, Collection $branchIds): array
    {
        $branchIds = $branchIds
            ->map(fn ($value): string => $this->clean($value))
            ->filter()
            ->unique()
            ->values();

        if ($branchIds->isEmpty()) {
            return [];
        }

        $rows = Attendance::query()
            ->where('check_in_date', $now->toDateString())
            ->whereIn('check_in_branch_id', $branchIds->all())
            ->get(['empId', 'check_in_branch_id', 'check_in_date', 'check_in_time']);

        $employeeEmpIds = $rows
            ->pluck('empId')
            ->map(fn ($value): string => $this->clean($value))
            ->filter()
            ->unique()
            ->values();

        $employeesByEmpId = $employeeEmpIds->isEmpty()
            ? collect()
            : Employee::query()
                ->whereIn('empId', $employeeEmpIds->all())
                ->get(['id', 'empId', 'name'])
                ->keyBy(fn (Employee $employee): string => $this->clean($employee->empId));

        $index = [];

        foreach ($rows as $row) {
            $branchId = $this->clean($row->check_in_branch_id);
            $empId = $this->clean($row->empId);
            $checkInTime = $this->clean($row->check_in_time);

            if (
                $branchId === '' ||
                $empId === '' ||
                $checkInTime === '' ||
                ! $branchIds->contains($branchId)
            ) {
                continue;
            }

            try {
                $timestamp = Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $row->check_in_date.' '.$checkInTime,
                    $now->timezone
                );
            } catch (\Throwable) {
                continue;
            }

            /** @var Employee|null $employee */
            $employee = $employeesByEmpId->get($empId);
            $existing = $index[$branchId]['at'] ?? null;

            if (! $existing instanceof Carbon || $timestamp->lt($existing)) {
                $index[$branchId] = [
                    'at' => $timestamp,
                    'employee_id' => $employee?->id,
                    'emp_id' => $empId,
                    'employee_name' => $this->clean($employee?->name),
                ];
            }
        }

        return $index;
    }

    private function isEligibleEmployee(Employee $employee): bool
    {
        $status = strtolower($this->clean($employee->status));

        return $status === '' || $status !== 'inactive';
    }

    private function normalizeTime($value): string
    {
        $time = $this->clean($value);

        if ($time === '') {
            return '';
        }

        try {
            return Carbon::createFromFormat('H:i:s', $time)->format('H:i');
        } catch (\Throwable) {
            try {
                return Carbon::createFromFormat('H:i', $time)->format('H:i');
            } catch (\Throwable) {
                return '';
            }
        }
    }

    private function clean($value): string
    {
        return trim((string) $value);
    }
}
