<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceDayOverride;
use App\Models\AttendanceFraudReport;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeAdvanceTransaction;
use App\Models\EmployeeBankDetailRequest;
use App\Models\EmployeeDetail;
use App\Models\EmployeeLocationPing;
use App\Models\EmployeeSalaryHold;
use App\Models\HoAttendanceImport;
use App\Models\HoAttendanceImportOverride;
use App\Services\AttendancePunctualityService;
use App\Services\EmployeeAttendanceBlockService;
use App\Services\EmployeeNotificationDispatchService;
use App\Services\HoAttendanceImportService;
use App\Support\ExcelTextValue;
use App\Support\MayPfEligibility;
use App\Support\MayPfSecurityDeposit;
use App\Support\PfSalaryBreakdown;
use App\Support\SalaryProration;
use App\Support\SalaryReportRegion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AttendanceManagementController extends Controller
{
    private const STATUS_FULL_DAY = 'full_day';

    private const STATUS_FULL_DAY_REMOTE = 'full_day_remote';

    private const STATUS_HALF_DAY = 'half_day';

    private const STATUS_SINGLE_PUNCH = 'single_punch';

    private const STATUS_ABSENT = 'absent';

    private const STATUS_WEEKOFF = 'weekoff';

    private const STATUS_NOT_JOINED = 'not_joined';

    private const STATUS_FUTURE = 'future';

    private const FULL_DAY_MINUTES = 460;

    private const REPORT_CACHE_SECONDS = 60;

    public function __construct(
        private readonly EmployeeAttendanceBlockService $blockService,
        private readonly AttendancePunctualityService $punctualityService,
        private readonly EmployeeNotificationDispatchService $notificationDispatchService
    ) {
    }

    public function hoAttendance(Request $request)
    {
        $filters = $this->resolveListFilters($request);
        $month = $this->resolveMonthRange($filters['month']);
        $rows = $this->filterImportedEmployeeReportRows(
            $this->importEmployeeReportRows(
                $month['start']->toDateString(),
                $month['end']->toDateString()
            ),
            $filters
        );

        return view('admin.attendance.ho_attendance', [
            'rows' => $rows->values(),
            'filters' => [
                ...$filters,
            ],
        ]);
    }

    public function reports(Request $request)
    {
        $this->syncEligibleEmployeesForReports();

        $branches = $this->branchCollection();
        $activeBranches = $this->activeBranchCollection();
        $branchMap = $branches->keyBy(fn (Branch $branch): string => $this->clean($branch->branchId));
        $activeBranchMap = $activeBranches->keyBy(fn (Branch $branch): string => $this->clean($branch->branchId));
        $filters = $this->resolveReportFilters($request);
        if ($this->isZonalAdmin($request)) {
            $filters['scope'] = 'branch';
        }
        $reportView = $filters['report_view'];
        $latestAttendanceMap = $this->latestAttendanceMap();
        $range = $this->resolveReportRange($filters);
        $hoBranch = $this->headOfficeBranch($branches);
        $allEmployees = $this->employeeCollection(
            includeDetails: false,
            includeAdvanceTotal: false
        );

        if (in_array($filters['report_view'], ['attendance', 'salary'], true)) {
            $allEmployees = $this->withoutInactiveEmployees($allEmployees);
        }

        $importedLatestAttendanceMap = $this->importLatestAttendanceDateMap(
            null,
            $range['start']->toDateString(),
            $range['end']->toDateString()
        );
        $reportLatestAttendanceMap = $this->latestAttendanceMapWithImportedHoDates(
            $latestAttendanceMap,
            $importedLatestAttendanceMap,
            $hoBranch?->branchId
        );

        $employees = $this->filterEmployees(
            $allEmployees,
            $filters,
            $reportLatestAttendanceMap,
            $filters['location_search'] !== '' ? $activeBranchMap : $branchMap,
            $filters['scope'],
            $hoBranch?->branchId
        );
        if ($this->clean($filters['table_search']) !== '') {
            $this->attachEmployeeDetails($employees);
        }
        $employees = $this->filterReportTableSearch(
            $employees,
            $filters['table_search'],
            $reportLatestAttendanceMap,
            $branchMap
        );

        if ($reportView === 'advance') {
            $advanceWindow = $this->resolveAdvanceReportWindow($filters);
            $advanceRows = $this->advanceReportRows(
                $employees,
                $reportLatestAttendanceMap,
                $branchMap,
                $advanceWindow['start']->toDateString(),
                $advanceWindow['end']->toDateString()
            );
            $reportPaginator = $this->paginateReportRows($advanceRows, $request, $filters['per_page']);
            $reportRows = collect($reportPaginator->items())->values();

            return view('admin.attendance.reports', [
                'filters' => [
                    ...$filters,
                    'start_date' => $advanceWindow['start']->toDateString(),
                    'end_date' => $advanceWindow['end']->toDateString(),
                    'calendar_month' => $range['start']->format('Y-m'),
                    'advance_window_start' => $advanceWindow['start']->toDateString(),
                    'advance_window_end' => $advanceWindow['end']->toDateString(),
                    'location_options' => $this->reportLocationOptions($activeBranches),
                    'branches' => $activeBranches,
                ],
                'reportRows' => $reportRows,
                'reportPaginator' => $reportPaginator,
                'reportView' => $reportView,
                'reportRoute' => $this->reportRouteForView($reportView),
            ]);
        }

        $employeePaginator = $this->paginateReportEmployees($employees, $request, $filters['per_page']);
        $visibleEmployees = collect($employeePaginator->items())->values();
        $this->attachEmployeeDetails($visibleEmployees);

        $importedAttendanceMap = $this->importDailySummaryMap(
            $visibleEmployees->pluck('empId')->map(fn ($empId) => $this->clean($empId))->filter()->values(),
            $range['start']->toDateString(),
            $range['end']->toDateString()
        );

        $attendanceMap = $this->attendanceRecordMap(
            $visibleEmployees->pluck('empId'),
            $range['start'],
            $range['end']
        );
        $advanceRange = $reportView === 'salary'
            ? $this->advancePayrollRange($range['start'])
            : $range;
        $advanceTotals = $reportView === 'salary'
            ? $this->advanceTotalsByEmployee(
                $visibleEmployees,
                $advanceRange['start']->toDateString(),
                $advanceRange['end']->toDateString()
            )
            : [];

        $employeeRows = $this->buildEmployeeRows(
            $visibleEmployees,
            $attendanceMap,
            $importedAttendanceMap,
            $reportLatestAttendanceMap,
            $branchMap,
            $range['start'],
            $range['end'],
            $advanceTotals,
            $reportView === 'salary'
        );

        $reportRows = $employeeRows->values();

        if ($reportView === 'salary') {
            $reportRows = $this->markSalaryHolds(
                $this->applyPfRateOfWagesDeductions(
                    $this->resolveReportUans($reportRows),
                    $range['start']
                ),
                $range['start']
            );
        }

        return view('admin.attendance.reports', [
            'filters' => [
                ...$filters,
                'start_date' => $range['start']->toDateString(),
                'end_date' => $range['end']->toDateString(),
                'calendar_month' => $range['start']->format('Y-m'),
                'advance_window_start' => $range['start']->toDateString(),
                'advance_window_end' => $range['end']->toDateString(),
                'location_options' => $this->reportLocationOptions($activeBranches),
                'branches' => $activeBranches,
            ],
            'reportRows' => $reportRows,
            'reportPaginator' => $employeePaginator,
            'reportView' => $reportView,
            'reportRoute' => $this->reportRouteForView($reportView),
        ]);
    }

    private function syncEligibleEmployeesForReports(): void
    {
        $today = $this->today();
        $cacheKey = 'attendance:block-sync:reports:'.$today->toDateString();

        if (Cache::has($cacheKey)) {
            return;
        }

        $this->blockService->syncEligibleEmployees($today);
        Cache::put($cacheKey, true, now()->addMinutes(10));
    }

    public function salaryReportExport(Request $request)
    {
        $statewise = $request->input('format') === 'statewise';
        $pfEmployees = $request->input('format') === 'pf-employees';
        $salaryHold = $request->input('format') === 'salary-hold';
        $payload = $this->salaryReportPayload(
            ($statewise || $pfEmployees || $salaryHold) ? $this->requestWithoutSalaryLocationFilters($request) : $request
        );
        $filters = $payload['filters'];
        $reportRows = $salaryHold
            ? $payload['reportRows']->where('salary_on_hold', true)->values()
            : ($pfEmployees
                ? $payload['reportRows']->values()
                : $payload['reportRows']->where('salary_on_hold', false)->values());
        $spreadsheet = new Spreadsheet();

        if ($pfEmployees) {
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('PF Employee Salary');
            $this->populatePfEmployeeSalarySheet(
                $sheet,
                $reportRows->filter(fn (array $row): bool => MayPfEligibility::isEligible(
                Carbon::parse($filters['start_date']),
                $row['emp_id'] ?? null,
                $row['uan_number'] ?? null,
                $row['pf_eligible'] ?? null
            ))->values()
            );
        } elseif ($statewise) {
            foreach (SalaryReportRegion::groups($reportRows) as $index => $group) {
                $sheet = $index === 0
                    ? $spreadsheet->getActiveSheet()
                    : $spreadsheet->createSheet();
                $sheet->setTitle($group['title']);
                $this->populateSalaryReportSheet($sheet, $group['rows']);
            }
        } else {
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle($salaryHold ? 'Salary Hold Employees' : 'Salary Report');
            $this->populateSalaryReportSheet($sheet, $reportRows);
        }

        $filename = sprintf(
            $pfEmployees
                ? 'pf-employee-salary-report-%s-to-%s.xlsx'
                : ($statewise
                    ? 'statewise-salary-report-%s-to-%s.xlsx'
                    : ($salaryHold ? 'salary-hold-employees-%s-to-%s.xlsx' : 'salary-report-%s-to-%s.xlsx')),
            $filters['start_date'],
            $filters['end_date']
        );

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function updateSalaryHold(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employee,id'],
            'payroll_month' => ['required', 'date_format:Y-m'],
            'action' => ['required', 'in:hold,release'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $employee = Employee::query()->findOrFail((int) $data['employee_id']);
        $payrollMonth = Carbon::createFromFormat('Y-m', $data['payroll_month'])->startOfMonth()->toDateString();

        if ($data['action'] === 'release') {
            EmployeeSalaryHold::query()
                ->where('employee_id', $employee->id)
                ->whereDate('payroll_month', $payrollMonth)
                ->delete();

            return back()->with('status', 'Salary hold released for '.$employee->name.'.');
        }

        $admin = $request->user('admin');
        EmployeeSalaryHold::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'payroll_month' => $payrollMonth,
            ],
            [
                'emp_id' => $this->clean($employee->empId),
                'reason' => $this->clean($data['reason'] ?? ''),
                'held_by' => $this->clean($admin?->name ?: $admin?->email),
            ]
        );

        return back()->with('status', 'Salary placed on hold for '.$employee->name.'.');
    }

    public function dailyAttendance(Request $request)
    {
        return $this->attendanceList($request, false, false);
    }

    public function outsourceAttendance(Request $request)
    {
        return $this->attendanceList($request, false, true);
    }

    public function nightShiftAttendance(Request $request)
    {
        return $this->attendanceList($request, true, false);
    }

    private function attendanceList(Request $request, bool $nightShiftOnly, bool $outsourcedOnly)
    {
        $branches = $this->branchCollection();
        $activeBranches = $this->activeBranchCollection();
        $branchMap = $branches->keyBy(fn (Branch $branch): string => $this->clean($branch->branchId));
        $filters = $this->resolveDailyAttendanceFilters($request);
        $nightShiftEmpIds = $this->nightShiftEmployeeIds();
        $outsourcedEmpIds = $this->outsourcedEmployeeIds();

        $query = Attendance::query()
            ->where('check_in_date', $filters['date']);

        if ($outsourcedOnly) {
            if ($outsourcedEmpIds->isNotEmpty()) {
                $query->whereIn(DB::raw('TRIM(empId)'), $outsourcedEmpIds->all());
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($outsourcedEmpIds->isNotEmpty()) {
            $query->whereNotIn(DB::raw('TRIM(empId)'), $outsourcedEmpIds->all());
        }

        if ($nightShiftEmpIds->isNotEmpty()) {
            $nightShiftOnly
                ? $query->whereIn('empId', $nightShiftEmpIds->all())
                : $query->whereNotIn('empId', $nightShiftEmpIds->all());
        } elseif ($nightShiftOnly) {
            $query->whereRaw('1 = 0');
        }

        if ($filters['emp_id'] !== '') {
            $query->where('empId', 'like', '%'.$filters['emp_id'].'%');
        }

        if ($filters['branch_id'] !== '') {
            $query->where(function ($branchQuery) use ($filters) {
                $branchQuery
                    ->where('check_in_branch_id', $filters['branch_id'])
                    ->orWhere('check_out_branch_id', $filters['branch_id']);
            });
        }

        if ($this->isZonalAdmin($request)) {
            $hoBranchId = $this->clean($this->headOfficeBranch($branches)?->branchId);

            if ($hoBranchId !== '') {
                $query->where(function ($branchQuery) use ($hoBranchId): void {
                    $branchQuery
                        ->where(function ($nested) use ($hoBranchId): void {
                            $nested
                                ->whereNull('check_in_branch_id')
                                ->orWhere('check_in_branch_id', '!=', $hoBranchId);
                        })
                        ->where(function ($nested) use ($hoBranchId): void {
                            $nested
                                ->whereNull('check_out_branch_id')
                                ->orWhere('check_out_branch_id', '!=', $hoBranchId);
                        });
                });
            }
        }

        $attendanceRows = $query
            ->orderBy('check_in_time')
            ->orderBy('id')
            ->get();

        $employeeMap = Employee::query()
            ->whereIn('empId', $attendanceRows->pluck('empId')->map(fn ($empId) => $this->clean($empId))->filter()->unique()->values())
            ->get()
            ->keyBy(fn (Employee $employee): string => $this->clean($employee->empId));

        $rows = $attendanceRows->map(function (Attendance $attendance) use ($branchMap, $employeeMap): array {
            $empId = $this->clean($attendance->empId);
            /** @var Employee|null $employee */
            $employee = $employeeMap->get($empId);
            $checkInBranch = $this->attendanceBranchMeta(
                $this->clean($attendance->check_in_branch_id),
                $branchMap,
                $attendance->latitude,
                $attendance->longitude
            );
            $checkOutBranch = $this->attendanceBranchMeta(
                $this->clean($attendance->check_out_branch_id),
                $branchMap,
                $attendance->check_out_latitude,
                $attendance->check_out_longitude
            );
            $checkInLocation = $this->attendanceLocationMeta($attendance->latitude, $attendance->longitude);
            $checkOutLocation = $this->attendanceLocationMeta($attendance->check_out_latitude, $attendance->check_out_longitude);

            return [
                'attendance_id' => $attendance->id,
                'emp_id' => $empId,
                'employee_name' => $employee?->name ?: '--',
                'designation' => $employee?->designation ?: '--',
                'check_in_time' => $attendance->check_in_time ? Carbon::parse($attendance->check_in_time)->format('h:i A') : '--',
                'check_in_branch' => $checkInBranch,
                'check_in_location' => $checkInLocation,
                'check_in_image_url' => $this->resolvePhotoUrl($attendance->photo_path),
                'check_out_time' => $attendance->check_out_time ? Carbon::parse($attendance->check_out_time)->format('h:i A') : '--',
                'check_out_branch' => $checkOutBranch,
                'check_out_location' => $checkOutLocation,
                'check_out_image_url' => $this->resolvePhotoUrl($attendance->check_out_photo_path),
                'worked_time' => $this->formatWorkedMinutes($this->workedMinutes($attendance)),
                'status' => $this->effectiveAttendanceStatus($attendance, $attendance->check_in_date),
            ];
        })->values();

        return view('admin.attendance.daily_attendance', [
            'breadcrumbTitle' => $outsourcedOnly ? 'Outsource' : 'Attendance',
            'pageTitle' => $outsourcedOnly
                ? 'Outsource Attendance'
                : ($nightShiftOnly ? 'Night Shift Attendance' : 'Daily Attendance'),
            'pageSubtitle' => $nightShiftOnly
                ? 'Night shift check-in and check-out records. These users are excluded from the regular daily attendance page.'
                : ($outsourcedOnly
                    ? 'Outsourced employee check-in and check-out records only.'
                    : 'Filter a date, branch, or employee ID to inspect daily check-in and check-out records.'),
            'resetRoute' => $outsourcedOnly
                ? 'admin-attendance-outsource'
                : ($nightShiftOnly ? 'admin-attendance-night-shift' : 'admin-attendance-daily'),
            'filters' => [
                ...$filters,
                'branches' => $activeBranches,
                'selected_branch_search' => $this->selectedBranchSearch($activeBranches, $filters['branch_id']),
                'branch_options' => $this->branchSearchOptions($activeBranches),
            ],
            'rows' => $rows,
            'summary' => [
                'total' => $rows->count(),
                'completed' => $rows->filter(fn (array $row): bool => $row['check_out_time'] !== '--')->count(),
                'single_punch' => $rows->filter(fn (array $row): bool => $row['check_out_time'] === '--')->count(),
            ],
        ]);
    }

    public function outOfOffice(Request $request)
    {
        $branches = $this->branchCollection();
        $activeBranches = $this->activeBranchCollection();
        $branchMap = $branches->keyBy(fn (Branch $branch): string => $this->clean($branch->branchId));
        $filters = $this->resolveReviewFilters($request);
        $range = $this->resolveReviewRange($filters);
        $hoBranchId = $this->clean($this->headOfficeBranch($branches)?->branchId);

        $query = EmployeeLocationPing::query()
            ->where('is_out_of_office', true)
            ->whereBetween('recorded_at', [
                $range['start']->copy()->startOfDay(),
                $range['end']->copy()->endOfDay(),
            ]);

        if ($filters['emp_id'] !== '') {
            $query->where('emp_id', 'like', '%'.$filters['emp_id'].'%');
        }

        if ($filters['branch_id'] !== '') {
            $query->where('branch_id', $filters['branch_id']);
        }

        if ($hoBranchId !== '') {
            $query->where('branch_id', '!=', $hoBranchId);
        }

        $pings = $query
            ->orderBy('recorded_at')
            ->get();

        $employeeMap = Employee::query()
            ->whereIn('empId', $pings->pluck('emp_id')->map(fn ($empId) => $this->clean($empId))->filter()->unique()->values())
            ->get()
            ->keyBy(fn (Employee $employee): string => $this->clean($employee->empId));

        $rows = $pings
            ->groupBy(function (EmployeeLocationPing $ping): string {
                return $this->clean($ping->emp_id).'|'.Carbon::parse($ping->recorded_at)->toDateString();
            })
            ->map(function (Collection $dayPings) use ($employeeMap, $branchMap, $filters): ?array {
                /** @var EmployeeLocationPing $first */
                $first = $dayPings->first();
                $empId = $this->clean($first->emp_id);
                /** @var Employee|null $employee */
                $employee = $employeeMap->get($empId);
                /** @var Branch|null $branch */
                $branch = $branchMap->get($this->clean($first->branch_id));

                if (($filters['state'] ?? '') !== '' && strcasecmp($this->clean($branch?->state), $filters['state']) !== 0) {
                    return null;
                }

                if (($filters['city'] ?? '') !== '' && strcasecmp($this->clean($branch?->city), $filters['city']) !== 0) {
                    return null;
                }

                $attendanceIds = $dayPings
                    ->pluck('attendance_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
                $overrideStatus = Attendance::query()
                    ->whereIn('id', $attendanceIds)
                    ->pluck('attendance_status_override')
                    ->map(fn ($status): string => $this->clean($status))
                    ->filter()
                    ->first();
                $maxDistance = (float) $dayPings->max('distance_meters');
                $firstPing = Carbon::parse($dayPings->min('recorded_at'));
                $lastPing = Carbon::parse($dayPings->max('recorded_at'));

                return [
                    'date' => Carbon::parse($first->recorded_at)->toDateString(),
                    'emp_id' => $empId,
                    'employee_name' => $employee?->name ?: '--',
                    'designation' => $employee?->designation ?: '--',
                    'branch_id' => $this->clean($first->branch_id),
                    'branch_name' => $branch?->branchName ?: '--',
                    'state' => $branch?->state ?: '',
                    'city' => $branch?->city ?: '',
                    'first_out_at' => $firstPing->format('h:i A'),
                    'last_out_at' => $lastPing->format('h:i A'),
                    'ping_count' => $dayPings->count(),
                    'max_distance' => $this->formatDistance($maxDistance),
                    'attendance_ids' => $attendanceIds,
                    'override_status' => $overrideStatus,
                    'override_label' => $overrideStatus ? $this->statusLabel($overrideStatus) : '--',
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $row): string => $row['date'].' '.$row['last_out_at'])
            ->values();

        $branchOptions = $activeBranches
            ->reject(fn (Branch $branch): bool => $hoBranchId !== '' && $this->clean($branch->branchId) === $hoBranchId)
            ->values();

        return view('admin.attendance.out_of_office', [
            'filters' => [
                ...$filters,
                'states' => $branchOptions->pluck('state')->filter()->map(fn ($value) => trim((string) $value))->unique()->sort()->values(),
                'cities' => $branchOptions->pluck('city')->filter()->map(fn ($value) => trim((string) $value))->unique()->sort()->values(),
                'branches' => $branchOptions,
                'selected_branch_search' => $this->selectedBranchSearch($branchOptions, $filters['branch_id']),
                'branch_options' => $this->branchSearchOptions($branchOptions),
            ],
            'rows' => $rows,
            'summary' => [
                'employees' => $rows->pluck('emp_id')->unique()->count(),
                'days' => $rows->count(),
                'pings' => $pings->count(),
            ],
        ]);
    }

    public function updateOutOfOfficeStatuses(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'attendance_ids' => ['required', 'array', 'min:1'],
            'attendance_ids.*' => ['integer', 'exists:attendance,id'],
            'override_status' => ['required', 'in:full_day,half_day'],
        ]);

        $attendanceIds = $data['attendance_ids'];

        if ($this->isZonalAdmin($request)) {
            $attendanceIds = $this->branchAttendanceIdsOnly($attendanceIds);
        }

        if (empty($attendanceIds)) {
            return back()->with('status', 'No branch attendance entries were selected.');
        }

        Attendance::query()
            ->whereIn('id', $attendanceIds)
            ->update([
                'attendance_status_override' => $data['override_status'],
                'updated_at' => now(),
            ]);

        $this->blockService->syncEligibleEmployees();

        return back()->with('status', 'Out of office attendance updated.');
    }

    public function fraudReports(Request $request)
    {
        $branches = $this->branchCollection();
        $activeBranches = $this->activeBranchCollection();
        $branchMap = $branches->keyBy(fn (Branch $branch): string => $this->clean($branch->branchId));
        $filters = $this->resolveReviewFilters($request);
        $range = $this->resolveReviewRange($filters);

        $query = AttendanceFraudReport::query()
            ->whereBetween('reported_at', [
                $range['start']->copy()->startOfDay(),
                $range['end']->copy()->endOfDay(),
            ]);

        if ($filters['emp_id'] !== '') {
            $query->where('emp_id', 'like', '%'.$filters['emp_id'].'%');
        }

        if ($filters['branch_id'] !== '') {
            $query->where('branch_id', $filters['branch_id']);
        }

        $reports = $query
            ->latest('reported_at')
            ->latest('id')
            ->get();
        $employeeMap = Employee::query()
            ->whereIn('empId', $reports->pluck('emp_id')->map(fn ($empId) => $this->clean($empId))->filter()->unique()->values())
            ->get()
            ->keyBy(fn (Employee $employee): string => $this->clean($employee->empId));

        $rows = $reports
            ->map(function (AttendanceFraudReport $report) use ($employeeMap, $branchMap, $filters): ?array {
                $empId = $this->clean($report->emp_id);
                /** @var Employee|null $employee */
                $employee = $employeeMap->get($empId);
                /** @var Branch|null $branch */
                $branch = $branchMap->get($this->clean($report->branch_id));

                if (($filters['state'] ?? '') !== '' && strcasecmp($this->clean($branch?->state), $filters['state']) !== 0) {
                    return null;
                }

                if (($filters['city'] ?? '') !== '' && strcasecmp($this->clean($branch?->city), $filters['city']) !== 0) {
                    return null;
                }

                return [
                    'id' => $report->id,
                    'reported_at' => $report->reported_at?->format('d M Y h:i A') ?: '--',
                    'emp_id' => $empId,
                    'employee_name' => $employee?->name ?: '--',
                    'designation' => $employee?->designation ?: '--',
                    'branch_id' => $this->clean($report->branch_id),
                    'branch_name' => $branch?->branchName ?: '--',
                    'state' => $branch?->state ?: '',
                    'city' => $branch?->city ?: '',
                    'source' => match ($this->clean($report->source)) {
                        'check_out' => 'Check-Out',
                        'check_in' => 'Check-In',
                        default => '--',
                    },
                    'fraud_type' => 'Mobile Screen',
                    'confidence' => $report->confidence !== null
                        ? number_format((float) $report->confidence * 100, 0).'%'
                        : '--',
                    'reason' => $this->clean($report->reason) ?: '--',
                    'proof_url' => $this->resolvePhotoUrl($report->proof_path),
                ];
            })
            ->filter()
            ->values();

        return view('admin.attendance.fraud_reports', [
            'filters' => [
                ...$filters,
                'states' => $activeBranches->pluck('state')->filter()->map(fn ($value) => trim((string) $value))->unique()->sort()->values(),
                'cities' => $activeBranches->pluck('city')->filter()->map(fn ($value) => trim((string) $value))->unique()->sort()->values(),
                'branches' => $activeBranches,
                'selected_branch_search' => $this->selectedBranchSearch($activeBranches, $filters['branch_id']),
                'branch_options' => $this->branchSearchOptions($activeBranches),
            ],
            'rows' => $rows,
            'summary' => [
                'total' => $rows->count(),
                'employees' => $rows->pluck('emp_id')->unique()->count(),
                'branches' => $rows->pluck('branch_id')->filter()->unique()->count(),
            ],
        ]);
    }

    public function employeeCalendar(Request $request, string $empId): JsonResponse
    {
        $employee = Employee::query()
            ->whereRaw('TRIM(empId) = ?', [$this->clean($empId)])
            ->firstOrFail();

        if ($this->isZonalAdmin($request) && $this->employeeBelongsToHeadOffice($employee)) {
            return response()->json([
                'message' => 'HO employees are not available for Zonal attendance.',
            ], 403);
        }

        $month = $this->resolveMonthRange($request->string('month')->toString());
        $range = $this->resolveCalendarRange($request, $month);
        $branchMap = $this->branchCollection()->keyBy(fn (Branch $branch): string => $this->clean($branch->branchId));
        $employmentStartDates = $this->employmentStartDateMap(collect([$employee->empId]));
        $employmentStartDate = $this->employmentStartDate($employee, $employmentStartDates[$this->clean($employee->empId)] ?? null);
        $records = $this->attendanceRecordMap(
            collect([$employee->empId]),
            $range['start'],
            $range['end']
        );
        $employeeRecords = $records[$this->clean($employee->empId)] ?? [];
        $importedRecords = $this->importDailySummaryMap(
            collect([$employee->empId]),
            $range['start']->toDateString(),
            $range['end']->toDateString()
        );
        $employeeImportedRecords = $importedRecords[$this->clean($employee->empId)] ?? [];
        $dayOverrides = $this->attendanceDayOverrideMap(
            collect([$employee->empId]),
            $range['start'],
            $range['end']
        );
        $employeeDayOverrides = $dayOverrides[$this->clean($employee->empId)] ?? [];
        $summary = [
            'full_days' => 0,
            'half_days' => 0,
            'single_punch_days' => 0,
            'absent_days' => 0,
            'week_off_days' => 0,
            'credited_days' => 0,
            'regularized_days' => 0,
        ];
        $days = [];
        $cursor = $range['start']->copy();

        while ($cursor->lte($range['end'])) {
            $date = $cursor->toDateString();
            /** @var Attendance|null $record */
            $record = $employeeRecords[$date] ?? null;
            $importedRecord = $employeeImportedRecords[$date] ?? null;
            $dayOverrideStatus = $this->clean($employeeDayOverrides[$date] ?? '');
            $appStatus = $this->effectiveAttendanceStatus($record, $date, $employmentStartDate);
            $importedStatus = $importedRecord
                ? $this->effectiveImportedAttendanceStatus($importedRecord, $date)
                : null;
            $status = $this->applyAttendanceDayOverrideStatus(
                $this->calendarAttendanceStatus(
                    $this->cumulativeAttendanceStatus($appStatus, $importedStatus),
                    $cursor
                ),
                $dayOverrideStatus
            );
            $checkInBranch = $this->attendanceBranchMeta(
                $this->clean($record?->check_in_branch_id),
                $branchMap,
                $record?->latitude,
                $record?->longitude
            );
            $checkOutBranch = $this->attendanceBranchMeta(
                $this->clean($record?->check_out_branch_id),
                $branchMap,
                $record?->check_out_latitude,
                $record?->check_out_longitude
            );
            $checkInLocation = $this->attendanceLocationMeta($record?->latitude, $record?->longitude);
            $checkOutLocation = $this->attendanceLocationMeta($record?->check_out_latitude, $record?->check_out_longitude);

            if ($status !== self::STATUS_FUTURE) {
                if (in_array($status, [self::STATUS_FULL_DAY, self::STATUS_FULL_DAY_REMOTE], true)) {
                    $summary['full_days']++;
                    $summary['credited_days'] += 1;
                } elseif ($status === self::STATUS_HALF_DAY) {
                    $summary['half_days']++;
                    $summary['credited_days'] += 0.5;
                } elseif ($status === self::STATUS_SINGLE_PUNCH) {
                    $summary['single_punch_days']++;
                } elseif ($status === self::STATUS_ABSENT) {
                    $summary['absent_days']++;
                } elseif ($status === self::STATUS_WEEKOFF) {
                    $summary['week_off_days']++;
                }
            }

            $overrideStatus = $this->clean($record?->attendance_status_override);
            $importOverrideStatus = $this->clean($importedRecord['override_status'] ?? '');
            $displayOverrideStatus = $dayOverrideStatus !== ''
                ? $dayOverrideStatus
                : ($overrideStatus !== '' ? $overrideStatus : $importOverrideStatus);
            $isRegularized = $this->isRegularizedStatus($overrideStatus)
                || $this->isRegularizedStatus($importOverrideStatus)
                || $this->isRegularizedStatus($dayOverrideStatus);

            if ($isRegularized) {
                $summary['regularized_days']++;
            }

            $dayOverrideShift = $this->dayOverrideShiftPayload($employee, $cursor, $dayOverrideStatus);

            $days[] = [
                'day' => $cursor->day,
                'date' => $date,
                'weekday' => $cursor->format('D'),
                'status' => $status,
                'label' => $this->displayStatusLabel($status, $displayOverrideStatus),
                'is_regularized' => $isRegularized,
                'regularized_label' => $dayOverrideStatus !== '' ? 'Admin Override' : ($isRegularized ? 'Regularized' : null),
                'check_in' => $dayOverrideShift['check_in']
                    ?? ($record?->check_in_time
                    ? Carbon::parse($record->check_in_time)->format('h:i A')
                    : $this->formatImportedTime($importedRecord['first_login'] ?? null)),
                'check_out' => $dayOverrideShift['check_out']
                    ?? ($record?->check_out_time
                    ? Carbon::parse($record->check_out_time)->format('h:i A')
                    : $this->formatImportedTime($importedRecord['last_logout'] ?? null)),
                'check_in_datetime' => $dayOverrideShift['check_in_datetime']
                    ?? ($record?->check_in_date && $record?->check_in_time
                    ? Carbon::parse($record->check_in_date.' '.$record->check_in_time)->format('d M Y h:i A')
                    : $this->formatImportedDateTime($date, $importedRecord['first_login'] ?? null)),
                'check_out_datetime' => $dayOverrideShift['check_out_datetime']
                    ?? ($record?->check_out_date && $record?->check_out_time
                    ? Carbon::parse($record->check_out_date.' '.$record->check_out_time)->format('d M Y h:i A')
                    : $this->formatImportedDateTime($date, $importedRecord['last_logout'] ?? null)),
                'worked_time' => $dayOverrideShift['worked_time']
                    ?? ($record
                    ? $this->formatWorkedMinutes($this->workedMinutes($record))
                    : $this->formatImportedSeconds((int) ($importedRecord['logged_seconds'] ?? 0))),
                'check_in_branch' => $checkInBranch['label'],
                'check_in_branch_details' => $checkInBranch['details'],
                'check_in_branch_url' => $checkInBranch['url'],
                'check_out_branch' => $checkOutBranch['label'],
                'check_out_branch_details' => $checkOutBranch['details'],
                'check_out_branch_url' => $checkOutBranch['url'],
                'check_in_location' => $checkInLocation['label'],
                'check_in_location_details' => $checkInLocation['details'],
                'check_in_location_url' => $checkInLocation['url'],
                'check_out_location' => $checkOutLocation['label'],
                'check_out_location_details' => $checkOutLocation['details'],
                'check_out_location_url' => $checkOutLocation['url'],
                'login_image_url' => $this->resolvePhotoUrl($record?->photo_path),
                'logout_image_url' => $this->resolvePhotoUrl($record?->check_out_photo_path),
                'has_details' => $record !== null || $importedRecord !== null,
                'override_status' => $overrideStatus,
                'import_override_status' => $importOverrideStatus,
                'day_override_status' => $dayOverrideStatus,
                'can_mark_present' => $status === self::STATUS_ABSENT,
                'can_mark_full_day' => in_array($status, [self::STATUS_HALF_DAY, self::STATUS_SINGLE_PUNCH], true),
                'can_mark_half_day' => in_array($status, [self::STATUS_HALF_DAY, self::STATUS_SINGLE_PUNCH], true),
                'can_mark_absent' => ! in_array($status, [self::STATUS_ABSENT, self::STATUS_FUTURE, self::STATUS_NOT_JOINED], true),
                'can_clear_day_override' => $dayOverrideStatus !== '',
            ];

            $cursor->addDay();
        }

        return response()->json([
            'employee' => [
                'empId' => $this->clean($employee->empId),
                'name' => $employee->name,
            ],
            'month' => $month['label'],
            'period' => [
                'from' => $range['start']->toDateString(),
                'to' => $range['end']->toDateString(),
                'label' => $range['start']->toDateString() === $range['end']->toDateString()
                    ? $range['start']->format('d M Y')
                    : $range['start']->format('d M Y').' - '.$range['end']->format('d M Y'),
            ],
            'summary' => $summary,
            'days' => $days,
        ]);
    }

    public function updateCalendarDayOverride(Request $request): JsonResponse
    {
        $data = $request->validate([
            'emp_id' => ['required', 'string', 'max:100'],
            'attendance_date' => ['required', 'date'],
            'override_status' => ['nullable', 'in:full_day,half_day,absent'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $empId = $this->clean($data['emp_id']);
        $attendanceDate = Carbon::parse($data['attendance_date'], config('app.timezone', 'Asia/Kolkata'))->toDateString();
        $overrideStatus = $this->clean($data['override_status'] ?? '');

        $employee = Employee::query()
            ->whereRaw('TRIM(empId) = ?', [$empId])
            ->firstOrFail();

        if ($this->isZonalAdmin($request)) {
            if (! in_array($overrideStatus, ['', self::STATUS_FULL_DAY, self::STATUS_HALF_DAY], true)) {
                return response()->json([
                    'message' => 'Zonal users can mark branch users only as full day or half day.',
                ], 403);
            }

            if ($this->employeeBelongsToHeadOffice($employee)) {
                return response()->json([
                    'message' => 'HO employees are not available for Zonal attendance updates.',
                ], 403);
            }
        }

        if ($overrideStatus === '') {
            AttendanceDayOverride::query()
                ->where('emp_id', $empId)
                ->whereDate('attendance_date', $attendanceDate)
                ->delete();

            $message = 'Attendance day override cleared.';
        } else {
            AttendanceDayOverride::query()->updateOrCreate(
                [
                    'emp_id' => $empId,
                    'attendance_date' => $attendanceDate,
                ],
                [
                    'final_status' => $overrideStatus,
                    'reason' => $this->nullIfBlank($data['reason'] ?? null),
                    'created_by' => (string) (auth('admin')->id() ?? ''),
                    'updated_by' => (string) (auth('admin')->id() ?? ''),
                ]
            );

            $message = 'Attendance day marked as '.$this->statusLabel($overrideStatus).'.';
        }

        $this->blockService->syncEligibleEmployees();

        return response()->json([
            'message' => $message,
        ]);
    }

    public function hoAttendanceImport(Request $request)
    {
        $filters = $this->resolveImportFilters($request);
        $range = $this->resolveImportRange($filters);
        $baseOverviewRows = $this->importOverviewRows($range['from'], $range['to']);
        $baseImportReportRows = $this->importEmployeeReportRows($range['from'], $range['to']);
        $overviewRows = $this->importOverviewRows($range['from'], $range['to'], $filters['search']);
        $importReportRows = $this->importEmployeeReportRows($range['from'], $range['to'], $filters['search']);
        $singlePunchRows = $this->importReviewRows($range['from'], $range['to'], self::STATUS_SINGLE_PUNCH, $filters['search']);
        $halfDayRows = $this->importReviewRows($range['from'], $range['to'], self::STATUS_HALF_DAY, $filters['search']);

        $summaryCards = [
            'imported_rows' => HoAttendanceImport::query()
                ->whereBetween('attendance_date', [$range['from'], $range['to']])
                ->count(),
            'employees' => $baseImportReportRows->count(),
            'attendance_days' => (int) $baseOverviewRows->sum('attendance_days'),
            'batches' => HoAttendanceImport::query()
                ->whereBetween('attendance_date', [$range['from'], $range['to']])
                ->distinct('import_batch')
                ->count('import_batch'),
            'present_days' => (int) $baseImportReportRows->sum('present_days'),
            'absent_days' => (int) $baseImportReportRows->sum('absent_days'),
            'single_punch_days' => (int) $baseImportReportRows->sum('single_punch_days'),
            'half_days' => (int) $baseImportReportRows->sum('half_days'),
        ];

        return view('admin.attendance.import', [
            'filters' => $filters,
            'overviewRows' => $overviewRows,
            'importReportRows' => $importReportRows,
            'singlePunchRows' => $singlePunchRows,
            'halfDayRows' => $halfDayRows,
            'calendarMonth' => Carbon::parse($range['from'], config('app.timezone', 'Asia/Kolkata'))->format('Y-m'),
            'summaryCards' => $summaryCards,
        ]);
    }

    public function importHoAttendance(Request $request, HoAttendanceImportService $importService): RedirectResponse
    {
        $data = $request->validate([
            'attendance_file' => ['required', 'file', 'mimes:xlsx,xls,xlsm'],
        ]);

        try {
            $result = $importService->import($data['attendance_file']);
        } catch (\Throwable $exception) {
            return back()->with('status', 'Import failed: '.$exception->getMessage());
        }

        $message = sprintf(
            'Import completed. Inserted: %d | Duplicate skipped: %d | Skipped: %d',
            $result['inserted'],
            $result['duplicates'],
            $result['skipped']
        );

        return redirect()
            ->route('admin-attendance-import')
            ->with('status', $message);
    }

    public function importedAttendanceCalendar(Request $request, string $empId): JsonResponse
    {
        $month = $this->resolveMonthRange($request->string('month')->toString());
        $range = $this->resolveCalendarRange($request, $month);
        $employee = HoAttendanceImport::query()
            ->select('emp_id', DB::raw("MAX(NULLIF(employee_name, '')) as employee_name"))
            ->where('emp_id', $this->clean($empId))
            ->groupBy('emp_id')
            ->firstOrFail();
        $employeeName = $this->importDisplayEmployeeName(
            $employee->emp_id,
            $employee->employee_name,
            $this->employeeNamesByEmpId(collect([$employee->emp_id]))
        );

        $dailyRows = $this->importDailySummaryMap(
            collect([$this->clean($empId)]),
            $range['start']->toDateString(),
            $range['end']->toDateString()
        );
        $employeeDays = $dailyRows[$this->clean($empId)] ?? [];
        $summary = [
            'full_days' => 0,
            'half_days' => 0,
            'single_punch_days' => 0,
            'absent_days' => 0,
            'week_off_days' => 0,
            'regularized_days' => 0,
        ];
        $days = [];
        $cursor = $range['start']->copy();

        while ($cursor->lte($range['end'])) {
            $date = $cursor->toDateString();
            $daySummary = $employeeDays[$date] ?? null;
            $status = $this->calendarAttendanceStatus(
                $this->effectiveImportedAttendanceStatus($daySummary, $date),
                $cursor
            );

            if (in_array($status, [self::STATUS_FULL_DAY, self::STATUS_FULL_DAY_REMOTE], true)) {
                $summary['full_days']++;
            } elseif ($status === self::STATUS_HALF_DAY) {
                $summary['half_days']++;
            } elseif ($status === self::STATUS_SINGLE_PUNCH) {
                $summary['single_punch_days']++;
            } elseif ($status === self::STATUS_ABSENT) {
                $summary['absent_days']++;
            } elseif ($status === self::STATUS_WEEKOFF) {
                $summary['week_off_days']++;
            }

            $overrideStatus = $this->clean($daySummary['override_status'] ?? '');
            $isRegularized = in_array($overrideStatus, [self::STATUS_FULL_DAY, self::STATUS_FULL_DAY_REMOTE, self::STATUS_HALF_DAY, self::STATUS_SINGLE_PUNCH, self::STATUS_ABSENT], true);

            if ($isRegularized) {
                $summary['regularized_days']++;
            }

            $days[] = [
                'day' => $cursor->day,
                'date' => $date,
                'weekday' => $cursor->format('D'),
                'status' => $status,
                'label' => $this->displayStatusLabel($status, $overrideStatus),
                'check_in' => $daySummary['first_login_label'] ?? null,
                'check_out' => $daySummary['last_logout_label'] ?? null,
                'worked_time' => $daySummary['worked_time_label'] ?? '--',
                'has_details' => $daySummary !== null,
                'override_status' => $overrideStatus,
                'is_regularized' => $isRegularized,
                'regularized_label' => $isRegularized ? 'Regularized' : null,
                'login_image_url' => '',
                'logout_image_url' => '',
            ];

            $cursor->addDay();
        }

        return response()->json([
            'employee' => [
                'empId' => $employee->emp_id,
                'name' => $employeeName,
            ],
            'month' => $month['label'],
            'period' => [
                'from' => $range['start']->toDateString(),
                'to' => $range['end']->toDateString(),
                'label' => $range['start']->toDateString() === $range['end']->toDateString()
                    ? $range['start']->format('d M Y')
                    : $range['start']->format('d M Y').' - '.$range['end']->format('d M Y'),
            ],
            'summary' => $summary,
            'days' => $days,
        ]);
    }

    public function updateImportedAttendanceStatuses(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'selected_records' => ['required', 'array', 'min:1'],
            'selected_records.*' => ['string'],
            'override_status' => ['required', 'in:full_day,half_day,absent'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'search' => ['nullable', 'string'],
            'show_single_punch' => ['nullable', 'string'],
            'show_half_day' => ['nullable', 'string'],
        ]);

        $employeeMap = Employee::query()
            ->whereIn('empId', collect($data['selected_records'])
                ->map(fn ($record): string => $this->clean(explode('|', (string) $record, 2)[0] ?? ''))
                ->filter()
                ->unique()
                ->all())
            ->get()
            ->keyBy(fn (Employee $employee): string => $this->clean($employee->empId));
        $adminUser = auth('admin')->user();
        $actor = trim((string) ($adminUser->email ?? $adminUser->name ?? 'admin'));
        $rows = [];
        $notificationsByEmployee = [];

        foreach ($data['selected_records'] as $recordKey) {
            [$empId, $attendanceDate] = explode('|', (string) $recordKey, 2) + ['', ''];
            $empId = $this->clean($empId);
            $attendanceDate = $this->clean($attendanceDate);

            if ($empId === '' || ! $this->isDateString($attendanceDate)) {
                continue;
            }

            $rows[] = [
                'emp_id' => $empId,
                'attendance_date' => $attendanceDate,
                'final_status' => $data['override_status'],
                'created_by' => $actor,
                'updated_by' => $actor,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $notificationsByEmployee[$empId][] = $attendanceDate;
        }

        if ($rows !== []) {
            HoAttendanceImportOverride::query()->upsert(
                $rows,
                ['emp_id', 'attendance_date'],
                ['final_status', 'updated_by', 'updated_at']
            );
        }

        foreach ($notificationsByEmployee as $empId => $dates) {
            /** @var Employee|null $employee */
            $employee = $employeeMap->get($empId);

            if (! $employee) {
                continue;
            }

            $this->notificationDispatchService->sendToEmployee(
                $employee,
                'Attendance Regularization Updated',
                $this->attendanceRegularizationNotificationBody($dates, $data['override_status']),
                auth('admin')->id()
            );
        }

        return redirect()
            ->route('admin-attendance-import', array_filter([
                'from_date' => $data['from_date'] ?? null,
                'to_date' => $data['to_date'] ?? null,
                'search' => $data['search'] ?? null,
                'show_single_punch' => $data['show_single_punch'] ?? null,
                'show_half_day' => $data['show_half_day'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''))
            ->with('status', 'Imported attendance status updated for the selected entries.');
    }

    public function attendanceReview(Request $request)
    {
        $branches = $this->branchCollection();
        $activeBranches = $this->activeBranchCollection();
        $branchMap = $branches->keyBy(fn (Branch $branch): string => $this->clean($branch->branchId));
        $latestAttendanceMap = $this->latestAttendanceMap();
        $filters = $this->resolveReviewFilters($request);
        $reviewRange = $this->resolveReviewRange($filters);

        $employees = $this->filterEmployees(
            $this->employeeCollection(),
            $filters,
            $latestAttendanceMap,
            $branchMap,
            $this->isZonalAdmin($request) ? 'branch' : 'all',
            $this->headOfficeBranch($branches)?->branchId
        )->reject(fn (Employee $employee): bool => (bool) $employee->is_night_shift)->values();

        $employeeMap = $employees->keyBy(fn (Employee $employee): string => $this->clean($employee->empId));
        $attendanceRows = Attendance::query()
            ->whereIn('empId', $employeeMap->keys()->all())
            ->whereBetween('check_in_date', [$reviewRange['start']->toDateString(), $reviewRange['end']->toDateString()])
            ->whereDate('check_in_date', '<', $this->today()->toDateString())
            ->orderBy('empId')
            ->orderBy('check_in_date')
            ->orderBy('id')
            ->get();

        $reviewRows = $attendanceRows
            ->map(function (Attendance $attendance) use ($employeeMap, $branchMap): ?array {
                $empId = $this->clean($attendance->empId);
                /** @var Employee|null $employee */
                $employee = $employeeMap->get($empId);

                if (! $employee) {
                    return null;
                }

                $status = $this->effectiveAttendanceStatus($attendance, $attendance->check_in_date);

                if (! in_array($status, [self::STATUS_HALF_DAY, self::STATUS_SINGLE_PUNCH], true)) {
                    return null;
                }

                if ($this->clean($attendance->attendance_status_override) !== '') {
                    return null;
                }

                $branchId = $this->clean($attendance->branchId);
                /** @var Branch|null $branch */
                $branch = $branchMap->get($branchId);

                return [
                    'attendance_id' => $attendance->id,
                    'date' => $attendance->check_in_date,
                    'emp_id' => $empId,
                    'employee_name' => $employee->name,
                    'designation' => $employee->designation,
                    'branch_id' => $branchId,
                    'branch_name' => $branch?->branchName,
                    'state' => $branch?->state,
                    'city' => $branch?->city,
                    'status' => $status,
                    'status_label' => $this->displayStatusLabel($status, $attendance->attendance_status_override),
                    'override_status' => $attendance->attendance_status_override,
                    'check_in' => $attendance->check_in_time ? Carbon::parse($attendance->check_in_time)->format('h:i A') : '--',
                    'check_out' => $attendance->check_out_time ? Carbon::parse($attendance->check_out_time)->format('h:i A') : '--',
                    'worked_time' => $this->formatWorkedMinutes($this->workedMinutes($attendance)),
                ];
            })
            ->filter()
            ->when(
                $filters['issue'] !== 'all',
                fn (Collection $collection): Collection => $collection->where('status', $filters['issue'])
            )
            ->values();
        $rows = $this->groupAttendanceReviewRows($reviewRows);

        return view('admin.attendance.review', [
            'filters' => [
                ...$filters,
                'states' => $activeBranches->pluck('state')->filter()->map(fn ($value) => trim((string) $value))->unique()->sort()->values(),
                'cities' => $activeBranches->pluck('city')->filter()->map(fn ($value) => trim((string) $value))->unique()->sort()->values(),
                'branches' => $activeBranches,
                'selected_branch_search' => $this->selectedBranchSearch($activeBranches, $filters['branch_id']),
                'branch_options' => $this->branchSearchOptions($activeBranches),
            ],
            'rows' => $rows,
            'summary' => [
                'half_days' => $reviewRows->where('status', self::STATUS_HALF_DAY)->count(),
                'single_punch_days' => $reviewRows->where('status', self::STATUS_SINGLE_PUNCH)->count(),
                'total_rows' => $rows->count(),
            ],
        ]);
    }

    public function updateAttendanceStatuses(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'attendance_ids' => ['required', 'array', 'min:1'],
            'attendance_ids.*' => ['integer', 'exists:attendance,id'],
            'override_status' => ['required', 'in:full_day,half_day,absent'],
        ]);

        if ($this->isZonalAdmin($request) && ! in_array($data['override_status'], [self::STATUS_FULL_DAY, self::STATUS_HALF_DAY], true)) {
            return back()->with('status', 'Zonal users can mark branch users only as full day or half day.');
        }

        $attendanceIds = $data['attendance_ids'];

        if ($this->isZonalAdmin($request)) {
            $attendanceIds = $this->branchAttendanceIdsOnly($attendanceIds);
        }

        if (empty($attendanceIds)) {
            return back()->with('status', 'No branch attendance entries were selected.');
        }

        $attendanceRows = Attendance::query()
            ->whereIn('id', $attendanceIds)
            ->get(['id', 'empId', 'check_in_date']);

        Attendance::query()
            ->whereIn('id', $attendanceIds)
            ->update([
                'attendance_status_override' => $data['override_status'],
                'updated_at' => now(),
            ]);

        $this->blockService->syncEligibleEmployees();
        $employeeMap = Employee::query()
            ->whereIn('empId', $attendanceRows->pluck('empId')->map(fn ($value): string => $this->clean($value))->filter()->unique()->all())
            ->get()
            ->keyBy(fn (Employee $employee): string => $this->clean($employee->empId));

        foreach ($attendanceRows->groupBy(fn (Attendance $attendance): string => $this->clean($attendance->empId)) as $empId => $rows) {
            /** @var Employee|null $employee */
            $employee = $employeeMap->get($empId);

            if (! $employee) {
                continue;
            }

            $dates = $rows
                ->pluck('check_in_date')
                ->map(fn ($value): string => $this->clean($value))
                ->filter()
                ->values()
                ->all();

            $this->notificationDispatchService->sendToEmployee(
                $employee,
                'Attendance Regularization Updated',
                $this->attendanceRegularizationNotificationBody($dates, $data['override_status']),
                auth('admin')->id()
            );
        }

        return back()->with('status', 'Attendance status updated for the selected entries.');
    }

    public function blockedEmployees(Request $request)
    {
        $this->blockService->syncEligibleEmployees();

        $branches = $this->branchCollection();
        $activeBranches = $this->activeBranchCollection();
        $branchMap = $branches->keyBy(fn (Branch $branch): string => $this->clean($branch->branchId));
        $latestAttendanceMap = $this->latestAttendanceMap();
        $filters = $this->resolveBlockedFilters($request);

        $employees = $this->filterEmployees(
            $this->employeeCollection()->where('status', 'Blocked')->values(),
            $filters,
            $latestAttendanceMap,
            $branchMap,
            'all',
            $this->headOfficeBranch($branches)?->branchId
        );

        $consecutiveAbsences = $this->blockService->consecutiveAbsentDaysForEmployees($employees);

        $rows = $employees->map(function (Employee $employee) use ($latestAttendanceMap, $branchMap, $consecutiveAbsences): array {
            $empId = $this->clean($employee->empId);
            $latest = $latestAttendanceMap[$empId] ?? null;
            $branchId = $this->clean($employee->assigned_branch_id) ?: ($latest['branch_id'] ?? '');
            /** @var Branch|null $branch */
            $branch = $branchMap->get($branchId);

            return [
                'id' => $employee->id,
                'emp_id' => $empId,
                'employee_name' => $employee->name,
                'designation' => $employee->designation,
                'branch_id' => $branchId,
                'branch_name' => $branch?->branchName,
                'state' => $branch?->state,
                'city' => $branch?->city,
                'blocked_on' => $employee->attendance_blocked_on,
                'last_attendance' => $latest['check_in_date'] ?? null,
                'consecutive_absences' => $consecutiveAbsences[(int) $employee->id] ?? 0,
            ];
        })->values();

        return view('admin.attendance.blocked', [
            'filters' => [
                ...$filters,
                'states' => $activeBranches->pluck('state')->filter()->map(fn ($value) => trim((string) $value))->unique()->sort()->values(),
                'cities' => $activeBranches->pluck('city')->filter()->map(fn ($value) => trim((string) $value))->unique()->sort()->values(),
                'branches' => $activeBranches,
                'selected_branch_search' => $this->selectedBranchSearch($activeBranches, $filters['branch_id']),
                'branch_options' => $this->branchSearchOptions($activeBranches),
            ],
            'rows' => $rows,
        ]);
    }

    public function unblockEmployees(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:employee,id'],
        ]);

        Employee::query()
            ->whereIn('id', $data['employee_ids'])
            ->update([
                'status' => 'Active',
                'attendance_blocked_on' => null,
                'attendance_unblocked_on' => now(config('app.timezone', 'Asia/Kolkata'))->toDateString(),
            ]);

        return back()->with('status', 'Selected employees have been unblocked.');
    }

    private function attendanceRegularizationNotificationBody(array $dates, string $overrideStatus): string
    {
        $cleanDates = collect($dates)
            ->map(fn ($date): string => $this->clean($date))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $count = $cleanDates->count();
        $statusLabel = match ($overrideStatus) {
            self::STATUS_FULL_DAY => 'Full Day',
            self::STATUS_HALF_DAY => 'Half Day',
            self::STATUS_ABSENT => 'Absent',
            default => ucwords(str_replace('_', ' ', $overrideStatus)),
        };

        if ($count === 0) {
            return 'Your attendance regularization was updated by admin.';
        }

        if ($count === 1) {
            return sprintf(
                'Your attendance on %s was regularized as %s.',
                Carbon::parse($cleanDates->first())->format('d M Y'),
                $statusLabel
            );
        }

        return sprintf(
            '%d attendance entries from %s to %s were regularized as %s.',
            $count,
            Carbon::parse($cleanDates->first())->format('d M Y'),
            Carbon::parse($cleanDates->last())->format('d M Y'),
            $statusLabel
        );
    }

    private function groupAttendanceReviewRows(Collection $rows): Collection
    {
        return $rows
            ->map(function (array $row): ?array {
                $date = $this->isDateString((string) ($row['date'] ?? ''))
                    ? Carbon::parse($row['date'], config('app.timezone', 'Asia/Kolkata'))->startOfDay()
                    : null;

                if (! $date instanceof Carbon) {
                    return null;
                }

                return [
                    'attendance_ids' => collect([(int) ($row['attendance_id'] ?? 0)])->filter()->values()->all(),
                    'date' => $date->format('d M Y'),
                    'entry_count' => 1,
                    'emp_id' => $row['emp_id'] ?? '',
                    'employee_name' => $row['employee_name'] ?? '',
                    'designation' => $row['designation'] ?? '',
                    'branch_id' => $row['branch_id'] ?? '',
                    'branch_name' => $row['branch_name'] ?? '',
                    'state' => $row['state'] ?? '',
                    'city' => $row['city'] ?? '',
                    'status' => $row['status'] ?? '',
                    'status_label' => $row['status_label'] ?? '',
                    'check_in' => $row['check_in'] ?? '--',
                    'check_out' => $row['check_out'] ?? '--',
                    'worked_time' => $row['worked_time'] ?? '--',
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $row): string => Carbon::parse($row['date'], config('app.timezone', 'Asia/Kolkata'))->toDateString())
            ->values();
    }

    private function resolveListFilters(Request $request): array
    {
        return [
            'month' => $request->string('month')->toString() ?: $this->today()->format('Y-m'),
            'emp_id' => trim((string) $request->input('emp_id', '')),
            'employee_name' => trim((string) $request->input('employee_name', '')),
            'state' => '',
        ];
    }

    private function resolveReportFilters(Request $request): array
    {
        $reportView = match ($request->route()?->getName()) {
            'admin-attendance-reports' => 'attendance',
            'admin-salary-reports' => 'salary',
            'admin-advance-reports' => 'advance',
            default => in_array($request->input('report_view'), ['attendance', 'salary', 'advance'], true)
                ? (string) $request->input('report_view')
                : 'attendance',
        };

        return [
            'month' => $request->string('month')->toString() ?: $this->today()->format('Y-m'),
            'from_date' => trim((string) $request->input('from_date', '')),
            'to_date' => trim((string) $request->input('to_date', '')),
            'scope' => in_array($request->input('scope'), ['all', 'ho', 'branch'], true) ? (string) $request->input('scope') : 'all',
            'report_view' => $reportView,
            'emp_id' => trim((string) $request->input('emp_id', '')),
            'location_search' => trim((string) $request->input('location_search', '')),
            'state' => trim((string) $request->input('state', '')),
            'city' => trim((string) $request->input('city', '')),
            'branch_id' => $this->normalizeBranchFilter($request->input('branch_id', '')),
            'table_search' => trim((string) $request->input('table_search', '')),
            'per_page' => $this->resolveReportPerPage($request),
            'menu' => trim((string) $request->input('menu', '')),
        ];
    }

    private function resolveReportPerPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', 25);

        return in_array($perPage, [25, 50, 100, 200], true) ? $perPage : 25;
    }

    private function resolveReviewFilters(Request $request): array
    {
        $defaultFrom = $this->today()->copy()->startOfMonth()->toDateString();
        $defaultTo = $this->today()->toDateString();

        return [
            'month' => $request->string('month')->toString() ?: $this->today()->format('Y-m'),
            'emp_id' => trim((string) $request->input('emp_id', '')),
            'state' => trim((string) $request->input('state', '')),
            'city' => trim((string) $request->input('city', '')),
            'branch_id' => $this->normalizeBranchFilter($request->input('branch_id', '')),
            'from_date' => trim((string) $request->input('from_date', $defaultFrom)) ?: $defaultFrom,
            'to_date' => trim((string) $request->input('to_date', $defaultTo)) ?: $defaultTo,
            'issue' => in_array($request->input('issue'), ['all', self::STATUS_HALF_DAY, self::STATUS_SINGLE_PUNCH], true)
                ? (string) $request->input('issue')
                : 'all',
        ];
    }

    private function resolveReviewRange(array $filters): array
    {
        $month = $this->resolveMonthRange($filters['month']);
        $start = $month['start']->copy();
        $end = $month['end']->copy();

        if (($filters['from_date'] ?? '') !== '' && $this->isDateString($filters['from_date'])) {
            $fromDate = Carbon::parse($filters['from_date'], config('app.timezone', 'Asia/Kolkata'))->startOfDay();

            if ($fromDate->betweenIncluded($month['start'], $month['end'])) {
                $start = $fromDate;
            }
        }

        if (($filters['to_date'] ?? '') !== '' && $this->isDateString($filters['to_date'])) {
            $toDate = Carbon::parse($filters['to_date'], config('app.timezone', 'Asia/Kolkata'))->startOfDay();

            if ($toDate->betweenIncluded($month['start'], $month['end'])) {
                $end = $toDate;
            }
        }

        if ($end->lt($start)) {
            [$start, $end] = [$end->copy(), $start->copy()];
        }

        return [
            'start' => $start->copy()->startOfDay(),
            'end' => $end->copy()->startOfDay(),
        ];
    }

    private function resolveBlockedFilters(Request $request): array
    {
        return [
            'emp_id' => trim((string) $request->input('emp_id', '')),
            'state' => trim((string) $request->input('state', '')),
            'city' => trim((string) $request->input('city', '')),
            'branch_id' => $this->normalizeBranchFilter($request->input('branch_id', '')),
        ];
    }

    private function resolveImportFilters(Request $request): array
    {
        $defaultFrom = $this->today()->copy()->startOfMonth()->toDateString();
        $defaultTo = $this->today()->toDateString();

        return [
            'from_date' => trim((string) $request->input('from_date', $defaultFrom)) ?: $defaultFrom,
            'to_date' => trim((string) $request->input('to_date', $defaultTo)) ?: $defaultTo,
            'search' => trim((string) $request->input('search', $request->input('review_search', ''))),
            'show_single_punch' => $this->resolveImportVisibilityFilter($request, 'show_single_punch'),
            'show_half_day' => $this->resolveImportVisibilityFilter($request, 'show_half_day'),
        ];
    }

    private function resolveDailyAttendanceFilters(Request $request): array
    {
        return [
            'date' => $this->isDateString(trim((string) $request->input('date', '')))
                ? trim((string) $request->input('date'))
                : $this->today()->toDateString(),
            'branch_id' => $this->normalizeBranchFilter($request->input('branch_id', '')),
            'emp_id' => trim((string) $request->input('emp_id', '')),
        ];
    }

    private function resolveImportRange(array $filters): array
    {
        $from = $this->isDateString($filters['from_date'])
            ? $filters['from_date']
            : $this->today()->copy()->startOfMonth()->toDateString();
        $to = $this->isDateString($filters['to_date'])
            ? $filters['to_date']
            : $this->today()->toDateString();

        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }

        return ['from' => $from, 'to' => $to];
    }

    private function importOverviewRows(string $fromDate, string $toDate, string $search = ''): Collection
    {
        $dailySummary = HoAttendanceImport::query()
            ->selectRaw("
                emp_id,
                attendance_date,
                MAX(NULLIF(employee_name, '')) as employee_name,
                MAX(NULLIF(branch_name, '')) as branch_name,
                MIN(login_time) as first_login
            ")
            ->whereBetween('attendance_date', [$fromDate, $toDate])
            ->groupBy('emp_id', 'attendance_date');

        $rows = DB::query()
            ->fromSub($dailySummary, 'imported_summary')
            ->selectRaw("
                emp_id,
                MAX(NULLIF(employee_name, '')) as employee_name,
                MAX(NULLIF(branch_name, '')) as branch_name,
                COUNT(*) as attendance_days,
                SUM(CASE WHEN first_login IS NOT NULL AND first_login <= '09:30:59' THEN 1 ELSE 0 END) as login_930,
                SUM(CASE WHEN first_login BETWEEN '09:31:00' AND '09:40:59' THEN 1 ELSE 0 END) as login_940,
                SUM(CASE WHEN first_login BETWEEN '09:41:00' AND '10:00:59' THEN 1 ELSE 0 END) as login_1000,
                SUM(CASE WHEN first_login BETWEEN '10:01:00' AND '10:30:59' THEN 1 ELSE 0 END) as login_1030,
                SUM(CASE WHEN first_login >= '10:31:00' THEN 1 ELSE 0 END) as login_1031
            ")
            ->groupBy('emp_id')
            ->orderBy('employee_name')
            ->orderBy('emp_id')
            ->get();

        $employeeNameMap = $this->employeeNamesByEmpId($rows->pluck('emp_id'));

        return $rows
            ->map(function (object $row) use ($employeeNameMap): object {
                $row->emp_id = $this->clean($row->emp_id);
                $row->employee_name = $this->importDisplayEmployeeName(
                    $row->emp_id,
                    $row->employee_name,
                    $employeeNameMap
                );

                return $row;
            })
            ->filter(function (object $row) use ($search): bool {
                if ($search === '') {
                    return true;
                }

                return $this->matchesImportSearch([
                    'emp_id' => $row->emp_id,
                    'employee_name' => $row->employee_name,
                ], $search);
            })
            ->values();
    }

    private function importEmployeeReportRows(string $fromDate, string $toDate, string $search = ''): Collection
    {
        $dailyMap = $this->importDailySummaryMap(null, $fromDate, $toDate);
        $start = Carbon::parse($fromDate, config('app.timezone', 'Asia/Kolkata'))->startOfDay();
        $end = Carbon::parse($toDate, config('app.timezone', 'Asia/Kolkata'))->startOfDay();

        return collect($dailyMap)
            ->map(function (array $dateMap, string $empId) use ($start, $end): array {
                $employeeName = '';
                $branchName = 'HO';
                $presentDays = 0;
                $absentDays = 0;
                $weekOffDays = 0;
                $halfDays = 0;
                $singlePunchDays = 0;
                $loggedSeconds = 0;
                $cursor = $start->copy();

                foreach ($dateMap as $daySummary) {
                    $employeeName = $employeeName ?: ($daySummary['employee_name'] ?: $empId);
                    $branchName = $branchName === 'HO' ? ($daySummary['branch_name'] ?: 'HO') : $branchName;
                }

                while ($cursor->lte($end)) {
                    $daySummary = $dateMap[$cursor->toDateString()] ?? null;
                    $status = $this->calendarAttendanceStatus(
                        $this->effectiveImportedAttendanceStatus($daySummary, $cursor->toDateString()),
                        $cursor
                    );

                    if ($daySummary) {
                        $loggedSeconds += (int) ($daySummary['logged_seconds'] ?? 0);
                    }

                    if ($status === self::STATUS_ABSENT) {
                        $absentDays++;
                    } elseif ($status === self::STATUS_WEEKOFF) {
                        $weekOffDays++;
                    } elseif ($status === self::STATUS_HALF_DAY) {
                        $halfDays++;
                        $presentDays++;
                    } elseif ($status === self::STATUS_SINGLE_PUNCH) {
                        $singlePunchDays++;
                        $presentDays++;
                    } elseif ($status === self::STATUS_FULL_DAY) {
                        $presentDays++;
                    }

                    $cursor->addDay();
                }

                return [
                    'emp_id' => $empId,
                    'employee_name' => $employeeName ?: $empId,
                    'branch_name' => $branchName ?: 'HO',
                    'present_days' => $presentDays,
                    'absent_days' => $absentDays,
                    'week_off_days' => $weekOffDays,
                    'half_days' => $halfDays,
                    'single_punch_days' => $singlePunchDays,
                    'logged_seconds' => $loggedSeconds,
                    'logged_time_label' => $this->formatImportedSeconds($loggedSeconds),
                ];
            })
            ->filter(fn (array $row): bool => $this->matchesImportSearch($row, $search))
            ->sortBy(['employee_name', 'emp_id'])
            ->values();
    }

    private function filterImportedEmployeeReportRows(Collection $rows, array $filters): Collection
    {
        $empIdFilter = strtolower($this->clean($filters['emp_id'] ?? ''));
        $employeeNameFilter = strtolower($this->clean($filters['employee_name'] ?? ''));

        return $rows
            ->filter(function (array $row) use ($empIdFilter, $employeeNameFilter): bool {
                if ($empIdFilter !== '' && ! str_contains(strtolower($this->clean($row['emp_id'] ?? '')), $empIdFilter)) {
                    return false;
                }

                if ($employeeNameFilter !== '' && ! str_contains(strtolower($this->clean($row['employee_name'] ?? '')), $employeeNameFilter)) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    private function importReviewRows(string $fromDate, string $toDate, string $status, string $search = ''): Collection
    {
        $today = $this->today()->toDateString();

        return collect($this->importDailySummaryMap(null, $fromDate, $toDate))
            ->flatMap(function (array $dateMap): array {
                return array_values($dateMap);
            })
            ->filter(function (array $row) use ($status, $search, $today): bool {
                if (($row['attendance_date'] ?? '') >= $today) {
                    return false;
                }

                if ($this->clean($row['override_status'] ?? '') !== '') {
                    return false;
                }

                if (($row['status'] ?? '') !== $status) {
                    return false;
                }

                return $this->matchesImportSearch($row, $search);
            })
            ->sortByDesc('attendance_date')
            ->values();
    }

    private function resolveImportVisibilityFilter(Request $request, string $key): bool
    {
        $value = strtolower(trim((string) $request->input($key, '1')));

        return ! in_array($value, ['0', 'false', 'hide', 'off'], true);
    }

    private function matchesImportSearch(array|object $row, string $search): bool
    {
        if ($search === '') {
            return true;
        }

        $needle = strtolower($search);
        $fields = [
            data_get($row, 'emp_id'),
            data_get($row, 'employee_name'),
        ];

        foreach ($fields as $field) {
            if (str_contains(strtolower($this->clean((string) $field)), $needle)) {
                return true;
            }
        }

        return false;
    }

    private function employeeNamesByEmpId(Collection $empIds): array
    {
        $cleanIds = $empIds
            ->map(fn ($empId): string => $this->clean($empId))
            ->filter()
            ->unique()
            ->values();

        if ($cleanIds->isEmpty()) {
            return [];
        }

        return Employee::query()
            ->whereIn('empId', $cleanIds->all())
            ->get(['empId', 'name'])
            ->mapWithKeys(fn (Employee $employee): array => [
                $this->clean($employee->empId) => $this->clean($employee->name),
            ])
            ->filter()
            ->all();
    }

    private function importDisplayEmployeeName(string $empId, ?string $importedName, array $employeeNameMap): string
    {
        $cleanEmpId = $this->clean($empId);
        $employeeName = $employeeNameMap[$cleanEmpId] ?? '';

        if ($employeeName !== '') {
            return $employeeName;
        }

        $cleanImportedName = $this->clean($importedName);

        if ($cleanImportedName !== '' && strcasecmp($cleanImportedName, $cleanEmpId) !== 0) {
            return $cleanImportedName;
        }

        return $cleanEmpId;
    }

    private function importLatestAttendanceDateMap(?Collection $empIds, string $fromDate, string $toDate): array
    {
        if (! ($empIds instanceof Collection)) {
            $cacheKey = sprintf('attendance:import-latest:%s:%s', $fromDate, $toDate);

            return Cache::remember($cacheKey, now()->addSeconds(self::REPORT_CACHE_SECONDS), function () use ($fromDate, $toDate): array {
                return HoAttendanceImport::query()
                    ->selectRaw('emp_id, MAX(attendance_date) as latest_attendance_date')
                    ->whereBetween('attendance_date', [$fromDate, $toDate])
                    ->groupBy('emp_id')
                    ->pluck('latest_attendance_date', 'emp_id')
                    ->mapWithKeys(fn ($date, $empId): array => [$this->clean($empId) => $this->clean($date)])
                    ->filter()
                    ->all();
            });
        }

        $query = HoAttendanceImport::query()
            ->selectRaw('emp_id, MAX(attendance_date) as latest_attendance_date')
            ->whereBetween('attendance_date', [$fromDate, $toDate]);

        if ($empIds->isNotEmpty()) {
            $query->whereIn('emp_id', $empIds->values()->all());
        }

        return $query
            ->groupBy('emp_id')
            ->pluck('latest_attendance_date', 'emp_id')
            ->mapWithKeys(fn ($date, $empId): array => [$this->clean($empId) => $this->clean($date)])
            ->filter()
            ->all();
    }

    private function importDailySummaryMap(?Collection $empIds, string $fromDate, string $toDate): array
    {
        $query = HoAttendanceImport::query()
            ->selectRaw("
                emp_id,
                attendance_date,
                MAX(NULLIF(employee_name, '')) as employee_name,
                MAX(NULLIF(branch_name, '')) as branch_name,
                MIN(COALESCE(login_time, logout_time)) as first_seen,
                MIN(login_time) as first_login,
                MAX(logout_time) as last_logout,
                MAX(COALESCE(logout_time, login_time)) as last_activity,
                MAX(NULLIF(work_duration, '')) as work_duration,
                MAX(NULLIF(attendance_status, '')) as attendance_status,
                SUM(CASE WHEN login_time IS NOT NULL THEN 1 ELSE 0 END) as login_punches,
                SUM(CASE WHEN logout_time IS NOT NULL THEN 1 ELSE 0 END) as logout_punches,
                COUNT(*) as punch_count
            ")
            ->whereBetween('attendance_date', [$fromDate, $toDate]);

        if ($empIds instanceof Collection && $empIds->isNotEmpty()) {
            $query->whereIn('emp_id', $empIds->values()->all());
        }

        $rows = $query
            ->groupBy('emp_id', 'attendance_date')
            ->orderBy('emp_id')
            ->orderBy('attendance_date')
            ->get();

        $overrideMap = $this->importOverrideMap($fromDate, $toDate, $empIds);
        $employeeNameMap = $this->employeeNamesByEmpId($rows->pluck('emp_id'));
        $map = [];

        foreach ($rows as $row) {
            $empId = $this->clean($row->emp_id);
            $attendanceDate = $row->attendance_date;
            $loggedSeconds = $this->parseImportedDurationToSeconds($row->work_duration);
            $firstLogin = $row->first_login ?: $row->first_seen;
            $lastLogout = $row->last_logout;

            if ($loggedSeconds <= 0 && $firstLogin && $lastLogout) {
                $checkIn = Carbon::parse($attendanceDate.' '.$firstLogin);
                $checkOut = Carbon::parse($attendanceDate.' '.$lastLogout);
                $loggedSeconds = $checkOut->gt($checkIn) ? $checkIn->diffInSeconds($checkOut) : 0;
            }

            $daySummary = [
                'emp_id' => $empId,
                'employee_name' => $this->importDisplayEmployeeName($empId, $row->employee_name, $employeeNameMap),
                'branch_name' => trim((string) $row->branch_name) ?: 'HO',
                'attendance_date' => $attendanceDate,
                'first_login' => $firstLogin,
                'first_login_label' => $this->formatImportedTime($firstLogin),
                'last_logout' => $lastLogout,
                'last_logout_label' => $this->formatImportedTime($lastLogout),
                'last_activity' => $row->last_activity,
                'last_activity_label' => $this->formatImportedTime($row->last_activity),
                'attendance_status' => trim((string) $row->attendance_status),
                'work_duration' => trim((string) $row->work_duration),
                'worked_time_label' => $this->formatImportedSeconds($loggedSeconds),
                'logged_seconds' => $loggedSeconds,
                'login_punches' => (int) $row->login_punches,
                'logout_punches' => (int) $row->logout_punches,
                'punch_count' => (int) $row->punch_count,
                'override_status' => $overrideMap[$empId][$attendanceDate] ?? null,
            ];

            $daySummary['status'] = $this->effectiveImportedAttendanceStatus($daySummary, $attendanceDate);
            $daySummary['status_label'] = $this->displayStatusLabel($daySummary['status'], $daySummary['override_status'] ?? null);
            $daySummary['record_key'] = $empId.'|'.$attendanceDate;

            $map[$empId][$attendanceDate] = $daySummary;
        }

        return $map;
    }

    private function importOverrideMap(string $fromDate, string $toDate, ?Collection $empIds = null): array
    {
        $map = [];
        $query = HoAttendanceImportOverride::query()
            ->whereBetween('attendance_date', [$fromDate, $toDate]);

        if ($empIds instanceof Collection && $empIds->isNotEmpty()) {
            $query->whereIn('emp_id', $empIds->values()->all());
        }

        $rows = $query->get();

        foreach ($rows as $row) {
            $map[$this->clean($row->emp_id)][Carbon::parse($row->attendance_date)->toDateString()] = $this->clean($row->final_status);
        }

        return $map;
    }

    private function attendanceDayOverrideMap(Collection $empIds, Carbon $start, Carbon $end): array
    {
        $cleanIds = $empIds
            ->map(fn ($empId): string => $this->clean((string) $empId))
            ->filter()
            ->unique()
            ->values();

        if ($cleanIds->isEmpty()) {
            return [];
        }

        $map = [];
        $rows = AttendanceDayOverride::query()
            ->whereIn('emp_id', $cleanIds->all())
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->get(['emp_id', 'attendance_date', 'final_status']);

        foreach ($rows as $row) {
            $status = $this->clean($row->final_status);

            if (! $this->isRegularizedStatus($status)) {
                continue;
            }

            $map[$this->clean($row->emp_id)][Carbon::parse($row->attendance_date)->toDateString()] = $status;
        }

        return $map;
    }

    private function effectiveImportedAttendanceStatus(?array $daySummary, string $date): string
    {
        if (! $daySummary) {
            if (Carbon::parse($date, config('app.timezone', 'Asia/Kolkata'))->gt($this->today())) {
                return self::STATUS_FUTURE;
            }

            return self::STATUS_ABSENT;
        }

        $override = $this->clean($daySummary['override_status'] ?? '');

        if (in_array($override, [self::STATUS_FULL_DAY, self::STATUS_HALF_DAY, self::STATUS_SINGLE_PUNCH, self::STATUS_ABSENT], true)) {
            return $override;
        }

        $mappedStatus = $this->importedStatusFromCode($daySummary['attendance_status'] ?? '');
        $loggedSeconds = (int) ($daySummary['logged_seconds'] ?? 0);
        $hasCheckIn = $this->clean($daySummary['first_login'] ?? '') !== '';
        $hasCheckOut = $this->clean($daySummary['last_logout'] ?? '') !== '';

        if ($hasCheckIn && ! $hasCheckOut) {
            return self::STATUS_SINGLE_PUNCH;
        }

        if ($mappedStatus === self::STATUS_SINGLE_PUNCH) {
            return self::STATUS_SINGLE_PUNCH;
        }

        if ($mappedStatus === self::STATUS_ABSENT) {
            return self::STATUS_ABSENT;
        }

        $hasAttendanceSignal = $hasCheckIn
            || $this->clean($daySummary['last_activity'] ?? '') !== ''
            || $loggedSeconds > 0
            || in_array($mappedStatus, [self::STATUS_FULL_DAY, self::STATUS_HALF_DAY, self::STATUS_SINGLE_PUNCH], true);

        if ($loggedSeconds > 0) {
            return $loggedSeconds < (self::FULL_DAY_MINUTES * 60)
                ? self::STATUS_HALF_DAY
                : self::STATUS_FULL_DAY;
        }

        if (in_array($mappedStatus, [self::STATUS_FULL_DAY, self::STATUS_HALF_DAY], true)) {
            return $mappedStatus;
        }

        if ($this->clean($daySummary['first_login'] ?? '') !== '' || $this->clean($daySummary['last_activity'] ?? '') !== '') {
            return self::STATUS_SINGLE_PUNCH;
        }

        return self::STATUS_ABSENT;
    }

    private function cumulativeAttendanceStatus(string $appStatus, ?string $importedStatus): string
    {
        $statuses = array_values(array_filter([$appStatus, $importedStatus]));

        foreach ([self::STATUS_FULL_DAY_REMOTE, self::STATUS_FULL_DAY, self::STATUS_HALF_DAY, self::STATUS_SINGLE_PUNCH, self::STATUS_ABSENT] as $status) {
            if (in_array($status, $statuses, true)) {
                return $status;
            }
        }

        return $appStatus;
    }

    private function hasReportActivity(array $records, array $importedRecords, array $dayOverrides): bool
    {
        foreach ($records as $record) {
            if (! $record instanceof Attendance) {
                continue;
            }

            if (
                $this->clean($record->check_in_time) !== ''
                || $this->clean($record->check_out_time) !== ''
                || $this->isRegularizedStatus($this->clean($record->attendance_status_override))
            ) {
                return true;
            }
        }

        foreach ($importedRecords as $record) {
            if (
                $this->clean($record['first_login'] ?? '') !== ''
                || $this->clean($record['last_logout'] ?? '') !== ''
                || $this->isRegularizedStatus($this->clean($record['override_status'] ?? ''))
            ) {
                return true;
            }
        }

        foreach ($dayOverrides as $status) {
            if ($this->isRegularizedStatus($this->clean($status))) {
                return true;
            }
        }

        return false;
    }

    private function importedStatusFromCode(?string $status): ?string
    {
        $normalized = strtolower($this->clean($status));
        $normalized = str_replace([' ', '-', '_', '.'], '', $normalized);

        return match ($normalized) {
            'p', 'present', 'fullday', 'fd' => self::STATUS_FULL_DAY,
            'halfday', 'hd', 'half' => self::STATUS_HALF_DAY,
            'singlepunch', 'single', 'sp' => self::STATUS_SINGLE_PUNCH,
            'a', 'absent' => self::STATUS_ABSENT,
            default => null,
        };
    }

    private function parseImportedDurationToSeconds(?string $duration): int
    {
        $value = $this->clean($duration);

        if ($value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            $numeric = (float) $value;

            if ($numeric > 0 && $numeric <= 1) {
                return (int) round($numeric * 86400);
            }
        }

        if (preg_match('/^(\d{1,3}):(\d{2})(?::(\d{2}))?$/', $value, $matches) === 1) {
            $hours = (int) $matches[1];
            $minutes = (int) $matches[2];
            $seconds = isset($matches[3]) ? (int) $matches[3] : 0;

            return ($hours * 3600) + ($minutes * 60) + $seconds;
        }

        return 0;
    }

    private function formatImportedSeconds(int $seconds): string
    {
        if ($seconds <= 0) {
            return '--';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return sprintf('%02dh %02dm', $hours, $minutes);
    }

    private function formatImportedTime(?string $time): ?string
    {
        $value = $this->clean($time);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('h:i A');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function formatImportedDateTime(string $date, ?string $time): ?string
    {
        $value = $this->clean($time);

        if ($value === '') {
            return null;
        }

        try {
            $dateTime = preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1
                ? $value
                : $date.' '.$value;

            return Carbon::parse($dateTime)->format('d M Y h:i A');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function resolveMonthRange(?string $month): array
    {
        $value = $month && preg_match('/^\d{4}-\d{2}$/', $month) ? $month : $this->today()->format('Y-m');
        $start = Carbon::createFromFormat('!Y-m', $value, config('app.timezone', 'Asia/Kolkata'))->startOfMonth();

        return [
            'value' => $start->format('Y-m'),
            'label' => $start->format('F Y'),
            'start' => $start,
            'end' => $start->copy()->endOfMonth(),
        ];
    }

    private function resolveCalendarRange(Request $request, array $month): array
    {
        $start = $month['start']->copy();
        $end = $month['end']->copy();
        $fromDateValue = trim((string) $request->input('from_date', ''));
        $toDateValue = trim((string) $request->input('to_date', ''));

        if ($fromDateValue !== '' && $this->isDateString($fromDateValue)) {
            $fromDate = Carbon::parse($fromDateValue, config('app.timezone', 'Asia/Kolkata'))->startOfDay();

            if ($fromDate->betweenIncluded($month['start'], $month['end'])) {
                $start = $fromDate;
            }
        }

        if ($toDateValue !== '' && $this->isDateString($toDateValue)) {
            $toDate = Carbon::parse($toDateValue, config('app.timezone', 'Asia/Kolkata'))->startOfDay();

            if ($toDate->betweenIncluded($month['start'], $month['end'])) {
                $end = $toDate;
            }
        }

        if ($end->lt($start)) {
            [$start, $end] = [$end->copy(), $start->copy()];
        }

        return [
            'start' => $start->copy()->startOfDay(),
            'end' => $end->copy()->startOfDay(),
        ];
    }

    private function resolveReportRange(array $filters): array
    {
        $month = $this->resolveMonthRange($filters['month']);
        $start = $month['start']->copy();
        $end = $month['end']->copy();

        if ($filters['report_view'] === 'advance') {
            if ($filters['from_date'] !== '' && $this->isDateString($filters['from_date'])) {
                $start = Carbon::parse($filters['from_date'], config('app.timezone', 'Asia/Kolkata'))->startOfDay();
            }

            if ($filters['to_date'] !== '' && $this->isDateString($filters['to_date'])) {
                $end = Carbon::parse($filters['to_date'], config('app.timezone', 'Asia/Kolkata'))->startOfDay();
            }
        } else {
            if ($filters['from_date'] !== '' && $this->isDateString($filters['from_date'])) {
                $fromDate = Carbon::parse($filters['from_date'], config('app.timezone', 'Asia/Kolkata'))->startOfDay();

                if ($fromDate->betweenIncluded($month['start'], $month['end'])) {
                    $start = $fromDate;
                }
            }

            if ($filters['to_date'] !== '' && $this->isDateString($filters['to_date'])) {
                $toDate = Carbon::parse($filters['to_date'], config('app.timezone', 'Asia/Kolkata'))->startOfDay();

                if ($toDate->betweenIncluded($month['start'], $month['end'])) {
                    $end = $toDate;
                }
            }
        }

        if ($end->lt($start)) {
            [$start, $end] = [$end->copy(), $start->copy()];
        }

        return [
            'start' => $start->copy()->startOfDay(),
            'end' => $end->copy()->startOfDay(),
        ];
    }

    private function resolveAdvanceReportWindow(array $filters): array
    {
        $month = $this->resolveMonthRange($filters['month']);

        return [
            'start' => $month['start']->copy()->day(13)->startOfDay(),
            'end' => $month['start']->copy()->addMonthNoOverflow()->day(11)->startOfDay(),
        ];
    }

    private function buildEmployeeRows(
        Collection $employees,
        array $attendanceMap,
        array $importedAttendanceMap,
        array $latestAttendanceMap,
        Collection $branchMap,
        Carbon $start,
        Carbon $end,
        array $advanceTotalsByEmployeeId = [],
        bool $applyAppSalaryRules = false
    ): Collection {
        $today = $this->today();
        $salaryDaysInMonth = $start->daysInMonth;
        $todayDate = $today->copy()->startOfDay();
        $isCurrentMonthRange = $start->isSameMonth($today);
        $summaryEndDate = $applyAppSalaryRules && $isCurrentMonthRange
            ? $end->copy()->min($todayDate->copy()->subDay())
            : $end->copy();
        $employmentStartDates = $this->employmentStartDateMap($employees->pluck('empId'));
        $consecutiveAbsences = $this->blockService->consecutiveAbsentDaysForEmployees($employees, $today);
        $dayOverrideMap = $this->attendanceDayOverrideMap($employees->pluck('empId'), $start, $end);

        return $employees->map(function (Employee $employee) use (
            $attendanceMap,
            $importedAttendanceMap,
            $dayOverrideMap,
            $latestAttendanceMap,
            $branchMap,
            $start,
            $end,
            $todayDate,
            $isCurrentMonthRange,
            $summaryEndDate,
            $salaryDaysInMonth,
            $employmentStartDates,
            $consecutiveAbsences,
            $advanceTotalsByEmployeeId,
            $applyAppSalaryRules
        ): array {
            $empId = $this->clean($employee->empId);
            $detail = $employee->detail;
            $records = $attendanceMap[$empId] ?? [];
            $importedRecords = $importedAttendanceMap[$empId] ?? [];
            $dayOverrides = $dayOverrideMap[$empId] ?? [];
            $latest = $latestAttendanceMap[$empId] ?? null;
            $firstActivityDate = collect([
                $employmentStartDates[$empId] ?? null,
                collect(array_keys($importedRecords))->sort()->first(),
                collect($dayOverrides)
                    ->filter(fn ($status): bool => $this->isRegularizedStatus($this->clean($status)))
                    ->keys()
                    ->sort()
                    ->first(),
            ])->filter()->sort()->first();
            $employmentStartDate = $this->employmentStartDate($employee, $firstActivityDate);
            $branchId = $latest['branch_id'] ?? '';
            /** @var Branch|null $branch */
            $branch = $branchMap->get($branchId);
            $fullDays = 0;
            $halfDays = 0;
            $singlePunchDays = 0;
            $absentDays = 0;
            $weekOffDays = 0;
            $regularizedDays = 0;
            $regularizedPayableDays = 0;
            $completedPunches = 0;
            $anyPunches = 0;
            $paidSundayDays = 0;
            $sundayLoggedDays = 0;
            $lastPresentDate = $latest['check_in_date'] ?? null;
            $cursor = $start->copy();

            while ($cursor->lte($summaryEndDate)) {
                $date = $cursor->toDateString();
                /** @var Attendance|null $record */
                $record = $records[$date] ?? null;
                $importedRecord = $importedRecords[$date] ?? null;
                $dayOverrideStatus = $this->clean($dayOverrides[$date] ?? '');
                $overrideStatus = $this->clean($record?->attendance_status_override);
                $importOverrideStatus = $this->clean($importedRecord['override_status'] ?? '');
                $regularizedStatus = collect([
                    $dayOverrideStatus,
                    $overrideStatus,
                    $importOverrideStatus,
                ])->first(fn (string $status): bool => $this->isRegularizedStatus($status)) ?? '';
                $isRegularizedDay = $regularizedStatus !== '';
                $appStatus = $this->effectiveAttendanceStatus($record, $date, $employmentStartDate);
                $importedStatus = $importedRecord
                    ? $this->effectiveImportedAttendanceStatus($importedRecord, $date)
                    : null;
                $status = $this->applyAttendanceDayOverrideStatus(
                    $this->calendarAttendanceStatus(
                        $this->cumulativeAttendanceStatus($appStatus, $importedStatus),
                        $cursor
                    ),
                    $regularizedStatus
                );

                if ($employmentStartDate instanceof Carbon && $cursor->lt($employmentStartDate)) {
                    $cursor->addDay();

                    continue;
                }

                $hasAppCompletedPunch = $record && $record->check_out_date && $record->check_out_time;
                $hasImportedCompletedPunch = $importedRecord
                    && $this->clean($importedRecord['first_login'] ?? '') !== ''
                    && $this->clean($importedRecord['last_logout'] ?? '') !== '';
                $hasAnyPunch = ($record && (
                    $this->clean($record->check_in_time) !== ''
                    || $this->clean($record->check_out_time) !== ''
                )) || ($importedRecord && (
                    $this->clean($importedRecord['first_login'] ?? '') !== ''
                    || $this->clean($importedRecord['last_logout'] ?? '') !== ''
                ));

                if ($hasAnyPunch) {
                    $anyPunches++;
                }

                if ($hasAppCompletedPunch || $hasImportedCompletedPunch) {
                    $completedPunches++;
                }

                if ($cursor->isSunday() && ! $isRegularizedDay && $cursor->lte($todayDate)) {
                    if ($hasAppCompletedPunch || $hasImportedCompletedPunch) {
                        $sundayLoggedDays++;
                    }

                    if ($applyAppSalaryRules && (! $isCurrentMonthRange || $cursor->lt($todayDate))) {
                        $paidSundayDays++;
                    }

                    $weekOffDays++;
                    $cursor->addDay();

                    continue;
                }

                if ($isRegularizedDay) {
                    $regularizedDays++;
                }

                if (in_array($status, [self::STATUS_FULL_DAY, self::STATUS_FULL_DAY_REMOTE], true)) {
                    $fullDays++;
                    $lastPresentDate = $this->latestDateString($lastPresentDate, $date);
                } elseif ($status === self::STATUS_HALF_DAY) {
                    $halfDays++;
                    $lastPresentDate = $this->latestDateString($lastPresentDate, $date);
                } elseif ($status === self::STATUS_SINGLE_PUNCH) {
                    $singlePunchDays++;
                    $lastPresentDate = $this->latestDateString($lastPresentDate, $date);
                    if ($isRegularizedDay) {
                        $regularizedPayableDays++;
                    }
                } elseif ($status === self::STATUS_ABSENT) {
                    $absentDays++;
                    if ($isRegularizedDay) {
                        $regularizedPayableDays++;
                    }
                } elseif ($status === self::STATUS_WEEKOFF) {
                    $weekOffDays++;
                }

                $cursor->addDay();
            }

            $presentDays = $fullDays;
            $payableDays = $presentDays + (0.5 * $halfDays) + $weekOffDays + $regularizedPayableDays;
            $hasSalaryActivity = $anyPunches > 0 || $regularizedDays > 0;

            if (! $hasSalaryActivity) {
                $payableDays = 0.0;
            }

            $reportPayableDays = $payableDays;
            $salary = is_numeric($employee->salary) ? (float) $employee->salary : null;
            $advance = array_key_exists((int) $employee->id, $advanceTotalsByEmployeeId)
                ? (float) $advanceTotalsByEmployeeId[(int) $employee->id]
                : 0.0;
            $configuredPf = is_numeric($detail?->pfAmount)
                ? (float) $detail->pfAmount
                : (is_numeric($employee->pf) ? (float) $employee->pf : 0.0);
            $pf = MayPfEligibility::deductionFor(
                $start,
                $employee->empId,
                $detail?->uanNumber,
                $configuredPf,
                $employee->pf_eligible
            );
            $securityDeposit = MayPfSecurityDeposit::amountFor(
                $start,
                $employee->empId,
                $detail?->uanNumber,
                $employee->designation ?: $detail?->designation,
                $employee->pf_eligible,
                $employee->pfsecuritydeposit
            );

            if (! $hasSalaryActivity) {
                $pf = 0.0;
                $securityDeposit = 0.0;
            }
            $salaryPerDay = $salary !== null && $salaryDaysInMonth > 0
                ? SalaryProration::dailyRate($salary, $salaryDaysInMonth)
                : null;
            $grossPayableSalary = $salaryPerDay !== null
                ? ($hasSalaryActivity
                    ? SalaryProration::grossPayable($salary, $salaryDaysInMonth, $payableDays)
                    : 0.0)
                : null;
            $salaryPayable = $grossPayableSalary !== null
                ? ($hasSalaryActivity
                    ? round($grossPayableSalary - $advance - $pf - $securityDeposit)
                    : 0.0)
                : null;
            $punctuality = $this->punctualityService->analyzeEmployee(
                $employee,
                $records,
                $importedRecords,
                $branch,
                $start,
                $end
            );

            return [
                'id' => $employee->id,
                'emp_id' => $empId,
                'employee_name' => $employee->name,
                'contact' => $this->clean($employee->contact),
                'account_name' => $this->clean($detail?->empName) ?: $employee->name,
                'bank_name' => $this->clean($detail?->bankName),
                'bank_account_number' => $this->clean($detail?->bankAcNo),
                'ifsc_code' => $this->clean($detail?->ifscCode),
                'uan_number' => $this->clean($detail?->uanNumber),
                'passbook_doc' => $this->clean($detail?->passbookDoc),
                'passbook_doc_url' => $this->clean($detail?->passbook_doc_url),
                'designation' => $employee->designation,
                'status' => $employee->status ?: 'Unknown',
                'branch_id' => $branchId,
                'branch_name' => $branch?->branchName,
                'state' => $branch?->state,
                'city' => $branch?->city,
                'full_days' => $fullDays,
                'half_days' => $halfDays,
                'single_punches' => $singlePunchDays,
                'absent_days' => $absentDays,
                'week_off_days' => $weekOffDays,
                'paid_sunday_days' => $paidSundayDays,
                'sunday_logged_days' => $sundayLoggedDays,
                'present_days' => $presentDays,
                'regularized_days' => $regularizedDays,
                'completed_punches' => $completedPunches,
                'credited_days' => $payableDays,
                'payable_days' => $reportPayableDays,
                'payable_days_label' => $this->formatReportDayCount($reportPayableDays),
                'salary' => $salary,
                'salary_per_day' => $salaryPerDay,
                'salary_days_in_month' => $salaryDaysInMonth,
                'advance' => $advance,
                'pf' => $pf,
                'pf_eligible' => $employee->pf_eligible,
                'security_deposit' => $securityDeposit,
                'gross_payable_salary' => $grossPayableSalary,
                'payable_salary' => $salaryPayable,
                'net_payable_salary' => $salaryPayable,
                'late_logins' => $punctuality['late_days_count'],
                'early_logouts' => $punctuality['early_logout_days_count'],
                'irregular_days' => $punctuality['irregular_days_count'],
                'punctuality_schedule' => $punctuality['schedule_label'],
                'late_details' => $punctuality['details'],
                'last_present_date' => $lastPresentDate,
                'consecutive_absences' => $consecutiveAbsences[(int) $employee->id] ?? 0,
            ];
        })->values();
    }

    private function advanceTotalsByEmployee(Collection $employees, string $fromDate, string $toDate): array
    {
        if ($employees->isEmpty()) {
            return [];
        }

        return EmployeeAdvanceTransaction::query()
            ->selectRaw('employee_id, COALESCE(SUM(amount), 0) as total_advance')
            ->whereIn('employee_id', $employees->pluck('id')->all())
            ->whereBetween('advance_date', [$fromDate, $toDate])
            ->groupBy('employee_id')
            ->pluck('total_advance', 'employee_id')
            ->map(fn ($value): float => (float) $value)
            ->all();
    }

    private function advancePayrollRange(Carbon $payrollMonth): array
    {
        $anchor = $payrollMonth->copy()->startOfMonth();

        return [
            'start' => $anchor->copy()->day(13)->startOfDay(),
            'end' => $anchor->copy()->addMonthNoOverflow()->day(11)->startOfDay(),
        ];
    }

    private function advanceReportRows(
        Collection $employees,
        array $latestAttendanceMap,
        Collection $branchMap,
        string $fromDate,
        string $toDate
    ): Collection {
        if ($employees->isEmpty()) {
            return collect();
        }

        $employeeMap = $employees->keyBy(fn (Employee $employee): int => (int) $employee->id);
        $transactions = EmployeeAdvanceTransaction::query()
            ->whereIn('employee_id', $employeeMap->keys()->all())
            ->whereBetween('advance_date', [$fromDate, $toDate])
            ->orderByDesc('advance_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('employee_id');

        return $transactions
            ->map(function (Collection $rows, int|string $employeeId) use ($employeeMap, $latestAttendanceMap, $branchMap): ?array {
                /** @var Employee|null $employee */
                $employee = $employeeMap->get((int) $employeeId);

                if (! $employee) {
                    return null;
                }

                $empId = $this->clean($employee->empId);
                $latest = $latestAttendanceMap[$empId] ?? null;
                $branchId = $this->clean($employee->assigned_branch_id) ?: ($latest['branch_id'] ?? '');
                /** @var Branch|null $branch */
                $branch = $branchMap->get($branchId);

                return [
                    'id' => $employee->id,
                    'emp_id' => $empId,
                    'employee_name' => $employee->name,
                    'designation' => $employee->designation,
                    'branch_id' => $branchId,
                    'branch_name' => $branch?->branchName,
                    'state' => $branch?->state,
                    'city' => $branch?->city,
                    'transaction_count' => $rows->count(),
                    'total_advance' => (float) $rows->sum('amount'),
                    'last_advance_date' => $rows->max('advance_date'),
                    'pf' => is_numeric($employee->pf) ? (float) $employee->pf : 0.0,
                ];
            })
            ->filter()
            ->sortBy([
                ['employee_name', 'asc'],
                ['emp_id', 'asc'],
            ])
            ->values();
    }

    private function buildHoSummary(Collection $employees, array $attendanceMap, array $todayAttendanceMap): array
    {
        $todayString = $this->today()->toDateString();
        $presentToday = 0;
        $singlePunchToday = 0;
        $absentToday = 0;
        $completedPunches = 0;

        foreach ($employees as $employee) {
            $empId = $this->clean($employee->empId);
            /** @var Attendance|null $todayRecord */
            $todayRecord = $todayAttendanceMap[$empId][$todayString] ?? null;
            $status = $this->effectiveAttendanceStatus($todayRecord, $todayString);

            if (in_array($status, [self::STATUS_FULL_DAY, self::STATUS_FULL_DAY_REMOTE, self::STATUS_HALF_DAY], true)) {
                $presentToday++;
            } elseif ($status === self::STATUS_SINGLE_PUNCH) {
                $singlePunchToday++;
            } elseif ($status === self::STATUS_ABSENT) {
                $absentToday++;
            }

            foreach ($attendanceMap[$empId] ?? [] as $record) {
                if ($record->check_out_date && $record->check_out_time) {
                    $completedPunches++;
                }
            }
        }

        return [
            'employees' => $employees->count(),
            'present_today' => $presentToday,
            'absent_today' => $absentToday,
            'single_punch_today' => $singlePunchToday,
            'blocked_employees' => $employees->where('status', 'Blocked')->count(),
            'completed_punches' => $completedPunches,
        ];
    }

    private function groupRowsByRegion(Collection $rows): Collection
    {
        return $rows
            ->groupBy(fn (array $row): string => ($row['state'] ?: '--').'|'.($row['city'] ?: '--'))
            ->map(function (Collection $group): array {
                return [
                    'state' => $group->first()['state'] ?: '--',
                    'city' => $group->first()['city'] ?: '--',
                    'employee_count' => $group->count(),
                    'branch_count' => $group->pluck('branch_id')->filter()->unique()->count(),
                    'full_days' => $group->sum('full_days'),
                    'half_days' => $group->sum('half_days'),
                    'single_punches' => $group->sum('single_punches'),
                    'absent_days' => $group->sum('absent_days'),
                    'blocked_employees' => $group->where('status', 'Blocked')->count(),
                ];
            })
            ->values();
    }

    private function groupRowsByBranch(Collection $rows, bool $onlyHo, ?string $hoBranchId): Collection
    {
        $filtered = $rows;

        if ($hoBranchId) {
            $filtered = $filtered->filter(function (array $row) use ($onlyHo, $hoBranchId): bool {
                $isHo = $row['branch_id'] === $hoBranchId;

                return $onlyHo ? $isHo : ! $isHo;
            })->values();
        }

        return $filtered
            ->groupBy(fn (array $row): string => $row['branch_id'] ?: '--')
            ->map(function (Collection $group): array {
                return [
                    'branch_id' => $group->first()['branch_id'] ?: '--',
                    'branch_name' => $group->first()['branch_name'] ?: '--',
                    'city' => $group->first()['city'] ?: '--',
                    'state' => $group->first()['state'] ?: '--',
                    'employee_count' => $group->count(),
                    'full_days' => $group->sum('full_days'),
                    'half_days' => $group->sum('half_days'),
                    'single_punches' => $group->sum('single_punches'),
                    'absent_days' => $group->sum('absent_days'),
                    'completed_punches' => $group->sum('completed_punches'),
                    'blocked_employees' => $group->where('status', 'Blocked')->count(),
                ];
            })
            ->values();
    }

    private function attendanceRecordMap(Collection $empIds, Carbon $start, Carbon $end): array
    {
        $cleanIds = $empIds
            ->map(fn ($empId): string => $this->clean((string) $empId))
            ->filter()
            ->unique()
            ->values();

        if ($cleanIds->isEmpty()) {
            return [];
        }

        $map = [];
        $records = Attendance::query()
            ->whereIn('empId', $cleanIds->all())
            ->whereBetween('check_in_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('check_in_date')
            ->orderBy('id')
            ->get([
                'id',
                'empId',
                'check_in_branch_id',
                'check_out_branch_id',
                'check_in_date',
                'check_in_time',
                'check_out_date',
                'check_out_time',
                'photo_path',
                'check_out_photo_path',
                'latitude',
                'longitude',
                'check_out_latitude',
                'check_out_longitude',
                'attendance_status_override',
            ]);

        foreach ($records as $record) {
            $empId = $this->clean($record->empId);
            $date = Carbon::parse($record->check_in_date)->toDateString();
            $map[$empId][$date] = $record;
        }

        return $map;
    }

    private function latestAttendanceMap(): array
    {
        return Cache::remember('attendance:latest-map', now()->addSeconds(self::REPORT_CACHE_SECONDS), function (): array {
            $latestKeysByEmpId = DB::table('attendance')
                ->selectRaw("TRIM(empId) as emp_id, MAX(CONCAT(check_in_date, '#', LPAD(id, 20, '0'))) as latest_key")
                ->whereNotNull('check_in_date')
                ->groupByRaw('TRIM(empId)')
                ->pluck('latest_key', 'emp_id');
            $latestIds = $latestKeysByEmpId
                ->map(function (?string $key): ?int {
                    if (! $key || ! str_contains($key, '#')) {
                        return null;
                    }

                    return (int) substr($key, strrpos($key, '#') + 1);
                })
                ->filter()
                ->values()
                ->all();
            $map = [];
            $records = $latestIds === []
                ? collect()
                : Attendance::query()
                    ->whereIn('id', $latestIds)
                    ->get(['empId', 'check_in_branch_id', 'check_out_branch_id', 'check_in_date']);

            foreach ($records as $record) {
                $map[$this->clean($record->empId)] = [
                    'branch_id' => $this->attendanceBranchId($record),
                    'check_in_date' => $record->check_in_date,
                ];
            }

            return $map;
        });
    }

    private function latestAttendanceMapWithImportedHo(array $latestAttendanceMap, array $importedAttendanceMap, ?string $hoBranchId): array
    {
        $cleanHoBranchId = $this->clean($hoBranchId);

        if ($cleanHoBranchId === '') {
            return $latestAttendanceMap;
        }

        foreach ($importedAttendanceMap as $empId => $dateMap) {
            if ($dateMap === []) {
                continue;
            }

            $importedDates = array_keys($dateMap);
            rsort($importedDates);
            $latestImportedDate = $importedDates[0] ?? null;

            if (! $latestImportedDate) {
                continue;
            }

            $latestAppDate = $latestAttendanceMap[$empId]['check_in_date'] ?? null;

            if ($latestAppDate && $latestAppDate >= $latestImportedDate) {
                continue;
            }

            $latestAttendanceMap[$empId] = [
                'branch_id' => $cleanHoBranchId,
                'check_in_date' => $latestImportedDate,
            ];
        }

        return $latestAttendanceMap;
    }

    private function latestAttendanceMapWithImportedHoDates(array $latestAttendanceMap, array $importedLatestAttendanceMap, ?string $hoBranchId): array
    {
        $cleanHoBranchId = $this->clean($hoBranchId);

        if ($cleanHoBranchId === '') {
            return $latestAttendanceMap;
        }

        foreach ($importedLatestAttendanceMap as $empId => $latestImportedDate) {
            $empId = $this->clean($empId);
            $latestImportedDate = $this->clean($latestImportedDate);

            if ($empId === '' || $latestImportedDate === '') {
                continue;
            }

            $latestAppDate = $latestAttendanceMap[$empId]['check_in_date'] ?? null;

            if ($latestAppDate && $latestAppDate >= $latestImportedDate) {
                continue;
            }

            $latestAttendanceMap[$empId] = [
                'branch_id' => $cleanHoBranchId,
                'check_in_date' => $latestImportedDate,
            ];
        }

        return $latestAttendanceMap;
    }

    private function latestDateString(?string $currentDate, string $candidateDate): string
    {
        $currentDate = $this->clean($currentDate);

        return $currentDate !== '' && $currentDate >= $candidateDate
            ? $currentDate
            : $candidateDate;
    }

    private function withoutInactiveEmployees(Collection $employees): Collection
    {
        return $employees
            ->reject(fn (Employee $employee): bool => strtolower($this->clean($employee->status)) === 'inactive')
            ->values();
    }

    private function filterEmployees(
        Collection $employees,
        array $filters,
        array $latestAttendanceMap,
        Collection $branchMap,
        string $scope,
        ?string $hoBranchId
    ): Collection {
        return $employees->filter(function (Employee $employee) use ($filters, $latestAttendanceMap, $branchMap, $scope, $hoBranchId): bool {
            $empId = $this->clean($employee->empId);

            if (($filters['emp_id'] ?? '') !== '' && ! str_contains(strtolower($empId), strtolower($filters['emp_id']))) {
                return false;
            }

            $employeeNameFilter = strtolower($this->clean($filters['employee_name'] ?? ''));

            if ($employeeNameFilter !== '' && ! str_contains(strtolower($this->clean($employee->name)), $employeeNameFilter)) {
                return false;
            }

            $latest = $latestAttendanceMap[$empId] ?? null;
            $branchId = $this->clean($employee->assigned_branch_id) ?: ($latest['branch_id'] ?? '');
            /** @var Branch|null $branch */
            $branch = $branchMap->get($branchId);

            if (($filters['state'] ?? '') !== '' && strcasecmp($this->clean($branch?->state), $filters['state']) !== 0) {
                return false;
            }

            if (($filters['city'] ?? '') !== '' && strcasecmp($this->clean($branch?->city), $filters['city']) !== 0) {
                return false;
            }

            if (($filters['branch_id'] ?? '') !== '' && $branchId !== $filters['branch_id']) {
                return false;
            }

            if (($filters['location_search'] ?? '') !== '' && ! $this->branchMatchesLocationSearch($branch, $filters['location_search'])) {
                return false;
            }

            if ($scope === 'ho' && $hoBranchId && $branchId !== $hoBranchId) {
                return false;
            }

            if ($scope === 'branch' && $hoBranchId && ($branchId === '' || $branchId === $hoBranchId)) {
                return false;
            }

            return true;
        })->values();
    }

    private function filterReportTableSearch(
        Collection $employees,
        string $search,
        array $latestAttendanceMap,
        Collection $branchMap
    ): Collection {
        $needle = strtolower($this->clean($search));

        if ($needle === '') {
            return $employees->values();
        }

        return $employees->filter(function (Employee $employee) use ($needle, $latestAttendanceMap, $branchMap): bool {
            $empId = $this->clean($employee->empId);
            $latest = $latestAttendanceMap[$empId] ?? null;
            $branchId = $latest['branch_id'] ?? '';
            /** @var Branch|null $branch */
            $branch = $branchMap->get($branchId);
            $detail = $employee->detail;
            $fields = [
                $empId,
                $employee->name,
                $employee->contact,
                $employee->designation,
                $employee->status,
                $branchId,
                $branch?->branchName,
                $branch?->city,
                $branch?->state,
                $detail?->empName,
                $detail?->bankName,
                $detail?->bankAcNo,
                $detail?->ifscCode,
                $detail?->uanNumber,
            ];

            foreach ($fields as $field) {
                if (str_contains(strtolower($this->clean((string) $field)), $needle)) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    private function paginateReportEmployees(Collection $employees, Request $request, int $perPage): LengthAwarePaginator
    {
        $page = max((int) $request->input('page', 1), 1);
        $total = $employees->count();
        $items = $employees->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }

    private function paginateReportRows(Collection $rows, Request $request, int $perPage): LengthAwarePaginator
    {
        $page = max((int) $request->input('page', 1), 1);
        $total = $rows->count();
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }

    private function employeeCollection(bool $includeDetails = true, bool $includeAdvanceTotal = true): Collection
    {
        $employeeColumns = [
            'id',
            'empId',
            'name',
            'contact',
            'designation',
            'status',
            'salary',
            'pf',
            'shift_timing',
            'is_night_shift',
        ];

        if (Schema::hasColumn('employee', 'pf_eligible')) {
            $employeeColumns[] = 'pf_eligible';
        }

        if (Schema::hasColumn('employee', 'pfsecuritydeposit')) {
            $employeeColumns[] = 'pfsecuritydeposit';
        }

        if (Schema::hasColumn('employee', 'assigned_branch_id')) {
            $employeeColumns[] = 'assigned_branch_id';
        }

        $query = Employee::query()
            ->where(function ($query): void {
                $query->where('is_outsourced', false)
                    ->orWhereNull('is_outsourced');
            })
            ->select($employeeColumns);

        if ($includeAdvanceTotal) {
            $query->withSum('advanceTransactions as effective_advance_total', 'amount');
        }

        $employees = $query
            ->orderBy('name')
            ->get();

        if ($includeDetails) {
            $this->attachEmployeeDetails($employees);
        }

        return $employees;
    }

    private function attachEmployeeDetails(Collection $employees): void
    {
        $empIds = $employees
            ->map(fn (Employee $employee): string => $this->clean($employee->empId))
            ->filter()
            ->unique()
            ->values();

        if ($empIds->isEmpty()) {
            return;
        }

        $detailsByEmployeeId = EmployeeDetail::query()
            ->whereIn(DB::raw('TRIM(employeeId)'), $empIds->all())
            ->orderByDesc('id')
            ->get([
                'id',
                'employeeId',
                'empName',
                'designation',
                'bankName',
                'bankAcNo',
                'ifscCode',
                'uanNumber',
                'passbookDoc',
                'salary',
                'pfAmount',
            ])
            ->unique(fn (EmployeeDetail $detail): string => $this->clean($detail->employeeId))
            ->keyBy(fn (EmployeeDetail $detail): string => $this->clean($detail->employeeId));

        $employees->each(function (Employee $employee) use ($detailsByEmployeeId): void {
            $employee->setRelation('detail', $detailsByEmployeeId->get($this->clean($employee->empId)));
        });
    }

    private function nightShiftEmployeeIds(): Collection
    {
        return Employee::query()
            ->where('is_night_shift', true)
            ->where(function ($query): void {
                $query->where('is_outsourced', false)
                    ->orWhereNull('is_outsourced');
            })
            ->pluck('empId')
            ->map(fn ($empId): string => $this->clean($empId))
            ->filter()
            ->unique()
            ->values();
    }

    private function outsourcedEmployeeIds(): Collection
    {
        return Employee::query()
            ->where('is_outsourced', true)
            ->pluck('empId')
            ->map(fn ($empId): string => $this->clean($empId))
            ->filter()
            ->unique()
            ->values();
    }

    private function branchMatchesLocationSearch(?Branch $branch, string $search): bool
    {
        if (! $branch) {
            return false;
        }

        $needle = strtolower($this->clean($search));

        if ($needle === '') {
            return true;
        }

        $branchLabel = $this->clean($branch->branchId).' - '.$this->clean($branch->branchName);
        $fields = [
            $branch->state,
            $branch->city,
            $branch->branchId,
            $branch->branchName,
            $branchLabel,
        ];

        foreach ($fields as $field) {
            if (str_contains(strtolower($this->clean((string) $field)), $needle)) {
                return true;
            }
        }

        return false;
    }

    private function reportLocationOptions(Collection $activeBranches): Collection
    {
        $options = collect();

        foreach ($activeBranches as $branch) {
            $state = $this->clean($branch->state);
            $city = $this->clean($branch->city);
            $branchId = $this->clean($branch->branchId);
            $branchName = $this->clean($branch->branchName);

            if ($state !== '') {
                $options->push(['value' => $state, 'label' => 'State', 'rank' => 1]);
            }

            if ($city !== '') {
                $options->push(['value' => $city, 'label' => 'City'.($state !== '' ? ' - '.$state : ''), 'rank' => 2]);
            }

            if ($branchId !== '' || $branchName !== '') {
                $options->push([
                    'value' => trim($branchId.' - '.$branchName, ' -'),
                    'label' => trim(implode(', ', array_filter([$city, $state]))) ?: 'Active Branch',
                    'rank' => 3,
                ]);
            }
        }

        return $options
            ->unique(fn (array $option): string => $option['rank'].'|'.strtolower($option['value']))
            ->sortBy([['rank', 'asc'], ['value', 'asc']])
            ->map(fn (array $option): array => [
                'value' => $option['value'],
                'label' => $option['label'],
            ])
            ->values();
    }

    private function reportRouteForView(string $reportView): string
    {
        return match ($reportView) {
            'salary' => 'admin-salary-reports',
            'advance' => 'admin-advance-reports',
            default => 'admin-attendance-reports',
        };
    }

    private function salaryReportPayload(Request $request): array
    {
        $this->blockService->syncEligibleEmployees();

        $branches = $this->branchCollection();
        $activeBranches = $this->activeBranchCollection();
        $branchMap = $branches->keyBy(fn (Branch $branch): string => $this->clean($branch->branchId));
        $activeBranchMap = $activeBranches->keyBy(fn (Branch $branch): string => $this->clean($branch->branchId));
        $latestAttendanceMap = $this->latestAttendanceMap();
        $filters = $this->resolveReportFilters($request);
        $filters['report_view'] = 'salary';
        $range = $this->resolveReportRange($filters);
        $hoBranch = $this->headOfficeBranch($branches);
        $allEmployees = $this->withoutInactiveEmployees(
            $this->employeeCollection(includeDetails: false)
        );
        $importedAttendanceMap = $this->importDailySummaryMap(
            $allEmployees->pluck('empId')->map(fn ($empId) => $this->clean($empId))->filter()->values(),
            $range['start']->toDateString(),
            $range['end']->toDateString()
        );
        $reportLatestAttendanceMap = $this->latestAttendanceMapWithImportedHo(
            $latestAttendanceMap,
            $importedAttendanceMap,
            $hoBranch?->branchId
        );

        $employees = $this->filterEmployees(
            $allEmployees,
            $filters,
            $reportLatestAttendanceMap,
            $filters['location_search'] !== '' ? $activeBranchMap : $branchMap,
            $filters['scope'],
            $hoBranch?->branchId
        );
        $this->attachEmployeeDetails($employees);

        $attendanceMap = $this->attendanceRecordMap(
            $employees->pluck('empId'),
            $range['start'],
            $range['end']
        );
        $advanceRange = $this->advancePayrollRange($range['start']);
        $advanceTotals = $this->advanceTotalsByEmployee(
            $employees,
            $advanceRange['start']->toDateString(),
            $advanceRange['end']->toDateString()
        );

        $reportRows = $this->buildEmployeeRows(
            $employees,
            $attendanceMap,
            $importedAttendanceMap,
            $reportLatestAttendanceMap,
            $branchMap,
            $range['start'],
            $range['end'],
            $advanceTotals,
            true
        )->values();

        return [
            'filters' => [
                ...$filters,
                'start_date' => $range['start']->toDateString(),
                'end_date' => $range['end']->toDateString(),
            ],
            'reportRows' => $this->markSalaryHolds(
                $this->applyPfRateOfWagesDeductions(
                    $this->resolveReportUans($reportRows),
                    $range['start']
                ),
                $range['start']
            ),
        ];
    }

    private function applyPfRateOfWagesDeductions(Collection $reportRows, Carbon $payrollMonth): Collection
    {
        return $reportRows->map(function (array $row) use ($payrollMonth): array {
            if (! MayPfEligibility::isEligible(
                $payrollMonth,
                $row['emp_id'] ?? null,
                $row['uan_number'] ?? null,
                $row['pf_eligible'] ?? null
            )) {
                return $row;
            }

            $breakdown = PfSalaryBreakdown::forReportRow($row);
            $row['pf'] = $breakdown['ee_pf'];
            $row['payable_salary'] = $breakdown['take_home_salary'];
            $row['net_payable_salary'] = $breakdown['take_home_salary'];

            return $row;
        })->values();
    }

    private function markSalaryHolds(Collection $reportRows, Carbon $payrollMonth): Collection
    {
        $empIds = $reportRows
            ->pluck('emp_id')
            ->map(fn ($empId): string => $this->clean($empId))
            ->filter()
            ->unique()
            ->values();
        $holds = EmployeeSalaryHold::query()
            ->whereDate('payroll_month', $payrollMonth->copy()->startOfMonth()->toDateString())
            ->whereIn('emp_id', $empIds->all())
            ->get(['emp_id', 'reason'])
            ->keyBy(fn (EmployeeSalaryHold $hold): string => $this->clean($hold->emp_id));

        return $reportRows->map(function (array $row) use ($holds): array {
            $hold = $holds->get($this->clean($row['emp_id'] ?? ''));
            $row['salary_on_hold'] = $hold instanceof EmployeeSalaryHold;
            $row['salary_hold_reason'] = $this->clean($hold?->reason);

            return $row;
        })->values();
    }

    private function resolveReportUans(Collection $reportRows): Collection
    {
        $empIds = $reportRows
            ->pluck('emp_id')
            ->map(fn ($empId): string => $this->clean($empId))
            ->filter()
            ->unique()
            ->values();
        $requestUans = EmployeeBankDetailRequest::query()
            ->whereIn('status', [
                EmployeeBankDetailRequest::STATUS_SUBMITTED,
                EmployeeBankDetailRequest::STATUS_VERIFIED,
            ])
            ->whereIn('emp_id', $empIds->all())
            ->whereNotNull('requested_uan_number')
            ->where('requested_uan_number', '<>', '')
            ->orderByDesc('id')
            ->get(['emp_id', 'requested_uan_number'])
            ->unique(fn (EmployeeBankDetailRequest $request): string => $this->clean($request->emp_id))
            ->mapWithKeys(fn (EmployeeBankDetailRequest $request): array => [
                $this->clean($request->emp_id) => $this->normalizeUan($request->requested_uan_number),
            ]);

        return $reportRows->map(function (array $row) use ($requestUans): array {
            $empId = $this->clean($row['emp_id'] ?? '');
            $uan = $this->normalizeUan($row['uan_number'] ?? null);

            if ($uan === '') {
                $uan = $this->normalizeUan($requestUans->get($empId));
            }

            if ($uan === '') {
                $uan = MayPfEligibility::uanForEmployee($empId);
            }

            $row['uan_number'] = $uan;

            return $row;
        })->values();
    }

    private function normalizeUan(?string $uanNumber): string
    {
        return preg_replace('/\D+/', '', (string) $uanNumber) ?? '';
    }

    private function requestWithoutSalaryLocationFilters(Request $request): Request
    {
        return $request->duplicate(array_merge($request->query->all(), [
            'scope' => 'all',
            'state' => '',
            'city' => '',
            'branch_id' => '',
            'location_search' => '',
        ]));
    }

    private function populateSalaryReportSheet(Worksheet $sheet, Collection $reportRows): void
    {
        $sheet->fromArray([
            'Name',
            'Contact',
            'Bank Name',
            'Name as per A/c',
            'A/c Number',
            'IFSC Code',
            'UAN Number',
            'Base Salary',
            'Security Deposit',
            'Net Salary',
            'Payable Days',
        ], null, 'A1');

        $rowNumber = 2;

        foreach ($reportRows as $row) {
            $sheet->fromArray([
                $row['employee_name'] ?: '--',
                $row['contact'] ?: '--',
                $row['bank_name'] ?: '--',
                $row['account_name'] ?: '--',
                '',
                $row['ifsc_code'] ?: '--',
                $row['uan_number'] ?: '--',
                $row['salary'] !== null ? round((float) $row['salary']) : '--',
                round((float) ($row['security_deposit'] ?? 0)),
                $row['net_payable_salary'] !== null ? round((float) $row['net_payable_salary']) : '--',
                $this->formatReportDayCount($row['payable_days'] ?? 0),
            ], null, 'A'.$rowNumber);
            ExcelTextValue::setCell(
                $sheet,
                'E'.$rowNumber,
                $row['bank_account_number'] ?? null,
                '--'
            );

            $rowNumber++;
        }

        $lastRow = max(1, $rowNumber - 1);

        $sheet->getStyle('A1:K1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'A63D2F'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getStyle('A1:K'.$lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D9C7BC'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        if ($lastRow >= 2) {
            $sheet->getStyle('H2:J'.$lastRow)
                ->getNumberFormat()
                ->setFormatCode('#,##0');
        }

        foreach (range('A', 'K') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function populatePfEmployeeSalarySheet(Worksheet $sheet, Collection $reportRows): void
    {
        $headers = [
            'SL NO',
            'Employee ID',
            'Employee Name',
            'UAN Number',
            'Designation',
            'State',
            'Branch',
            'Number of Working Days',
            'Present Days',
            'Absent Days',
            'Full Days',
            'Half Days',
            'Single Punches',
            'Week Off Days',
            'Regularized Days',
            'Payable Days',
            'State BASIC Monthly',
            'State DA Monthly',
            'State BASIC + DA Monthly',
            'Gross Salary',
            'BASIC',
            'DA',
            'BASIC + DA SALARY',
            'PF RATE OF WAGES',
            'OTHER ALLOWANCES',
            'GROSS SALARY',
            'EE-PF -12% ON BASIC',
            'ESI - 0.75% ON GROSS',
            'PT',
            'Advance',
            'Security Deposit',
            'TOTAL DEDUCTIONS',
            'TAKE HOME SALARY',
            'ER-PF -12%',
            'ER-ESI-3.25%',
            'CTC',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $rowNumber = 2;

        foreach ($reportRows as $index => $row) {
            $breakdown = PfSalaryBreakdown::forReportRow($row);
            $sheet->fromArray([
                $index + 1,
                $row['emp_id'] ?: '--',
                $row['employee_name'] ?: '--',
                '',
                $row['designation'] ?: '--',
                $row['state'] ?: '--',
                $row['branch_name'] ?: '--',
                $row['salary_days_in_month'] ?? 0,
                $row['present_days'] ?? 0,
                $row['absent_days'] ?? 0,
                $row['full_days'] ?? 0,
                $row['half_days'] ?? 0,
                $row['single_punches'] ?? 0,
                $row['week_off_days'] ?? 0,
                $row['regularized_days'] ?? 0,
                $row['payable_days'] ?? 0,
                ...array_values($breakdown),
            ], null, 'A'.$rowNumber, true);
            ExcelTextValue::setCell($sheet, 'D'.$rowNumber, $row['uan_number'] ?? null, '--');
            $rowNumber++;
        }

        $lastRow = max(1, $rowNumber - 1);
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:'.$lastColumn.$lastRow);
        $sheet->getStyle('A1:'.$lastColumn.'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'A63D2F']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->getStyle('A1:'.$lastColumn.$lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D9C7BC'],
                ],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        if ($lastRow >= 2) {
            $sheet->getStyle('H2:P'.$lastRow)->getNumberFormat()->setFormatCode('0.0');
            $sheet->getStyle('Q2:S'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('T2:AJ'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
        }

        foreach (range(1, count($headers)) as $columnIndex) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }
    }

    private function branchCollection(): Collection
    {
        return Cache::remember('attendance:branches:all', now()->addMinutes(10), function (): Collection {
            return Branch::query()
                ->select(['id', 'branchId', 'branchName', 'state', 'city', 'status'])
                ->orderBy('branchName')
                ->get();
        });
    }

    private function activeBranchCollection(): Collection
    {
        return Cache::remember('attendance:branches:active', now()->addMinutes(10), function (): Collection {
            return Branch::query()
                ->select(['id', 'branchId', 'branchName', 'state', 'city', 'status'])
                ->where('status', 1)
                ->orderBy('branchName')
                ->get();
        });
    }

    private function selectedBranchSearch(Collection $branches, string $branchId): string
    {
        if ($branchId === '') {
            return '';
        }

        /** @var Branch|null $branch */
        $branch = $branches->first(function (Branch $branch) use ($branchId): bool {
            return $this->clean($branch->branchId) === $branchId;
        });

        return $branch ? trim($branch->branchId.' - '.$branch->branchName) : '';
    }

    private function branchSearchOptions(Collection $branches): Collection
    {
        return $branches
            ->map(function (Branch $branch): array {
                return [
                    'id' => $this->clean($branch->branchId),
                    'label' => trim($branch->branchId.' - '.$branch->branchName),
                    'meta' => trim(implode(', ', array_filter([
                        $branch->city ?? null,
                        $branch->state ?? null,
                    ]))),
                ];
            })
            ->values();
    }

    private function normalizeBranchFilter($value): string
    {
        $normalized = $this->clean($value);

        if ($normalized === '') {
            return '';
        }

        $parts = preg_split('/\s*-\s*/', $normalized, 2);

        return $this->clean($parts[0] ?? $normalized);
    }

    private function headOfficeBranch(Collection $branches): ?Branch
    {
        /** @var Branch|null $branch */
        $branch = $branches->first(function (Branch $branch): bool {
            return strcasecmp($this->clean($branch->branchName), 'Head Office') === 0
                || strcasecmp($this->clean($branch->branchId), 'AGPL000') === 0;
        });

        return $branch;
    }

    private function effectiveAttendanceStatus(
        ?Attendance $attendance,
        ?string $date = null,
        ?Carbon $employmentStartDate = null
    ): string {
        if ($date && $employmentStartDate instanceof Carbon && Carbon::parse($date)->lt($employmentStartDate)) {
            return self::STATUS_NOT_JOINED;
        }

        if (! $attendance) {
            if ($date && Carbon::parse($date)->gt($this->today())) {
                return self::STATUS_FUTURE;
            }

            return self::STATUS_ABSENT;
        }

        $override = $this->clean($attendance->attendance_status_override);

        if (in_array($override, [self::STATUS_FULL_DAY, self::STATUS_FULL_DAY_REMOTE, self::STATUS_HALF_DAY, self::STATUS_SINGLE_PUNCH, self::STATUS_ABSENT], true)) {
            return $override;
        }

        if (! $attendance->check_out_date || ! $attendance->check_out_time) {
            return self::STATUS_SINGLE_PUNCH;
        }

        return $this->workedMinutes($attendance) < self::FULL_DAY_MINUTES
            ? self::STATUS_HALF_DAY
            : self::STATUS_FULL_DAY;
    }

    private function workedMinutes(?Attendance $attendance): int
    {
        if (! $attendance || ! $attendance->check_in_date || ! $attendance->check_in_time || ! $attendance->check_out_date || ! $attendance->check_out_time) {
            return 0;
        }

        $checkIn = Carbon::parse($attendance->check_in_date.' '.$attendance->check_in_time);
        $checkOut = Carbon::parse($attendance->check_out_date.' '.$attendance->check_out_time);

        if ($checkOut->lte($checkIn)) {
            return 0;
        }

        return (int) $checkIn->diffInMinutes($checkOut);
    }

    private function formatWorkedMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '--';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return sprintf('%02dh %02dm', $hours, $remaining);
    }

    private function dayOverrideShiftPayload(Employee $employee, Carbon $date, string $overrideStatus): ?array
    {
        if (! in_array($overrideStatus, [self::STATUS_FULL_DAY, self::STATUS_FULL_DAY_REMOTE, self::STATUS_HALF_DAY], true)) {
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
            'check_in' => $checkIn->format('h:i A'),
            'check_out' => $checkOut->format('h:i A'),
            'check_in_datetime' => $checkIn->format('d M Y h:i A'),
            'check_out_datetime' => $checkOut->format('d M Y h:i A'),
            'worked_time' => $this->formatWorkedMinutes((int) $checkIn->diffInMinutes($checkOut)),
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

        $start = Carbon::parse($date->toDateString().' '.$startTime, config('app.timezone', 'Asia/Kolkata'));
        $end = Carbon::parse($date->toDateString().' '.$endTime, config('app.timezone', 'Asia/Kolkata'));

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

    private function formatReportDayCount(mixed $value): string
    {
        $formatted = number_format((float) $value, 1, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_FULL_DAY => 'Full Day',
            self::STATUS_FULL_DAY_REMOTE => 'Full Day Remote',
            self::STATUS_HALF_DAY => 'Half Day',
            self::STATUS_SINGLE_PUNCH => 'Single Punch',
            self::STATUS_ABSENT => 'Absent',
            self::STATUS_WEEKOFF => 'W/O',
            self::STATUS_NOT_JOINED => 'Not Joined',
            default => 'Upcoming',
        };
    }

    private function calendarAttendanceStatus(string $status, Carbon $date): string
    {
        if ($date->isSunday() && in_array($status, [
            self::STATUS_FULL_DAY,
            self::STATUS_FULL_DAY_REMOTE,
            self::STATUS_HALF_DAY,
            self::STATUS_SINGLE_PUNCH,
            self::STATUS_ABSENT,
        ], true)) {
            return self::STATUS_WEEKOFF;
        }

        return $status;
    }

    private function applyAttendanceDayOverrideStatus(string $status, string $overrideStatus): string
    {
        return $this->isRegularizedStatus($overrideStatus) ? $overrideStatus : $status;
    }

    private function isRegularizedStatus(string $status): bool
    {
        return in_array($status, [
            self::STATUS_FULL_DAY,
            self::STATUS_FULL_DAY_REMOTE,
            self::STATUS_HALF_DAY,
            self::STATUS_SINGLE_PUNCH,
            self::STATUS_ABSENT,
        ], true);
    }

    private function displayStatusLabel(string $status, ?string $overrideStatus = null): string
    {
        $overrideStatus = $this->clean($overrideStatus);
        $label = $this->statusLabel($status);

        if (in_array($overrideStatus, [self::STATUS_FULL_DAY, self::STATUS_FULL_DAY_REMOTE, self::STATUS_HALF_DAY, self::STATUS_SINGLE_PUNCH, self::STATUS_ABSENT], true)) {
            return $label.' (Regularized)';
        }

        return $label;
    }

    private function employmentStartDateMap(Collection $empIds): array
    {
        $cleanIds = $empIds
            ->map(fn ($empId): string => $this->clean((string) $empId))
            ->filter()
            ->unique()
            ->values();

        if ($cleanIds->isEmpty()) {
            return [];
        }

        return Attendance::query()
            ->selectRaw('TRIM(empId) as emp_id, MIN(check_in_date) as first_attendance_date')
            ->whereIn('empId', $cleanIds->all())
            ->groupBy('empId')
            ->pluck('first_attendance_date', 'emp_id')
            ->all();
    }

    private function employmentStartDate(Employee $employee, ?string $firstAttendanceDate = null): ?Carbon
    {
        $doj = $this->clean((string) $employee->doj);

        if ($doj !== '' && $this->isDateString($doj)) {
            return Carbon::parse($doj, config('app.timezone', 'Asia/Kolkata'))->startOfDay();
        }

        $attendanceDate = $this->clean($firstAttendanceDate);

        if ($attendanceDate !== '' && $this->isDateString($attendanceDate)) {
            return Carbon::parse($attendanceDate, config('app.timezone', 'Asia/Kolkata'))->startOfDay();
        }

        return null;
    }

    private function evaluatedDayCount(Carbon $start, Carbon $end): int
    {
        $today = $this->today();
        $effectiveEnd = $end->copy()->min($today);

        if ($effectiveEnd->lt($start)) {
            return 0;
        }

        return $start->diffInDays($effectiveEnd) + 1;
    }

    private function resolvePhotoUrl(?string $path): string
    {
        $trimmed = $this->clean($path);

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

    private function today(): Carbon
    {
        return now(config('app.timezone', 'Asia/Kolkata'))->startOfDay();
    }

    private function isZonalAdmin(Request $request): bool
    {
        return false;
    }

    private function branchAttendanceIdsOnly(array $attendanceIds): array
    {
        $hoBranchId = $this->clean($this->headOfficeBranch($this->branchCollection())?->branchId);

        if ($hoBranchId === '') {
            return $attendanceIds;
        }

        return Attendance::query()
            ->whereIn('id', $attendanceIds)
            ->where(function ($query) use ($hoBranchId): void {
                $query
                    ->where(function ($nested) use ($hoBranchId): void {
                        $nested
                            ->whereNull('check_in_branch_id')
                            ->orWhere('check_in_branch_id', '!=', $hoBranchId);
                    })
                    ->where(function ($nested) use ($hoBranchId): void {
                        $nested
                            ->whereNull('check_out_branch_id')
                            ->orWhere('check_out_branch_id', '!=', $hoBranchId);
                    });
            })
            ->pluck('id')
            ->all();
    }

    private function employeeBelongsToHeadOffice(Employee $employee): bool
    {
        $hoBranchId = $this->clean($this->headOfficeBranch($this->branchCollection())?->branchId);

        if ($hoBranchId === '') {
            return false;
        }

        $assignedBranchId = $this->clean($employee->assigned_branch_id);

        if ($assignedBranchId !== '') {
            return $assignedBranchId === $hoBranchId;
        }

        $attendance = Attendance::query()
            ->where('empId', $this->clean($employee->empId))
            ->latest('id')
            ->first(['check_in_branch_id', 'check_out_branch_id']);

        return $this->attendanceBranchId($attendance) === $hoBranchId;
    }

    private function clean(?string $value): string
    {
        return trim((string) $value);
    }

    private function nullIfBlank(mixed $value): ?string
    {
        $trimmed = $this->clean($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function formatDistance(float $distanceMeters): string
    {
        if ($distanceMeters >= 1000) {
            return number_format($distanceMeters / 1000, 2).' km';
        }

        return round($distanceMeters).' m';
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

    private function attendanceBranchMeta(
        ?string $branchId,
        Collection $branchMap,
        mixed $latitude = null,
        mixed $longitude = null
    ): array {
        $cleanBranchId = $this->clean($branchId);
        /** @var Branch|null $branch */
        $branch = $cleanBranchId !== '' ? $branchMap->get($cleanBranchId) : null;
        $branchName = $this->clean($branch?->branchName);
        $branchAddress = collect([
            $this->clean($branch?->addressline),
            $this->clean($branch?->area),
            $this->clean($branch?->city),
            $this->clean($branch?->state),
            $this->clean($branch?->pincode),
        ])->filter()->implode(', ');
        $label = $cleanBranchId !== ''
            ? trim($cleanBranchId.($branchName !== '' ? ' - '.$branchName : ''))
            : '--';
        $details = collect([
            $label !== '--' ? $label : '',
            $branchAddress,
        ])->filter()->implode("\n");
        $url = $this->clean($branch?->url);

        if ($url === '') {
            $url = $this->mapUrl($latitude ?? $branch?->latitude, $longitude ?? $branch?->longitude);
        }

        return [
            'label' => $label,
            'details' => $details !== '' ? $details : $label,
            'url' => $url,
        ];
    }

    private function attendanceLocationMeta(mixed $latitude = null, mixed $longitude = null): array
    {
        $lat = $this->cleanCoordinate($latitude);
        $lng = $this->cleanCoordinate($longitude);

        if ($lat === null || $lng === null) {
            return [
                'label' => '--',
                'details' => 'Location unavailable',
                'url' => '',
            ];
        }

        $label = sprintf('%s, %s', $lat, $lng);

        return [
            'label' => $label,
            'details' => "Latitude: {$lat}\nLongitude: {$lng}",
            'url' => $this->mapUrl($lat, $lng),
        ];
    }

    private function cleanCoordinate(mixed $value): ?string
    {
        $coordinate = trim((string) $value);

        if ($coordinate === '' || ! is_numeric($coordinate)) {
            return null;
        }

        return $coordinate;
    }

    private function mapUrl(mixed $latitude = null, mixed $longitude = null): string
    {
        $lat = $this->cleanCoordinate($latitude);
        $lng = $this->cleanCoordinate($longitude);

        if ($lat === null || $lng === null) {
            return '';
        }

        return 'https://www.google.com/maps?q='.urlencode($lat.','.$lng);
    }

    private function isDateString(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }
}
