<?php

namespace App\Support;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminMenu
{
    public static function groups(): array
    {
        return [
            [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'items' => [
                    ['key' => 'dashboard.home', 'label' => 'Dashboard'],
                ],
            ],
            [
                'key' => 'messenger',
                'label' => 'Messenger',
                'items' => [
                    ['key' => 'messenger.index', 'label' => 'Messenger'],
                ],
            ],
            [
                'key' => 'branch',
                'label' => 'Branch',
                'items' => [
                    ['key' => 'branch.create', 'label' => 'Add Branch'],
                    ['key' => 'branch.index', 'label' => 'All Branches'],
                    ['key' => 'branch.logins', 'label' => 'Branch Logins'],
                    ['key' => 'branch.opening', 'label' => 'Opening & Keys'],
                    ['key' => 'branch.opening_timings', 'label' => 'Opening Timings'],
                ],
            ],
            [
                'key' => 'admins',
                'label' => 'Admins',
                'items' => [
                    ['key' => 'admins.create', 'label' => 'Add Admin'],
                    ['key' => 'admins.index', 'label' => 'All Admins'],
                ],
            ],
            [
                'key' => 'employee',
                'label' => 'Employee',
                'items' => [
                    ['key' => 'employee.create', 'label' => 'Add Employee'],
                    ['key' => 'employee.index', 'label' => 'All Employees'],
                    ['key' => 'employee.onboarded', 'label' => 'Newly Onboarded'],
                    ['key' => 'employee.night_shift_users', 'label' => 'Nightshift Users'],
                ],
            ],
            [
                'key' => 'recruitment',
                'label' => 'Recruitment',
                'items' => [
                    ['key' => 'recruitment.hiring', 'label' => 'Hiring'],
                    ['key' => 'recruitment.joining', 'label' => 'Joining'],
                ],
            ],
            [
                'key' => 'attendance',
                'label' => 'Attendance',
                'items' => [
                    ['key' => 'attendance.daily', 'label' => 'Daily Attendance'],
                    ['key' => 'attendance.night_shift', 'label' => 'Night Shift'],
                    ['key' => 'attendance.out_of_office', 'label' => 'Out of Office'],
                    ['key' => 'attendance.review', 'label' => 'Half Day / Single Punch'],
                    ['key' => 'attendance.fraud_reports', 'label' => 'Fraud Reports'],
                    ['key' => 'attendance.blocked', 'label' => 'Blocked Employees'],
                    ['key' => 'attendance.reports', 'label' => 'Attendance Reports'],
                    ['key' => 'attendance.te_tracker', 'label' => 'TE Tracker'],
                ],
            ],
            [
                'key' => 'salary',
                'label' => 'Salary',
                'items' => [
                    ['key' => 'salary.advance', 'label' => 'Add Advance Details'],
                    ['key' => 'salary.bank_requests', 'label' => 'Bank Detail Requests'],
                    ['key' => 'salary.account_details', 'label' => 'Account Details'],
                    ['key' => 'salary.reports', 'label' => 'Salary Reports'],
                    ['key' => 'salary.advance_reports', 'label' => 'Advance Reports'],
                ],
            ],
            [
                'key' => 'outsource',
                'label' => 'Outsource',
                'items' => [
                    ['key' => 'outsource.employee_create', 'label' => 'Add Outsource Employee'],
                    ['key' => 'outsource.employee_index', 'label' => 'Outsource Employees'],
                    ['key' => 'outsource.location_create', 'label' => 'Add Outsource Location'],
                    ['key' => 'outsource.location_index', 'label' => 'All Outsource Locations'],
                    ['key' => 'outsource.attendance', 'label' => 'Outsource Attendance'],
                    ['key' => 'outsource.leave_review', 'label' => 'Outsource Leave Review'],
                    ['key' => 'outsource.leave_reports', 'label' => 'Outsource Leave Reports'],
                ],
            ],
            [
                'key' => 'leaves',
                'label' => 'Leaves',
                'items' => [
                    ['key' => 'leaves.review', 'label' => 'Review Leave'],
                    ['key' => 'leaves.reports', 'label' => 'Leave Reports'],
                ],
            ],
            [
                'key' => 'work_visits',
                'label' => 'Work Visit',
                'items' => [
                    ['key' => 'work_visits.review', 'label' => 'Review Work Visit'],
                    ['key' => 'work_visits.reports', 'label' => 'Work Visit Reports'],
                ],
            ],
            [
                'key' => 'notifications',
                'label' => 'Notifications',
                'items' => [
                    ['key' => 'notifications.index', 'label' => 'Notifications'],
                ],
            ],
            [
                'key' => 'reports',
                'label' => 'Reports',
                'items' => [
                    ['key' => 'reports.attendance', 'label' => 'Attendance Reports'],
                    ['key' => 'reports.salary', 'label' => 'Salary Reports'],
                    ['key' => 'reports.advance', 'label' => 'Advance Reports'],
                ],
            ],
        ];
    }

    public static function allKeys(): array
    {
        return collect(self::groups())
            ->flatMap(fn (array $group): array => collect($group['items'] ?? [])->pluck('key')->all())
            ->values()
            ->all();
    }

    public static function normalizeKeys(mixed $keys): array
    {
        $allowed = array_flip(self::allKeys());

        return collect(is_array($keys) ? $keys : [])
            ->map(fn ($key): string => trim((string) $key))
            ->filter(fn (string $key): bool => isset($allowed[$key]))
            ->unique()
            ->values()
            ->all();
    }

    public static function selectedKeysFor(Admin $admin): array
    {
        if (self::isHrAdmin($admin)) {
            return self::allKeys();
        }

        if (is_array($admin->sidebar_menu_permissions)) {
            return self::normalizeKeys($admin->sidebar_menu_permissions);
        }

        return self::defaultKeysForRole($admin->role);
    }

    public static function defaultKeysForRole(?string $role): array
    {
        $role = strtolower(trim((string) $role));

        return match ($role) {
            Admin::ROLE_HIRING => ['dashboard.home', 'messenger.index', 'recruitment.hiring'],
            Admin::ROLE_JOINING => ['dashboard.home', 'messenger.index', 'recruitment.joining'],
            Admin::ROLE_OPENING => ['dashboard.home', 'messenger.index', 'branch.opening', 'branch.opening_timings'],
            Admin::ROLE_ACCOUNTS => [
                'messenger.index',
                'salary.advance',
                'salary.bank_requests',
                'salary.account_details',
                'salary.reports',
                'outsource.employee_create',
                'outsource.employee_index',
                'outsource.location_create',
                'outsource.location_index',
                'outsource.attendance',
                'outsource.leave_review',
                'outsource.leave_reports',
            ],
            Admin::ROLE_SUBHR => [
                'employee.create',
                'employee.index',
                'employee.onboarded',
                'recruitment.hiring',
                'recruitment.joining',
            ],
            Admin::ROLE_ZONAL => [
                'dashboard.home',
                'attendance.daily',
                'attendance.out_of_office',
                'attendance.review',
                'attendance.fraud_reports',
                'attendance.blocked',
                'attendance.reports',
                'reports.attendance',
            ],
            default => [],
        };
    }

    public static function adminCanSee(?Admin $admin, string $key): bool
    {
        if (! $admin instanceof Admin) {
            return false;
        }

        return in_array($key, self::selectedKeysFor($admin), true);
    }

    public static function adminCanSeeAny(?Admin $admin, array $keys): bool
    {
        foreach ($keys as $key) {
            if (self::adminCanSee($admin, $key)) {
                return true;
            }
        }

        return false;
    }

    public static function adminCanAccessRequest(Admin $admin, Request $request): bool
    {
        $keys = self::keysForRequest($request);

        return $keys !== [] && self::adminCanSeeAny($admin, $keys);
    }

    public static function keysForRequest(Request $request): array
    {
        $routeName = $request->route()?->getName();

        if (! is_string($routeName) || $routeName === '') {
            return [];
        }

        return self::keysForRoute($routeName, $request);
    }

    private static function isHrAdmin(Admin $admin): bool
    {
        $role = strtolower(trim((string) $admin->role));

        return $role === '' || $role === Admin::ROLE_HR_ADMIN;
    }

    private static function keysForRoute(string $routeName, Request $request): array
    {
        $map = [
            'admin-dashboard' => ['dashboard.home'],
            'admin-messenger*' => ['messenger.index'],
            'admin-branch-create' => ['branch.create'],
            'admin-branch-store' => ['branch.create'],
            'admin-branch-index' => ['branch.index'],
            'admin-branch-edit' => ['branch.index'],
            'admin-branch-update' => ['branch.index'],
            'admin-branch-inactive' => ['branch.index'],
            'admin-branch-active' => ['branch.index'],
            'admin-branch-delete' => ['branch.index'],
            'admin-branch-logins' => ['branch.logins'],
            'admin-branch-opening-index' => ['branch.opening'],
            'admin-branch-opening-update' => ['branch.opening'],
            'admin-branch-opening-timings' => ['branch.opening_timings'],
            'admin-create' => ['admins.create'],
            'admin-store' => ['admins.create'],
            'admin-index' => ['admins.index'],
            'admin-edit' => ['admins.index'],
            'admin-update' => ['admins.index'],
            'admin-employee-create' => $request->input('is_outsourced') == '1'
                ? ['outsource.employee_create', 'employee.create']
                : ['employee.create'],
            'admin-employee-store' => ['employee.create', 'outsource.employee_create'],
            'admin-employee-import' => ['employee.create'],
            'admin-employee-index' => $request->input('tab') === 'onboarded'
                ? ['employee.onboarded']
                : ($request->input('tab') === 'outsource' ? ['outsource.employee_index'] : ['employee.index']),
            'admin-employee-edit' => ['employee.index', 'outsource.employee_index'],
            'admin-employee-update' => ['employee.index', 'outsource.employee_index'],
            'admin-employee-onboarded-*' => ['employee.onboarded'],
            'admin-employee-inactive' => ['employee.index', 'outsource.employee_index'],
            'admin-employee-active' => ['employee.index', 'outsource.employee_index'],
            'admin-employee-delete' => ['employee.index', 'outsource.employee_index'],
            'admin-night-shift-users*' => ['employee.night_shift_users'],
            'admin-hiring-*' => ['recruitment.hiring'],
            'admin-joining-*' => ['recruitment.joining'],
            'admin-attendance-daily' => ['attendance.daily'],
            'admin-attendance-calendar*' => ['attendance.daily'],
            'admin-attendance-night-shift' => ['attendance.night_shift'],
            'admin-attendance-out-of-office*' => ['attendance.out_of_office'],
            'admin-attendance-review*' => ['attendance.review'],
            'admin-attendance-fraud-reports' => ['attendance.fraud_reports'],
            'admin-attendance-blocked*' => ['attendance.blocked'],
            'admin-attendance-reports' => ['attendance.reports', 'reports.attendance'],
            'admin-attendance-outsource' => ['outsource.attendance'],
            'admin-attendance-te-tracker' => ['attendance.te_tracker'],
            'admin-salary-advance' => ['salary.advance'],
            'admin-salary-advance-update' => ['salary.advance'],
            'admin-salary-advance-import' => ['salary.advance'],
            'admin-salary-advance-history' => ['salary.advance'],
            'admin-salary-advance-history-merge' => ['salary.advance'],
            'admin-salary-advance-request-*' => ['salary.advance'],
            'admin-bank-detail-requests*' => ['salary.bank_requests'],
            'admin-salary-account-details*' => ['salary.account_details'],
            'admin-salary-reports*' => ['salary.reports', 'reports.salary'],
            'admin-advance-reports' => ['salary.advance_reports', 'reports.advance'],
            'admin-outsource-create' => ['outsource.location_create'],
            'admin-outsource-store' => ['outsource.location_create'],
            'admin-outsource-index' => ['outsource.location_index'],
            'admin-outsource-edit' => ['outsource.location_index'],
            'admin-outsource-update' => ['outsource.location_index'],
            'admin-outsource-inactive' => ['outsource.location_index'],
            'admin-outsource-active' => ['outsource.location_index'],
            'admin-outsource-delete' => ['outsource.location_index'],
            'admin-outsource-leaves-review' => ['outsource.leave_review'],
            'admin-outsource-leaves-reports' => ['outsource.leave_reports'],
            'admin-leaves-review*' => ['leaves.review'],
            'admin-leaves-reports' => ['leaves.reports'],
            'admin-work-visits-review*' => ['work_visits.review'],
            'admin-work-visits-reports' => ['work_visits.reports'],
            'admin-notifications*' => ['notifications.index'],
        ];

        foreach ($map as $pattern => $keys) {
            if (Str::is($pattern, $routeName)) {
                return $keys;
            }
        }

        return [];
    }
}
