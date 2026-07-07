<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\HoAttendanceImport;
use App\Models\HoAttendanceImportOverride;
use App\Models\LeaveRequest;
use App\Models\RecruitmentCandidate;
use App\Models\SiteVisitRequest;
use App\Services\AttendancePunctualityService;
use App\Services\BranchOpeningAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Intervention\Image\Facades\Image;


class AdminController extends Controller
{
    private const STATUS_FULL_DAY = 'full_day';
    private const STATUS_FULL_DAY_REMOTE = 'full_day_remote';
    private const STATUS_HALF_DAY = 'half_day';
    private const STATUS_SINGLE_PUNCH = 'single_punch';
    private const STATUS_ABSENT = 'absent';
    private const FULL_DAY_MINUTES = 460;
    private const THEME_COOKIE_NAME = 'admin_theme_preferences';
    private const DEFAULT_CREATED_ADMIN_PASSWORD = '12345678';

    private const THEME_OPTIONS = [
        'blue-theme',
        'light',
        'dark',
        'semi-dark',
        'bodered-theme',
        'emerald-theme',
        'violet-theme',
        'sunset-theme',
        'copper-theme',
        'ocean-theme',
        'mulberry-theme',
    ];

    private const CARD_STYLE_OPTIONS = [
        'rounded',
        'sharp',
    ];

    private const TABLE_DENSITY_OPTIONS = [
        'comfortable',
        'compact',
    ];

    private const COLOR_FIELDS = [
        'theme_primary_color',
        'theme_background_color',
        'theme_surface_color',
        'theme_sidebar_background_color',
        'theme_text_color',
        'theme_muted_text_color',
        'theme_border_color',
    ];

    private const RECRUITMENT_REGION_LABELS = [
        'karnataka' => 'Karnataka',
        'ap' => 'AP',
        'tn' => 'TN',
        'ts' => 'TS',
        'pondicherry' => 'Pondicherry',
        'other' => 'Other',
    ];

    public function login(Request $request)
    {
        return view('admin.login', $this->loginThemeViewData($request));
    }


    public function loginPost(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required'],
        ]);

        $login = trim((string) $request->input('email'));
        $admin = Admin::query()
            ->where('email', $login)
            ->orWhere('name', $login)
            ->first();

        if ($admin && Hash::check((string) $request->input('password'), (string) $admin->password)) {
            Auth::guard('admin')->login($admin);
            $request->session()->regenerate();
            $redirectRoute = match (trim((string) $admin->role)) {
                Admin::ROLE_ACCOUNTS => 'admin-salary-advance',
                Admin::ROLE_SUBHR => 'admin-employee-index',
                default => 'admin-dashboard',
            };
            $notification1 = array(
                'message' => 'Admin Login Successfully',
                'alert-type' => 'success'
            );
            return redirect()
                ->route($redirectRoute)
                ->with($notification1)
                ->cookie($this->themePreferenceCookie($admin));
        } else {
            $notification2 = array(
                'message' => 'Invalid Credentials',
                'alert-type' => 'error'
            );
            return back()->with($notification2);
        }
    }

    public function dashboard(
        AttendancePunctualityService $punctualityService,
        BranchOpeningAnalyticsService $branchOpeningAnalyticsService
    )
    {
        /** @var Admin|null $admin */
        $admin = auth('admin')->user();
        $adminRole = $this->dashboardAdminRole($admin);
        $showAttendanceDashboard = ! in_array($adminRole, [Admin::ROLE_HIRING, Admin::ROLE_JOINING], true);
        $showRecruitmentDashboard = in_array($adminRole, [Admin::ROLE_HR_ADMIN, Admin::ROLE_HIRING, Admin::ROLE_JOINING], true);

        if ($admin instanceof Admin && trim((string) $admin->role) === Admin::ROLE_ACCOUNTS) {
            return redirect()->route('admin-salary-advance');
        }

        if ($admin instanceof Admin && trim((string) $admin->role) === Admin::ROLE_SUBHR) {
            return redirect()->route('admin-employee-index');
        }

        $today = Carbon::today(config('app.timezone', 'Asia/Kolkata'));
        $activeBranches = Branch::query()
            ->where('status', 1)
            ->orderBy('branchName')
            ->get(['branchId', 'branchName', 'city', 'state']);
        $activeBranchCount = $activeBranches->count();
        $hoBranchId = $this->cleanDashboardValue($this->headOfficeBranch($activeBranches)?->branchId);

        $employees = Employee::query()
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '')
                    ->orWhere('status', '!=', 'Inactive');
            })
            ->get(['empId', 'name', 'designation', 'mailId', 'contact', 'last_login_branch_id']);
        $employeeCount = $employees->count();
        $employeeMap = $employees->keyBy(fn (Employee $employee): string => $this->cleanDashboardValue($employee->empId));

        $importedHoEmployeeIds = HoAttendanceImport::query()
            ->distinct()
            ->pluck('emp_id')
            ->map(fn ($empId): string => $this->cleanDashboardValue($empId))
            ->filter()
            ->unique();

        $latestAttendanceByEmpId = Attendance::query()
            ->orderByDesc('id')
            ->get(['empId', 'check_in_branch_id', 'check_out_branch_id'])
            ->filter(fn (Attendance $attendance): bool => $this->cleanDashboardValue($attendance->empId) !== '')
            ->unique(fn (Attendance $attendance): string => $this->cleanDashboardValue($attendance->empId))
            ->keyBy(fn (Attendance $attendance): string => $this->cleanDashboardValue($attendance->empId));

        $appHoEmployeeIds = $hoBranchId === ''
            ? collect()
            : $employees
                ->filter(function (Employee $employee) use ($hoBranchId, $latestAttendanceByEmpId): bool {
                    $empId = $this->cleanDashboardValue($employee->empId);
                    $latestAttendance = $latestAttendanceByEmpId->get($empId);
                    $branchId = $this->cleanDashboardValue($employee->last_login_branch_id);

                    if ($branchId === '') {
                        $branchId = $this->cleanDashboardValue($latestAttendance?->check_out_branch_id)
                            ?: $this->cleanDashboardValue($latestAttendance?->check_in_branch_id);
                    }

                    return $branchId === $hoBranchId;
                })
                ->pluck('empId')
                ->map(fn ($empId): string => $this->cleanDashboardValue($empId))
                ->filter()
                ->unique();

        $cumulativeHoEmployeeIds = $importedHoEmployeeIds
            ->merge($appHoEmployeeIds)
            ->filter()
            ->unique();

        $hoEmployeeIds = $employees
            ->filter(fn (Employee $employee): bool => $cumulativeHoEmployeeIds->contains($this->cleanDashboardValue($employee->empId)))
            ->pluck('empId')
            ->map(fn ($empId): string => $this->cleanDashboardValue($empId))
            ->filter()
            ->unique()
            ->values();
        $hoEmployeeCount = $hoEmployeeIds->count();
        $branchEmployeeCount = max($employeeCount - $hoEmployeeCount, 0);
        $branchEmployeeIds = $employees
            ->pluck('empId')
            ->map(fn ($empId): string => $this->cleanDashboardValue($empId))
            ->filter()
            ->reject(fn (string $empId): bool => $hoEmployeeIds->contains($empId))
            ->unique()
            ->values();

        $appliedCandidatesCount = 0;
        $hiredCandidatesCount = 0;
        $selectedCandidatesCount = 0;
        $onboardedCandidatesCount = 0;
        $markedOnDutyCandidatesCount = 0;
        $recruitmentRegionChart = [
            'title' => 'Applied Candidates by Region',
            'description' => 'Distribution of submitted applications across regions.',
            'labels' => [],
            'series' => [],
            'total_candidates' => 0,
        ];
        $recruitmentPositionChart = [
            'title' => 'Applied Candidates by Position',
            'description' => 'Applications grouped by position applied for.',
            'labels' => [],
            'series' => [],
            'total_candidates' => 0,
        ];

        if ($showRecruitmentDashboard) {
            $recruitmentCandidatesQuery = RecruitmentCandidate::query()
                ->whereNotNull('submission_code')
                ->whereNotIn('status', [
                    RecruitmentCandidate::STATUS_HIRING_REJECTED,
                    RecruitmentCandidate::STATUS_JOINING_REJECTED,
                ])
                ->orderByDesc('created_at');

            $recruitmentCandidates = $recruitmentCandidatesQuery->get([
                'id',
                'status',
                'contact_number',
                'position_applied_for',
                'hiring_payload',
                'created_at',
            ]);
            $uniqueRecruitmentCandidates = $recruitmentCandidates
                ->unique(fn (RecruitmentCandidate $candidate): string => $this->dashboardRecruitmentMobileKey($candidate))
                ->values();

            $appliedCandidatesCount = $uniqueRecruitmentCandidates->count();
            $hiredCandidatesCount = $uniqueRecruitmentCandidates
                ->filter(fn (RecruitmentCandidate $candidate): bool => $this->isDashboardHiredCandidateStatus($candidate->status))
                ->count();
            $selectedCandidatesCount = $uniqueRecruitmentCandidates
                ->filter(fn (RecruitmentCandidate $candidate): bool => $this->isDashboardSelectedCandidateStatus($candidate->status))
                ->count();
            $onboardedCandidatesCount = $uniqueRecruitmentCandidates
                ->filter(fn (RecruitmentCandidate $candidate): bool => trim((string) $candidate->status) === RecruitmentCandidate::STATUS_ONBOARDED)
                ->count();
            $markedOnDutyCandidatesCount = $uniqueRecruitmentCandidates
                ->filter(fn (RecruitmentCandidate $candidate): bool => trim((string) $candidate->status) === RecruitmentCandidate::STATUS_JOINED)
                ->count();

            $regionCounts = $uniqueRecruitmentCandidates
                ->groupBy(fn (RecruitmentCandidate $candidate): string => $this->dashboardRecruitmentRegionLabel($candidate))
                ->map(fn (Collection $rows): int => $rows->count());

            $orderedRegions = collect(self::RECRUITMENT_REGION_LABELS)
                ->map(function (string $label) use ($regionCounts): array {
                    return [
                        'label' => $label,
                        'count' => (int) ($regionCounts[$label] ?? 0),
                    ];
                })
                ->filter(fn (array $row): bool => $row['count'] > 0)
                ->values();

            $recruitmentRegionChart = [
                'title' => 'Applied Candidates by Region',
                'description' => 'Distribution of submitted applications across regions.',
                'labels' => $orderedRegions->pluck('label')->all(),
                'series' => $orderedRegions->pluck('count')->all(),
                'total_candidates' => $appliedCandidatesCount,
            ];

            $positionRows = $uniqueRecruitmentCandidates
                ->groupBy(function (RecruitmentCandidate $candidate): string {
                    $position = trim((string) $candidate->position_applied_for);

                    return $position !== '' ? $position : 'Unspecified';
                })
                ->map(fn (Collection $rows, string $position): array => [
                    'label' => $position,
                    'count' => $rows->count(),
                ])
                ->sortBy([
                    ['count', 'desc'],
                    ['label', 'asc'],
                ])
                ->take(10)
                ->values();

            $recruitmentPositionChart = [
                'title' => 'Applied Candidates by Position',
                'description' => 'Top positions by submitted applications.',
                'labels' => $positionRows->pluck('label')->all(),
                'series' => $positionRows->pluck('count')->all(),
                'total_candidates' => $appliedCandidatesCount,
            ];
        }

        $branchesById = $activeBranches->keyBy(fn (Branch $branch): string => $this->cleanDashboardValue($branch->branchId));
        $hoCheckedInCount = 0;
        $branchCheckedInCount = 0;
        $hoCheckInSummary = collect();
        $branchCheckInSummary = collect();
        $todayAttendenceCount = 0;
        $singlePunchCount = 0;
        $halfDayCount = 0;
        $pendingLeaveCount = 0;
        $pendingWorkVisitCount = 0;
        $branchOpeningLateCount = 0;
        $branchOpeningOverdueCount = 0;
        $branchOpeningAttentionRows = collect();
        $regularEmployeeCount = 0;
        $irregularEmployeeCount = 0;
        $hoLateDays = 0;
        $branchLateDays = 0;
        $lateLeaderboard = collect();
        $dashboardPunctualityStart = $today->copy()->startOfMonth();
        $hoAttendanceChart = $this->emptyDashboardAttendanceChart(collect(), 'HO Attendance Statistics', 'Imported HO attendance status for the last 7 days.', 0);
        $branchAttendanceChart = $this->emptyDashboardAttendanceChart(collect(), 'Branch Attendance Statistics', 'App attendance status for branch employees over the last 7 days.', 0);

        if ($showAttendanceDashboard) {
            $branchOpeningDashboard = $branchOpeningAnalyticsService->dashboardData($today);
            $branchOpeningLateCount = (int) ($branchOpeningDashboard['late_count'] ?? 0);
            $branchOpeningOverdueCount = (int) ($branchOpeningDashboard['overdue_count'] ?? 0);
            $branchOpeningAttentionRows = $branchOpeningDashboard['attention_rows'] ?? collect();

            $todayAttendanceRecords = Attendance::query()
                ->whereDate('check_in_date', Carbon::today())
                ->get();
            $todayAttendenceCount = $todayAttendanceRecords->count();
            $singlePunchCount = $todayAttendanceRecords
                ->filter(fn (Attendance $attendance): bool => $this->effectiveDashboardAttendanceStatus($attendance) === self::STATUS_SINGLE_PUNCH)
                ->count();
            $halfDayCount = $todayAttendanceRecords
                ->filter(fn (Attendance $attendance): bool => $this->effectiveDashboardAttendanceStatus($attendance) === self::STATUS_HALF_DAY)
                ->count();
            $todayCheckInsByEmpId = $todayAttendanceRecords
                ->sortByDesc('id')
                ->filter(fn (Attendance $attendance): bool => $this->cleanDashboardValue($attendance->empId) !== '')
                ->unique(fn (Attendance $attendance): string => $this->cleanDashboardValue($attendance->empId))
                ->values();
            $todayHoCheckIns = $todayCheckInsByEmpId
                ->filter(fn (Attendance $attendance): bool => $hoBranchId !== '' && $this->cleanDashboardValue($attendance->check_in_branch_id) === $hoBranchId)
                ->values();
            $todayBranchCheckIns = $todayCheckInsByEmpId
                ->filter(function (Attendance $attendance) use ($hoBranchId): bool {
                    $branchId = $this->cleanDashboardValue($attendance->check_in_branch_id);

                    return $branchId !== '' && $branchId !== $hoBranchId;
                })
                ->values();
            $hoCheckedInCount = $todayHoCheckIns->count();
            $branchCheckedInCount = $todayBranchCheckIns->count();
            $hoCheckInSummary = $todayHoCheckIns
                ->groupBy(fn (Attendance $attendance): string => $this->cleanDashboardValue($attendance->check_in_branch_id))
                ->map(function (Collection $records, string $branchId) use ($branchesById, $employeeMap): array {
                    /** @var Branch|null $branch */
                    $branch = $branchesById->get($branchId);
                    $employees = $records
                        ->map(function (Attendance $attendance) use ($employeeMap): array {
                            /** @var Employee|null $employee */
                            $employee = $employeeMap->get($this->cleanDashboardValue($attendance->empId));

                            return [
                                'emp_id' => $this->cleanDashboardValue($attendance->empId),
                                'name' => $this->cleanDashboardValue($employee?->name) ?: 'Unknown Employee',
                                'check_in_time' => $this->cleanDashboardValue($attendance->check_in_time),
                            ];
                        })
                        ->sortBy(fn (array $item): string => strtolower($item['name']))
                        ->values()
                        ->all();

                    return [
                        'branch_id' => $branchId,
                        'branch_name' => $this->cleanDashboardValue($branch?->branchName) ?: 'Head Office',
                        'city' => $this->cleanDashboardValue($branch?->city),
                        'state' => $this->cleanDashboardValue($branch?->state),
                        'employee_count' => count($employees),
                        'employees' => $employees,
                    ];
                })
                ->sortBy(fn (array $item): string => strtolower($item['branch_name']))
                ->values();
            $branchCheckInSummary = $todayBranchCheckIns
                ->groupBy(fn (Attendance $attendance): string => $this->cleanDashboardValue($attendance->check_in_branch_id))
                ->map(function (Collection $records, string $branchId) use ($branchesById, $employeeMap): array {
                    /** @var Branch|null $branch */
                    $branch = $branchesById->get($branchId);
                    $employees = $records
                        ->map(function (Attendance $attendance) use ($employeeMap): array {
                            /** @var Employee|null $employee */
                            $employee = $employeeMap->get($this->cleanDashboardValue($attendance->empId));

                            return [
                                'emp_id' => $this->cleanDashboardValue($attendance->empId),
                                'name' => $this->cleanDashboardValue($employee?->name) ?: 'Unknown Employee',
                                'check_in_time' => $this->cleanDashboardValue($attendance->check_in_time),
                            ];
                        })
                        ->sortBy(fn (array $item): string => strtolower($item['name']))
                        ->values()
                        ->all();

                    return [
                        'branch_id' => $branchId,
                        'branch_name' => $this->cleanDashboardValue($branch?->branchName) ?: 'Unknown Branch',
                        'city' => $this->cleanDashboardValue($branch?->city),
                        'state' => $this->cleanDashboardValue($branch?->state),
                        'employee_count' => count($employees),
                        'employees' => $employees,
                    ];
                })
                ->sortBy(fn (array $item): string => strtolower($item['branch_name']))
                ->values();
            $pendingLeaveCount = LeaveRequest::query()
                ->where('status', 'pending')
                ->count();
            $pendingWorkVisitCount = SiteVisitRequest::query()
                ->where('status', 'pending')
                ->count();
            $dashboardAttendanceMap = $this->dashboardAttendanceRecordMap(
                $employees->pluck('empId'),
                $dashboardPunctualityStart,
                $today
            );
            $dashboardImportedAttendanceMap = $this->dashboardImportedDailySummaryMap(
                $employees->pluck('empId'),
                $dashboardPunctualityStart->toDateString(),
                $today->toDateString()
            );
            $punctualityRows = $employees
                ->map(function (Employee $employee) use (
                    $latestAttendanceByEmpId,
                    $branchesById,
                    $hoEmployeeIds,
                    $hoBranchId,
                    $dashboardAttendanceMap,
                    $dashboardImportedAttendanceMap,
                    $dashboardPunctualityStart,
                    $today,
                    $punctualityService
                ): array {
                    $empId = $this->cleanDashboardValue($employee->empId);
                    $latestAttendance = $latestAttendanceByEmpId->get($empId);
                    $branchId = $this->cleanDashboardValue($employee->last_login_branch_id);

                    if ($branchId === '') {
                        $branchId = $this->cleanDashboardValue($latestAttendance?->check_out_branch_id)
                            ?: $this->cleanDashboardValue($latestAttendance?->check_in_branch_id);
                    }

                    $scope = $hoEmployeeIds->contains($empId) || ($hoBranchId !== '' && $branchId === $hoBranchId)
                        ? 'HO'
                        : 'Branch';
                    /** @var Branch|null $branch */
                    $branch = $scope === 'HO'
                        ? $branchesById->get($hoBranchId)
                        : $branchesById->get($branchId);
                    $punctuality = $punctualityService->analyzeEmployee(
                        $employee,
                        $dashboardAttendanceMap[$empId] ?? [],
                        $dashboardImportedAttendanceMap[$empId] ?? [],
                        $branch,
                        $dashboardPunctualityStart,
                        $today
                    );

                    return [
                        'emp_id' => $empId,
                        'employee_name' => $this->cleanDashboardValue($employee->name) ?: 'Unknown Employee',
                        'designation' => $this->cleanDashboardValue($employee->designation),
                        'scope' => $scope,
                        'branch_id' => $branchId,
                        'branch_name' => $this->cleanDashboardValue($branch?->branchName),
                        'late_days' => $punctuality['late_days_count'],
                        'early_logout_days' => $punctuality['early_logout_days_count'],
                        'irregular_days' => $punctuality['irregular_days_count'],
                        'tracked_days' => $punctuality['tracked_days_count'],
                        'schedule' => $punctuality['schedule_label'],
                        'details' => $punctuality['details'],
                    ];
                })
                ->values();
            $trackedPunctualityRows = $punctualityRows
                ->filter(fn (array $row): bool => $row['tracked_days'] > 0)
                ->values();
            $regularEmployeeCount = $trackedPunctualityRows
                ->filter(fn (array $row): bool => $row['irregular_days'] === 0)
                ->count();
            $irregularEmployeeCount = $trackedPunctualityRows
                ->filter(fn (array $row): bool => $row['irregular_days'] > 0)
                ->count();
            $hoLateDays = $trackedPunctualityRows
                ->where('scope', 'HO')
                ->sum('late_days');
            $branchLateDays = $trackedPunctualityRows
                ->where('scope', 'Branch')
                ->sum('late_days');
            $lateLeaderboard = $trackedPunctualityRows
                ->filter(fn (array $row): bool => $row['late_days'] > 0 || $row['early_logout_days'] > 0)
                ->sortBy([
                    ['late_days', 'desc'],
                    ['early_logout_days', 'desc'],
                    ['irregular_days', 'desc'],
                    ['employee_name', 'asc'],
                ])
                ->take(10)
                ->values();
            $dashboardTrendDates = $this->dashboardTrendDates();
            $hoAttendanceChart = $this->dashboardHoAttendanceChart($dashboardTrendDates, $hoEmployeeIds);
            $branchAttendanceChart = $this->dashboardBranchAttendanceChart($dashboardTrendDates, $branchEmployeeIds);
        }

        return view("admin.dashboard", compact(
            'showAttendanceDashboard',
            'showRecruitmentDashboard',
            'activeBranchCount',
            'employeeCount',
            'hoEmployeeCount',
            'branchEmployeeCount',
            'appliedCandidatesCount',
            'hiredCandidatesCount',
            'selectedCandidatesCount',
            'onboardedCandidatesCount',
            'markedOnDutyCandidatesCount',
            'recruitmentRegionChart',
            'recruitmentPositionChart',
            'hoCheckedInCount',
            'branchCheckedInCount',
            'hoCheckInSummary',
            'branchCheckInSummary',
            'todayAttendenceCount',
            'singlePunchCount',
            'halfDayCount',
            'pendingLeaveCount',
            'pendingWorkVisitCount',
            'branchOpeningLateCount',
            'branchOpeningOverdueCount',
            'branchOpeningAttentionRows',
            'regularEmployeeCount',
            'irregularEmployeeCount',
            'hoLateDays',
            'branchLateDays',
            'lateLeaderboard',
            'dashboardPunctualityStart',
            'today',
            'hoAttendanceChart',
            'branchAttendanceChart'
        ));
    }

    private function dashboardTrendDates(int $days = 7): Collection
    {
        $today = Carbon::now(config('app.timezone', 'Asia/Kolkata'))->startOfDay();

        return collect(range($days - 1, 0))
            ->map(fn (int $offset): Carbon => $today->copy()->subDays($offset))
            ->values();
    }

    private function dashboardAdminRole(?Admin $admin): string
    {
        $role = strtolower(trim((string) ($admin?->role ?? '')));

        if ($role === Admin::ROLE_ZONAL) {
            return Admin::ROLE_HR_ADMIN;
        }

        return $role !== '' ? $role : Admin::ROLE_HR_ADMIN;
    }

    private function applyDashboardRecruitmentAdminFilter($query, ?Admin $admin): void
    {
        $role = $this->dashboardAdminRole($admin);

        if (! in_array($role, [Admin::ROLE_HIRING, Admin::ROLE_JOINING], true)) {
            return;
        }

        $position = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($admin?->position ?? '')) ?? ''));

        if ($position === '') {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereRaw('LOWER(TRIM(position_applied_for)) = ?', [$position]);
    }

    private function isDashboardHiredCandidateStatus(?string $status): bool
    {
        return in_array(trim((string) $status), [
            RecruitmentCandidate::STATUS_ONBOARDED,
            RecruitmentCandidate::STATUS_JOINED,
        ], true);
    }

    private function isDashboardSelectedCandidateStatus(?string $status): bool
    {
        return in_array(trim((string) $status), [
            RecruitmentCandidate::STATUS_HIRING_SELECTED,
            RecruitmentCandidate::STATUS_JOINING_FORM_SHARED,
            RecruitmentCandidate::STATUS_JOINING_SUBMITTED,
            RecruitmentCandidate::STATUS_JOINING_HOLD,
            RecruitmentCandidate::STATUS_JOINING_UPDATE_REQUESTED,
            RecruitmentCandidate::STATUS_ONBOARDED,
            RecruitmentCandidate::STATUS_JOINED,
        ], true);
    }

    private function dashboardRecruitmentRegionLabel(RecruitmentCandidate $candidate): string
    {
        $state = strtolower(trim((string) (
            data_get($candidate->hiring_payload, 'preferred_work_location_state')
            ?: data_get($candidate->hiring_payload, 'preferred_work_location_branch_state')
        )));
        $place = strtolower(trim((string) data_get($candidate->hiring_payload, 'place')));
        $source = $state !== '' ? $state : $place;
        $compact = str_replace([' ', '.', '-', '_'], '', $source);

        if ($source === '') {
            return self::RECRUITMENT_REGION_LABELS['other'];
        }

        return match (true) {
            str_contains($source, 'karnataka'), str_contains($source, 'bangalore'), str_contains($source, 'bengaluru'), $compact === 'ka'
                => self::RECRUITMENT_REGION_LABELS['karnataka'],
            str_contains($source, 'andhra'), str_contains($source, 'vijayawada'), str_contains($source, 'vizag'), $compact === 'ap'
                => self::RECRUITMENT_REGION_LABELS['ap'],
            str_contains($source, 'tamil'), str_contains($source, 'chennai'), $compact === 'tn'
                => self::RECRUITMENT_REGION_LABELS['tn'],
            str_contains($source, 'telangana'), str_contains($source, 'hyderabad'), $compact === 'ts'
                => self::RECRUITMENT_REGION_LABELS['ts'],
            str_contains($source, 'pondicherry'), str_contains($source, 'puducherry'), $compact === 'py'
                => self::RECRUITMENT_REGION_LABELS['pondicherry'],
            default => self::RECRUITMENT_REGION_LABELS['other'],
        };
    }

    private function dashboardRecruitmentMobileKey(RecruitmentCandidate $candidate): string
    {
        $mobile = trim((string) ($candidate->contact_number ?: data_get($candidate->hiring_payload, 'contact_number')));
        $normalizedMobile = preg_replace('/\D+/', '', $mobile) ?? '';

        if ($normalizedMobile !== '') {
            return 'mobile:'.$normalizedMobile;
        }

        return 'candidate:'.(int) $candidate->id;
    }

    private function dashboardHoAttendanceChart(Collection $dates, Collection $hoEmployeeIds): array
    {
        $employeeIds = $hoEmployeeIds
            ->map(fn ($empId): string => $this->cleanDashboardValue($empId))
            ->filter()
            ->unique()
            ->values();

        $chart = $this->emptyDashboardAttendanceChart(
            $dates,
            'HO Attendance Statistics',
            'Imported HO attendance status for the last 7 days.',
            $employeeIds->count()
        );

        if ($employeeIds->isEmpty() || $dates->isEmpty()) {
            return $chart;
        }

        $fromDate = $dates->first()->toDateString();
        $toDate = $dates->last()->toDateString();

        $overrideMap = HoAttendanceImportOverride::query()
            ->whereBetween('attendance_date', [$fromDate, $toDate])
            ->whereIn('emp_id', $employeeIds->all())
            ->get(['emp_id', 'attendance_date', 'final_status'])
            ->groupBy(fn (HoAttendanceImportOverride $override): string => $this->cleanDashboardValue($override->emp_id))
            ->map(function (Collection $rows): array {
                return $rows
                    ->mapWithKeys(function (HoAttendanceImportOverride $override): array {
                        return [
                            Carbon::parse($override->attendance_date)->toDateString() => $this->cleanDashboardValue($override->final_status),
                        ];
                    })
                    ->all();
            })
            ->all();

        $importRows = HoAttendanceImport::query()
            ->whereBetween('attendance_date', [$fromDate, $toDate])
            ->whereIn('emp_id', $employeeIds->all())
            ->orderBy('attendance_date')
            ->get([
                'emp_id',
                'attendance_date',
                'login_time',
                'logout_time',
                'attendance_status',
                'work_duration',
            ]);

        $statusMap = [];

        foreach ($importRows->groupBy(function (HoAttendanceImport $row): string {
            return $this->cleanDashboardValue($row->emp_id).'|'.Carbon::parse($row->attendance_date)->toDateString();
        }) as $key => $rows) {
            [$empId, $attendanceDate] = explode('|', $key, 2);
            $firstLogin = $rows
                ->pluck('login_time')
                ->filter(fn ($value): bool => $this->cleanDashboardValue($value) !== '')
                ->sort()
                ->first();
            $lastLogout = $rows
                ->pluck('logout_time')
                ->filter(fn ($value): bool => $this->cleanDashboardValue($value) !== '')
                ->sort()
                ->last();
            $lastActivity = collect([$lastLogout, $firstLogin])
                ->filter(fn ($value): bool => $this->cleanDashboardValue($value) !== '')
                ->last();
            $attendanceStatus = $rows
                ->pluck('attendance_status')
                ->map(fn ($value): string => $this->cleanDashboardValue($value))
                ->filter()
                ->last();
            $workDuration = $rows
                ->pluck('work_duration')
                ->map(fn ($value): string => $this->cleanDashboardValue($value))
                ->filter()
                ->last();
            $loggedSeconds = $this->dashboardImportedDurationToSeconds($workDuration);

            if ($loggedSeconds <= 0 && $firstLogin && $lastLogout) {
                $checkIn = Carbon::parse($attendanceDate.' '.$firstLogin);
                $checkOut = Carbon::parse($attendanceDate.' '.$lastLogout);
                $loggedSeconds = $checkOut->gt($checkIn) ? $checkIn->diffInSeconds($checkOut) : 0;
            }

            $statusMap[$empId][$attendanceDate] = $this->effectiveDashboardImportedAttendanceStatus([
                'attendance_status' => $attendanceStatus,
                'logged_seconds' => $loggedSeconds,
                'first_login' => $firstLogin,
                'last_logout' => $lastLogout,
                'last_activity' => $lastActivity,
                'override_status' => $overrideMap[$empId][$attendanceDate] ?? null,
            ]);
        }

        return $this->fillDashboardAttendanceChart($chart, $dates, $employeeIds, $statusMap);
    }

    private function dashboardBranchAttendanceChart(Collection $dates, Collection $branchEmployeeIds): array
    {
        $employeeIds = $branchEmployeeIds
            ->map(fn ($empId): string => $this->cleanDashboardValue($empId))
            ->filter()
            ->unique()
            ->values();

        $chart = $this->emptyDashboardAttendanceChart(
            $dates,
            'Branch Attendance Statistics',
            'App attendance status for branch employees over the last 7 days.',
            $employeeIds->count()
        );

        if ($employeeIds->isEmpty() || $dates->isEmpty()) {
            return $chart;
        }

        $fromDate = $dates->first()->toDateString();
        $toDate = $dates->last()->toDateString();

        $records = Attendance::query()
            ->whereBetween('check_in_date', [$fromDate, $toDate])
            ->whereIn('empId', $employeeIds->all())
            ->orderByDesc('id')
            ->get([
                'id',
                'empId',
                'check_in_date',
                'check_in_time',
                'check_out_date',
                'check_out_time',
                'attendance_status_override',
            ]);

        $statusMap = [];

        foreach ($records as $attendance) {
            $empId = $this->cleanDashboardValue($attendance->empId);
            $attendanceDate = $this->cleanDashboardValue($attendance->check_in_date);

            if ($empId === '' || $attendanceDate === '' || isset($statusMap[$empId][$attendanceDate])) {
                continue;
            }

            $statusMap[$empId][$attendanceDate] = $this->effectiveDashboardAttendanceStatus($attendance);
        }

        return $this->fillDashboardAttendanceChart($chart, $dates, $employeeIds, $statusMap);
    }

    private function dashboardAttendanceRecordMap(Collection $empIds, Carbon $start, Carbon $end): array
    {
        $cleanIds = $empIds
            ->map(fn ($empId): string => $this->cleanDashboardValue($empId))
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
            ->get();

        foreach ($records as $record) {
            $empId = $this->cleanDashboardValue($record->empId);
            $attendanceDate = $this->cleanDashboardValue($record->check_in_date);

            if ($empId === '' || $attendanceDate === '') {
                continue;
            }

            $map[$empId][$attendanceDate] = $record;
        }

        return $map;
    }

    private function dashboardImportedDailySummaryMap(Collection $empIds, string $fromDate, string $toDate): array
    {
        $cleanIds = $empIds
            ->map(fn ($empId): string => $this->cleanDashboardValue($empId))
            ->filter()
            ->unique()
            ->values();

        if ($cleanIds->isEmpty()) {
            return [];
        }

        $rows = HoAttendanceImport::query()
            ->selectRaw("
                emp_id,
                attendance_date,
                MIN(COALESCE(login_time, logout_time)) as first_seen,
                MIN(login_time) as first_login,
                MAX(logout_time) as last_logout
            ")
            ->whereIn('emp_id', $cleanIds->all())
            ->whereBetween('attendance_date', [$fromDate, $toDate])
            ->groupBy('emp_id', 'attendance_date')
            ->orderBy('emp_id')
            ->orderBy('attendance_date')
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $empId = $this->cleanDashboardValue($row->emp_id);
            $attendanceDate = $this->cleanDashboardValue($row->attendance_date);

            if ($empId === '' || $attendanceDate === '') {
                continue;
            }

            $map[$empId][$attendanceDate] = [
                'first_login' => $row->first_login ?: $row->first_seen,
                'last_logout' => $row->last_logout,
            ];
        }

        return $map;
    }

    private function emptyDashboardAttendanceChart(
        Collection $dates,
        string $title,
        string $description,
        int $employeeCount
    ): array {
        return [
            'title' => $title,
            'description' => $description,
            'employee_count' => $employeeCount,
            'labels' => $dates->map(fn (Carbon $date): string => $date->format('d M'))->all(),
            'series' => [
                ['name' => 'Full Day', 'data' => array_fill(0, $dates->count(), 0)],
                ['name' => 'Half Day', 'data' => array_fill(0, $dates->count(), 0)],
                ['name' => 'Single Punch', 'data' => array_fill(0, $dates->count(), 0)],
                ['name' => 'Absent', 'data' => array_fill(0, $dates->count(), 0)],
            ],
        ];
    }

    private function fillDashboardAttendanceChart(
        array $chart,
        Collection $dates,
        Collection $employeeIds,
        array $statusMap
    ): array {
        $fullDay = [];
        $halfDay = [];
        $singlePunch = [];
        $absent = [];

        foreach ($dates as $date) {
            $dateKey = $date->toDateString();
            $fullDayCount = 0;
            $halfDayCount = 0;
            $singlePunchCount = 0;
            $absentCount = 0;

            foreach ($employeeIds as $empId) {
                $status = $statusMap[$empId][$dateKey] ?? self::STATUS_ABSENT;

                if (in_array($status, [self::STATUS_FULL_DAY, self::STATUS_FULL_DAY_REMOTE], true)) {
                    $fullDayCount++;
                } elseif ($status === self::STATUS_HALF_DAY) {
                    $halfDayCount++;
                } elseif ($status === self::STATUS_SINGLE_PUNCH) {
                    $singlePunchCount++;
                } else {
                    $absentCount++;
                }
            }

            $fullDay[] = $fullDayCount;
            $halfDay[] = $halfDayCount;
            $singlePunch[] = $singlePunchCount;
            $absent[] = $absentCount;
        }

        $chart['series'][0]['data'] = $fullDay;
        $chart['series'][1]['data'] = $halfDay;
        $chart['series'][2]['data'] = $singlePunch;
        $chart['series'][3]['data'] = $absent;

        return $chart;
    }

    private function effectiveDashboardImportedAttendanceStatus(array $daySummary): string
    {
        $override = $this->cleanDashboardValue($daySummary['override_status'] ?? '');

        if (in_array($override, [self::STATUS_FULL_DAY, self::STATUS_HALF_DAY, self::STATUS_SINGLE_PUNCH, self::STATUS_ABSENT], true)) {
            return $override;
        }

        $mappedStatus = $this->dashboardImportedStatusFromCode($daySummary['attendance_status'] ?? '');
        $loggedSeconds = (int) ($daySummary['logged_seconds'] ?? 0);
        $hasCheckIn = $this->cleanDashboardValue($daySummary['first_login'] ?? '') !== '';
        $hasCheckOut = $this->cleanDashboardValue($daySummary['last_logout'] ?? '') !== '';

        if ($hasCheckIn && ! $hasCheckOut) {
            return self::STATUS_SINGLE_PUNCH;
        }

        if ($mappedStatus === self::STATUS_SINGLE_PUNCH) {
            return self::STATUS_SINGLE_PUNCH;
        }

        if ($mappedStatus === self::STATUS_ABSENT) {
            return self::STATUS_ABSENT;
        }

        if ($loggedSeconds > 0) {
            return $loggedSeconds < (self::FULL_DAY_MINUTES * 60)
                ? self::STATUS_HALF_DAY
                : self::STATUS_FULL_DAY;
        }

        if (in_array($mappedStatus, [self::STATUS_FULL_DAY, self::STATUS_HALF_DAY], true)) {
            return $mappedStatus;
        }

        if ($hasCheckIn || $this->cleanDashboardValue($daySummary['last_activity'] ?? '') !== '') {
            return self::STATUS_SINGLE_PUNCH;
        }

        return self::STATUS_ABSENT;
    }

    private function dashboardImportedStatusFromCode(?string $status): ?string
    {
        $normalized = strtolower($this->cleanDashboardValue($status));
        $normalized = str_replace([' ', '-', '_', '.'], '', $normalized);

        return match ($normalized) {
            'p', 'present', 'fullday', 'fd' => self::STATUS_FULL_DAY,
            'halfday', 'hd', 'half' => self::STATUS_HALF_DAY,
            'singlepunch', 'single', 'sp' => self::STATUS_SINGLE_PUNCH,
            'a', 'absent' => self::STATUS_ABSENT,
            default => null,
        };
    }

    private function dashboardImportedDurationToSeconds(?string $duration): int
    {
        $value = $this->cleanDashboardValue($duration);

        if ($value === '') {
            return 0;
        }

        $segments = array_map('trim', explode(':', $value));

        if (count($segments) === 2) {
            [$hours, $minutes] = $segments;

            return (((int) $hours) * 3600) + (((int) $minutes) * 60);
        }

        if (count($segments) === 3) {
            [$hours, $minutes, $seconds] = $segments;

            return (((int) $hours) * 3600) + (((int) $minutes) * 60) + ((int) $seconds);
        }

        return 0;
    }

    private function effectiveDashboardAttendanceStatus(?Attendance $attendance): string
    {
        if (! $attendance) {
            return self::STATUS_ABSENT;
        }

        $override = strtolower(trim((string) $attendance->attendance_status_override));

        if (in_array($override, [self::STATUS_FULL_DAY, self::STATUS_FULL_DAY_REMOTE, self::STATUS_HALF_DAY, self::STATUS_SINGLE_PUNCH, self::STATUS_ABSENT], true)) {
            return $override;
        }

        if (! $attendance->check_out_date || ! $attendance->check_out_time) {
            return self::STATUS_SINGLE_PUNCH;
        }

        return $this->dashboardWorkedMinutes($attendance) < self::FULL_DAY_MINUTES
            ? self::STATUS_HALF_DAY
            : self::STATUS_FULL_DAY;
    }

    private function dashboardWorkedMinutes(?Attendance $attendance): int
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

    private function headOfficeBranch(Collection $branches): ?Branch
    {
        /** @var Branch|null $branch */
        $branch = $branches->first(function (Branch $branch): bool {
            return strcasecmp($this->cleanDashboardValue($branch->branchName), 'Head Office') === 0
                || strcasecmp($this->cleanDashboardValue($branch->branchId), 'AGPL000') === 0;
        });

        return $branch;
    }

    private function cleanDashboardValue($value): string
    {
        return trim((string) $value);
    }

    public function adminLogout(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $notification = array(
            'message' => 'Admin Logout Successfully.',
            'alert-type' => 'success'
        );

        return redirect()
            ->route('admin-login')
            ->with($notification)
            ->cookie($this->themePreferenceCookie($admin));
    }


    public function changePassword()
    {
        return view('admin.change_password');
    }

    public function updatePassword(Request $request)
    {

        $request->validate([
            'old_password' => 'required',
            'new_password' => 'required|confirmed',
        ]);

        $hashedPassword = Auth::guard('admin')->user()->password;
        if (Hash::check($request->old_password, $hashedPassword)) {
            $admin = Auth::guard('admin')->user();
            $admin->password = bcrypt($request->new_password);
            $admin->save();


            $notification1 = array(
                'message' => 'Password Updated Successfully',
                'alert-type' => 'success'
            );

            return redirect()->route('admin-login')->with($notification1);
        } else {

            $notification2 = array(
                'message' => 'Old password is not match',
                'alert-type' => 'error'
            );
            return redirect()->back()->with($notification2);
        }
    }

    public function adminProfile()
    {
        $admin = Auth::guard('admin')->user();

        return view('admin.profile', [
            'admin' => $admin,
            'themeOptions' => self::THEME_OPTIONS,
            'cardStyleOptions' => self::CARD_STYLE_OPTIONS,
            'tableDensityOptions' => self::TABLE_DENSITY_OPTIONS,
            'themeColorDefaults' => $this->themePalette($admin?->theme_preference),
        ]);
    }


    public function adminProfileDetailsUpdate(Request $request)
    {
        $admin = Auth::guard('admin')->user();
        $data = $request->validate($this->profileDetailsValidationRules());

        $admin->name = $data['name'];
        $admin->email = $data['email'];
        $admin->phone = $data['phone'] ?? null;
        $admin->position = $data['position'] ?? null;
        $admin->address = $data['address'] ?? null;

        if ($request->file('image')) {
            $admin->image = $this->storeAdminImage($request, $admin->image);
        }
        $admin->save();

        return redirect()->back()->with('flash_success', 'Profile details updated successfully.');
    }


    public function adminCreate()
    {
        return view('admin.create');
    }

    public function adminStore(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
            'position' => ['required', 'string', 'max:255'],
        ]);

        $themeDefaults = $this->themePalette('blue-theme');
        $admin = new Admin();
        $admin->name = trim((string) $data['name']);
        $admin->email = trim((string) $data['email']);
        $admin->phone = null;
        $admin->address = null;
        $admin->image = null;
        $admin->theme_preference = 'blue-theme';
        $admin->card_style = 'rounded';
        $admin->table_density = 'comfortable';
        $admin->sidebar_collapsed = false;

        foreach (self::COLOR_FIELDS as $field) {
            $admin->{$field} = $themeDefaults[$field] ?? null;
        }

        $admin->password = Hash::make(self::DEFAULT_CREATED_ADMIN_PASSWORD);
        $admin->password_hint = self::DEFAULT_CREATED_ADMIN_PASSWORD;
        $admin->role = Admin::ROLE_HIRING;
        $admin->position = trim((string) $data['position']);

        $admin->save();

        return redirect()
            ->route('admin-create')
            ->with('flash_success', 'Admin created successfully.');
    }


    public function adminIndex()
    {
        $admins = $this->manageableAdminQuery()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'address', 'image', 'role', 'position']);

        $adminColumns = [
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'address' => 'Address',
            'image' => 'Image',
            'role' => 'Role',
            'position' => 'Position',
        ];

        return view('admin.index', compact('admins', 'adminColumns'));
    }

    public function adminEdit($id)
    {
        $admin = $this->manageableAdminQuery()->findOrFail($id);

        return view('admin.edit', compact('admin'));
    }

    public function adminUpdate(Request $request, $id)
    {
        $admin = $this->manageableAdminQuery()->findOrFail($id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email,' . $admin->id],
            'position' => ['required', 'string', 'max:255'],
        ]);

        $admin->name = trim((string) $data['name']);
        $admin->email = trim((string) $data['email']);
        $admin->position = trim((string) $data['position']);
        $admin->save();

        return redirect()
            ->route('admin-index')
            ->with('flash_success', 'Admin updated successfully.');
    }

    private function manageableAdminQuery()
    {
        return Admin::query()
            ->where(function ($query): void {
                $query->whereNull('name')
                    ->orWhereRaw('LOWER(name) NOT LIKE ?', ['%pramila%']);
            })
            ->where(function ($query): void {
                $query->whereNull('email')
                    ->orWhereRaw('LOWER(email) NOT LIKE ?', ['%pramila%']);
            });
    }




    public function adminThemeUpdate(Request $request)
    {
        $admin = Auth::guard('admin')->user();
        $data = $request->validate($this->themeValidationRules());

        $admin->theme_preference = $data['theme_preference'];
        $admin->card_style = $data['card_style'];
        $admin->table_density = $data['table_density'];
        $admin->sidebar_collapsed = $request->boolean('sidebar_collapsed');

        foreach (self::COLOR_FIELDS as $field) {
            $admin->{$field} = $this->normalizeThemeColor($data[$field] ?? null);
        }

        $admin->save();

        return redirect()
            ->back()
            ->with('flash_success', 'Theme preferences updated successfully.')
            ->cookie($this->themePreferenceCookie($admin));
    }

    private function loginThemeViewData(Request $request): array
    {
        $themeState = $this->decodeThemeCookie($request->cookie(self::THEME_COOKIE_NAME));
        $themePreference = $this->normalizeThemePreference($themeState['theme_preference'] ?? null);
        $cardStyle = $this->normalizeCardStyle($themeState['card_style'] ?? null);
        $themePalettes = $this->loginThemePalettes();
        $activePalette = $themePalettes[$themePreference] ?? $themePalettes['light'];
        $resolvedColors = $activePalette;

        $resolvedTextColor = $this->ensureReadableTextColor(
            $resolvedColors['theme_text_color'],
            [
                $resolvedColors['theme_background_color'],
                $resolvedColors['theme_surface_color'],
            ]
        );

        $resolvedMutedTextColor = $this->ensureReadableTextColor(
            $resolvedColors['theme_muted_text_color'],
            [
                $resolvedColors['theme_background_color'],
                $resolvedColors['theme_surface_color'],
            ],
            $resolvedTextColor,
            $resolvedTextColor,
            3.2
        );

        $loginFormTextColor = $this->ensureReadableTextColor(
            $activePalette['theme_text_color'],
            [
                $resolvedColors['theme_background_color'],
                $resolvedColors['theme_surface_color'],
            ]
        );

        $loginFormMutedTextColor = $this->ensureReadableTextColor(
            $activePalette['theme_muted_text_color'],
            [
                $resolvedColors['theme_background_color'],
                $resolvedColors['theme_surface_color'],
            ],
            $loginFormTextColor,
            $loginFormTextColor,
            3.2
        );

        return [
            'themePreference' => $themePreference,
            'cardStyle' => $cardStyle,
            'resolvedColors' => $resolvedColors,
            'resolvedTextColor' => $resolvedTextColor,
            'resolvedMutedTextColor' => $resolvedMutedTextColor,
            'loginFormTextColor' => $loginFormTextColor,
            'loginFormMutedTextColor' => $loginFormMutedTextColor,
            'themePrimaryColorRgb' => $this->hexToRgb($resolvedColors['theme_primary_color']),
            'themePrimaryContrastColor' => $this->contrastTextColor($resolvedColors['theme_primary_color']),
            'resolvedTextColorRgb' => $this->hexToRgb($resolvedTextColor),
            'loginFormTextColorRgb' => $this->hexToRgb($loginFormTextColor),
            'themeBackgroundColorRgb' => $this->hexToRgb($resolvedColors['theme_background_color']),
        ];
    }

    private function themePreferenceCookie(?Admin $admin)
    {
        return cookie(
            self::THEME_COOKIE_NAME,
            json_encode($this->themeCookiePayload($admin)),
            60 * 24 * 365 * 5
        );
    }

    private function themeCookiePayload(?Admin $admin): array
    {
        $payload = [
            'theme_preference' => $this->normalizeThemePreference($admin?->theme_preference),
            'card_style' => $this->normalizeCardStyle($admin?->card_style),
        ];

        foreach (self::COLOR_FIELDS as $field) {
            $color = $this->normalizeThemeColor($admin?->{$field});

            if ($color !== null) {
                $payload[$field] = $color;
            }
        }

        return $payload;
    }

    private function decodeThemeCookie(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeThemePreference(?string $value): string
    {
        return in_array($value, self::THEME_OPTIONS, true) ? $value : 'light';
    }

    private function normalizeCardStyle(?string $value): string
    {
        return in_array($value, self::CARD_STYLE_OPTIONS, true) ? $value : 'rounded';
    }

    private function loginThemePalettes(): array
    {
        return [
            'blue-theme' => [
                'theme_primary_color' => '#0D6EFD',
                'theme_highlight_color' => '#63A4FF',
                'theme_background_color' => '#0F172A',
                'theme_surface_color' => '#17213A',
                'theme_sidebar_background_color' => '#111C33',
                'theme_text_color' => '#F8FAFC',
                'theme_muted_text_color' => '#B9C4D4',
                'theme_border_color' => '#31405E',
            ],
            'light' => [
                'theme_primary_color' => '#A63D2F',
                'theme_highlight_color' => '#C8A24A',
                'theme_background_color' => '#FCF7F2',
                'theme_surface_color' => '#FFFFFF',
                'theme_sidebar_background_color' => '#FFF9F3',
                'theme_text_color' => '#35231D',
                'theme_muted_text_color' => '#8B6B61',
                'theme_border_color' => '#E9D8CC',
            ],
            'dark' => [
                'theme_primary_color' => '#6EA8FE',
                'theme_highlight_color' => '#E0B96D',
                'theme_background_color' => '#10151C',
                'theme_surface_color' => '#1B2431',
                'theme_sidebar_background_color' => '#161F2B',
                'theme_text_color' => '#EAF1FF',
                'theme_muted_text_color' => '#9DAAC0',
                'theme_border_color' => '#344154',
            ],
            'semi-dark' => [
                'theme_primary_color' => '#0D6EFD',
                'theme_highlight_color' => '#F0C36A',
                'theme_background_color' => '#F3F6FB',
                'theme_surface_color' => '#FFFFFF',
                'theme_sidebar_background_color' => '#17213A',
                'theme_text_color' => '#172033',
                'theme_muted_text_color' => '#667085',
                'theme_border_color' => '#D7DEEA',
            ],
            'bodered-theme' => [
                'theme_primary_color' => '#A63D2F',
                'theme_highlight_color' => '#C8A24A',
                'theme_background_color' => '#FFF9F5',
                'theme_surface_color' => '#FFFFFF',
                'theme_sidebar_background_color' => '#FFFCF8',
                'theme_text_color' => '#35231D',
                'theme_muted_text_color' => '#8B6B61',
                'theme_border_color' => '#E4D2C4',
            ],
            'emerald-theme' => [
                'theme_primary_color' => '#047857',
                'theme_highlight_color' => '#22C55E',
                'theme_background_color' => '#ECFDF5',
                'theme_surface_color' => '#FFFFFF',
                'theme_sidebar_background_color' => '#052E2B',
                'theme_text_color' => '#10231D',
                'theme_muted_text_color' => '#577064',
                'theme_border_color' => '#BFE7D1',
            ],
            'violet-theme' => [
                'theme_primary_color' => '#7C3AED',
                'theme_highlight_color' => '#F472B6',
                'theme_background_color' => '#F5F3FF',
                'theme_surface_color' => '#FFFFFF',
                'theme_sidebar_background_color' => '#1E1B4B',
                'theme_text_color' => '#241A3E',
                'theme_muted_text_color' => '#6D5F85',
                'theme_border_color' => '#DDD6FE',
            ],
            'sunset-theme' => [
                'theme_primary_color' => '#E11D48',
                'theme_highlight_color' => '#F59E0B',
                'theme_background_color' => '#FFF1F2',
                'theme_surface_color' => '#FFFFFF',
                'theme_sidebar_background_color' => '#3B1827',
                'theme_text_color' => '#331A25',
                'theme_muted_text_color' => '#7F5D66',
                'theme_border_color' => '#FED7E2',
            ],
            'copper-theme' => [
                'theme_primary_color' => '#B45309',
                'theme_highlight_color' => '#E9A23B',
                'theme_background_color' => '#FFF8F1',
                'theme_surface_color' => '#FFFFFF',
                'theme_sidebar_background_color' => '#3A2315',
                'theme_text_color' => '#342016',
                'theme_muted_text_color' => '#836252',
                'theme_border_color' => '#EBCFB8',
            ],
            'ocean-theme' => [
                'theme_primary_color' => '#0F766E',
                'theme_highlight_color' => '#38BDF8',
                'theme_background_color' => '#F1FAFC',
                'theme_surface_color' => '#FFFFFF',
                'theme_sidebar_background_color' => '#0B2F3A',
                'theme_text_color' => '#18313A',
                'theme_muted_text_color' => '#5B7680',
                'theme_border_color' => '#C4E5EC',
            ],
            'mulberry-theme' => [
                'theme_primary_color' => '#9D174D',
                'theme_highlight_color' => '#F59EB2',
                'theme_background_color' => '#FFF6FA',
                'theme_surface_color' => '#FFFFFF',
                'theme_sidebar_background_color' => '#341326',
                'theme_text_color' => '#351C2A',
                'theme_muted_text_color' => '#7D6070',
                'theme_border_color' => '#F2CCDB',
            ],
        ];
    }

    private function hexToRgbArray(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function hexToRgb(string $hex): string
    {
        return implode(', ', $this->hexToRgbArray($hex));
    }

    private function contrastTextColor(string $hex): string
    {
        [$red, $green, $blue] = $this->hexToRgbArray($hex);
        $brightness = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;

        return $brightness >= 160 ? '#2D1A14' : '#FFF9F4';
    }

    private function contrastRatio(string $foreground, string $background): float
    {
        $toLuminance = function (string $hex): float {
            $channels = array_map(
                static function (int $channel): float {
                    $value = $channel / 255;

                    return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
                },
                $this->hexToRgbArray($hex)
            );

            return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
        };

        $lighter = max($toLuminance($foreground), $toLuminance($background));
        $darker = min($toLuminance($foreground), $toLuminance($background));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function ensureReadableTextColor(
        string $preferred,
        array $backgrounds,
        string $fallbackDark = '#2D1A14',
        string $fallbackLight = '#FFF9F4',
        float $minimumContrast = 4.5
    ): string {
        foreach ($backgrounds as $background) {
            if ($this->contrastRatio($preferred, $background) < $minimumContrast) {
                $bestCandidate = $fallbackDark;
                $bestScore = 0.0;

                foreach ([$fallbackDark, $fallbackLight] as $candidate) {
                    $score = null;

                    foreach ($backgrounds as $candidateBackground) {
                        $ratio = $this->contrastRatio($candidate, $candidateBackground);
                        $score = $score === null ? $ratio : min($score, $ratio);
                    }

                    if ($score > $bestScore) {
                        $bestCandidate = $candidate;
                        $bestScore = $score;
                    }
                }

                return $bestCandidate;
            }
        }

        return $preferred;
    }

    private function profileDetailsValidationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'digits_between:7,15'],
            'position' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:5120'],
        ];
    }

    private function themeValidationRules(): array
    {
        return [
            'theme_preference' => ['required', 'in:' . implode(',', self::THEME_OPTIONS)],
            'card_style' => ['required', 'in:' . implode(',', self::CARD_STYLE_OPTIONS)],
            'table_density' => ['required', 'in:' . implode(',', self::TABLE_DENSITY_OPTIONS)],
            'sidebar_collapsed' => ['nullable', 'boolean'],
            'theme_primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_surface_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_sidebar_background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_text_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_muted_text_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_border_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    private function storeAdminImage(Request $request, ?string $existingImage): string
    {
        $image = $request->file('image');
        $directory = public_path('storage/admin');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        if (! empty($existingImage)) {
            @unlink($directory.'/'.$existingImage);
        }

        $filename = 'admin' . time() . '.' . $image->getClientOriginalExtension();

        Image::make($image)->resize(256, 256)->save($directory.'/'.$filename);

        return $filename;
    }

    private function normalizeThemeColor(?string $value): ?string
    {
        $color = strtoupper(trim((string) $value));

        if ($color === '') {
            return null;
        }

        return preg_match('/^#[0-9A-F]{6}$/', $color) === 1 ? $color : null;
    }

    private function themePalette(?string $themePreference): array
    {
        return match ($themePreference) {
            'blue-theme' => [
                'theme_primary_color' => '#0D6EFD',
                'theme_background_color' => '#0F172A',
                'theme_surface_color' => '#17213A',
                'theme_sidebar_background_color' => '#111C33',
                'theme_text_color' => '#F8FAFC',
                'theme_muted_text_color' => '#B9C4D4',
                'theme_border_color' => '#31405E',
            ],
            'light' => [
                'theme_primary_color' => '#A63D2F',
                'theme_background_color' => '#FCF7F2',
                'theme_surface_color' => '#FFFFFF',
                'theme_sidebar_background_color' => '#FFF9F3',
                'theme_text_color' => '#35231D',
                'theme_muted_text_color' => '#8B6B61',
                'theme_border_color' => '#E9D8CC',
            ],
            'dark' => [
                'theme_primary_color' => '#6EA8FE',
                'theme_background_color' => '#10151C',
                'theme_surface_color' => '#1B2431',
                'theme_sidebar_background_color' => '#161F2B',
                'theme_text_color' => '#EAF1FF',
                'theme_muted_text_color' => '#9DAAC0',
                'theme_border_color' => '#344154',
            ],
            'semi-dark' => [
                'theme_primary_color' => '#0D6EFD',
                'theme_background_color' => '#F3F6FB',
                'theme_surface_color' => '#FFFFFF',
                'theme_sidebar_background_color' => '#17213A',
                'theme_text_color' => '#172033',
                'theme_muted_text_color' => '#667085',
                'theme_border_color' => '#D7DEEA',
            ],
            'bodered-theme' => [
                'theme_primary_color' => '#A63D2F',
                'theme_background_color' => '#FFF9F5',
                'theme_surface_color' => '#FFFFFF',
                'theme_sidebar_background_color' => '#FFFCF8',
                'theme_text_color' => '#35231D',
                'theme_muted_text_color' => '#8B6B61',
                'theme_border_color' => '#E4D2C4',
            ],
            'emerald-theme' => [
                'theme_primary_color' => '#047857',
                'theme_background_color' => '#ECFDF5',
                'theme_surface_color' => '#FFFFFF',
                'theme_sidebar_background_color' => '#052E2B',
                'theme_text_color' => '#10231D',
                'theme_muted_text_color' => '#577064',
                'theme_border_color' => '#BFE7D1',
            ],
            'violet-theme' => [
                'theme_primary_color' => '#7C3AED',
                'theme_background_color' => '#F5F3FF',
                'theme_surface_color' => '#FFFFFF',
                'theme_sidebar_background_color' => '#1E1B4B',
                'theme_text_color' => '#241A3E',
                'theme_muted_text_color' => '#6D5F85',
                'theme_border_color' => '#DDD6FE',
            ],
            'sunset-theme' => [
                'theme_primary_color' => '#E11D48',
                'theme_background_color' => '#FFF1F2',
                'theme_surface_color' => '#FFFFFF',
                'theme_sidebar_background_color' => '#3B1827',
                'theme_text_color' => '#331A25',
                'theme_muted_text_color' => '#7F5D66',
                'theme_border_color' => '#FED7E2',
            ],
            'copper-theme' => [
                'theme_primary_color' => '#B45309',
                'theme_background_color' => '#FFF8F1',
                'theme_surface_color' => '#FFFFFF',
                'theme_sidebar_background_color' => '#3A2315',
                'theme_text_color' => '#342016',
                'theme_muted_text_color' => '#836252',
                'theme_border_color' => '#EBCFB8',
            ],
            'ocean-theme' => [
                'theme_primary_color' => '#0F766E',
                'theme_background_color' => '#F1FAFC',
                'theme_surface_color' => '#FFFFFF',
                'theme_sidebar_background_color' => '#0B2F3A',
                'theme_text_color' => '#18313A',
                'theme_muted_text_color' => '#5B7680',
                'theme_border_color' => '#C4E5EC',
            ],
            'mulberry-theme' => [
                'theme_primary_color' => '#9D174D',
                'theme_background_color' => '#FFF6FA',
                'theme_surface_color' => '#FFFFFF',
                'theme_sidebar_background_color' => '#341326',
                'theme_text_color' => '#351C2A',
                'theme_muted_text_color' => '#7D6070',
                'theme_border_color' => '#F2CCDB',
            ],
            default => [
                'theme_primary_color' => '#A63D2F',
                'theme_background_color' => '#FCF7F2',
                'theme_surface_color' => '#FFFFFF',
                'theme_sidebar_background_color' => '#FFF9F3',
                'theme_text_color' => '#35231D',
                'theme_muted_text_color' => '#8B6B61',
                'theme_border_color' => '#E9D8CC',
            ],
        };
    }
}
