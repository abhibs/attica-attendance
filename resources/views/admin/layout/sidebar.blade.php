@php
    $adminUser = Auth::guard('admin')->user();
    $adminRole = strtolower(trim((string) ($adminUser?->role ?? 'hr_admin')));
    $isHrLimitedSidebarUser = strtolower(trim((string) ($adminUser?->name ?? ''))) === 'hr'
        && strtolower(trim((string) ($adminUser?->email ?? ''))) === 'hr.attica@gmail.com';

    $canSeeMenu = static fn (string $key): bool => \App\Support\AdminMenu::adminCanSee($adminUser, $key);
    $canSeeAnyMenu = static fn (array $keys): bool => \App\Support\AdminMenu::adminCanSeeAny($adminUser, $keys);

    $branchMenuKeys = ['branch.create', 'branch.index', 'branch.logins', 'branch.opening', 'branch.opening_timings'];
    $adminMenuKeys = ['admins.create', 'admins.index'];
    $employeeMenuKeys = ['employee.create', 'employee.index', 'employee.onboarded', 'employee.night_shift_users'];
    $attendanceMenuKeys = ['attendance.daily', 'attendance.night_shift', 'attendance.out_of_office', 'attendance.review', 'attendance.fraud_reports', 'attendance.blocked'];
    $attendanceReviewKeys = ['attendance.out_of_office', 'attendance.review', 'attendance.fraud_reports', 'attendance.blocked'];
    $salaryMenuKeys = ['salary.advance', 'salary.bank_requests', 'salary.account_details', 'salary.reports'];
    $outsourceMenuKeys = ['outsource.employee_create', 'outsource.employee_index', 'outsource.location_create', 'outsource.location_index', 'outsource.attendance', 'outsource.leave_review', 'outsource.leave_reports'];
    $leaveMenuKeys = ['leaves.review', 'leaves.reports'];
    $workVisitMenuKeys = ['work_visits.review', 'work_visits.reports'];
    $reportsMenuKeys = ['reports.attendance', 'reports.salary', 'reports.advance'];

    $showDashboardMenu = $canSeeMenu('dashboard.home');
    $showBranchMenu = $canSeeAnyMenu($branchMenuKeys);
    $showAdminMenu = ! $isHrLimitedSidebarUser && $canSeeAnyMenu($adminMenuKeys);
    $showEmployeeMenu = $canSeeAnyMenu($employeeMenuKeys);
    $showMessengerMenu = $canSeeMenu('messenger.index');
    $canManageHiring = ! $isHrLimitedSidebarUser && $canSeeMenu('recruitment.hiring');
    $canManageJoining = ! $isHrLimitedSidebarUser && $canSeeMenu('recruitment.joining');
    $showAttendanceMenu = ! $isHrLimitedSidebarUser && $canSeeAnyMenu($attendanceMenuKeys);
    $showSalaryMenu = ! $isHrLimitedSidebarUser && $canSeeAnyMenu($salaryMenuKeys);
    $showOutsourceMenu = $canSeeAnyMenu($outsourceMenuKeys);
    $showLeaveMenu = $canSeeAnyMenu($leaveMenuKeys);
    $showWorkVisitMenu = $canSeeAnyMenu($workVisitMenuKeys);
    $showNotificationsMenu = $canSeeMenu('notifications.index');
    $showTeTrackerMenu = $canSeeMenu('attendance.te_tracker');
    $showReportsMenu = $canSeeAnyMenu($reportsMenuKeys);

    $branchMenuOpen = $showBranchMenu && request()->routeIs('admin-branch-*', 'admin-branch-opening-*');
    $branchOpeningActive = request()->routeIs('admin-branch-opening-*');
    $branchLoginsActive = request()->routeIs('admin-branch-logins');
    $outsourceEmployeeListMenuActive = request()->routeIs('admin-employee-index') && request()->input('tab') === 'outsource';
    $outsourceMenuOpen = $showOutsourceMenu && (request()->routeIs('admin-outsource-*', 'admin-attendance-outsource')
        || (request()->routeIs('admin-employee-create') && request()->input('is_outsourced') == '1')
        || $outsourceEmployeeListMenuActive);
    $outsourceLocationMenuActive = request()->routeIs('admin-outsource-*') && ! request()->routeIs('admin-outsource-leaves-*');
    $outsourceAttendanceMenuActive = request()->routeIs('admin-attendance-outsource');
    $outsourceLeaveReviewMenuActive = request()->routeIs('admin-outsource-leaves-review');
    $outsourceLeaveReportsMenuActive = request()->routeIs('admin-outsource-leaves-reports');
    $outsourceEmployeeCreateMenuActive = request()->routeIs('admin-employee-create') && request()->input('is_outsourced') == '1';
    $employeeMenuOpen = $showEmployeeMenu && request()->routeIs('admin-employee-*', 'admin-night-shift-users*');
    $nightShiftUsersActive = request()->routeIs('admin-night-shift-users*');
    $recruitmentMenuOpen = ($canManageHiring || $canManageJoining) && request()->routeIs('admin-hiring-*', 'admin-joining-*');
    $hiringMenuActive = request()->routeIs('admin-hiring-*');
    $joiningMenuActive = request()->routeIs('admin-joining-*');
    $adminUsersMenuOpen = $showAdminMenu && request()->routeIs('admin-create', 'admin-index', 'admin-edit');
    $adminCreateActive = request()->routeIs('admin-create');
    $adminIndexActive = request()->routeIs('admin-index', 'admin-edit');
    $reportsMenuOpen = $showReportsMenu
        && (request()->routeIs('admin-attendance-reports', 'admin-salary-reports', 'admin-advance-reports'))
        && request()->input('menu', 'reports') === 'reports';
    $salaryReportsStandaloneActive = request()->routeIs('admin-salary-reports', 'admin-salary-reports-export') && request()->input('menu', '') !== 'reports';
    $salaryMenuOpen = $showSalaryMenu
        && ((request()->routeIs('admin-salary-*') && ! request()->routeIs('admin-salary-reports')) || $salaryReportsStandaloneActive);
    $teTrackerMenuOpen = $showTeTrackerMenu && request()->routeIs('admin-attendance-te-tracker');
    $attendanceMenuOpen = $showAttendanceMenu && request()->routeIs('admin-attendance-*') && ! request()->routeIs('admin-attendance-outsource') && ! $reportsMenuOpen && ! $teTrackerMenuOpen;
    $leaveMenuOpen = $showLeaveMenu && request()->routeIs('admin-leaves-*');
    $workVisitMenuOpen = $showWorkVisitMenu && request()->routeIs('admin-work-visits-*');
    $attendanceBranchMenuOpen = $canSeeMenu('attendance.daily') && request()->routeIs('admin-attendance-daily', 'admin-attendance-calendar');
    $attendanceNightShiftMenuOpen = $canSeeMenu('attendance.night_shift') && request()->routeIs('admin-attendance-night-shift');
    $attendanceReviewMenuOpen = $canSeeAnyMenu($attendanceReviewKeys) && request()->routeIs('admin-attendance-review', 'admin-attendance-review-*', 'admin-attendance-blocked', 'admin-attendance-blocked-*', 'admin-attendance-out-of-office', 'admin-attendance-out-of-office-*', 'admin-attendance-fraud-reports');
    $attendanceReportsActive = request()->routeIs('admin-attendance-reports');
    $salaryReportsActive = ! $isHrLimitedSidebarUser && $reportsMenuOpen && request()->routeIs('admin-salary-reports');
    $advanceReportsActive = ! $isHrLimitedSidebarUser && $reportsMenuOpen && request()->routeIs('admin-advance-reports');
    $notificationsMenuOpen = $showNotificationsMenu && request()->routeIs('admin-notifications*');
    $messengerMenuOpen = $showMessengerMenu && request()->routeIs('admin-messenger*');
    $showAttendanceAlert = ($hasBlockedEmployees ?? false) || ($hasAttendanceReviewAlerts ?? false);
@endphp

<style>
    .sidebar-wrapper .metismenu ul {
        border: 0 !important;
        box-shadow: none !important;
        background: transparent !important;
    }

    .sidebar-wrapper .metismenu ul a,
    .sidebar-wrapper .metismenu ul li {
        border: 0 !important;
    }
</style>

<aside class="sidebar-wrapper" data-simplebar="true">
    <div class="sidebar-header">
        <div class="logo-icon">
            <img src="{{ \App\Support\ProjectAsset::url('public/admin/assets/images/attica_logo.png') }}" class="logo-img" alt="Attica Pagar logo">
        </div>
        <div class="logo-name flex-grow-1">
            <h5 class="mb-0">Attica Pagar</h5>
        </div>
        <div class="sidebar-close">
            <span class="material-icons-outlined">close</span>
        </div>
    </div>
    <div class="sidebar-nav">
        <ul class="metismenu" id="sidenav">
            @if ($showDashboardMenu)
                <li class="{{ request()->routeIs('admin-dashboard') ? 'mm-active' : '' }}">
                    <a href="{{ route('admin-dashboard') }}">
                        <div class="parent-icon"><i class="material-icons-outlined">home</i></div>
                        <div class="menu-title">Dashboard</div>
                    </a>
                </li>
            @endif

            @if ($showBranchMenu)
                <li class="{{ $branchMenuOpen ? 'mm-active' : '' }}">
                    <a href="javascript:;" class="has-arrow" aria-expanded="{{ $branchMenuOpen ? 'true' : 'false' }}">
                        <div class="parent-icon"><i class="material-icons-outlined">widgets</i></div>
                        <div class="menu-title">Branch</div>
                    </a>
                    <ul class="{{ $branchMenuOpen ? 'mm-show' : '' }}">
                        @if ($canSeeMenu('branch.create'))
                            <li><a href="{{ route('admin-branch-create') }}"><i class="material-icons-outlined">arrow_right</i>Add Branch</a></li>
                        @endif
                        @if ($canSeeMenu('branch.index'))
                            <li><a href="{{ route('admin-branch-index') }}"><i class="material-icons-outlined">arrow_right</i>All Branches</a></li>
                        @endif
                        @if ($canSeeMenu('branch.logins'))
                            <li class="{{ $branchLoginsActive ? 'mm-active' : '' }}">
                                <a href="{{ route('admin-branch-logins') }}"><i class="material-icons-outlined">arrow_right</i>Branch Logins</a>
                            </li>
                        @endif
                        @if ($canSeeMenu('branch.opening'))
                            <li class="{{ $branchOpeningActive ? 'mm-active' : '' }}">
                                <a href="{{ route('admin-branch-opening-index') }}"><i class="material-icons-outlined">arrow_right</i>Opening & Keys</a>
                            </li>
                        @endif
                        @if ($canSeeMenu('branch.opening_timings'))
                            <li class="{{ request()->routeIs('admin-branch-opening-timings') ? 'mm-active' : '' }}">
                                <a href="{{ route('admin-branch-opening-timings') }}"><i class="material-icons-outlined">arrow_right</i>Opening Timings</a>
                            </li>
                        @endif
                    </ul>
                </li>
            @endif

            @if ($showAdminMenu)
                <li class="{{ $adminUsersMenuOpen ? 'mm-active' : '' }}">
                    <a href="javascript:;" class="has-arrow" aria-expanded="{{ $adminUsersMenuOpen ? 'true' : 'false' }}">
                        <div class="parent-icon"><i class="material-icons-outlined">admin_panel_settings</i></div>
                        <div class="menu-title">Admins</div>
                    </a>
                    <ul class="{{ $adminUsersMenuOpen ? 'mm-show' : '' }}">
                        @if ($canSeeMenu('admins.create'))
                            <li class="{{ $adminCreateActive ? 'mm-active' : '' }}">
                                <a href="{{ route('admin-create') }}"><i class="material-icons-outlined">arrow_right</i>Add Admin</a>
                            </li>
                        @endif
                        @if ($canSeeMenu('admins.index'))
                            <li class="{{ $adminIndexActive ? 'mm-active' : '' }}">
                                <a href="{{ route('admin-index') }}"><i class="material-icons-outlined">arrow_right</i>All Admins</a>
                            </li>
                        @endif
                    </ul>
                </li>
            @endif

            @if ($showEmployeeMenu)
                <li class="{{ $employeeMenuOpen ? 'mm-active' : '' }}">
                    <a href="javascript:;" class="has-arrow" aria-expanded="{{ $employeeMenuOpen ? 'true' : 'false' }}">
                        <div class="parent-icon"><i class="material-icons-outlined">apps</i></div>
                        <div class="menu-title">Employee</div>
                    </a>
                    <ul class="{{ $employeeMenuOpen ? 'mm-show' : '' }}">
                        @if ($canSeeMenu('employee.create'))
                            <li><a href="{{ route('admin-employee-create') }}"><i class="material-icons-outlined">arrow_right</i>Add Employee</a></li>
                        @endif
                        @if ($canSeeMenu('employee.index'))
                            <li><a href="{{ route('admin-employee-index') }}"><i class="material-icons-outlined">arrow_right</i>All Employees</a></li>
                        @endif
                        @if ($canSeeMenu('employee.onboarded'))
                            <li class="{{ request()->routeIs('admin-employee-index') && request()->query('tab') === 'onboarded' ? 'mm-active' : '' }}">
                                <a href="{{ route('admin-employee-index', ['tab' => 'onboarded']) }}"><i class="material-icons-outlined">arrow_right</i>Newly Onboarded</a>
                            </li>
                        @endif
                        @if ($canSeeMenu('employee.night_shift_users'))
                            <li class="{{ $nightShiftUsersActive ? 'mm-active' : '' }}">
                                <a href="{{ route('admin-night-shift-users') }}"><i class="material-icons-outlined">arrow_right</i>Nightshift Users</a>
                            </li>
                        @endif
                    </ul>
                </li>
            @endif

            @if ($showMessengerMenu)
                <li class="{{ $messengerMenuOpen ? 'mm-active' : '' }}">
                    <a href="{{ route('admin-messenger') }}">
                        <div class="parent-icon"><i class="material-icons-outlined">chat</i></div>
                        <div class="menu-title menu-title-with-alert">
                            <span>Messenger</span>
                            @if (($adminUnreadMessagesCount ?? 0) > 0)
                                <span class="badge bg-danger">{{ $adminUnreadMessagesCount }}</span>
                            @endif
                        </div>
                    </a>
                </li>
            @endif

            @if ($canManageHiring || $canManageJoining)
                <li class="{{ $recruitmentMenuOpen ? 'mm-active' : '' }}">
                    <a href="javascript:;" class="has-arrow" aria-expanded="{{ $recruitmentMenuOpen ? 'true' : 'false' }}">
                        <div class="parent-icon"><i class="material-icons-outlined">group_add</i></div>
                        <div class="menu-title">Recruitment</div>
                    </a>
                    <ul class="{{ $recruitmentMenuOpen ? 'mm-show' : '' }}">
                        @if ($canManageHiring)
                            <li class="{{ $hiringMenuActive ? 'mm-active' : '' }}">
                                <a href="{{ route('admin-hiring-index') }}"><i class="material-icons-outlined">arrow_right</i>Hiring</a>
                            </li>
                        @endif
                        @if ($canManageJoining)
                            <li class="{{ $joiningMenuActive ? 'mm-active' : '' }}">
                                <a href="{{ route('admin-joining-index') }}"><i class="material-icons-outlined">arrow_right</i>Joining</a>
                            </li>
                        @endif
                    </ul>
                </li>
            @endif

            @if ($showAttendanceMenu)
                <li class="{{ $attendanceMenuOpen ? 'mm-active' : '' }}">
                    <a href="javascript:;" class="has-arrow" aria-expanded="{{ $attendanceMenuOpen ? 'true' : 'false' }}">
                        <div class="parent-icon"><i class="material-icons-outlined">fact_check</i></div>
                        <div class="menu-title menu-title-with-alert">
                            <span>Attendance</span>
                            @if ($showAttendanceAlert)
                                <span class="sidebar-notification-dot" aria-hidden="true"></span>
                            @endif
                        </div>
                    </a>
                    <ul class="{{ $attendanceMenuOpen ? 'mm-show' : '' }}">
                        @if ($canSeeMenu('attendance.daily'))
                            <li class="{{ $attendanceBranchMenuOpen ? 'mm-active' : '' }}">
                                <a href="javascript:;" class="has-arrow" aria-expanded="{{ $attendanceBranchMenuOpen ? 'true' : 'false' }}">
                                    <i class="material-icons-outlined">arrow_right</i>
                                    <span class="menu-title">Branch</span>
                                </a>
                                <ul class="{{ $attendanceBranchMenuOpen ? 'mm-show' : '' }}">
                                    <li><a href="{{ route('admin-attendance-daily') }}"><i class="material-icons-outlined">arrow_right</i>Daily Attendance</a></li>
                                </ul>
                            </li>
                        @endif

                        @if ($canSeeMenu('attendance.night_shift'))
                            <li class="{{ $attendanceNightShiftMenuOpen ? 'mm-active' : '' }}">
                                <a href="{{ route('admin-attendance-night-shift') }}">
                                    <i class="material-icons-outlined">arrow_right</i>
                                    <span class="menu-title">Night Shift</span>
                                </a>
                            </li>
                        @endif

                        @if ($canSeeAnyMenu($attendanceReviewKeys))
                            <li class="{{ $attendanceReviewMenuOpen ? 'mm-active' : '' }}">
                                <a href="javascript:;" class="has-arrow" aria-expanded="{{ $attendanceReviewMenuOpen ? 'true' : 'false' }}">
                                    <i class="material-icons-outlined">arrow_right</i>
                                    <span class="menu-title menu-title-with-alert">
                                        <span>Review</span>
                                        @if ($showAttendanceAlert)
                                            <span class="sidebar-notification-dot" aria-hidden="true"></span>
                                        @endif
                                    </span>
                                </a>
                                <ul class="{{ $attendanceReviewMenuOpen ? 'mm-show' : '' }}">
                                    @if ($canSeeMenu('attendance.out_of_office'))
                                        <li class="{{ request()->routeIs('admin-attendance-out-of-office', 'admin-attendance-out-of-office-*') ? 'mm-active' : '' }}">
                                            <a href="{{ route('admin-attendance-out-of-office') }}">
                                                <i class="material-icons-outlined">arrow_right</i>
                                                <span class="menu-title">Out of Office</span>
                                            </a>
                                        </li>
                                    @endif
                                    @if ($canSeeMenu('attendance.review'))
                                        <li>
                                            <a href="{{ route('admin-attendance-review') }}">
                                                <i class="material-icons-outlined">arrow_right</i>
                                                <span class="menu-title menu-title-with-alert">
                                                    <span>Half Day / Single Punch</span>
                                                    @if ($hasAttendanceReviewAlerts ?? false)
                                                        <span class="sidebar-notification-dot" aria-hidden="true"></span>
                                                    @endif
                                                </span>
                                            </a>
                                        </li>
                                    @endif
                                    @if ($canSeeMenu('attendance.fraud_reports'))
                                        <li class="{{ request()->routeIs('admin-attendance-fraud-reports') ? 'mm-active' : '' }}">
                                            <a href="{{ route('admin-attendance-fraud-reports') }}">
                                                <i class="material-icons-outlined">arrow_right</i>
                                                <span class="menu-title">Fraud Reports</span>
                                            </a>
                                        </li>
                                    @endif
                                    @if ($canSeeMenu('attendance.blocked'))
                                        <li>
                                            <a href="{{ route('admin-attendance-blocked') }}">
                                                <i class="material-icons-outlined">arrow_right</i>
                                                <span class="menu-title menu-title-with-alert">
                                                    <span>Blocked Employees</span>
                                                    @if ($hasBlockedEmployees ?? false)
                                                        <span class="sidebar-notification-dot" aria-hidden="true"></span>
                                                    @endif
                                                </span>
                                            </a>
                                        </li>
                                    @endif
                                </ul>
                            </li>
                        @endif
                    </ul>
                </li>
            @endif

            @if ($showSalaryMenu)
                <li class="{{ $salaryMenuOpen ? 'mm-active' : '' }}">
                    <a href="javascript:;" class="has-arrow" aria-expanded="{{ $salaryMenuOpen ? 'true' : 'false' }}">
                        <div class="parent-icon"><i class="material-icons-outlined">account_balance_wallet</i></div>
                        <div class="menu-title">Salary</div>
                    </a>
                    <ul class="{{ $salaryMenuOpen ? 'mm-show' : '' }}">
                        @if ($canSeeMenu('salary.advance'))
                            <li class="{{ request()->routeIs('admin-salary-advance', 'admin-salary-advance-update', 'admin-salary-advance-import', 'admin-salary-advance-history', 'admin-salary-advance-history-merge', 'admin-salary-advance-request-*') ? 'mm-active' : '' }}">
                                <a href="{{ route('admin-salary-advance') }}"><i class="material-icons-outlined">arrow_right</i>Add Advance Details</a>
                            </li>
                        @endif
                        @if ($canSeeMenu('salary.bank_requests'))
                            <li class="{{ request()->routeIs('admin-bank-detail-requests*') ? 'mm-active' : '' }}">
                                <a href="{{ route('admin-bank-detail-requests') }}"><i class="material-icons-outlined">arrow_right</i>Bank Detail Requests</a>
                            </li>
                        @endif
                        @if ($canSeeMenu('salary.account_details'))
                            <li class="{{ request()->routeIs('admin-salary-account-details*') ? 'mm-active' : '' }}">
                                <a href="{{ route('admin-salary-account-details') }}"><i class="material-icons-outlined">arrow_right</i>Account Details</a>
                            </li>
                        @endif
                        @if ($canSeeMenu('salary.reports'))
                            <li class="{{ $salaryReportsStandaloneActive ? 'mm-active' : '' }}">
                                <a href="{{ route('admin-salary-reports') }}"><i class="material-icons-outlined">arrow_right</i>Salary Reports</a>
                            </li>
                        @endif
                    </ul>
                </li>
            @endif

            @if ($showOutsourceMenu)
                <li class="{{ $outsourceMenuOpen ? 'mm-active' : '' }}">
                    <a href="javascript:;" class="has-arrow" aria-expanded="{{ $outsourceMenuOpen ? 'true' : 'false' }}">
                        <div class="parent-icon"><i class="material-icons-outlined">apartment</i></div>
                        <div class="menu-title">Outsource</div>
                    </a>
                    <ul class="{{ $outsourceMenuOpen ? 'mm-show' : '' }}">
                        @if ($canSeeMenu('outsource.employee_create'))
                            <li class="{{ $outsourceEmployeeCreateMenuActive ? 'mm-active' : '' }}">
                                <a href="{{ route('admin-employee-create', ['is_outsourced' => 1]) }}"><i class="material-icons-outlined">arrow_right</i>Add Outsource Employee</a>
                            </li>
                        @endif
                        @if ($canSeeMenu('outsource.employee_index'))
                            <li class="{{ $outsourceEmployeeListMenuActive ? 'mm-active' : '' }}">
                                <a href="{{ route('admin-employee-index', ['tab' => 'outsource']) }}"><i class="material-icons-outlined">arrow_right</i>Outsource Employees</a>
                            </li>
                        @endif
                        @if ($canSeeMenu('outsource.location_create'))
                            <li class="{{ request()->routeIs('admin-outsource-create', 'admin-outsource-store') ? 'mm-active' : '' }}">
                                <a href="{{ route('admin-outsource-create') }}"><i class="material-icons-outlined">arrow_right</i>Add Outsource Location</a>
                            </li>
                        @endif
                        @if ($canSeeMenu('outsource.location_index'))
                            <li class="{{ $outsourceLocationMenuActive ? 'mm-active' : '' }}">
                                <a href="{{ route('admin-outsource-index') }}"><i class="material-icons-outlined">arrow_right</i>All Outsource Locations</a>
                            </li>
                        @endif
                        @if ($canSeeMenu('outsource.attendance'))
                            <li class="{{ $outsourceAttendanceMenuActive ? 'mm-active' : '' }}">
                                <a href="{{ route('admin-attendance-outsource') }}"><i class="material-icons-outlined">arrow_right</i>Outsource Attendance</a>
                            </li>
                        @endif
                        @if ($canSeeMenu('outsource.leave_review'))
                            <li class="{{ $outsourceLeaveReviewMenuActive ? 'mm-active' : '' }}">
                                <a href="{{ route('admin-outsource-leaves-review') }}">
                                    <i class="material-icons-outlined">arrow_right</i>
                                    <span class="menu-title menu-title-with-alert">
                                        <span>Outsource Leave Review</span>
                                        @if ($hasPendingOutsourceLeaveRequests ?? false)
                                            <span class="sidebar-notification-dot" aria-hidden="true"></span>
                                        @endif
                                    </span>
                                </a>
                            </li>
                        @endif
                        @if ($canSeeMenu('outsource.leave_reports'))
                            <li class="{{ $outsourceLeaveReportsMenuActive ? 'mm-active' : '' }}">
                                <a href="{{ route('admin-outsource-leaves-reports') }}"><i class="material-icons-outlined">arrow_right</i>Outsource Leave Reports</a>
                            </li>
                        @endif
                    </ul>
                </li>
            @endif

            @if ($showLeaveMenu)
                <li class="{{ $leaveMenuOpen ? 'mm-active' : '' }}">
                    <a href="javascript:;" class="has-arrow" aria-expanded="{{ $leaveMenuOpen ? 'true' : 'false' }}">
                        <div class="parent-icon"><i class="material-icons-outlined">event_note</i></div>
                        <div class="menu-title menu-title-with-alert">
                            <span>Leaves</span>
                            @if ($hasPendingLeaveRequests ?? false)
                                <span class="sidebar-notification-dot" aria-hidden="true"></span>
                            @endif
                        </div>
                    </a>
                    <ul class="{{ $leaveMenuOpen ? 'mm-show' : '' }}">
                        @if ($canSeeMenu('leaves.review'))
                            <li class="{{ request()->routeIs('admin-leaves-review') ? 'mm-active' : '' }}">
                                <a href="{{ route('admin-leaves-review') }}">
                                    <i class="material-icons-outlined">arrow_right</i>
                                    <span class="menu-title menu-title-with-alert">
                                        <span>Review Leave</span>
                                        @if ($hasPendingLeaveRequests ?? false)
                                            <span class="sidebar-notification-dot" aria-hidden="true"></span>
                                        @endif
                                    </span>
                                </a>
                            </li>
                        @endif
                        @if ($canSeeMenu('leaves.reports'))
                            <li class="{{ request()->routeIs('admin-leaves-reports') ? 'mm-active' : '' }}">
                                <a href="{{ route('admin-leaves-reports') }}"><i class="material-icons-outlined">arrow_right</i>Leave Reports</a>
                            </li>
                        @endif
                    </ul>
                </li>
            @endif

            @if ($showWorkVisitMenu)
                <li class="{{ $workVisitMenuOpen ? 'mm-active' : '' }}">
                    <a href="javascript:;" class="has-arrow" aria-expanded="{{ $workVisitMenuOpen ? 'true' : 'false' }}">
                        <div class="parent-icon"><i class="material-icons-outlined">pin_drop</i></div>
                        <div class="menu-title menu-title-with-alert">
                            <span>Work Visit</span>
                            @if ($hasPendingWorkVisitRequests ?? false)
                                <span class="sidebar-notification-dot" aria-hidden="true"></span>
                            @endif
                        </div>
                    </a>
                    <ul class="{{ $workVisitMenuOpen ? 'mm-show' : '' }}">
                        @if ($canSeeMenu('work_visits.review'))
                            <li class="{{ request()->routeIs('admin-work-visits-review') ? 'mm-active' : '' }}">
                                <a href="{{ route('admin-work-visits-review') }}">
                                    <i class="material-icons-outlined">arrow_right</i>
                                    <span class="menu-title menu-title-with-alert">
                                        <span>Review Work Visit</span>
                                        @if ($hasPendingWorkVisitRequests ?? false)
                                            <span class="sidebar-notification-dot" aria-hidden="true"></span>
                                        @endif
                                    </span>
                                </a>
                            </li>
                        @endif
                        @if ($canSeeMenu('work_visits.reports'))
                            <li class="{{ request()->routeIs('admin-work-visits-reports') ? 'mm-active' : '' }}">
                                <a href="{{ route('admin-work-visits-reports') }}"><i class="material-icons-outlined">arrow_right</i>Work Visit Reports</a>
                            </li>
                        @endif
                    </ul>
                </li>
            @endif

            @if ($showNotificationsMenu)
                <li class="{{ $notificationsMenuOpen ? 'mm-active' : '' }}">
                    <a href="{{ route('admin-notifications') }}">
                        <div class="parent-icon"><i class="material-icons-outlined">notifications</i></div>
                        <div class="menu-title">Notifications</div>
                    </a>
                </li>
            @endif

            @if ($showTeTrackerMenu)
                <li class="{{ $teTrackerMenuOpen ? 'mm-active' : '' }}">
                    <a href="{{ route('admin-attendance-te-tracker') }}">
                        <div class="parent-icon"><i class="material-icons-outlined">route</i></div>
                        <div class="menu-title">TE Tracker</div>
                    </a>
                </li>
            @endif

            @if ($showReportsMenu)
                <li class="{{ $reportsMenuOpen ? 'mm-active' : '' }}">
                    <a href="javascript:;" class="has-arrow" aria-expanded="{{ $reportsMenuOpen ? 'true' : 'false' }}">
                        <div class="parent-icon"><i class="material-icons-outlined">assessment</i></div>
                        <div class="menu-title">Reports</div>
                    </a>
                    <ul class="{{ $reportsMenuOpen ? 'mm-show' : '' }}">
                        @if ($canSeeMenu('reports.attendance'))
                            <li class="{{ $attendanceReportsActive ? 'mm-active' : '' }}">
                                <a href="{{ route('admin-attendance-reports', ['menu' => 'reports']) }}"><i class="material-icons-outlined">arrow_right</i>Attendance Reports</a>
                            </li>
                        @endif
                        @if (! $isHrLimitedSidebarUser && $canSeeMenu('reports.salary'))
                            <li class="{{ $salaryReportsActive ? 'mm-active' : '' }}">
                                <a href="{{ route('admin-salary-reports', ['menu' => 'reports']) }}"><i class="material-icons-outlined">arrow_right</i>Salary Reports</a>
                            </li>
                        @endif
                        @if (! $isHrLimitedSidebarUser && $canSeeMenu('reports.advance'))
                            <li class="{{ $advanceReportsActive ? 'mm-active' : '' }}">
                                <a href="{{ route('admin-advance-reports', ['menu' => 'reports']) }}"><i class="material-icons-outlined">arrow_right</i>Advance Reports</a>
                            </li>
                        @endif
                    </ul>
                </li>
            @endif

            <li class="{{ request()->routeIs('admin-all-permission', 'admin-add-permission', 'edit.permission') ? 'mm-active' : '' }}">
                <a href="javascript:;" class="has-arrow" aria-expanded="{{ request()->routeIs('admin-all-permission', 'admin-add-permission', 'edit.permission') ? 'true' : 'false' }}">
                    <div class="parent-icon"><i class="material-icons-outlined">assessment</i></div>
                    <div class="menu-title">Roles And Permission</div>
                </a>
                <ul class="{{ request()->routeIs('admin-all-permission', 'admin-add-permission', 'edit.permission') ? 'mm-show' : '' }}">
                    <li class="{{ request()->routeIs('admin-all-permission') ? 'mm-active' : '' }}">
                        <a href="{{ route('admin-all-permission') }}"><i class="material-icons-outlined">arrow_right</i>All Permission</a>
                    </li>
                    <li class="{{ request()->routeIs('admin-add-permission') ? 'mm-active' : '' }}">
                        <a href="{{ route('admin-add-permission') }}"><i class="material-icons-outlined">arrow_right</i>Add Permission</a>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</aside>
