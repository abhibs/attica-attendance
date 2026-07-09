<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminMessengerController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AttendanceManagementController;
use App\Http\Controllers\Admin\BankDetailRequestController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\BranchOpeningController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\LeaveController;
use App\Http\Controllers\Admin\NightShiftUserController;
use App\Http\Controllers\Admin\OutsourceLocationController;
use App\Http\Controllers\Admin\RecruitmentController;
use App\Http\Controllers\Admin\SalaryController;
use App\Http\Controllers\Admin\TeTrackerController;
use App\Http\Controllers\Admin\VmAttendanceController;
use App\Http\Controllers\Admin\WorkVisitController;
use App\Http\Controllers\Admin\RoleController;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/test', function () {
    return "Abhiram";
});


Route::group(
    ['prefix' => 'admin'],
    function () {
        Route::get('/login', [AdminController::class, 'login'])->name('admin-login');
        Route::post('/login', [AdminController::class, 'loginPost'])->name('admin-login-post');
        Route::get('/vm-login', [VmAttendanceController::class, 'login'])->name('admin-vm-login');
        Route::post('/vm-login', [VmAttendanceController::class, 'loginPost'])->name('admin-vm-login-post');
        Route::get('/vm/attendance', [VmAttendanceController::class, 'attendance'])->name('admin-vm-attendance');
        Route::post('/vm/logout', [VmAttendanceController::class, 'logout'])->name('admin-vm-logout');
        Route::group(
            ['middleware' => 'auth:admin'],
            function () {
                Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('admin-dashboard');
                Route::get('/logout', [Admincontroller::class, 'adminLogout'])->name('admin-logout');
                Route::get('/profile', [Admincontroller::class, 'adminProfile'])->name('admin-profile');
                Route::post('/profile/update/details', [AdminController::class, 'adminProfileDetailsUpdate'])->name('admin-profile-details-update');
                Route::post('/profile/update/theme', [AdminController::class, 'adminThemeUpdate'])->name('admin-profile-theme-update');
                Route::get('/change/password', [Admincontroller::class, 'changePassword'])->name('admin-change-password');
                Route::post('/update/password', [AdminController::class, 'updatePassword'])->name('admin-password-update');
                Route::middleware('admin.role:hr_admin,hiring,joining,opening,accounts')->group(function () {
                    Route::get('/messenger', [AdminMessengerController::class, 'index'])->name('admin-messenger');
                    Route::post('/messenger', [AdminMessengerController::class, 'store'])->name('admin-messenger-store');
                });

                Route::middleware('admin.role:hr_admin,accounts')->group(function () {
                    Route::get('/salary/advance', [SalaryController::class, 'advanceDetails'])->name('admin-salary-advance');
                    Route::post('/salary/advance', [SalaryController::class, 'updateAdvanceDetails'])->name('admin-salary-advance-update');
                    Route::post('/salary/advance/import', [SalaryController::class, 'importAdvanceDetails'])->name('admin-salary-advance-import');
                    Route::get('/salary/advance/history/{empId}', [SalaryController::class, 'advanceHistory'])->name('admin-salary-advance-history');
                    Route::post('/salary/advance/history/{empId}/merge', [SalaryController::class, 'mergeAdvanceHistory'])->name('admin-salary-advance-history-merge');
                    Route::post('/salary/advance-requests/{advanceRequest}/verify', [SalaryController::class, 'verifyAdvanceRequest'])->name('admin-salary-advance-request-verify');
                    Route::post('/salary/advance-requests/{advanceRequest}/reject', [SalaryController::class, 'rejectAdvanceRequest'])->name('admin-salary-advance-request-reject');
                    Route::get('/salary/bank-detail-requests', [BankDetailRequestController::class, 'index'])->name('admin-bank-detail-requests');
                    Route::post('/salary/bank-detail-requests/bulk', [BankDetailRequestController::class, 'bulk'])->name('admin-bank-detail-requests-bulk');
                    Route::post('/salary/bank-detail-requests/{requestRecord}/approve', [BankDetailRequestController::class, 'approve'])->name('admin-bank-detail-requests-approve');
                    Route::post('/salary/bank-detail-requests/{requestRecord}/reject', [BankDetailRequestController::class, 'reject'])->name('admin-bank-detail-requests-reject');
                    Route::post('/salary/bank-detail-requests/{requestRecord}/verify', [BankDetailRequestController::class, 'verify'])->name('admin-bank-detail-requests-verify');
                    Route::get('/salary/reports', [AttendanceManagementController::class, 'reports'])->name('admin-salary-reports');
                    Route::get('/salary/reports/export', [AttendanceManagementController::class, 'salaryReportExport'])->name('admin-salary-reports-export');
                    Route::post('/salary/reports/hold', [AttendanceManagementController::class, 'updateSalaryHold'])->name('admin-salary-reports-hold');
                    Route::get('/salary/account-details', [SalaryController::class, 'accountDetails'])->name('admin-salary-account-details');
                    Route::get('/salary/account-details/export', [SalaryController::class, 'accountDetailsExport'])->name('admin-salary-account-details-export');
                });

                Route::middleware('admin.role:hr_admin')->group(function () {
                    Route::get('/branch/create', [BranchController::class, 'create'])->name('admin-branch-create');
                    Route::post('/branch/store', [BranchController::class, 'store'])->name('admin-branch-store');
                    Route::get('/branch/index', [BranchController::class, 'index'])->name('admin-branch-index');
                    Route::get('/branch/logins', [BranchController::class, 'logins'])->name('admin-branch-logins');
                    Route::get('/branch/edit/{id}', [BranchController::class, 'edit'])->name('admin-branch-edit');
                    Route::post('/branch/update', [BranchController::class, 'update'])->name('admin-branch-update');
                    Route::get('/branch/inactive/{id}', [BranchController::class, 'inactive'])->name('admin-branch-inactive');
                    Route::get('/branch/active/{id}', [BranchController::class, 'active'])->name('admin-branch-active');
                    Route::get('/branch/delete/{id}', [BranchController::class, 'delete'])->name('admin-branch-delete');
                    Route::get('/outsource/create', [OutsourceLocationController::class, 'create'])->name('admin-outsource-create');
                    Route::post('/outsource/store', [OutsourceLocationController::class, 'store'])->name('admin-outsource-store');
                    Route::get('/outsource/index', [OutsourceLocationController::class, 'index'])->name('admin-outsource-index');
                    Route::get('/outsource/edit/{id}', [OutsourceLocationController::class, 'edit'])->name('admin-outsource-edit');
                    Route::post('/outsource/update', [OutsourceLocationController::class, 'update'])->name('admin-outsource-update');
                    Route::get('/outsource/inactive/{id}', [OutsourceLocationController::class, 'inactive'])->name('admin-outsource-inactive');
                    Route::get('/outsource/active/{id}', [OutsourceLocationController::class, 'active'])->name('admin-outsource-active');
                    Route::get('/outsource/delete/{id}', [OutsourceLocationController::class, 'delete'])->name('admin-outsource-delete');
                });

                Route::middleware('admin.role:hr_admin,subhr')->group(function () {
                    Route::get('/employee/create', [EmployeeController::class, 'create'])->name('admin-employee-create');
                    Route::post('/employee/store', [EmployeeController::class, 'store'])->name('admin-employee-store');
                    Route::post('/employee/import', [EmployeeController::class, 'import'])->name('admin-employee-import');
                    Route::get('/employee/index', [EmployeeController::class, 'index'])->name('admin-employee-index');
                    Route::get('/employee/edit/{id}', [EmployeeController::class, 'edit'])->name('admin-employee-edit');
                    Route::post('/employee/update', [EmployeeController::class, 'update'])->name('admin-employee-update');
                    Route::post('/employee/onboarded/{candidateId}/join', [RecruitmentController::class, 'markJoined'])
                        ->name('admin-employee-onboarded-join');
                    Route::post('/employee/onboarded/{candidateId}/delete', [RecruitmentController::class, 'deleteOnboarded'])
                        ->name('admin-employee-onboarded-delete');
                    Route::match(['get', 'post'], '/employee/inactive/{id}', [EmployeeController::class, 'inactive'])->name('admin-employee-inactive');
                    Route::get('/employee/active/{id}', [EmployeeController::class, 'active'])->name('admin-employee-active');
                    Route::get('/employee/delete/{id}', [EmployeeController::class, 'delete'])->name('admin-employee-delete');
                });

                Route::middleware('admin.role:hr_admin,opening')->group(function () {
                    Route::get('/branch/opening', [BranchOpeningController::class, 'index'])->name('admin-branch-opening-index');
                    Route::post('/branch/opening', [BranchOpeningController::class, 'update'])->name('admin-branch-opening-update');
                    Route::get('/branch/opening/timings', [BranchOpeningController::class, 'timings'])->name('admin-branch-opening-timings');
                });

                Route::middleware('admin.role:hiring,hr_admin,subhr')->group(function () {
                    Route::get('/hiring', [RecruitmentController::class, 'hiringIndex'])->name('admin-hiring-index');
                    Route::get('/hiring/create', [RecruitmentController::class, 'hiringCreate'])->name('admin-hiring-create');
                    Route::post('/hiring', [RecruitmentController::class, 'hiringStore'])->name('admin-hiring-store');
                    Route::get('/hiring/{candidateId}', [RecruitmentController::class, 'hiringShow'])->name('admin-hiring-show');
                    Route::post('/hiring/{candidateId}/decision', [RecruitmentController::class, 'updateHiringDecision'])->name('admin-hiring-decision');
                });

                Route::middleware('admin.role:joining,hr_admin,subhr')->group(function () {
                    Route::get('/joining', [RecruitmentController::class, 'joiningIndex'])->name('admin-joining-index');
                    Route::post('/joining/{candidateId}/start', [RecruitmentController::class, 'startOnboarding'])->name('admin-joining-start');
                    Route::get('/joining/{candidateId}/onboarding', [RecruitmentController::class, 'editOnboarding'])->name('admin-joining-form');
                    Route::post('/joining/{candidateId}/onboarding', [RecruitmentController::class, 'storeOnboarding'])->name('admin-joining-store');
                    Route::post('/joining/{candidateId}/decision', [RecruitmentController::class, 'updateJoiningDecision'])->name('admin-joining-decision');
                });

                Route::middleware('admin.role:hr_admin,zonal')->group(function () {
                    Route::get('/attendance/reports', [AttendanceManagementController::class, 'reports'])->name('admin-attendance-reports');
                    Route::get('/attendance/daily', [AttendanceManagementController::class, 'dailyAttendance'])->name('admin-attendance-daily');
                    Route::get('/attendance/out-of-office', [AttendanceManagementController::class, 'outOfOffice'])->name('admin-attendance-out-of-office');
                    Route::post('/attendance/out-of-office/update', [AttendanceManagementController::class, 'updateOutOfOfficeStatuses'])->name('admin-attendance-out-of-office-update');
                    Route::get('/attendance/fraud-reports', [AttendanceManagementController::class, 'fraudReports'])->name('admin-attendance-fraud-reports');
                    Route::get('/attendance/review', [AttendanceManagementController::class, 'attendanceReview'])->name('admin-attendance-review');
                    Route::post('/attendance/review/update', [AttendanceManagementController::class, 'updateAttendanceStatuses'])->name('admin-attendance-review-update');
                    Route::get('/attendance/calendar/{empId}', [AttendanceManagementController::class, 'employeeCalendar'])->name('admin-attendance-calendar');
                    Route::post('/attendance/calendar/override', [AttendanceManagementController::class, 'updateCalendarDayOverride'])->name('admin-attendance-calendar-override');
                });

                Route::middleware('admin.role:hr_admin')->group(function () {
                    Route::get('/advance/reports', [AttendanceManagementController::class, 'reports'])->name('admin-advance-reports');

                    Route::get('/attendance/outsource', [AttendanceManagementController::class, 'outsourceAttendance'])->name('admin-attendance-outsource');
                    Route::get('/attendance/te-tracker', [TeTrackerController::class, 'index'])->name('admin-attendance-te-tracker');
                    Route::get('/attendance/night-shift', [AttendanceManagementController::class, 'nightShiftAttendance'])->name('admin-attendance-night-shift');
                    Route::get('/attendance/blocked', [AttendanceManagementController::class, 'blockedEmployees'])->name('admin-attendance-blocked');
                    Route::post('/attendance/blocked/unblock', [AttendanceManagementController::class, 'unblockEmployees'])->name('admin-attendance-blocked-unblock');

                    Route::get('/nightshift-users', [NightShiftUserController::class, 'index'])->name('admin-night-shift-users');
                    Route::post('/nightshift-users', [NightShiftUserController::class, 'update'])->name('admin-night-shift-users-update');
                    Route::get('/notifications', [AdminNotificationController::class, 'index'])->name('admin-notifications');
                    Route::post('/notifications', [AdminNotificationController::class, 'store'])->name('admin-notifications-store');

                    Route::get('/leaves/review', [LeaveController::class, 'review'])->name('admin-leaves-review');
                    Route::post('/leaves/review/update', [LeaveController::class, 'updateStatus'])->name('admin-leaves-review-update');
                    Route::get('/leaves/reports', [LeaveController::class, 'reports'])->name('admin-leaves-reports');
                    Route::get('/outsource/leaves/review', [LeaveController::class, 'outsourceReview'])->name('admin-outsource-leaves-review');
                    Route::get('/outsource/leaves/reports', [LeaveController::class, 'outsourceReports'])->name('admin-outsource-leaves-reports');

                    Route::get('/work-visits/review', [WorkVisitController::class, 'review'])->name('admin-work-visits-review');
                    Route::post('/work-visits/review/update', [WorkVisitController::class, 'updateStatus'])->name('admin-work-visits-review-update');
                    Route::get('/work-visits/reports', [WorkVisitController::class, 'reports'])->name('admin-work-visits-reports');


                    Route::get('/create', [AdminController::class, 'adminCreate'])->name('admin-create');
                    Route::post('/store', [AdminController::class, 'adminStore'])->name('admin-store');
                    Route::get('/index', [AdminController::class, 'adminIndex'])->name('admin-index');
                    Route::get('/edit/{id}', [AdminController::class, 'adminEdit'])->name('admin-edit');
                    Route::post('/update/{id}', [AdminController::class, 'adminUpdate'])->name('admin-update');


                });
            }




        );




                Route::group(
            ['middleware' => 'auth:admin'],
            function () {
        Route::get('/all/permission', [RoleController::class, 'allPermission'])->name('admin-all-permission');
        Route::get('/add/permission', [RoleController::class, 'addPermission'])->name('admin-add-permission');
        Route::post('/store/permission', [RoleController::class, 'storePermission'])->name('permission.store');
        Route::get('/edit/permission/{id}', [RoleController::class, 'editPermission'])->name('edit.permission');
        Route::post('/update/permission/{id}', [RoleController::class, 'updatePermission'])->name('permission.update');
        Route::get('/delete/permission/{id}', [RoleController::class, 'deletePermission'])->name('delete.permission');


                });
            }




);
