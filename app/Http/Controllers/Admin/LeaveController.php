<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Services\EmployeeNotificationDispatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class LeaveController extends Controller
{
    private const SCOPE_REGULAR = 'regular';
    private const SCOPE_OUTSOURCE = 'outsource';
    private const TABLE_PAGE_SIZE = 10;

    public function __construct(
        private readonly EmployeeNotificationDispatchService $notificationDispatchService
    ) {
    }

    public function review(Request $request): View
    {
        return $this->renderReview($request, self::SCOPE_REGULAR);
    }

    public function outsourceReview(Request $request): View
    {
        return $this->renderReview($request, self::SCOPE_OUTSOURCE);
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'leave_request_id' => ['nullable', 'integer', 'exists:leave_requests,id'],
            'leave_request_ids' => ['nullable', 'array'],
            'leave_request_ids.*' => ['integer', 'exists:leave_requests,id'],
            'status' => ['required', 'in:approved,rejected'],
            'review_note' => ['nullable', 'string', 'max:2000'],
            'scope' => ['nullable', 'in:regular,outsource'],
            'redirect_route' => ['nullable', 'in:admin-leaves-review,admin-outsource-leaves-review'],
        ]);

        $leaveRequestIds = collect($data['leave_request_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($leaveRequestIds->isEmpty() && ! empty($data['leave_request_id'])) {
            $leaveRequestIds = collect([(int) $data['leave_request_id']]);
        }

        if ($leaveRequestIds->isEmpty()) {
            return back()->with('status', 'No leave request was selected.');
        }

        $scope = $this->normalizeScope($data['scope'] ?? null);
        $leaveRequests = LeaveRequest::query()
            ->with('employee')
            ->whereIn('id', $leaveRequestIds->all())
            ->whereHas('employee', function ($employeeQuery) use ($scope): void {
                if ($scope === self::SCOPE_OUTSOURCE) {
                    $employeeQuery->where('is_outsourced', true);

                    return;
                }

                $employeeQuery
                    ->where('is_outsourced', false)
                    ->orWhereNull('is_outsourced');
            })
            ->orderBy('leave_date')
            ->get();

        if ($leaveRequests->isEmpty()) {
            return back()->with('status', 'Selected leave request could not be found.');
        }

        $admin = auth('admin')->user();
        $reviewNote = trim((string) ($data['review_note'] ?? '')) ?: null;

        LeaveRequest::query()
            ->whereIn('id', $leaveRequestIds->all())
            ->whereHas('employee', function ($employeeQuery) use ($scope): void {
                if ($scope === self::SCOPE_OUTSOURCE) {
                    $employeeQuery->where('is_outsourced', true);

                    return;
                }

                $employeeQuery
                    ->where('is_outsourced', false)
                    ->orWhereNull('is_outsourced');
            })
            ->update([
                'status' => $data['status'],
                'review_note' => $reviewNote,
                'reviewed_by' => trim((string) ($admin->email ?? $admin->name ?? 'admin')),
                'reviewed_at' => now(),
            ]);

        /** @var LeaveRequest $primaryLeaveRequest */
        $primaryLeaveRequest = $leaveRequests->first();

        if ($primaryLeaveRequest->employee) {
            $this->notificationDispatchService->sendToEmployee(
                $primaryLeaveRequest->employee,
                'Leave Request '.$this->statusLabel($data['status']),
                sprintf(
                    'Your leave request for %s was %s.%s',
                    $this->formatLeaveDateRange(
                        $leaveRequests
                            ->pluck('leave_date')
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

        return redirect()
            ->route($data['redirect_route'] ?? $this->reviewRouteForScope($scope))
            ->with('status', 'Leave request updated successfully.');
    }

    public function reports(Request $request): View
    {
        return $this->renderReports($request, self::SCOPE_REGULAR);
    }

    public function outsourceReports(Request $request): View
    {
        return $this->renderReports($request, self::SCOPE_OUTSOURCE);
    }

    private function renderReview(Request $request, string $scope): View
    {
        $filters = $this->filters($request, true);
        $reviewQuery = $this->baseQuery($filters, $scope)
            ->where('status', 'pending')
            ->orderBy('employee_id')
            ->orderBy('created_at')
            ->orderBy('leave_date');
        $leaveRequests = $reviewQuery
            ->paginate(self::TABLE_PAGE_SIZE)
            ->withQueryString();
        $rows = $this->groupReviewRows(collect($leaveRequests->items()));
        $today = Carbon::now(config('app.timezone', 'Asia/Kolkata'))->toDateString();

        return view('admin.leaves.review', [
            'filters' => $filters,
            'rows' => $rows,
            'paginator' => $leaveRequests,
            'scope' => $scope,
            'resetRoute' => $this->reviewRouteForScope($scope),
            'updateRoute' => 'admin-leaves-review-update',
            'isOutsourceScope' => $scope === self::SCOPE_OUTSOURCE,
            'breadcrumbTitle' => $scope === self::SCOPE_OUTSOURCE ? 'Outsource' : 'Leaves',
            'pageTitle' => $scope === self::SCOPE_OUTSOURCE ? 'Outsource Leave Review' : 'Review Leave',
            'pageSubtitle' => $scope === self::SCOPE_OUTSOURCE
                ? 'Approve or reject outsourced employee leave requests.'
                : 'Approve or reject employee leave requests from the HR dashboard.',
            'summary' => [
                'pending' => (clone $reviewQuery)->count(),
                'today' => (clone $reviewQuery)->whereDate('leave_date', $today)->count(),
            ],
        ]);
    }

    private function renderReports(Request $request, string $scope): View
    {
        $filters = $this->filters($request, false);
        $summaryQuery = $this->baseQuery($filters, $scope);
        $reportsQuery = $this->baseQuery($filters, $scope)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
        $rowsPaginator = $reportsQuery
            ->paginate(self::TABLE_PAGE_SIZE)
            ->withQueryString();
        $rows = $this->mapRows(collect($rowsPaginator->items()));

        return view('admin.leaves.reports', [
            'filters' => $filters,
            'rows' => $rows,
            'paginator' => $rowsPaginator,
            'scope' => $scope,
            'resetRoute' => $this->reportsRouteForScope($scope),
            'isOutsourceScope' => $scope === self::SCOPE_OUTSOURCE,
            'breadcrumbTitle' => $scope === self::SCOPE_OUTSOURCE ? 'Outsource' : 'Leaves',
            'pageTitle' => $scope === self::SCOPE_OUTSOURCE ? 'Outsource Leave Reports' : 'Leave Reports',
            'summary' => [
                'total' => (clone $summaryQuery)->count(),
                'pending' => (clone $summaryQuery)->where('status', 'pending')->count(),
                'approved' => (clone $summaryQuery)->where('status', 'approved')->count(),
                'rejected' => (clone $summaryQuery)->where('status', 'rejected')->count(),
            ],
        ]);
    }

    private function baseQuery(array $filters, string $scope)
    {
        return LeaveRequest::query()
            ->with('employee:id,empId,name,designation')
            ->whereHas('employee', function ($employeeQuery) use ($scope): void {
                if ($scope === self::SCOPE_OUTSOURCE) {
                    $employeeQuery->where('is_outsourced', true);

                    return;
                }

                $employeeQuery
                    ->where('is_outsourced', false)
                    ->orWhereNull('is_outsourced');
            })
            ->when($filters['emp_id'] !== '', fn ($query) => $query->where('emp_id', 'like', '%'.$filters['emp_id'].'%'))
            ->when($filters['status'] !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['from_date'] !== '', fn ($query) => $query->whereDate('leave_date', '>=', $filters['from_date']))
            ->when($filters['to_date'] !== '', fn ($query) => $query->whereDate('leave_date', '<=', $filters['to_date']));
    }

    private function mapRows(Collection $rows): Collection
    {
        return $rows->map(function (LeaveRequest $leaveRequest): array {
            return [
                'id' => $leaveRequest->id,
                'emp_id' => trim((string) $leaveRequest->emp_id),
                'employee_name' => trim((string) $leaveRequest->employee?->name) ?: '--',
                'designation' => trim((string) $leaveRequest->employee?->designation) ?: '--',
                'leave_date' => optional($leaveRequest->leave_date)?->format('d M Y') ?: '--',
                'leave_date_raw' => optional($leaveRequest->leave_date)?->toDateString(),
                'reason' => trim((string) $leaveRequest->reason),
                'status' => trim((string) $leaveRequest->status),
                'review_note' => trim((string) $leaveRequest->review_note),
                'reviewed_by' => trim((string) $leaveRequest->reviewed_by) ?: '--',
                'reviewed_at' => $leaveRequest->reviewed_at?->format('d M Y h:i A') ?: '--',
                'applied_at' => $leaveRequest->created_at?->format('d M Y h:i A') ?: '--',
            ];
        });
    }

    private function filters(Request $request, bool $pendingOnly): array
    {
        $today = Carbon::now(config('app.timezone', 'Asia/Kolkata'));
        $defaultFrom = $today->copy()->startOfMonth()->toDateString();
        $defaultTo = $today->toDateString();

        return [
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

        foreach ($rows as $leaveRequest) {
            /** @var LeaveRequest $leaveRequest */
            $signature = implode('|', [
                (int) $leaveRequest->employee_id,
                strtolower(trim((string) $leaveRequest->reason)),
                strtolower(trim((string) $leaveRequest->status)),
                $leaveRequest->created_at?->toDateString() ?: '',
            ]);

            $leaveDate = $leaveRequest->leave_date instanceof Carbon
                ? $leaveRequest->leave_date->copy()->startOfDay()
                : null;

            $shouldAppend = $currentGroup !== null
                && $currentGroup['signature'] === $signature
                && $leaveDate instanceof Carbon
                && $currentGroup['end_date'] instanceof Carbon
                && $leaveDate->equalTo($currentGroup['end_date']->copy()->addDay());

            if (! $shouldAppend) {
                if ($currentGroup !== null) {
                    $groups[] = $currentGroup;
                }

                $currentGroup = [
                    'signature' => $signature,
                    'leave_request_ids' => [(int) $leaveRequest->id],
                    'emp_id' => trim((string) $leaveRequest->emp_id),
                    'employee_name' => trim((string) $leaveRequest->employee?->name) ?: '--',
                    'designation' => trim((string) $leaveRequest->employee?->designation) ?: '--',
                    'start_date' => $leaveDate,
                    'end_date' => $leaveDate,
                    'reason' => trim((string) $leaveRequest->reason),
                    'status' => trim((string) $leaveRequest->status),
                    'applied_at' => $leaveRequest->created_at,
                    'contains_today' => $leaveDate?->toDateString() === $today,
                ];

                continue;
            }

            $currentGroup['leave_request_ids'][] = (int) $leaveRequest->id;
            $currentGroup['end_date'] = $leaveDate;
            $currentGroup['contains_today'] = $currentGroup['contains_today'] || $leaveDate?->toDateString() === $today;
        }

        if ($currentGroup !== null) {
            $groups[] = $currentGroup;
        }

        return collect($groups)->map(function (array $group): array {
            return [
                'leave_request_ids' => $group['leave_request_ids'],
                'emp_id' => $group['emp_id'],
                'employee_name' => $group['employee_name'],
                'designation' => $group['designation'],
                'leave_date' => $this->formatLeaveDateRange(collect([
                    $group['start_date'],
                    $group['end_date'],
                ])->filter(fn ($date): bool => $date instanceof Carbon)->unique(fn (Carbon $date): string => $date->toDateString())->values()),
                'reason' => $group['reason'],
                'status' => $group['status'],
                'applied_at' => $group['applied_at']?->format('d M Y h:i A') ?: '--',
                'contains_today' => $group['contains_today'],
            ];
        })->values();
    }

    private function formatLeaveDateRange(Collection $dates): string
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

    private function normalizeScope(?string $scope): string
    {
        return strtolower(trim((string) $scope)) === self::SCOPE_OUTSOURCE
            ? self::SCOPE_OUTSOURCE
            : self::SCOPE_REGULAR;
    }

    private function reviewRouteForScope(string $scope): string
    {
        return $scope === self::SCOPE_OUTSOURCE
            ? 'admin-outsource-leaves-review'
            : 'admin-leaves-review';
    }

    private function reportsRouteForScope(string $scope): string
    {
        return $scope === self::SCOPE_OUTSOURCE
            ? 'admin-outsource-leaves-reports'
            : 'admin-leaves-reports';
    }
}
