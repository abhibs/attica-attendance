<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\BranchOpeningDailyActivity;
use App\Models\BranchOpeningDailySummary;
use App\Models\BranchOpeningSetting;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BranchOpeningAnalyticsService
{
    public function syncDate(?Carbon $date = null, ?Carbon $referenceNow = null): void
    {
        $timezone = $this->timezone();
        $date = ($date ? $date->copy() : Carbon::now($timezone))->setTimezone($timezone)->startOfDay();
        $referenceNow = ($referenceNow ? $referenceNow->copy() : Carbon::now($timezone))->setTimezone($timezone);
        $dateString = $date->toDateString();

        $branches = Branch::query()
            ->get(['branchId', 'branchName'])
            ->keyBy(fn (Branch $branch): string => $this->clean($branch->branchId));

        $settings = BranchOpeningSetting::query()
            ->get(['branch_id', 'opening_time'])
            ->keyBy(fn (BranchOpeningSetting $setting): string => $this->clean($setting->branch_id));

        $rawCheckIns = Attendance::query()
            ->where('check_in_date', $dateString)
            ->get(['empId', 'check_in_branch_id', 'check_in_date', 'check_in_time']);

        $rawCheckOuts = Attendance::query()
            ->where('check_out_date', $dateString)
            ->get(['empId', 'check_out_branch_id', 'check_out_date', 'check_out_time']);

        $manualActivities = BranchOpeningDailyActivity::query()
            ->whereDate('attendance_date', $dateString)
            ->get([
                'branch_id',
                'opened_at',
                'opened_by_employee_id',
                'opened_by_emp_id',
                'opened_by_name',
                'closed_at',
                'closed_by_employee_id',
                'closed_by_emp_id',
                'closed_by_name',
            ])
            ->keyBy(fn (BranchOpeningDailyActivity $activity): string => $this->clean($activity->branch_id));

        $employeeEmpIds = $rawCheckIns
            ->pluck('empId')
            ->merge($rawCheckOuts->pluck('empId'))
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

        $checkInsByBranch = $rawCheckIns
            ->map(fn (Attendance $attendance): ?array => $this->mapCheckInRow($attendance, $employeesByEmpId, $timezone))
            ->filter()
            ->groupBy('branch_id')
            ->map(fn (Collection $rows): Collection => $rows->sortBy(fn (array $row): int => $row['at']->getTimestamp())->values());

        $checkOutsByBranch = $rawCheckOuts
            ->map(fn (Attendance $attendance): ?array => $this->mapCheckOutRow($attendance, $employeesByEmpId, $timezone))
            ->filter()
            ->groupBy('branch_id')
            ->map(fn (Collection $rows): Collection => $rows->sortByDesc(fn (array $row): int => $row['at']->getTimestamp())->values());

        $trackedBranchIds = $settings->keys()
            ->merge($checkInsByBranch->keys())
            ->merge($checkOutsByBranch->keys())
            ->map(fn ($value): string => $this->clean($value))
            ->filter()
            ->unique()
            ->values();

        if ($trackedBranchIds->isEmpty()) {
            BranchOpeningDailySummary::query()
                ->where('attendance_date', $dateString)
                ->delete();

            return;
        }

        $rows = $trackedBranchIds
            ->map(function (string $branchId) use (
                $branches,
                $settings,
                $checkInsByBranch,
                $checkOutsByBranch,
                $manualActivities,
                $date,
                $referenceNow
            ): array {
                /** @var Branch|null $branch */
                $branch = $branches->get($branchId);
                /** @var BranchOpeningSetting|null $setting */
                $setting = $settings->get($branchId);
                $scheduledTime = $this->normalizeTime($setting?->opening_time);
                $scheduledOpeningAt = $scheduledTime !== null
                    ? $this->parseDateTime($date->toDateString(), $scheduledTime, $this->timezone())
                    : null;

                /** @var Collection<int, array> $branchCheckIns */
                $branchCheckIns = $checkInsByBranch->get($branchId, collect());
                /** @var Collection<int, array> $branchCheckOuts */
                $branchCheckOuts = $checkOutsByBranch->get($branchId, collect());
                /** @var BranchOpeningDailyActivity|null $manualActivity */
                $manualActivity = $manualActivities->get($branchId);

                $firstCheckIn = $branchCheckIns->first();
                $closedRow = $branchCheckOuts->first();

                $openedAt = $manualActivity?->opened_at ?? ($firstCheckIn['at'] ?? null);
                $closedAt = $manualActivity?->closed_at ?? ($closedRow['at'] ?? null);
                $openingStatus = $this->openingStatus($scheduledOpeningAt, $openedAt, $date, $referenceNow);
                $openingDelayMinutes = $this->openingDelayMinutes($scheduledOpeningAt, $openedAt, $date, $referenceNow);
                $openDurationMinutes = $this->openDurationMinutes($openedAt, $closedAt);

                return [
                    'branch_id' => $branchId,
                    'branch_name' => $this->clean($branch?->branchName),
                    'attendance_date' => $date->toDateString(),
                    'scheduled_opening_time' => $scheduledTime,
                    'opening_status' => $openingStatus,
                    'opened_at' => $openedAt?->toDateTimeString(),
                    'opened_by_employee_id' => $manualActivity?->opened_by_employee_id ?? ($firstCheckIn['employee_id'] ?? null),
                    'opened_by_emp_id' => $manualActivity?->opened_by_emp_id ?? ($firstCheckIn['emp_id'] ?? null),
                    'opened_by_name' => $manualActivity?->opened_by_name ?? ($firstCheckIn['employee_name'] ?? null),
                    'first_check_in_at' => ($firstCheckIn['at'] ?? null)?->toDateTimeString(),
                    'first_check_in_emp_id' => $firstCheckIn['emp_id'] ?? null,
                    'first_check_in_name' => $firstCheckIn['employee_name'] ?? null,
                    'closed_at' => $closedAt?->toDateTimeString(),
                    'closed_by_employee_id' => $manualActivity?->closed_by_employee_id ?? ($closedRow['employee_id'] ?? null),
                    'closed_by_emp_id' => $manualActivity?->closed_by_emp_id ?? ($closedRow['emp_id'] ?? null),
                    'closed_by_name' => $manualActivity?->closed_by_name ?? ($closedRow['employee_name'] ?? null),
                    'total_check_ins' => $branchCheckIns->count(),
                    'total_check_outs' => $branchCheckOuts->count(),
                    'opening_delay_minutes' => $openingDelayMinutes,
                    'open_duration_minutes' => $openDurationMinutes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })
            ->all();

        BranchOpeningDailySummary::query()->upsert(
            $rows,
            ['branch_id', 'attendance_date'],
            [
                'branch_name',
                'scheduled_opening_time',
                'opening_status',
                'opened_at',
                'opened_by_employee_id',
                'opened_by_emp_id',
                'opened_by_name',
                'first_check_in_at',
                'first_check_in_emp_id',
                'first_check_in_name',
                'closed_at',
                'closed_by_employee_id',
                'closed_by_emp_id',
                'closed_by_name',
                'total_check_ins',
                'total_check_outs',
                'opening_delay_minutes',
                'open_duration_minutes',
                'updated_at',
            ]
        );

        BranchOpeningDailySummary::query()
            ->where('attendance_date', $dateString)
            ->whereNotIn('branch_id', $trackedBranchIds->all())
            ->delete();
    }

    public function syncRange(Carbon $from, Carbon $to, ?Carbon $referenceNow = null): void
    {
        $timezone = $this->timezone();
        $start = $from->copy()->setTimezone($timezone)->startOfDay();
        $end = $to->copy()->setTimezone($timezone)->startOfDay();

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $this->syncDate($cursor, $referenceNow);
            $cursor->addDay();
        }
    }

    public function dashboardData(?Carbon $today = null): array
    {
        $timezone = $this->timezone();
        $today = ($today ? $today->copy() : Carbon::now($timezone))->setTimezone($timezone)->startOfDay();

        $this->syncDate($today, Carbon::now($timezone));

        $attentionRows = BranchOpeningDailySummary::query()
            ->whereDate('attendance_date', $today->toDateString())
            ->whereIn('opening_status', [
                BranchOpeningDailySummary::STATUS_LATE,
                BranchOpeningDailySummary::STATUS_NOT_OPENED,
            ])
            ->orderByRaw("CASE WHEN opening_status = 'not_opened' THEN 0 ELSE 1 END")
            ->orderByDesc('opening_delay_minutes')
            ->orderBy('branch_name')
            ->get();

        return [
            'late_count' => $attentionRows->where('opening_status', BranchOpeningDailySummary::STATUS_LATE)->count(),
            'overdue_count' => $attentionRows->where('opening_status', BranchOpeningDailySummary::STATUS_NOT_OPENED)->count(),
            'attention_rows' => $attentionRows,
        ];
    }

    public function reportData(Carbon $from, Carbon $to, string $branchId = ''): array
    {
        $query = BranchOpeningDailySummary::query()
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('attendance_date')
            ->orderBy('branch_name');

        if ($branchId !== '') {
            $query->where('branch_id', $branchId);
        }

        /** @var Collection<int, BranchOpeningDailySummary> $rows */
        $rows = $query->get();
        $lateRows = $rows->where('opening_status', BranchOpeningDailySummary::STATUS_LATE)->values();
        $onTimeRows = $rows->where('opening_status', BranchOpeningDailySummary::STATUS_ON_TIME)->values();
        $notOpenedRows = $rows->where('opening_status', BranchOpeningDailySummary::STATUS_NOT_OPENED)->values();

        $mostLateBranches = $lateRows
            ->groupBy('branch_id')
            ->map(function (Collection $items, string $groupBranchId): array {
                /** @var BranchOpeningDailySummary|null $first */
                $first = $items->first();

                return [
                    'branch_id' => $groupBranchId,
                    'branch_name' => $this->clean($first?->branch_name),
                    'late_count' => $items->count(),
                    'average_delay_minutes' => (int) round($items->avg('opening_delay_minutes') ?? 0),
                    'max_delay_minutes' => (int) ($items->max('opening_delay_minutes') ?? 0),
                ];
            })
            ->sortBy([
                ['late_count', 'desc'],
                ['average_delay_minutes', 'desc'],
                ['branch_name', 'asc'],
            ])
            ->values()
            ->take(10);

        $shortestOpenTimes = $rows
            ->filter(fn (BranchOpeningDailySummary $row): bool => (int) ($row->open_duration_minutes ?? 0) > 0)
            ->sortBy([
                ['open_duration_minutes', 'asc'],
                ['attendance_date', 'desc'],
                ['branch_name', 'asc'],
            ])
            ->values()
            ->take(10);

        return [
            'rows' => $rows,
            'metrics' => [
                'tracked_days' => $rows->count(),
                'on_time_count' => $onTimeRows->count(),
                'late_count' => $lateRows->count(),
                'not_opened_count' => $notOpenedRows->count(),
                'average_opening_time' => $this->averageTimeOfDayLabel($rows, 'opened_at'),
                'average_closing_time' => $this->averageTimeOfDayLabel($rows, 'closed_at'),
                'average_open_duration' => $this->averageDurationLabel($rows, 'open_duration_minutes'),
            ],
            'most_late_branches' => $mostLateBranches,
            'shortest_open_times' => $shortestOpenTimes,
        ];
    }

    private function mapCheckInRow(Attendance $attendance, Collection $employeesByEmpId, string $timezone): ?array
    {
        $branchId = $this->clean($attendance->check_in_branch_id);
        $empId = $this->clean($attendance->empId);
        $time = $this->clean($attendance->check_in_time);

        if ($branchId === '' || $empId === '' || $time === '') {
            return null;
        }

        $at = $this->parseDateTime((string) $attendance->check_in_date, $time, $timezone);
        if (! $at instanceof Carbon) {
            return null;
        }

        /** @var Employee|null $employee */
        $employee = $employeesByEmpId->get($empId);

        return [
            'branch_id' => $branchId,
            'emp_id' => $empId,
            'employee_id' => $employee?->id,
            'employee_name' => $this->clean($employee?->name),
            'at' => $at,
        ];
    }

    private function mapCheckOutRow(Attendance $attendance, Collection $employeesByEmpId, string $timezone): ?array
    {
        $branchId = $this->clean($attendance->check_out_branch_id);
        $empId = $this->clean($attendance->empId);
        $time = $this->clean($attendance->check_out_time);

        if ($branchId === '' || $empId === '' || $time === '') {
            return null;
        }

        $at = $this->parseDateTime((string) $attendance->check_out_date, $time, $timezone);
        if (! $at instanceof Carbon) {
            return null;
        }

        /** @var Employee|null $employee */
        $employee = $employeesByEmpId->get($empId);

        return [
            'branch_id' => $branchId,
            'emp_id' => $empId,
            'employee_id' => $employee?->id,
            'employee_name' => $this->clean($employee?->name),
            'at' => $at,
        ];
    }

    private function openingStatus(?Carbon $scheduledOpeningAt, ?Carbon $openedAt, Carbon $date, Carbon $referenceNow): string
    {
        if (! $scheduledOpeningAt instanceof Carbon) {
            return $openedAt instanceof Carbon
                ? BranchOpeningDailySummary::STATUS_OPENED
                : BranchOpeningDailySummary::STATUS_NO_ACTIVITY;
        }

        if ($openedAt instanceof Carbon) {
            return $openedAt->lte($scheduledOpeningAt)
                ? BranchOpeningDailySummary::STATUS_ON_TIME
                : BranchOpeningDailySummary::STATUS_LATE;
        }

        if ($date->isSameDay($referenceNow) && $referenceNow->lt($scheduledOpeningAt)) {
            return BranchOpeningDailySummary::STATUS_PENDING;
        }

        return BranchOpeningDailySummary::STATUS_NOT_OPENED;
    }

    private function openingDelayMinutes(?Carbon $scheduledOpeningAt, ?Carbon $openedAt, Carbon $date, Carbon $referenceNow): int
    {
        if (! $scheduledOpeningAt instanceof Carbon) {
            return 0;
        }

        if ($openedAt instanceof Carbon) {
            return $openedAt->gt($scheduledOpeningAt)
                ? $scheduledOpeningAt->diffInMinutes($openedAt)
                : 0;
        }

        if ($date->isSameDay($referenceNow) && $referenceNow->gt($scheduledOpeningAt)) {
            return $scheduledOpeningAt->diffInMinutes($referenceNow);
        }

        return 0;
    }

    private function openDurationMinutes(?Carbon $openedAt, ?Carbon $closedAt): ?int
    {
        if (! $openedAt instanceof Carbon || ! $closedAt instanceof Carbon || $closedAt->lte($openedAt)) {
            return null;
        }

        return (int) $openedAt->diffInMinutes($closedAt);
    }

    private function averageTimeOfDayLabel(Collection $rows, string $field): string
    {
        $minutes = $rows
            ->map(function (BranchOpeningDailySummary $row) use ($field): ?int {
                $value = $row->{$field};

                if (! $value instanceof Carbon) {
                    return null;
                }

                return ($value->hour * 60) + $value->minute;
            })
            ->filter(fn ($value): bool => is_int($value))
            ->values();

        if ($minutes->isEmpty()) {
            return '--';
        }

        return $this->formatMinutesOfDay((int) round($minutes->avg() ?? 0));
    }

    private function averageDurationLabel(Collection $rows, string $field): string
    {
        $minutes = $rows
            ->map(fn (BranchOpeningDailySummary $row): ?int => $row->{$field})
            ->filter(fn ($value): bool => is_int($value) && $value > 0)
            ->values();

        if ($minutes->isEmpty()) {
            return '--';
        }

        return $this->formatDuration((int) round($minutes->avg() ?? 0));
    }

    public function formatDuration(?int $minutes): string
    {
        if (! is_int($minutes) || $minutes <= 0) {
            return '--';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours > 0 && $remainingMinutes > 0) {
            return sprintf('%dh %02dm', $hours, $remainingMinutes);
        }

        if ($hours > 0) {
            return sprintf('%dh', $hours);
        }

        return sprintf('%dm', $remainingMinutes);
    }

    public function formatMinutesOfDay(int $minutes): string
    {
        $normalized = (($minutes % 1440) + 1440) % 1440;
        $hours = intdiv($normalized, 60);
        $remainingMinutes = $normalized % 60;

        return sprintf('%02d:%02d', $hours, $remainingMinutes);
    }

    private function normalizeTime($value): ?string
    {
        $time = $this->clean($value);

        if ($time === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('H:i', substr($time, 0, 5), $this->timezone())->format('H:i:s');
        } catch (\Throwable) {
            try {
                return Carbon::createFromFormat('H:i:s', $time, $this->timezone())->format('H:i:s');
            } catch (\Throwable) {
                return null;
            }
        }
    }

    private function parseDateTime(string $date, string $time, string $timezone): ?Carbon
    {
        $normalizedDate = $this->clean($date);
        $normalizedTime = $this->clean($time);

        if ($normalizedDate === '' || $normalizedTime === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d H:i:s', $normalizedDate.' '.$normalizedTime, $timezone);
        } catch (\Throwable) {
            try {
                return Carbon::createFromFormat('Y-m-d H:i', $normalizedDate.' '.substr($normalizedTime, 0, 5), $timezone);
            } catch (\Throwable) {
                return null;
            }
        }
    }

    private function timezone(): string
    {
        return config('app.timezone', 'Asia/Kolkata');
    }

    private function clean($value): string
    {
        return trim((string) $value);
    }
}
