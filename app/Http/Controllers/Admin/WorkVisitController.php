<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\SiteVisitRequest;
use App\Services\EmployeeNotificationDispatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WorkVisitController extends Controller
{
    private const STATUS_FULL_DAY_REMOTE = 'full_day_remote';
    private const TABLE_PAGE_SIZE = 10;

    public function __construct(
        private readonly EmployeeNotificationDispatchService $notificationDispatchService
    ) {
    }

    public function review(Request $request): View
    {
        $filters = $this->filters($request, true);
        $reviewQuery = $this->baseQuery($filters)
            ->select([
                'id',
                'employee_id',
                'emp_id',
                'visit_date',
                'site_location',
                'latitude',
                'longitude',
                'photo_path',
                'reason',
                'approved_by',
                'status',
                'created_at',
            ])
            ->where('status', 'pending')
            ->orderBy('employee_id')
            ->orderBy('created_at')
            ->orderBy('visit_date');
        $siteVisitRequests = $reviewQuery
            ->paginate(self::TABLE_PAGE_SIZE)
            ->withQueryString();
        $rows = $this->groupReviewRows(collect($siteVisitRequests->items()));
        $today = Carbon::now(config('app.timezone', 'Asia/Kolkata'))->toDateString();

        return view('admin.work_visits.review', [
            'filters' => $filters,
            'rows' => $rows,
            'paginator' => $siteVisitRequests,
            'summary' => [
                'pending' => $siteVisitRequests->total(),
                'today' => (clone $reviewQuery)->whereDate('visit_date', $today)->count(),
            ],
        ]);
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_visit_request_id' => ['nullable', 'integer', 'exists:site_visit_requests,id'],
            'site_visit_request_ids' => ['nullable', 'array'],
            'site_visit_request_ids.*' => ['integer', 'exists:site_visit_requests,id'],
            'status' => ['required', 'in:approved,rejected'],
            'review_note' => ['nullable', 'string', 'max:2000'],
            'month' => ['nullable', 'date_format:Y-m'],
            'emp_id' => ['nullable', 'string', 'max:255'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
        ]);

        $siteVisitRequestIds = collect($data['site_visit_request_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($siteVisitRequestIds->isEmpty() && ! empty($data['site_visit_request_id'])) {
            $siteVisitRequestIds = collect([(int) $data['site_visit_request_id']]);
        }

        if ($siteVisitRequestIds->isEmpty()) {
            return back()->with('status', 'No work visit request was selected.');
        }

        $siteVisitRequests = SiteVisitRequest::query()
            ->with('employee')
            ->whereIn('id', $siteVisitRequestIds->all())
            ->orderBy('visit_date')
            ->get();

        if ($siteVisitRequests->isEmpty()) {
            return back()->with('status', 'Selected work visit request could not be found.');
        }

        $admin = auth('admin')->user();
        $reviewNote = trim((string) ($data['review_note'] ?? '')) ?: null;

        DB::transaction(function () use ($data, $siteVisitRequests, $admin, $reviewNote): void {
            foreach ($siteVisitRequests as $siteVisitRequest) {
                $attendanceId = $siteVisitRequest->attendance_id;

                if ($data['status'] === 'approved' && $siteVisitRequest->employee instanceof Employee) {
                    $attendance = $this->markRemoteAttendance($siteVisitRequest->employee, $siteVisitRequest);
                    $attendanceId = $attendance->id;
                }

                $siteVisitRequest->update([
                    'status' => $data['status'],
                    'review_note' => $reviewNote,
                    'reviewed_by' => trim((string) ($admin->email ?? $admin->name ?? 'admin')),
                    'reviewed_at' => now(),
                    'attendance_id' => $attendanceId,
                ]);
            }

            /** @var SiteVisitRequest $primarySiteVisitRequest */
            $primarySiteVisitRequest = $siteVisitRequests->first();

            if ($primarySiteVisitRequest->employee instanceof Employee) {
                $this->notificationDispatchService->sendToEmployee(
                    $primarySiteVisitRequest->employee,
                    'Work Visit '.$this->statusLabel($data['status']),
                    sprintf(
                        'Your work visit request for %s was %s.%s',
                        $this->formatVisitDateRange(
                            $siteVisitRequests
                                ->pluck('visit_date')
                                ->filter(fn ($date): bool => $date instanceof Carbon)
                                ->values()
                        ) ?: 'the selected dates',
                        strtolower($this->statusLabel($data['status'])),
                        $reviewNote !== null
                            ? ' Note: '.$reviewNote
                            : ''
                    ),
                    auth('admin')->id()
                );
            }
        });

        $redirectFilters = collect([
            'month' => $data['month'] ?? null,
            'emp_id' => $data['emp_id'] ?? null,
            'from_date' => $data['from_date'] ?? null,
            'to_date' => $data['to_date'] ?? null,
        ])
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->all();

        return redirect()
            ->route('admin-work-visits-review', $redirectFilters)
            ->with('status', 'Work visit request updated successfully.');
    }

    public function reports(Request $request): View
    {
        $filters = $this->filters($request, false);
        $summaryQuery = $this->baseQuery($filters);
        $reportsQuery = $this->baseQuery($filters)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
        $rowsPaginator = $reportsQuery
            ->paginate(self::TABLE_PAGE_SIZE)
            ->withQueryString();
        $rows = $this->mapRows(collect($rowsPaginator->items()));

        return view('admin.work_visits.reports', [
            'filters' => $filters,
            'rows' => $rows,
            'paginator' => $rowsPaginator,
            'summary' => [
                'total' => (clone $summaryQuery)->count(),
                'pending' => (clone $summaryQuery)->where('status', 'pending')->count(),
                'approved' => (clone $summaryQuery)->where('status', 'approved')->count(),
                'rejected' => (clone $summaryQuery)->where('status', 'rejected')->count(),
            ],
        ]);
    }

    private function markRemoteAttendance(Employee $employee, SiteVisitRequest $siteVisitRequest): Attendance
    {
        $empId = trim((string) $employee->empId);
        $visitDate = optional($siteVisitRequest->visit_date)->toDateString();
        $branchId = $this->latestBranchIdForEmployee($employee);

        $attendance = Attendance::query()
            ->where('empId', $empId)
            ->where('check_in_date', $visitDate)
            ->latest('id')
            ->first();

        if ($attendance) {
            $attendance->update([
                'check_in_branch_id' => trim((string) $attendance->check_in_branch_id) ?: $branchId,
                'check_out_branch_id' => trim((string) $attendance->check_out_branch_id) ?: $branchId,
                'check_out_photo_path' => trim((string) $attendance->check_out_photo_path) ?: $siteVisitRequest->photo_path,
                'attendance_status_override' => self::STATUS_FULL_DAY_REMOTE,
                'updated_at' => now(),
            ]);

            return $attendance->fresh();
        }

        return Attendance::query()->create([
            'empId' => $empId,
            'check_in_branch_id' => $branchId,
            'check_out_branch_id' => $branchId,
            'photo_path' => $siteVisitRequest->photo_path,
            'check_out_photo_path' => $siteVisitRequest->photo_path,
            'latitude' => $siteVisitRequest->latitude,
            'longitude' => $siteVisitRequest->longitude,
            'check_out_latitude' => $siteVisitRequest->latitude,
            'check_out_longitude' => $siteVisitRequest->longitude,
            'check_in_date' => $visitDate,
            'check_in_time' => '09:00:00',
            'check_out_date' => $visitDate,
            'check_out_time' => '18:00:00',
            'attendance_status_override' => self::STATUS_FULL_DAY_REMOTE,
        ]);
    }

    private function latestBranchIdForEmployee(Employee $employee): ?string
    {
        $attendance = Attendance::query()
            ->where('empId', trim((string) $employee->empId))
            ->latest('id')
            ->first(['check_in_branch_id', 'check_out_branch_id']);

        if (! $attendance) {
            return null;
        }

        return trim((string) $attendance->check_out_branch_id) ?: trim((string) $attendance->check_in_branch_id);
    }

    private function baseQuery(array $filters)
    {
        return SiteVisitRequest::query()
            ->with('employee:id,empId,name,designation')
            ->when($filters['emp_id'] !== '', fn ($query) => $query->where('emp_id', 'like', '%'.$filters['emp_id'].'%'))
            ->when($filters['status'] !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['from_date'] !== '', fn ($query) => $query->where('visit_date', '>=', $filters['from_date']))
            ->when($filters['to_date'] !== '', fn ($query) => $query->where('visit_date', '<=', $filters['to_date']));
    }

    private function mapRows(Collection $rows): Collection
    {
        return $rows->map(function (SiteVisitRequest $siteVisitRequest): array {
            return [
                'id' => $siteVisitRequest->id,
                'emp_id' => trim((string) $siteVisitRequest->emp_id),
                'employee_name' => trim((string) $siteVisitRequest->employee?->name) ?: '--',
                'designation' => trim((string) $siteVisitRequest->employee?->designation) ?: '--',
                'visit_date' => optional($siteVisitRequest->visit_date)?->format('d M Y') ?: '--',
                'visit_date_raw' => optional($siteVisitRequest->visit_date)?->toDateString(),
                'site_location' => trim((string) $siteVisitRequest->site_location),
                'reason' => trim((string) $siteVisitRequest->reason),
                'approved_by' => trim((string) $siteVisitRequest->approved_by),
                'photo_url' => $this->resolvePhotoUrl($siteVisitRequest->photo_path),
                'status' => trim((string) $siteVisitRequest->status),
                'review_note' => trim((string) $siteVisitRequest->review_note),
                'reviewed_by' => trim((string) $siteVisitRequest->reviewed_by) ?: '--',
                'reviewed_at' => $siteVisitRequest->reviewed_at?->format('d M Y h:i A') ?: '--',
                'applied_at' => $siteVisitRequest->created_at?->format('d M Y h:i A') ?: '--',
                'coordinates' => sprintf(
                    '%.6f, %.6f',
                    (float) $siteVisitRequest->latitude,
                    (float) $siteVisitRequest->longitude
                ),
                'map_url' => sprintf(
                    'https://www.google.com/maps?q=%s,%s',
                    $siteVisitRequest->latitude,
                    $siteVisitRequest->longitude
                ),
            ];
        });
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

    private function filters(Request $request, bool $pendingOnly): array
    {
        $today = Carbon::now(config('app.timezone', 'Asia/Kolkata'));
        $selectedMonth = trim((string) $request->input('month', $today->format('Y-m'))) ?: $today->format('Y-m');
        if (preg_match('/^\d{4}-\d{2}$/', $selectedMonth) !== 1) {
            $selectedMonth = $today->format('Y-m');
        }

        $selectedMonthDate = Carbon::createFromFormat('!Y-m', $selectedMonth, config('app.timezone', 'Asia/Kolkata'))->startOfMonth();
        $defaultFrom = $selectedMonthDate->copy()->startOfMonth()->toDateString();
        $defaultTo = $selectedMonthDate->isSameMonth($today)
            ? $today->toDateString()
            : $selectedMonthDate->copy()->endOfMonth()->toDateString();

        return [
            'month' => $selectedMonth,
            'emp_id' => trim((string) $request->input('emp_id')),
            'status' => $pendingOnly ? 'pending' : trim((string) $request->input('status', 'all')),
            'from_date' => trim((string) $request->input('from_date', $pendingOnly ? $defaultFrom : '')),
            'to_date' => trim((string) $request->input('to_date', $pendingOnly ? $defaultTo : '')),
        ];
    }

    private function groupReviewRows(Collection $rows): Collection
    {
        $today = Carbon::now(config('app.timezone', 'Asia/Kolkata'))->toDateString();
        $groups = [];
        $currentGroup = null;

        foreach ($rows as $siteVisitRequest) {
            /** @var SiteVisitRequest $siteVisitRequest */
            $signature = implode('|', [
                (int) $siteVisitRequest->employee_id,
                strtolower(trim((string) $siteVisitRequest->site_location)),
                strtolower(trim((string) $siteVisitRequest->reason)),
                strtolower(trim((string) $siteVisitRequest->approved_by)),
                strtolower(trim((string) $siteVisitRequest->status)),
                $siteVisitRequest->created_at?->toDateString() ?: '',
            ]);

            $visitDate = $siteVisitRequest->visit_date instanceof Carbon
                ? $siteVisitRequest->visit_date->copy()->startOfDay()
                : null;

            $shouldAppend = $currentGroup !== null
                && $currentGroup['signature'] === $signature
                && $visitDate instanceof Carbon
                && $currentGroup['end_date'] instanceof Carbon
                && $visitDate->equalTo($currentGroup['end_date']->copy()->addDay());

            if (! $shouldAppend) {
                if ($currentGroup !== null) {
                    $groups[] = $currentGroup;
                }

                $currentGroup = [
                    'signature' => $signature,
                    'site_visit_request_ids' => [(int) $siteVisitRequest->id],
                    'emp_id' => trim((string) $siteVisitRequest->emp_id),
                    'employee_name' => trim((string) $siteVisitRequest->employee?->name) ?: '--',
                    'designation' => trim((string) $siteVisitRequest->employee?->designation) ?: '--',
                    'start_date' => $visitDate,
                    'end_date' => $visitDate,
                    'site_location' => trim((string) $siteVisitRequest->site_location),
                    'approved_by' => trim((string) $siteVisitRequest->approved_by),
                    'reason' => trim((string) $siteVisitRequest->reason),
                    'photo_url' => $this->resolvePhotoUrl($siteVisitRequest->photo_path),
                    'coordinates' => sprintf('%.6f, %.6f', (float) $siteVisitRequest->latitude, (float) $siteVisitRequest->longitude),
                    'map_url' => sprintf('https://www.google.com/maps?q=%s,%s', $siteVisitRequest->latitude, $siteVisitRequest->longitude),
                    'status' => trim((string) $siteVisitRequest->status),
                    'applied_at' => $siteVisitRequest->created_at,
                    'contains_today' => $visitDate?->toDateString() === $today,
                ];

                continue;
            }

            $currentGroup['site_visit_request_ids'][] = (int) $siteVisitRequest->id;
            $currentGroup['end_date'] = $visitDate;
            $currentGroup['contains_today'] = $currentGroup['contains_today'] || $visitDate?->toDateString() === $today;
        }

        if ($currentGroup !== null) {
            $groups[] = $currentGroup;
        }

        return collect($groups)->map(function (array $group): array {
            return [
                'site_visit_request_ids' => $group['site_visit_request_ids'],
                'emp_id' => $group['emp_id'],
                'employee_name' => $group['employee_name'],
                'designation' => $group['designation'],
                'visit_date' => $this->formatVisitDateRange(collect([
                    $group['start_date'],
                    $group['end_date'],
                ])->filter(fn ($date): bool => $date instanceof Carbon)->unique(fn (Carbon $date): string => $date->toDateString())->values()),
                'site_location' => $group['site_location'],
                'approved_by' => $group['approved_by'],
                'photo_url' => $group['photo_url'],
                'coordinates' => $group['coordinates'],
                'map_url' => $group['map_url'],
                'reason' => $group['reason'],
                'status' => $group['status'],
                'applied_at' => $group['applied_at']?->format('d M Y h:i A') ?: '--',
                'contains_today' => $group['contains_today'],
            ];
        })->values();
    }

    private function formatVisitDateRange(Collection $dates): string
    {
        if ($dates->isEmpty()) {
            return '--';
        }

        /** @var Carbon|null $start */
        $start = $dates->sortBy(fn (Carbon $date): string => $date->toDateString())->first();
        /** @var Carbon|null $end */
        $end = $dates->sortByDesc(fn (Carbon $date): string => $date->toDateString())->first();

        if (! $start instanceof Carbon || ! $end instanceof Carbon) {
            return '--';
        }

        if ($start->isSameDay($end)) {
            return $start->format('d M Y');
        }

        if ($start->format('Y-m') === $end->format('Y-m')) {
            return sprintf('%s - %s', $start->format('d'), $end->format('d M Y'));
        }

        return sprintf('%s - %s', $start->format('d M Y'), $end->format('d M Y'));
    }

    private function statusLabel(string $status): string
    {
        return $status === 'approved' ? 'Approved' : 'Rejected';
    }
}
