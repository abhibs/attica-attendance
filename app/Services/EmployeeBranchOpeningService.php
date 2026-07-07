<?php

namespace App\Services;

use App\Models\BranchOpeningAssignment;
use App\Models\BranchOpeningDailyActivity;
use App\Models\BranchOpeningDailySummary;
use App\Models\BranchOpeningSetting;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class EmployeeBranchOpeningService
{
    public function __construct(
        private readonly BranchOpeningAnalyticsService $branchOpeningAnalyticsService
    ) {
    }

    public function payloadForEmployee(string $branchId, Employee $employee): array
    {
        $branchId = $this->clean($branchId);
        $today = Carbon::now($this->timezone())->toDateString();

        if ($branchId === '') {
            return $this->emptyPayload();
        }

        $assignmentTypes = BranchOpeningAssignment::query()
            ->where('branch_id', $branchId)
            ->where('employee_id', $employee->id)
            ->pluck('assignment_type')
            ->map(fn ($value): string => $this->clean($value))
            ->filter()
            ->unique()
            ->values();

        $setting = BranchOpeningSetting::query()
            ->where('branch_id', $branchId)
            ->first(['opening_time', 'admin_phone']);

        /** @var BranchOpeningDailySummary|null $summary */
        $summary = BranchOpeningDailySummary::query()
            ->where('branch_id', $branchId)
            ->whereDate('attendance_date', $today)
            ->first([
                'opening_status',
                'opened_at',
                'opened_by_emp_id',
                'opened_by_name',
                'closed_at',
                'closed_by_emp_id',
                'closed_by_name',
            ]);

        /** @var BranchOpeningDailyActivity|null $activity */
        $activity = BranchOpeningDailyActivity::query()
            ->where('branch_id', $branchId)
            ->whereDate('attendance_date', $today)
            ->first([
                'opened_at',
                'opened_by_emp_id',
                'opened_by_name',
                'closed_at',
                'closed_by_emp_id',
                'closed_by_name',
            ]);

        $openedAt = $summary?->opened_at ?? $activity?->opened_at;
        $closedAt = $summary?->closed_at ?? $activity?->closed_at;
        $openedByLabel = $this->personLabel(
            $summary?->opened_by_emp_id ?: $activity?->opened_by_emp_id,
            $summary?->opened_by_name ?: $activity?->opened_by_name
        );
        $closedByLabel = $this->personLabel(
            $summary?->closed_by_emp_id ?: $activity?->closed_by_emp_id,
            $summary?->closed_by_name ?: $activity?->closed_by_name
        );

        $hasDoorKey = $assignmentTypes->contains(BranchOpeningAssignment::TYPE_DOOR_KEY);
        $hasLockerKey = $assignmentTypes->contains(BranchOpeningAssignment::TYPE_LOCKER_KEY);
        $isOpener = $assignmentTypes->contains(BranchOpeningAssignment::TYPE_OPENER);
        $isAssigned = $assignmentTypes->isNotEmpty();
        $openingTime = $this->formatOpeningTime($setting?->opening_time);

        return [
            'is_assigned' => $isAssigned,
            'is_opener' => $isOpener,
            'has_door_key' => $hasDoorKey,
            'has_locker_key' => $hasLockerKey,
            'assignment_types' => $assignmentTypes->all(),
            'opening_time' => $openingTime,
            'admin_phone' => $this->normalizePhone($setting?->admin_phone),
            'managed_by_server' => $isOpener && $openingTime !== '',
            'status' => $this->resolveStatus($summary?->opening_status, $openedAt, $closedAt),
            'opened_at' => $openedAt?->copy()->setTimezone($this->timezone())->toIso8601String() ?? '',
            'opened_by_label' => $openedByLabel,
            'closed_at' => $closedAt?->copy()->setTimezone($this->timezone())->toIso8601String() ?? '',
            'closed_by_label' => $closedByLabel,
            'can_mark_opened' => $isAssigned && ! ($openedAt instanceof Carbon),
            'can_mark_closed' => $isAssigned && $openedAt instanceof Carbon && ! ($closedAt instanceof Carbon),
        ];
    }

    public function markOpened(string $branchId, Employee $employee): array
    {
        $branchId = $this->clean($branchId);
        $this->ensureAssigned($branchId, $employee);

        $timezone = $this->timezone();
        $now = Carbon::now($timezone)->second(0);
        $today = $now->toDateString();

        /** @var BranchOpeningDailyActivity $activity */
        $activity = BranchOpeningDailyActivity::query()->firstOrNew([
            'branch_id' => $branchId,
            'attendance_date' => $today,
        ]);

        if ($activity->opened_at instanceof Carbon) {
            return [
                'message' => 'Branch is already marked as opened.',
                'branchOpening' => $this->payloadForEmployee($branchId, $employee),
            ];
        }

        $activity->opened_at = $now;
        $activity->opened_by_employee_id = $employee->id;
        $activity->opened_by_emp_id = $this->clean($employee->empId);
        $activity->opened_by_name = $this->clean($employee->name);
        $activity->save();

        $this->branchOpeningAnalyticsService->syncDate($now, $now);

        return [
            'message' => 'Branch marked as opened.',
            'branchOpening' => $this->payloadForEmployee($branchId, $employee),
        ];
    }

    public function markClosed(string $branchId, Employee $employee): array
    {
        $branchId = $this->clean($branchId);
        $this->ensureAssigned($branchId, $employee);

        $timezone = $this->timezone();
        $now = Carbon::now($timezone)->second(0);
        $today = $now->toDateString();

        /** @var BranchOpeningDailyActivity $activity */
        $activity = BranchOpeningDailyActivity::query()->firstOrNew([
            'branch_id' => $branchId,
            'attendance_date' => $today,
        ]);

        if (! ($activity->opened_at instanceof Carbon)) {
            $summaryOpenedAt = BranchOpeningDailySummary::query()
                ->where('branch_id', $branchId)
                ->whereDate('attendance_date', $today)
                ->value('opened_at');

            if (! $summaryOpenedAt) {
                throw ValidationException::withMessages([
                    'branchOpening' => ['Mark the branch as opened first.'],
                ]);
            }
        }

        if ($activity->closed_at instanceof Carbon) {
            return [
                'message' => 'Branch is already marked as closed.',
                'branchOpening' => $this->payloadForEmployee($branchId, $employee),
            ];
        }

        $activity->closed_at = $now;
        $activity->closed_by_employee_id = $employee->id;
        $activity->closed_by_emp_id = $this->clean($employee->empId);
        $activity->closed_by_name = $this->clean($employee->name);
        $activity->save();

        $this->branchOpeningAnalyticsService->syncDate($now, $now);

        return [
            'message' => 'Branch marked as closed.',
            'branchOpening' => $this->payloadForEmployee($branchId, $employee),
        ];
    }

    private function ensureAssigned(string $branchId, Employee $employee): void
    {
        if ($branchId === '') {
            throw ValidationException::withMessages([
                'branchOpening' => ['Assigned branch could not be found.'],
            ]);
        }

        $assigned = BranchOpeningAssignment::query()
            ->where('branch_id', $branchId)
            ->where('employee_id', $employee->id)
            ->exists();

        if (! $assigned) {
            throw ValidationException::withMessages([
                'branchOpening' => ['You are not assigned for branch opening on this branch.'],
            ]);
        }
    }

    private function resolveStatus(?string $summaryStatus, ?Carbon $openedAt, ?Carbon $closedAt): string
    {
        if ($closedAt instanceof Carbon) {
            return 'closed';
        }

        if ($openedAt instanceof Carbon) {
            return 'opened';
        }

        $status = $this->clean($summaryStatus);

        return $status !== '' ? $status : 'pending';
    }

    private function personLabel($empId, $name): string
    {
        return trim($this->clean($empId).' '.$this->clean($name));
    }

    private function formatOpeningTime($value): string
    {
        $time = $this->clean($value);

        if ($time === '') {
            return '';
        }

        try {
            return Carbon::createFromFormat('H:i:s', $time, $this->timezone())->format('H:i');
        } catch (\Throwable) {
            try {
                return Carbon::createFromFormat('H:i', $time, $this->timezone())->format('H:i');
            } catch (\Throwable) {
                return '';
            }
        }
    }

    private function normalizePhone($value): string
    {
        return preg_replace('/[^0-9+\-\s]/', '', $this->clean($value)) ?: '';
    }

    private function clean($value): string
    {
        return trim((string) $value);
    }

    private function timezone(): string
    {
        return (string) config('app.timezone', 'Asia/Kolkata');
    }

    private function emptyPayload(): array
    {
        return [
            'is_assigned' => false,
            'is_opener' => false,
            'has_door_key' => false,
            'has_locker_key' => false,
            'assignment_types' => [],
            'opening_time' => '',
            'admin_phone' => '',
            'managed_by_server' => false,
            'status' => 'pending',
            'opened_at' => '',
            'opened_by_label' => '',
            'closed_at' => '',
            'closed_by_label' => '',
            'can_mark_opened' => false,
            'can_mark_closed' => false,
        ];
    }
}
