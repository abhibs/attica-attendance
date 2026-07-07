<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EmployeeAttendanceBlockService
{
    public function syncEligibleEmployees(?Carbon $today = null): int
    {
        $today = ($today ?? now(config('app.timezone', 'Asia/Kolkata')))->copy()->startOfDay();
        $employees = Employee::query()
            ->whereIn('status', ['Active', 'Blocked'])
            ->get();
        $absenceMap = $this->consecutiveAbsentDaysForEmployees($employees, $today);
        $employeeIdsToBlock = [];

        foreach ($employees as $employee) {
            if (trim((string) $employee->status) !== 'Active') {
                continue;
            }

            if ($this->cleanDate($employee->attendance_unblocked_on) === $today->toDateString()) {
                continue;
            }

            if (($absenceMap[(int) $employee->id] ?? 0) < 3) {
                continue;
            }

            $employeeIdsToBlock[] = (int) $employee->id;
        }

        if ($employeeIdsToBlock === []) {
            return 0;
        }

        return Employee::query()
            ->whereIn('id', $employeeIdsToBlock)
            ->update([
                'status' => 'Blocked',
                'attendance_blocked_on' => $today->toDateString(),
                'attendance_unblocked_on' => null,
            ]);
    }

    public function syncEmployee(Employee $employee, ?Carbon $today = null): bool
    {
        $today = ($today ?? now(config('app.timezone', 'Asia/Kolkata')))->copy()->startOfDay();
        $currentStatus = trim((string) $employee->status);

        if ($currentStatus === 'Blocked') {
            return false;
        }

        if ($currentStatus !== 'Active') {
            return false;
        }

        if ($this->cleanDate($employee->attendance_unblocked_on) === $today->toDateString()) {
            return false;
        }

        if (($this->consecutiveAbsentDaysForEmployees(collect([$employee]), $today)[(int) $employee->id] ?? 0) < 3) {
            return false;
        }

        $employee->status = 'Blocked';
        $employee->attendance_blocked_on = $today->toDateString();
        $employee->attendance_unblocked_on = null;
        $employee->save();

        return true;
    }

    public function isBlocked(Employee $employee, ?Carbon $today = null): bool
    {
        $today = ($today ?? now(config('app.timezone', 'Asia/Kolkata')))->copy()->startOfDay();

        if (trim((string) $employee->status) === 'Blocked') {
            return true;
        }

        if ($this->cleanDate($employee->attendance_unblocked_on) === $today->toDateString()) {
            return false;
        }

        return ($this->consecutiveAbsentDaysForEmployees(collect([$employee]), $today)[(int) $employee->id] ?? 0) >= 3;
    }

    public function consecutiveAbsentDays(Employee $employee, ?Carbon $today = null): int
    {
        $today = ($today ?? now(config('app.timezone', 'Asia/Kolkata')))->copy()->startOfDay();
        return $this->consecutiveAbsentDaysForEmployees(collect([$employee]), $today)[(int) $employee->id] ?? 0;
    }

    public function consecutiveAbsentDaysForEmployees(Collection $employees, ?Carbon $today = null): array
    {
        $today = ($today ?? now(config('app.timezone', 'Asia/Kolkata')))->copy()->startOfDay();
        $employeeIdByEmpId = [];

        foreach ($employees as $employee) {
            $empId = trim((string) $employee->empId);

            if ($empId !== '') {
                $employeeIdByEmpId[$empId] = (int) $employee->id;
            }
        }

        if ($employeeIdByEmpId === []) {
            return [];
        }

        $empIds = array_keys($employeeIdByEmpId);
        $latestAttendanceByEmpId = Attendance::query()
            ->selectRaw('empId, MAX(check_in_date) as latest_attendance_date')
            ->whereIn('empId', $empIds)
            ->groupBy('empId')
            ->pluck('latest_attendance_date', 'empId')
            ->all();

        $datesToCheck = $this->previousWorkingDates($today, 3);

        $presentDatesByEmpId = [];
        $attendanceRows = Attendance::query()
            ->whereIn('empId', $empIds)
            ->whereIn('check_in_date', $datesToCheck->all())
            ->where(function ($query) {
                $query->whereNull('attendance_status_override')
                    ->orWhere('attendance_status_override', '!=', 'absent');
            })
            ->get(['empId', 'check_in_date']);

        foreach ($attendanceRows as $attendance) {
            $presentDatesByEmpId[trim((string) $attendance->empId)][Carbon::parse($attendance->check_in_date)->toDateString()] = true;
        }

        $absenceMap = [];

        foreach ($employees as $employee) {
            $empId = trim((string) $employee->empId);
            $employeeId = (int) $employee->id;

            if ($empId === '' || ! array_key_exists($empId, $latestAttendanceByEmpId)) {
                $absenceMap[$employeeId] = 0;
                continue;
            }

            $presentDates = $presentDatesByEmpId[$empId] ?? [];

            $absenceMap[$employeeId] = $datesToCheck
                ->contains(fn (string $date): bool => isset($presentDates[$date]))
                ? 0
                : $datesToCheck->count();
        }

        return $absenceMap;
    }

    private function previousWorkingDates(Carbon $today, int $days): Collection
    {
        $dates = collect();
        $cursor = $today->copy()->subDay();

        while ($dates->count() < $days) {
            if (! $cursor->isSunday()) {
                $dates->push($cursor->toDateString());
            }

            $cursor->subDay();
        }

        return $dates;
    }

    private function cleanDate($value): string
    {
        return trim((string) $value);
    }
}
