<?php

namespace App\Providers;

use App\Models\AdminMessage;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    private const REVIEW_PENDING_STATUSES = [
        'half_day',
        'single_punch',
    ];

    private const REVIEW_RESOLVED_STATUSES = [
        'full_day',
        'full_day_remote',
        'half_day',
        'single_punch',
        'absent',
    ];

    private const FULL_DAY_MINUTES = 460;

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $helpersPath = app_path('Support/helpers.php');

        if (is_file($helpersPath)) {
            require_once $helpersPath;
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        View::composer('admin.layout.sidebar', function ($view): void {
            $adminId = auth('admin')->id();

            $view->with(Cache::remember('admin.sidebar.alerts.v2', now()->addSeconds(60), function (): array {
                $today = Carbon::now(config('app.timezone', 'Asia/Kolkata'));
                $monthStart = $today->copy()->startOfMonth()->toDateString();
                $monthEnd = $today->copy()->endOfMonth()->toDateString();
                $hasNightShiftColumn = Cache::rememberForever('schema.employee.is_night_shift.exists', static function (): bool {
                    return Schema::hasColumn('employee', 'is_night_shift');
                });

                $hasBlockedEmployees = Employee::query()
                    ->where('status', 'Blocked')
                    ->exists();

                $hasAttendanceReviewAlerts = Attendance::query()
                    ->whereBetween('check_in_date', [$monthStart, $monthEnd])
                    ->whereDate('check_in_date', '<', $today->toDateString())
                    ->when($hasNightShiftColumn, function ($query): void {
                        $query->whereNotIn('empId', Employee::query()
                            ->where('is_night_shift', true)
                            ->select('empId')
                        );
                    })
                    ->where(function ($query): void {
                        $query->whereIn('attendance_status_override', self::REVIEW_PENDING_STATUSES)
                            ->orWhere(function ($query): void {
                                $query->where(function ($query): void {
                                    $query->whereNull('attendance_status_override')
                                        ->orWhereNotIn('attendance_status_override', self::REVIEW_RESOLVED_STATUSES);
                                })->where(function ($query): void {
                                    $query->whereNull('check_out_date')
                                        ->orWhereNull('check_out_time')
                                        ->orWhereRaw(
                                            "TIMESTAMPDIFF(MINUTE, STR_TO_DATE(CONCAT(check_in_date, ' ', check_in_time), '%Y-%m-%d %H:%i:%s'), STR_TO_DATE(CONCAT(check_out_date, ' ', check_out_time), '%Y-%m-%d %H:%i:%s')) < ?",
                                            [self::FULL_DAY_MINUTES]
                                        );
                                });
                            });
                    })
                    ->exists();

                return [
                    'hasBlockedEmployees' => $hasBlockedEmployees,
                    'hasAttendanceReviewAlerts' => $hasAttendanceReviewAlerts,
                    'hasPendingLeaveRequests' => \App\Models\LeaveRequest::query()
                        ->where('status', 'pending')
                        ->whereHas('employee', function ($employeeQuery): void {
                            $employeeQuery
                                ->where('is_outsourced', false)
                                ->orWhereNull('is_outsourced');
                        })
                        ->exists(),
                    'hasPendingOutsourceLeaveRequests' => \App\Models\LeaveRequest::query()
                        ->where('status', 'pending')
                        ->whereHas('employee', function ($employeeQuery): void {
                            $employeeQuery->where('is_outsourced', true);
                        })
                        ->exists(),
                    'hasPendingWorkVisitRequests' => \App\Models\SiteVisitRequest::query()
                        ->where('status', 'pending')
                        ->exists(),
                ];
            }));
            $view->with('adminUnreadMessagesCount', ($adminId && Schema::hasTable('admin_messages'))
                ? AdminMessage::query()
                    ->where('receiver_admin_id', $adminId)
                    ->whereNull('read_at')
                    ->count()
                : 0
            );
        });
    }
}
