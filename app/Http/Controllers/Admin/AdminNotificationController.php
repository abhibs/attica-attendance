<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Services\EmployeeNotificationDispatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AdminNotificationController extends Controller
{
    public function __construct(
        private readonly EmployeeNotificationDispatchService $notificationDispatchService
    ) {
    }

    public function index()
    {
        $employees = $this->eligibleEmployees();
        $branches = Branch::query()
            ->where('status', 1)
            ->orderBy('branchName')
            ->get();

        $recentNotifications = AdminNotification::query()
            ->with([
                'deliveries.employee' => fn ($query) => $query->select('id', 'empId', 'name'),
            ])
            ->withCount('deliveries')
            ->latest('id')
            ->limit(25)
            ->get();

        return view('admin.notifications.index', [
            'employees' => $employees,
            'branches' => $branches,
            'states' => $branches->pluck('state')->map(fn ($state) => $this->clean($state))->filter()->unique()->sort()->values(),
            'cities' => $branches->pluck('city')->map(fn ($city) => $this->clean($city))->filter()->unique()->sort()->values(),
            'recentNotifications' => $recentNotifications,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'audience_type' => ['required', 'in:all,employee,branch,city,state'],
            'audience_value' => ['nullable', 'string', 'max:255'],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer', 'exists:employee,id'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:1000'],
        ]);

        if ($data['audience_type'] === 'employee' && empty($data['employee_ids'])) {
            return back()
                ->withInput()
                ->with('status', 'Please select at least one employee.');
        }

        if (
            ! in_array($data['audience_type'], ['all', 'employee'], true)
            && $this->clean($data['audience_value'] ?? '') === ''
        ) {
            return back()
                ->withInput()
                ->with('status', 'Please select the notification target.');
        }

        $targetEmployees = $this->targetEmployees(
            $data['audience_type'],
            $this->normalizeAudienceValue($data['audience_type'], $data['audience_value'] ?? ''),
            $data['employee_ids'] ?? []
        );

        if ($targetEmployees->isEmpty()) {
            return back()
                ->withInput()
                ->with('status', 'No employees matched the selected notification target.');
        }

        $this->notificationDispatchService->sendToEmployees(
            $targetEmployees,
            $this->clean($data['title'] ?? '') ?: 'Attica Pagar',
            $this->clean($data['body']),
            auth('admin')->id(),
            $data['audience_type'],
            $this->audienceValueForNotification($data, $targetEmployees)
        );

        return redirect()
            ->route('admin-notifications')
            ->with('status', 'Notification sent to '.$targetEmployees->count().' employee(s).');
    }

    private function targetEmployees(string $audienceType, string $audienceValue, array $employeeIds = []): Collection
    {
        $employees = $this->eligibleEmployees();

        if ($audienceType === 'all') {
            return $employees;
        }

        if ($audienceType === 'employee') {
            $selectedIds = collect($employeeIds)
                ->map(fn ($id): int => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();

            return $employees
                ->whereIn('id', $selectedIds)
                ->values();
        }

        $branches = Branch::query()
            ->where('status', 1)
            ->get()
            ->keyBy(fn (Branch $branch): string => $this->clean($branch->branchId));
        $latestBranches = $this->latestAttendanceBranchMap();

        return $employees
            ->filter(function (Employee $employee) use ($audienceType, $audienceValue, $branches, $latestBranches): bool {
                $branchId = $this->clean($employee->last_login_branch_id)
                    ?: ($latestBranches[$this->clean($employee->empId)] ?? '');

                if ($branchId === '') {
                    return false;
                }

                /** @var Branch|null $branch */
                $branch = $branches->get($branchId);

                if (! $branch) {
                    return false;
                }

                return match ($audienceType) {
                    'branch' => $branchId === $audienceValue,
                    'city' => strcasecmp($this->clean($branch->city), $audienceValue) === 0,
                    'state' => strcasecmp($this->clean($branch->state), $audienceValue) === 0,
                    default => false,
                };
            })
            ->values();
    }

    private function audienceValueForNotification(array $data, Collection $targetEmployees): ?string
    {
        if ($data['audience_type'] === 'all') {
            return null;
        }

        if ($data['audience_type'] === 'employee') {
            $count = $targetEmployees->count();

            if ($count === 1) {
                /** @var Employee|null $employee */
                $employee = $targetEmployees->first();

                return $employee
                    ? $this->clean($employee->empId).' - '.$this->clean($employee->name)
                    : 'Selected employee';
            }

            return 'Selected employees ('.$count.')';
        }

        return $this->normalizeAudienceValue($data['audience_type'], $data['audience_value'] ?? '');
    }

    private function eligibleEmployees(): Collection
    {
        return Employee::query()
            ->where(function ($query): void {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'Inactive');
            })
            ->orderBy('name')
            ->get();
    }

    private function latestAttendanceBranchMap(): array
    {
        $map = [];
        $records = Attendance::query()
            ->orderBy('check_in_date')
            ->orderBy('id')
            ->get(['empId', 'check_in_branch_id', 'check_out_branch_id']);

        foreach ($records as $record) {
            $empId = $this->clean($record->empId);
            $branchId = $this->clean($record->check_out_branch_id) ?: $this->clean($record->check_in_branch_id);

            if ($empId !== '' && $branchId !== '') {
                $map[$empId] = $branchId;
            }
        }

        return $map;
    }

    private function clean($value): string
    {
        return trim((string) $value);
    }

    private function normalizeAudienceValue(string $audienceType, $value): string
    {
        $normalized = $this->clean($value);

        if ($normalized === '') {
            return '';
        }

        if ($audienceType !== 'branch') {
            return $normalized;
        }

        $parts = preg_split('/\s*-\s*/', $normalized, 2);

        return $this->clean($parts[0] ?? $normalized);
    }
}
