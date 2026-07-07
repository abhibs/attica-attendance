<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceDayOverride;
use App\Models\AttendanceFraudReport;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeLocationPing;
use App\Models\OutsourceLocation;
use App\Services\EmployeeAttendanceBlockService;
use App\Services\NightShiftTimingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;

class AttendanceController extends Controller
{
    private const HEAD_OFFICE_BRANCH_ID = 'AGPL000';
    private const HEAD_OFFICE_ATTENDANCE_RADIUS_METERS = 150.0;
    private const DEFAULT_REQUIRED_ATTENDANCE_RADIUS_METERS = 350.0;
    private const DEFAULT_MAX_ATTENDANCE_RADIUS_METERS = 350.0;
    private const OUT_OF_OFFICE_RADIUS_METERS = 500.0;
    private const OUTSOURCE_ATTENDANCE_RADIUS_METERS = 300.0;
    private const STATUS_FULL_DAY = 'full_day';
    private const STATUS_FULL_DAY_REMOTE = 'full_day_remote';
    private const STATUS_HALF_DAY = 'half_day';
    private const STATUS_SINGLE_PUNCH = 'single_punch';
    private const STATUS_ABSENT = 'absent';
    private const FULL_DAY_MINUTES = 460;

    public function __construct(
        private readonly EmployeeAttendanceBlockService $blockService,
        private readonly NightShiftTimingService $nightShiftTimingService
    ) {
    }

    public function latest(Request $request): JsonResponse
    {
        $employee = $request->user();

        abort_unless($employee instanceof Employee, 401);

        return response()->json([
            'attendance' => $this->latestAttendancePayload($employee),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $employee = $request->user();

        abort_unless($employee instanceof Employee, 401);

        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $empId = trim((string) $employee->empId);
        $limit = (int) ($filters['limit'] ?? 31);
        $today = Carbon::now($this->appTimezone())->startOfDay();
        $selectedMonth = ! empty($filters['month'])
            ? Carbon::createFromFormat('Y-m', $filters['month'], $this->appTimezone())->startOfMonth()
            : null;
        $historyQuery = Attendance::query()
            ->where('empId', trim((string) $employee->empId))
            ->latest('check_in_date')
            ->latest('id');
        $overrideQuery = AttendanceDayOverride::query()
            ->where('emp_id', $empId)
            ->latest('attendance_date')
            ->latest('id');

        if ($selectedMonth) {
            $month = $selectedMonth->copy();
            $historyQuery->whereBetween('check_in_date', [
                $month->toDateString(),
                $month->copy()->endOfMonth()->toDateString(),
            ]);
            $overrideQuery->whereBetween('attendance_date', [
                $month->toDateString(),
                $month->copy()->endOfMonth()->toDateString(),
            ]);
        }

        $attendance = $historyQuery
            ->limit($limit)
            ->get();
        $overrides = $overrideQuery
            ->limit($limit)
            ->get()
            ->keyBy(fn (AttendanceDayOverride $override): string => Carbon::parse($override->attendance_date)->toDateString());
        $historyRows = $this->mergeAttendanceHistoryWithOverrides($attendance, $overrides, $limit);

        return response()->json([
            'attendance' => $historyRows,
            'summary' => $this->historySummaryPayload($historyRows, $selectedMonth, $today),
            'month' => $filters['month'] ?? null,
        ]);
    }

    public function checkIn(Request $request): JsonResponse
    {
        $employee = $request->user();
        $timezone = $this->appTimezone();

        abort_unless($employee instanceof Employee, 401);

        $attendanceGuard = $this->ensureEmployeeIsAllowedToMarkAttendance($employee, $timezone);

        if ($attendanceGuard) {
            return $attendanceGuard;
        }

        $usesLoggedInBranchOnly = $this->usesLoggedInBranchOnlyAttendance($request);
        $branchId = $this->resolveBranchId($employee, $request);

        $data = $request->validate([
            'latitude' => [$usesLoggedInBranchOnly ? 'nullable' : 'required', 'numeric'],
            'longitude' => [$usesLoggedInBranchOnly ? 'nullable' : 'required', 'numeric'],
            'attendance_mode' => ['nullable', 'in:web_desktop'],
            'web_view_width' => ['nullable', 'numeric'],
            'web_view_height' => ['nullable', 'numeric'],
            'web_has_fine_pointer' => ['nullable', 'boolean'],
            'web_has_touch_input' => ['nullable', 'boolean'],
            'photo' => ['required', 'image', 'max:5120'],
        ]);

        $now = Carbon::now($timezone);
        $existing = $this->activeAttendanceForCheckIn($employee, $now);

        if ($existing) {
            return response()->json([
                'message' => $this->isNightShiftEmployee($employee)
                    ? 'Night shift already checked in. Use the next morning punch as check-out within the configured 6-hour window.'
                    : 'Attendance already checked in for today.',
                'attendance' => $this->attendancePayload($existing, true),
            ], 422);
        }

        $nightShiftWindowGuard = $this->ensureNightShiftCheckInWindow($employee, $now);

        if ($nightShiftWindowGuard) {
            return $nightShiftWindowGuard;
        }

        if ($branchId === '' && (! $this->isOutsourcedEmployee($employee) || $usesLoggedInBranchOnly)) {
            return response()->json([
                'message' => 'Assigned branch location could not be found.',
            ], 422);
        }

        $locationValidation = $usesLoggedInBranchOnly
            ? $this->loggedInBranchAttendanceLocation($branchId)
            : $this->validateAttendanceLocation(
                $employee,
                $branchId,
                (float) $data['latitude'],
                (float) $data['longitude']
            );

        if ($locationValidation instanceof JsonResponse) {
            return $locationValidation;
        }

        $locationCode = $locationValidation['locationCode'] ?? $branchId;
        $photoPath = $this->storeAttendancePhoto(
            $request->file('photo'),
            $employee,
            $locationCode
        );

        $attendance = Attendance::query()->create([
            'empId' => trim($employee->empId),
            'check_in_branch_id' => $locationCode,
            'photo_path' => $photoPath,
            'latitude' => $usesLoggedInBranchOnly ? null : $data['latitude'],
            'longitude' => $usesLoggedInBranchOnly ? null : $data['longitude'],
            'check_in_date' => $now->toDateString(),
            'check_in_time' => $now->format('H:i:s'),
            'check_in_distance' => $locationValidation['distanceMeters'],
        ]);

        return response()->json([
            'message' => 'Attendance checked in successfully.',
            'attendance' => $this->attendancePayload($attendance, true),
            'locationNotice' => $locationValidation['warningMessage'] ?? null,
        ], 201);
    }

    public function checkOut(Request $request): JsonResponse
    {
        $employee = $request->user();
        $timezone = $this->appTimezone();

        abort_unless($employee instanceof Employee, 401);

        $attendanceGuard = $this->ensureEmployeeIsAllowedToMarkAttendance($employee, $timezone);

        if ($attendanceGuard) {
            return $attendanceGuard;
        }

        $usesLoggedInBranchOnly = $this->usesLoggedInBranchOnlyAttendance($request);
        $branchId = $this->resolveBranchId($employee, $request);

        $data = $request->validate([
            'latitude' => [$usesLoggedInBranchOnly ? 'nullable' : 'required', 'numeric'],
            'longitude' => [$usesLoggedInBranchOnly ? 'nullable' : 'required', 'numeric'],
            'attendance_mode' => ['nullable', 'in:web_desktop'],
            'web_view_width' => ['nullable', 'numeric'],
            'web_view_height' => ['nullable', 'numeric'],
            'web_has_fine_pointer' => ['nullable', 'boolean'],
            'web_has_touch_input' => ['nullable', 'boolean'],
            'photo' => ['required', 'image', 'max:5120'],
        ]);

        $now = Carbon::now($timezone);
        $attendance = $this->activeAttendanceForCheckOut($employee, $now);

        if (! $attendance) {
            return response()->json([
                'message' => $this->isNightShiftEmployee($employee)
                    ? 'No active night shift attendance found within the configured 6-hour checkout window.'
                    : 'No active attendance found for today.',
            ], 404);
        }

        if ($branchId === '' && (! $this->isOutsourcedEmployee($employee) || $usesLoggedInBranchOnly)) {
            return response()->json([
                'message' => 'Assigned branch location could not be found.',
            ], 422);
        }

        $locationValidation = $usesLoggedInBranchOnly
            ? $this->loggedInBranchAttendanceLocation($branchId)
            : $this->validateAttendanceLocation(
                $employee,
                $branchId,
                (float) $data['latitude'],
                (float) $data['longitude']
            );

        if ($locationValidation instanceof JsonResponse) {
            return $locationValidation;
        }

        $locationCode = $locationValidation['locationCode'] ?? $branchId;
        $checkOutPhotoPath = $this->storeAttendancePhoto(
            $request->file('photo'),
            $employee,
            $locationCode,
            'attendence_checkout'
        );

        $attendance->update([
            'check_out_branch_id' => $locationCode,
            'check_out_photo_path' => $checkOutPhotoPath,
            'check_out_latitude' => $usesLoggedInBranchOnly ? null : $data['latitude'],
            'check_out_longitude' => $usesLoggedInBranchOnly ? null : $data['longitude'],
            'check_out_date' => $now->toDateString(),
            'check_out_time' => $now->format('H:i:s'),
            'check_out_distance' => $locationValidation['distanceMeters'],
        ]);

        return response()->json([
            'message' => 'Attendance checked out successfully.',
            'attendance' => $this->attendancePayload($attendance->fresh(), false),
            'locationNotice' => $locationValidation['warningMessage'] ?? null,
        ]);
    }

    public function trackLocation(Request $request): JsonResponse
    {
        $employee = $request->user();
        $timezone = $this->appTimezone();

        abort_unless($employee instanceof Employee, 401);

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $now = Carbon::now($timezone);
        $attendance = $this->activeAttendanceForCheckOut($employee, $now);

        if (! $attendance) {
            return response()->json([
                'message' => 'No active attendance session found for location tracking.',
                'tracking' => false,
            ], 422);
        }

        $branchId = $this->attendanceBranchId($attendance) ?: $this->resolveBranchId($employee, $request);

        $branch = $this->findBranch($branchId);

        if (! $branch) {
            return response()->json([
                'message' => 'Assigned branch location could not be found.',
                'tracking' => false,
            ], 422);
        }

        $branchLatitude = $this->parseCoordinate($branch->latitude);
        $branchLongitude = $this->parseCoordinate($branch->longitude);

        if ($branchLatitude === null || $branchLongitude === null) {
            return response()->json([
                'message' => 'Assigned branch location is not configured.',
                'tracking' => false,
            ], 422);
        }

        $latitude = (float) $data['latitude'];
        $longitude = (float) $data['longitude'];
        $distanceMeters = round($this->calculateDistanceMeters(
            $latitude,
            $longitude,
            $branchLatitude,
            $branchLongitude
        ), 2);
        $isOutOfOffice = $distanceMeters > self::OUT_OF_OFFICE_RADIUS_METERS;
        $recordedAt = ! empty($data['recorded_at'])
            ? Carbon::parse($data['recorded_at'], $timezone)
            : $now;

        $ping = EmployeeLocationPing::query()->create([
            'employee_id' => $employee->id,
            'emp_id' => $this->clean($employee->empId),
            'attendance_id' => $attendance->id,
            'branch_id' => $this->clean($branch->branchId),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'branch_latitude' => $branchLatitude,
            'branch_longitude' => $branchLongitude,
            'distance_meters' => $distanceMeters,
            'is_out_of_office' => $isOutOfOffice,
            'recorded_at' => $recordedAt,
        ]);

        return response()->json([
            'tracking' => true,
            'outOfOffice' => $isOutOfOffice,
            'distanceMeters' => $distanceMeters,
            'allowedRadiusMeters' => self::OUT_OF_OFFICE_RADIUS_METERS,
            'pingId' => $ping->id,
        ], 201);
    }

    public function reportFraud(Request $request): JsonResponse
    {
        $employee = $request->user();
        $timezone = $this->appTimezone();

        abort_unless($employee instanceof Employee, 401);

        $data = $request->validate([
            'fraud_type' => ['nullable', 'in:mobile_screen'],
            'source' => ['nullable', 'in:check_in,check_out'],
            'confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'photo' => ['required', 'image', 'max:5120'],
        ]);

        $now = Carbon::now($timezone);
        $attendance = $this->activeAttendanceForCheckOut($employee, $now);
        $branchId = $attendance
            ? $this->attendanceBranchId($attendance)
            : $this->resolveBranchId($employee, $request);
        $proofPath = $this->storeFraudProofPhoto(
            $request->file('photo'),
            $employee,
            $branchId
        );

        AttendanceFraudReport::query()->create([
            'employee_id' => $employee->id,
            'emp_id' => $this->clean($employee->empId),
            'attendance_id' => $attendance?->id,
            'branch_id' => $this->clean($branchId),
            'fraud_type' => $data['fraud_type'] ?? 'mobile_screen',
            'confidence' => array_key_exists('confidence', $data) ? round((float) $data['confidence'], 2) : null,
            'reason' => $this->clean($data['reason'] ?? ''),
            'source' => $this->clean($data['source'] ?? ''),
            'proof_path' => $proofPath,
            'reported_at' => $now,
        ]);

        return response()->json([
            'message' => 'Fraud detected and reported to Zonal.',
        ], 201);
    }

    private function latestAttendancePayload(Employee $employee): ?array
    {
        $now = Carbon::now($this->appTimezone());

        if ($this->isNightShiftEmployee($employee)) {
            $activeAttendance = $this->activeAttendanceForCheckOut(
                $employee,
                $now
            );

            if ($activeAttendance) {
                return $this->attendancePayload($activeAttendance, true);
            }
        }

        $attendance = Attendance::query()
            ->where('empId', trim((string) $employee->empId))
            ->latest('id')
            ->first();

        $override = AttendanceDayOverride::query()
            ->where('emp_id', trim((string) $employee->empId))
            ->latest('attendance_date')
            ->latest('id')
            ->first();

        if ($override) {
            $overrideDate = Carbon::parse($override->attendance_date)->toDateString();
            $attendanceDate = $attendance?->check_in_date
                ? Carbon::parse($attendance->check_in_date)->toDateString()
                : null;

            if (! $attendanceDate || $overrideDate > $attendanceDate) {
                return $this->attendanceOverridePayload($override);
            }

            if ($overrideDate === $attendanceDate && $attendance) {
                return $this->attendancePayload(
                    $attendance,
                    $this->attendanceIsActiveSession($attendance, $now),
                    $this->clean($override->final_status)
                );
            }
        }

        return $attendance ? $this->attendancePayload($attendance, $this->attendanceIsActiveSession($attendance, $now)) : null;
    }

    private function historySummaryPayload($attendance, ?Carbon $selectedMonth = null, ?Carbon $today = null): array
    {
        $rows = collect($attendance);
        $completedRecords = $rows
            ->filter(fn (array $record): bool => ! empty($record['checkOutDate']) && ! empty($record['checkOutTime']))
            ->count();
        $today ??= Carbon::now($this->appTimezone())->startOfDay();
        $halfDays = $rows
            ->filter(function (array $record) use ($today): bool {
                $date = $this->clean($record['checkInDate'] ?? '');

                return ($record['status'] ?? '') === self::STATUS_HALF_DAY
                    && $date !== ''
                    && Carbon::parse($date, $this->appTimezone())->startOfDay()->ne($today);
            })
            ->pluck('checkInDate')
            ->unique()
            ->count();
        $singlePunchDays = $rows
            ->filter(function (array $record) use ($today): bool {
                $date = $this->clean($record['checkInDate'] ?? '');

                return ($record['status'] ?? '') === self::STATUS_SINGLE_PUNCH
                    && $date !== ''
                    && Carbon::parse($date, $this->appTimezone())->startOfDay()->ne($today);
            })
            ->pluck('checkInDate')
            ->unique()
            ->count();
        $absentDays = $rows
            ->filter(fn (array $record): bool => ($record['status'] ?? '') === self::STATUS_ABSENT)
            ->pluck('checkInDate')
            ->unique()
            ->count();

        if ($selectedMonth) {
            $statusByDate = $rows
                ->filter(fn (array $record): bool => $this->clean($record['checkInDate'] ?? '') !== '')
                ->keyBy(fn (array $record): string => Carbon::parse($record['checkInDate'], $this->appTimezone())->toDateString());
            $cursor = $selectedMonth->copy()->startOfMonth();
            $end = $selectedMonth->copy()->endOfMonth();
            $summaryEnd = $end->lt($today) ? $end : $today;
            $absentDays = 0;

            while ($cursor->lte($summaryEnd)) {
                $date = $cursor->toDateString();
                $status = $statusByDate->get($date)['status'] ?? self::STATUS_ABSENT;

                if (! $cursor->isSunday() && $status === self::STATUS_ABSENT) {
                    $absentDays++;
                }

                $cursor->addDay();
            }
        }

        return [
            'totalRecords' => $rows->count(),
            'presentDays' => $rows
                ->filter(fn (array $record): bool => in_array($record['status'] ?? '', [
                    self::STATUS_FULL_DAY,
                    self::STATUS_FULL_DAY_REMOTE,
                    self::STATUS_HALF_DAY,
                    self::STATUS_SINGLE_PUNCH,
                ], true))
                ->pluck('checkInDate')
                ->unique()
                ->count(),
            'completedRecords' => $completedRecords,
            'activeRecords' => $rows->filter(fn (array $record): bool => (bool) ($record['isActiveSession'] ?? false))->count(),
            'absentDays' => $absentDays,
            'halfDays' => $halfDays,
            'singlePunchDays' => $singlePunchDays,
        ];
    }

    private function attendancePayload(Attendance $attendance, ?bool $isActiveSession = null, ?string $dayOverrideStatus = null): array
    {
        $photoPath = trim((string) $attendance->photo_path);
        $checkOutPhotoPath = trim((string) $attendance->check_out_photo_path);
        $checkInBranchId = $this->clean($attendance->check_in_branch_id);
        $checkOutBranchId = $this->clean($attendance->check_out_branch_id);
        $checkInLatitude = $attendance->latitude === null ? null : (float) $attendance->latitude;
        $checkInLongitude = $attendance->longitude === null ? null : (float) $attendance->longitude;
        $checkOutLatitude = $attendance->check_out_latitude === null ? null : (float) $attendance->check_out_latitude;
        $checkOutLongitude = $attendance->check_out_longitude === null ? null : (float) $attendance->check_out_longitude;
        $status = $this->finalAttendanceStatus($attendance, $dayOverrideStatus);
        $dayOverrideShift = $attendance->check_in_date
            ? $this->dayOverrideShiftPayloadByEmpId($attendance->empId, Carbon::parse($attendance->check_in_date), $dayOverrideStatus)
            : null;

        return [
            'id' => $attendance->id,
            'empId' => trim((string) $attendance->empId),
            'branchId' => $this->attendanceBranchId($attendance),
            'checkInBranchId' => $checkInBranchId !== '' ? $checkInBranchId : null,
            'checkOutBranchId' => $checkOutBranchId !== '' ? $checkOutBranchId : null,
            'photoPath' => $photoPath,
            'photoUrl' => $this->resolvePhotoUrl($photoPath),
            'checkOutPhotoPath' => $checkOutPhotoPath,
            'checkOutPhotoUrl' => $this->resolvePhotoUrl($checkOutPhotoPath),
            'latitude' => $checkInLatitude,
            'longitude' => $checkInLongitude,
            'checkInLatitude' => $checkInLatitude,
            'checkInLongitude' => $checkInLongitude,
            'checkOutLatitude' => $checkOutLatitude,
            'checkOutLongitude' => $checkOutLongitude,
            'checkInDate' => $dayOverrideShift['check_in_date'] ?? $attendance->check_in_date,
            'checkInTime' => $dayOverrideShift['check_in_time'] ?? $attendance->check_in_time,
            'checkOutDate' => $dayOverrideShift['check_out_date'] ?? $attendance->check_out_date,
            'checkOutTime' => $dayOverrideShift['check_out_time'] ?? $attendance->check_out_time,
            'status' => $status,
            'statusLabel' => $this->statusLabel($status),
            'isAdminOverride' => $this->isAttendanceStatus($dayOverrideStatus),
            'isNightShift' => $this->isNightShiftEmployeeByEmpId($attendance->empId),
            'isActiveSession' => $isActiveSession ?? false,
        ];
    }

    private function mergeAttendanceHistoryWithOverrides($attendance, $overrides, int $limit): array
    {
        $rowsByDate = [];

        foreach ($attendance as $record) {
            $date = Carbon::parse($record->check_in_date)->toDateString();
            $override = $overrides->get($date);
            $rowsByDate[$date] = $this->attendancePayload(
                $record,
                false,
                $override ? $this->clean($override->final_status) : null
            );
        }

        foreach ($overrides as $override) {
            $date = Carbon::parse($override->attendance_date)->toDateString();

            if (isset($rowsByDate[$date])) {
                continue;
            }

            $rowsByDate[$date] = $this->attendanceOverridePayload($override);
        }

        krsort($rowsByDate);

        return array_slice(array_values($rowsByDate), 0, $limit);
    }

    private function attendanceOverridePayload(AttendanceDayOverride $override): array
    {
        $status = $this->clean($override->final_status);
        $date = Carbon::parse($override->attendance_date)->toDateString();
        $dayOverrideShift = $this->dayOverrideShiftPayloadByEmpId($override->emp_id, Carbon::parse($date), $status);

        return [
            'id' => null,
            'empId' => $this->clean($override->emp_id),
            'branchId' => null,
            'checkInBranchId' => null,
            'checkOutBranchId' => null,
            'photoPath' => '',
            'photoUrl' => '',
            'checkOutPhotoPath' => '',
            'checkOutPhotoUrl' => '',
            'latitude' => null,
            'longitude' => null,
            'checkInLatitude' => null,
            'checkInLongitude' => null,
            'checkOutLatitude' => null,
            'checkOutLongitude' => null,
            'checkInDate' => $dayOverrideShift['check_in_date'] ?? $date,
            'checkInTime' => $dayOverrideShift['check_in_time'] ?? null,
            'checkOutDate' => $dayOverrideShift['check_out_date'] ?? null,
            'checkOutTime' => $dayOverrideShift['check_out_time'] ?? null,
            'status' => $status,
            'statusLabel' => $this->statusLabel($status) . ' (Admin Override)',
            'isAdminOverride' => true,
            'isNightShift' => $this->isNightShiftEmployeeByEmpId($override->emp_id),
            'isActiveSession' => false,
        ];
    }

    private function finalAttendanceStatus(?Attendance $attendance, ?string $dayOverrideStatus = null): string
    {
        $dayOverrideStatus = $this->clean($dayOverrideStatus);

        if ($this->isAttendanceStatus($dayOverrideStatus)) {
            return $dayOverrideStatus;
        }

        if (! $attendance) {
            return self::STATUS_ABSENT;
        }

        $override = $this->clean($attendance->attendance_status_override);

        if ($this->isAttendanceStatus($override)) {
            return $override;
        }

        if (! $attendance->check_out_date || ! $attendance->check_out_time) {
            return self::STATUS_SINGLE_PUNCH;
        }

        return $this->workedMinutes($attendance) < self::FULL_DAY_MINUTES
            ? self::STATUS_HALF_DAY
            : self::STATUS_FULL_DAY;
    }

    private function isAttendanceStatus(?string $status): bool
    {
        return in_array($this->clean($status), [
            self::STATUS_FULL_DAY,
            self::STATUS_FULL_DAY_REMOTE,
            self::STATUS_HALF_DAY,
            self::STATUS_SINGLE_PUNCH,
            self::STATUS_ABSENT,
        ], true);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_FULL_DAY => 'Full Day',
            self::STATUS_FULL_DAY_REMOTE => 'Full Day Remote',
            self::STATUS_HALF_DAY => 'Half Day',
            self::STATUS_SINGLE_PUNCH => 'Single Punch',
            self::STATUS_ABSENT => 'Absent',
            default => 'Unknown',
        };
    }

    private function workedMinutes(Attendance $attendance): int
    {
        if (! $attendance->check_in_date || ! $attendance->check_in_time || ! $attendance->check_out_date || ! $attendance->check_out_time) {
            return 0;
        }

        $checkIn = Carbon::parse($attendance->check_in_date . ' ' . $attendance->check_in_time);
        $checkOut = Carbon::parse($attendance->check_out_date . ' ' . $attendance->check_out_time);

        if ($checkOut->lte($checkIn)) {
            return 0;
        }

        return (int) $checkIn->diffInMinutes($checkOut);
    }

    private function dayOverrideShiftPayloadByEmpId($empId, Carbon $date, ?string $overrideStatus): ?array
    {
        $overrideStatus = $this->clean($overrideStatus);

        if (! in_array($overrideStatus, [self::STATUS_FULL_DAY, self::STATUS_FULL_DAY_REMOTE, self::STATUS_HALF_DAY], true)) {
            return null;
        }

        $employee = Employee::query()
            ->whereRaw('TRIM(empId) = ?', [$this->clean($empId)])
            ->first(['id', 'empId', 'shift_timing']);

        if (! $employee instanceof Employee) {
            return null;
        }

        $shift = $this->employeeShiftRange($employee, $date);

        if ($shift === null) {
            return null;
        }

        $checkIn = $shift['start'];
        $checkOut = in_array($overrideStatus, [self::STATUS_FULL_DAY, self::STATUS_FULL_DAY_REMOTE], true)
            ? $shift['end']
            : $shift['start']->copy()->addMinutes((int) floor($shift['minutes'] / 2));

        return [
            'check_in_date' => $checkIn->toDateString(),
            'check_in_time' => $checkIn->format('H:i:s'),
            'check_out_date' => $checkOut->toDateString(),
            'check_out_time' => $checkOut->format('H:i:s'),
        ];
    }

    private function employeeShiftRange(Employee $employee, Carbon $date): ?array
    {
        $timing = $this->clean($employee->shift_timing) ?: '10:00 AM - 7:00 PM';
        $tokens = $this->shiftTimingTokens($timing);

        if (count($tokens) < 2) {
            return null;
        }

        $startTime = $this->normalizeShiftTime($tokens[0]);
        $endTime = $this->normalizeShiftTime($tokens[1]);

        if ($startTime === null || $endTime === null) {
            return null;
        }

        $start = Carbon::parse($date->toDateString().' '.$startTime, $this->appTimezone());
        $end = Carbon::parse($date->toDateString().' '.$endTime, $this->appTimezone());

        if ($end->lte($start)) {
            $startHour = (int) Carbon::createFromFormat('H:i:s', $startTime)->format('G');
            $endHour = (int) Carbon::createFromFormat('H:i:s', $endTime)->format('G');
            $startHasMeridiem = preg_match('/[APap][Mm]/', $tokens[0]) === 1;
            $endHasMeridiem = preg_match('/[APap][Mm]/', $tokens[1]) === 1;

            if (! $startHasMeridiem && ! $endHasMeridiem && $startHour <= 12 && $endHour < $startHour && $endHour <= 12) {
                $end->addHours(12);
            } else {
                $end->addDay();
            }
        }

        $minutes = (int) $start->diffInMinutes($end);

        if ($minutes <= 0) {
            return null;
        }

        return [
            'start' => $start,
            'end' => $end,
            'minutes' => $minutes,
        ];
    }

    private function shiftTimingTokens(string $value): array
    {
        preg_match_all(
            '/\d{1,2}(?::\d{2})?(?::\d{2})?\s*(?:[APap][Mm])?/',
            str_replace([' to ', ' TO ', '–', '—', 'Ã¢â‚¬â€œ', 'Ã¢â‚¬â€'], '-', $value),
            $matches
        );

        return array_values(array_filter(array_map('trim', $matches[0] ?? [])));
    }

    private function normalizeShiftTime(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^\d{1,2}$/', $trimmed) === 1) {
            $hour = (int) $trimmed;

            if ($hour >= 0 && $hour <= 23) {
                return sprintf('%02d:00:00', $hour);
            }
        }

        $formats = ['H:i:s', 'H:i', 'g:i A', 'g:iA', 'h:i A', 'h:iA', 'g A', 'gA', 'h A', 'hA'];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, strtoupper($trimmed))->format('H:i:s');
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($trimmed)->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function activeAttendanceForCheckIn(Employee $employee, Carbon $now): ?Attendance
    {
        $query = Attendance::query()
            ->where('empId', trim((string) $employee->empId))
            ->whereNull('check_out_date')
            ->latest('id');

        if ($this->isNightShiftEmployee($employee)) {
            $attendance = $query->first();

            return $attendance && $this->isWithinNightShiftCheckoutWindow($employee, $attendance, $now)
                ? $attendance
                : null;
        }

        return $query
            ->where('check_in_date', $now->toDateString())
            ->first();
    }

    private function activeAttendanceForCheckOut(Employee $employee, Carbon $now): ?Attendance
    {
        $attendance = Attendance::query()
            ->where('empId', trim((string) $employee->empId))
            ->whereNull('check_out_date')
            ->latest('id')
            ->first();

        if (! $attendance) {
            return null;
        }

        if (! $this->isNightShiftEmployee($employee)) {
            return $attendance->check_in_date === $now->toDateString()
                ? $attendance
                : null;
        }

        return $this->isWithinNightShiftCheckoutWindow($employee, $attendance, $now)
            ? $attendance
            : null;
    }

    private function isWithinNightShiftCheckoutWindow(Employee $employee, Attendance $attendance, Carbon $now): bool
    {
        return $this->nightShiftTimingService->isWithinCheckOutWindow(
            $employee,
            $attendance,
            $now,
            $this->appTimezone()
        );
    }

    private function isNightShiftEmployee(Employee $employee): bool
    {
        return (bool) $employee->is_night_shift;
    }

    private function isOutsourcedEmployee(Employee $employee): bool
    {
        return (bool) $employee->is_outsourced;
    }

    private function isNightShiftEmployeeByEmpId($empId): bool
    {
        $cleanEmpId = $this->clean($empId);

        if ($cleanEmpId === '') {
            return false;
        }

        return Employee::query()
            ->whereRaw('TRIM(empId) = ?', [$cleanEmpId])
            ->where('is_night_shift', true)
            ->exists();
    }

    private function resolvePhotoUrl(?string $path): string
    {
        $trimmed = trim((string) $path);

        if ($trimmed === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $trimmed) === 1) {
            return $trimmed;
        }

        $normalizedPath = ltrim($trimmed, '/');

        if (str_starts_with($normalizedPath, 'public/')) {
            $normalizedPath = substr($normalizedPath, 7);
        }

        return function_exists('project_asset')
            ? \project_asset($normalizedPath)
            : asset($normalizedPath);
    }

    private function storeAttendancePhoto(
        $image,
        Employee $employee,
        string $branchId,
        string $prefix = 'attendence'
    ): string
    {
        $directory = public_path('storage/AttendenceImage');

        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0777, true);
        }

        $extension = strtolower($image->getClientOriginalExtension() ?: 'jpg');
        $safeBranchId = preg_replace(
            '/[^A-Za-z0-9_-]+/',
            '-',
            $this->clean($branchId)
        );
        $empId = preg_replace('/[^A-Za-z0-9_-]+/', '-', trim((string) $employee->empId));
        $filename = sprintf(
            '%s_%s_%s_%s_%s.%s',
            $prefix,
            $safeBranchId ?: 'branch',
            $empId ?: 'employee',
            Carbon::now($this->appTimezone())->format('YmdHis'),
            bin2hex(random_bytes(4)),
            $extension
        );

        Image::make($image)
            ->orientate()
            ->resize(256, 256)
            ->save($directory.'/'.$filename);

        return 'storage/AttendenceImage/'.$filename;
    }

    private function storeFraudProofPhoto($image, Employee $employee, string $branchId): string
    {
        $directory = public_path('storage/AttendanceFraudProof');

        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0777, true);
        }

        $extension = strtolower($image->getClientOriginalExtension() ?: 'jpg');
        $safeBranchId = preg_replace('/[^A-Za-z0-9_-]+/', '-', $this->clean($branchId));
        $empId = preg_replace('/[^A-Za-z0-9_-]+/', '-', trim((string) $employee->empId));
        $filename = sprintf(
            'attendance_fraud_%s_%s_%s_%s.%s',
            $safeBranchId ?: 'branch',
            $empId ?: 'employee',
            Carbon::now($this->appTimezone())->format('YmdHis'),
            bin2hex(random_bytes(4)),
            $extension
        );

        Image::make($image)
            ->orientate()
            ->resize(512, 512, function ($constraint): void {
                $constraint->aspectRatio();
                $constraint->upsize();
            })
            ->save($directory.'/'.$filename);

        return 'storage/AttendanceFraudProof/'.$filename;
    }

    private function validateBranchRadius(
        string $branchId,
        float $latitude,
        float $longitude
    ): array|JsonResponse {
        $branch = $this->findBranch($branchId);

        if (! $branch) {
            return response()->json([
                'message' => 'Assigned branch location could not be found.',
            ], 422);
        }

        $branchLatitude = $this->parseCoordinate($branch->latitude);
        $branchLongitude = $this->parseCoordinate($branch->longitude);

        if ($branchLatitude === null || $branchLongitude === null) {
            return response()->json([
                'message' => 'Assigned branch location is not configured.',
            ], 422);
        }

        $distanceMeters = round(
            $this->calculateDistanceMeters(
                $latitude,
                $longitude,
                $branchLatitude,
                $branchLongitude
            ),
            2
        );

        $allowedRadiusMeters = $this->allowedRadiusForBranch($branch);
        $requiredRadiusMeters = $this->requiredRadiusForBranch($branch);

        if ($distanceMeters > $allowedRadiusMeters) {
            $branchLabel = $this->clean((string) ($branch->branchName ?: $branch->branchId));
            $isHeadOfficeBranch = $this->isHeadOfficeBranch($branch);
            $message = $isHeadOfficeBranch
                ? sprintf(
                    'You are %s away from %s. Attendance is allowed only within %s.',
                    $this->formatDistance($distanceMeters),
                    $branchLabel,
                    $this->formatDistance($allowedRadiusMeters)
                )
                : sprintf(
                    'You are %s away from %s. Required radius is %s, and maximum allowed radius is %s.',
                    $this->formatDistance($distanceMeters),
                    $branchLabel,
                    $this->formatDistance($requiredRadiusMeters),
                    $this->formatDistance($allowedRadiusMeters)
                );

            return response()->json([
                'message' => $message,
                'distanceMeters' => $distanceMeters,
                'allowedRadiusMeters' => $allowedRadiusMeters,
                'requiredRadiusMeters' => $requiredRadiusMeters,
                'withinAllowedRadius' => false,
            ], 422);
        }

        $warningMessage = null;

        if (
            ! $this->isHeadOfficeBranch($branch)
            && $distanceMeters > $requiredRadiusMeters
            && $distanceMeters <= $allowedRadiusMeters
        ) {
            $warningMessage = sprintf(
                'Required radius is %s but attendance is allowed up to %s. HR will review your image for verification.',
                $this->formatDistance($requiredRadiusMeters),
                $this->formatDistance($allowedRadiusMeters)
            );
        }

        return [
            'locationCode' => $this->clean($branch->branchId),
            'distanceMeters' => $distanceMeters,
            'requiredRadiusMeters' => $requiredRadiusMeters,
            'allowedRadiusMeters' => $allowedRadiusMeters,
            'withinRequiredRadius' => $distanceMeters <= $requiredRadiusMeters,
            'warningMessage' => $warningMessage,
        ];
    }

    private function validateAttendanceLocation(
        Employee $employee,
        string $branchId,
        float $latitude,
        float $longitude
    ): array|JsonResponse {
        if (! $this->isOutsourcedEmployee($employee)) {
            return $this->validateBranchRadius($branchId, $latitude, $longitude);
        }

        return $this->validateOutsourceRadius($employee, $latitude, $longitude);
    }

    private function loggedInBranchAttendanceLocation(string $branchId): array
    {
        return [
            'locationCode' => $this->clean($branchId),
            'distanceMeters' => null,
            'requiredRadiusMeters' => null,
            'allowedRadiusMeters' => null,
            'withinRequiredRadius' => true,
            'warningMessage' => null,
        ];
    }

    private function usesLoggedInBranchOnlyAttendance(Request $request): bool
    {
        if ($this->clean($request->input('attendance_mode')) !== 'web_desktop') {
            return false;
        }

        $userAgent = strtolower($this->clean($request->userAgent()));

        return ! str_contains($userAgent, 'android')
            && ! str_contains($userAgent, 'iphone')
            && ! str_contains($userAgent, 'ipad')
            && ! str_contains($userAgent, 'mobile')
            && $request->boolean('web_has_fine_pointer')
            && ! $request->boolean('web_has_touch_input')
            && $this->hasDesktopAttendanceViewport($request);
    }

    private function hasDesktopAttendanceViewport(Request $request): bool
    {
        $width = (float) $request->input('web_view_width', 0);
        $height = (float) $request->input('web_view_height', 0);

        if ($width <= 0 || $height <= 0) {
            return false;
        }

        $shortestSide = min($width, $height);
        $longestSide = max($width, $height);

        return $shortestSide >= 600 && $longestSide >= 900;
    }

    private function validateOutsourceRadius(
        Employee $employee,
        float $latitude,
        float $longitude
    ): array|JsonResponse {
        $locations = $employee->outsourceLocations()
            ->where('outsource_locations.status', 1)
            ->get([
                'outsource_locations.id',
                'outsource_locations.location_code',
                'outsource_locations.name',
                'outsource_locations.latitude',
                'outsource_locations.longitude',
            ]);

        if ($locations->isEmpty()) {
            return response()->json([
                'message' => 'No outsource location is configured for this employee. Contact HR.',
            ], 422);
        }

        $nearest = null;
        $nearestDistance = null;

        foreach ($locations as $location) {
            $locationLatitude = $this->parseCoordinate($location->latitude);
            $locationLongitude = $this->parseCoordinate($location->longitude);

            if ($locationLatitude === null || $locationLongitude === null) {
                continue;
            }

            $distance = round(
                $this->calculateDistanceMeters(
                    $latitude,
                    $longitude,
                    $locationLatitude,
                    $locationLongitude
                ),
                2
            );

            if ($nearestDistance === null || $distance < $nearestDistance) {
                $nearest = $location;
                $nearestDistance = $distance;
            }
        }

        if (! $nearest instanceof OutsourceLocation || $nearestDistance === null) {
            return response()->json([
                'message' => 'Assigned outsource location coordinates are not configured. Contact HR.',
            ], 422);
        }

        $radius = self::OUTSOURCE_ATTENDANCE_RADIUS_METERS;
        $locationLabel = $this->clean($nearest->name) !== ''
            ? $this->clean($nearest->name)
            : $this->clean($nearest->location_code);

        if ($nearestDistance > $radius) {
            return response()->json([
                'message' => sprintf(
                    'You are %s away from %s. Outsource attendance is allowed only within %s.',
                    $this->formatDistance($nearestDistance),
                    $locationLabel,
                    $this->formatDistance($radius)
                ),
                'distanceMeters' => $nearestDistance,
                'allowedRadiusMeters' => $radius,
                'requiredRadiusMeters' => $radius,
                'withinAllowedRadius' => false,
            ], 422);
        }

        return [
            'locationCode' => $this->clean($nearest->location_code),
            'distanceMeters' => $nearestDistance,
            'requiredRadiusMeters' => $radius,
            'allowedRadiusMeters' => $radius,
            'withinRequiredRadius' => true,
            'warningMessage' => null,
        ];
    }

    private function isHeadOfficeBranch(Branch $branch): bool
    {
        return strcasecmp($this->clean($branch->branchId), self::HEAD_OFFICE_BRANCH_ID) === 0
            || strcasecmp($this->clean($branch->branchName), 'Head Office') === 0;
    }

    private function isHeadOfficeBranchId(string $branchId): bool
    {
        $branchId = $this->clean($branchId);

        if ($branchId === '') {
            return false;
        }

        if (strcasecmp($branchId, self::HEAD_OFFICE_BRANCH_ID) === 0) {
            return true;
        }

        $branch = $this->findBranch($branchId);

        return $branch instanceof Branch && $this->isHeadOfficeBranch($branch);
    }

    private function headOfficeAttendanceOnlyResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Mobile attendance is available only for Head Office login.',
        ], 403);
    }

    private function requiredRadiusForBranch(Branch $branch): float
    {
        return $this->isHeadOfficeBranch($branch)
            ? self::HEAD_OFFICE_ATTENDANCE_RADIUS_METERS
            : self::DEFAULT_REQUIRED_ATTENDANCE_RADIUS_METERS;
    }

    private function allowedRadiusForBranch(Branch $branch): float
    {
        return $this->isHeadOfficeBranch($branch)
            ? self::HEAD_OFFICE_ATTENDANCE_RADIUS_METERS
            : self::DEFAULT_MAX_ATTENDANCE_RADIUS_METERS;
    }

    private function resolveBranchId(Employee $employee, ?Request $request = null): string
    {
        if ($request) {
            $branchId = $this->branchIdFromToken($request);

            if ($branchId !== '') {
                return $branchId;
            }
        }

        return $this->latestAttendanceBranchId($employee);
    }

    private function branchIdFromToken(Request $request): string
    {
        $token = $request->user()?->currentAccessToken();
        $abilities = is_array($token?->abilities) ? $token->abilities : [];

        foreach ($abilities as $ability) {
            if (str_starts_with((string) $ability, 'branch:')) {
                return $this->clean(substr((string) $ability, 7));
            }
        }

        return '';
    }

    private function latestAttendanceBranchId(Employee $employee): string
    {
        $attendance = Attendance::query()
            ->where('empId', $this->clean($employee->empId))
            ->latest('id')
            ->first(['check_in_branch_id', 'check_out_branch_id']);

        return $this->attendanceBranchId($attendance);
    }

    private function findBranch(?string $branchId): ?Branch
    {
        $branchId = $this->clean($branchId);

        if ($branchId === '') {
            return null;
        }

        return Branch::query()
            ->select(['id', 'branchId', 'branchName', 'latitude', 'longitude'])
            ->whereRaw('TRIM(branchId) = ?', [$branchId])
            ->first();
    }

    private function parseCoordinate($value): ?float
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '' || ! is_numeric($trimmed)) {
            return null;
        }

        return (float) $trimmed;
    }

    private function calculateDistanceMeters(
        float $fromLatitude,
        float $fromLongitude,
        float $toLatitude,
        float $toLongitude
    ): float {
        $earthRadius = 6371000.0;
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);
        $fromLatitudeRad = deg2rad($fromLatitude);
        $toLatitudeRad = deg2rad($toLatitude);

        $a = sin($latitudeDelta / 2) ** 2 +
            cos($fromLatitudeRad) * cos($toLatitudeRad) *
            sin($longitudeDelta / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function formatDistance(float $distanceMeters): string
    {
        if ($distanceMeters >= 1000) {
            return number_format($distanceMeters / 1000, 2).' km';
        }

        return round($distanceMeters).' m';
    }

    private function clean(?string $value): string
    {
        return trim((string) $value);
    }

    private function attendanceBranchId(?Attendance $attendance): string
    {
        if (! $attendance) {
            return '';
        }

        $branchId = $this->clean($attendance->check_out_branch_id);

        if ($branchId !== '') {
            return $branchId;
        }

        return $this->clean($attendance->check_in_branch_id);
    }

    private function appTimezone(): string
    {
        return (string) config('app.timezone', 'Asia/Kolkata');
    }

    private function attendanceIsActiveSession(Attendance $attendance, Carbon $now): bool
    {
        if ($attendance->check_out_date && $attendance->check_out_time) {
            return false;
        }

        $employee = Employee::query()
            ->whereRaw('TRIM(empId) = ?', [$this->clean($attendance->empId)])
            ->first(['id', 'empId', 'is_night_shift', 'shift_timing']);

        if (! $employee instanceof Employee) {
            return false;
        }

        if (! $this->isNightShiftEmployee($employee)) {
            return $attendance->check_in_date === $now->toDateString();
        }

        return $this->isWithinNightShiftCheckoutWindow($employee, $attendance, $now);
    }

    private function ensureNightShiftCheckInWindow(Employee $employee, Carbon $now): ?JsonResponse
    {
        if (! $this->isNightShiftEmployee($employee)) {
            return null;
        }

        if ($this->nightShiftTimingService->canCheckInNow($employee, $now, $this->appTimezone())) {
            return null;
        }

        $timingLabel = $this->nightShiftTimingService->configuredTimingLabel($employee);
        $message = 'Night shift check-in is allowed only within 6 hours of the configured shift timing.';

        if ($timingLabel !== '') {
            $message = sprintf(
                'Night shift check-in is allowed only within 6 hours of the configured shift timing (%s).',
                $timingLabel
            );
        }

        return response()->json([
            'message' => $message,
        ], 422);
    }

    private function ensureEmployeeIsAllowedToMarkAttendance(
        Employee $employee,
        string $timezone
    ): ?JsonResponse {
        $employee->refresh();

        if ($employee->isInactive()) {
            return response()->json([
                'message' => 'This employee account is inactive. Attendance is not allowed. Please contact HR.',
            ], 403);
        }

        $today = Carbon::now($timezone)->startOfDay();
        $this->blockService->syncEmployee($employee, $today);
        $employee->refresh();

        if (trim((string) $employee->status) !== 'Blocked') {
            return null;
        }

        return response()->json([
            'message' => 'Attendance access is blocked after 3 consecutive absent days. Please contact HR.',
        ], 423);
    }
}
