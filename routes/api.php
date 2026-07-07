<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AppUpdateController;
use App\Http\Controllers\EmployeeAuthController;
use App\Http\Controllers\EmployeeBranchOpeningController;
use App\Http\Controllers\EmployeeNotificationController;
use App\Http\Controllers\EmployeeRequestController;
use App\Http\Controllers\TeTrackerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/employee/login', [EmployeeAuthController::class, 'login']);
Route::get('/app/update', [AppUpdateController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/employee/profile', [EmployeeAuthController::class, 'profile']);
    Route::post('/employee/profile', [EmployeeAuthController::class, 'updateProfile']);
    Route::post('/employee/profile/photo', [EmployeeAuthController::class, 'updatePhoto']);
    Route::post('/employee/uan-number', [EmployeeRequestController::class, 'submitInitialUanNumber']);
    Route::post('/employee/bank-details/request', [EmployeeRequestController::class, 'requestBankDetailEdit']);
    Route::post('/employee/bank-details', [EmployeeRequestController::class, 'submitBankDetails']);
    Route::post('/employee/logout', [EmployeeAuthController::class, 'logout']);
    Route::post('/branch-opening/open', [EmployeeBranchOpeningController::class, 'markOpened']);
    Route::post('/branch-opening/close', [EmployeeBranchOpeningController::class, 'markClosed']);

    Route::get('/attendance/latest', [AttendanceController::class, 'latest']);
    Route::get('/attendance/history', [AttendanceController::class, 'history']);
    Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
    Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut']);
    Route::post('/attendance/location', [AttendanceController::class, 'trackLocation']);
    Route::post('/attendance/fraud-report', [AttendanceController::class, 'reportFraud']);
    Route::get('/te-tracker/branches', [TeTrackerController::class, 'branches']);
    Route::get('/te-tracker/visits', [TeTrackerController::class, 'visits']);
    Route::post('/te-tracker/check-in', [TeTrackerController::class, 'checkIn']);
    Route::get('/salary/summary', [EmployeeRequestController::class, 'salarySummary']);
    Route::get('/salary/advance-requests', [EmployeeRequestController::class, 'advanceRequests']);
    Route::post('/salary/advance-requests', [EmployeeRequestController::class, 'submitAdvanceRequest']);
    Route::get('/leaves', [EmployeeRequestController::class, 'leaveRequests']);
    Route::post('/leaves', [EmployeeRequestController::class, 'submitLeave']);
    Route::get('/site-visits', [EmployeeRequestController::class, 'siteVisitRequests']);
    Route::post('/site-visits', [EmployeeRequestController::class, 'submitSiteVisit']);
    Route::get('/notifications', [EmployeeNotificationController::class, 'index']);
    Route::get('/notifications/pending', [EmployeeNotificationController::class, 'pending']);
    Route::post('/notifications/read', [EmployeeNotificationController::class, 'markRead']);
    Route::post('/notifications/device-token', [EmployeeNotificationController::class, 'registerDeviceToken']);
    Route::post('/notifications/device-token/remove', [EmployeeNotificationController::class, 'removeDeviceToken']);
});
