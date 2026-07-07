<?php

use App\Http\Controllers\FrontendWebController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\RecruitmentVerificationController;
use Illuminate\Support\Facades\Route;

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

Route::get('/clear-cache', [MaintenanceController::class, 'clearCache']);
Route::get('migrate', [MaintenanceController::class, 'migrate']);
Route::get('optimize', [MaintenanceController::class, 'optimize']);
Route::get('optimize-clear', [MaintenanceController::class, 'optimizeClear']);
Route::get('resolve-may-pf-employee-ids', [MaintenanceController::class, 'resolveMayPfEmployeeIds']);

Route::get('/', [FrontendWebController::class, 'index']);

Route::view('/privacy-policy', 'privacy')->name('privacy-policy');

Route::get('/hiring/form', [RecruitmentVerificationController::class, 'hiringForm'])->name('recruitment-hiring-form-static');
Route::get('/hiring/update/{token}', [RecruitmentVerificationController::class, 'hiringUpdateForm'])->name('recruitment-hiring-update-link');
Route::get('/hiring/form/{token}', [RecruitmentVerificationController::class, 'hiringForm'])->name('recruitment-hiring-form-show');
Route::post('/hiring/form/{token}/resume-autofill', [RecruitmentVerificationController::class, 'autofillHiringFromResume'])->name('recruitment-hiring-form-autofill');
Route::post('/hiring/form/{token}', [RecruitmentVerificationController::class, 'submitHiringForm'])->name('recruitment-hiring-form-submit');
Route::get('/joining/update/{token}', [RecruitmentVerificationController::class, 'onboardingUpdateForm'])->name('recruitment-onboarding-update-link');
Route::get('/joining/form/{token}', [RecruitmentVerificationController::class, 'onboardingForm'])->name('recruitment-onboarding-form-show');
Route::post('/joining/form/{token}', [RecruitmentVerificationController::class, 'submitOnboardingForm'])->name('recruitment-onboarding-form-submit');

Route::get('/assets/{path}', [FrontendWebController::class, 'asset'])
    ->where('path', '.*');

Route::get('/icons/{path}', [FrontendWebController::class, 'icon'])
    ->where('path', '.*');

Route::get('/canvaskit/{path}', [FrontendWebController::class, 'canvaskit'])
    ->where('path', '.*');

Route::get('/{file}', [FrontendWebController::class, 'rootFile'])
    ->where('file', '[^/]+\.[^/]+');
