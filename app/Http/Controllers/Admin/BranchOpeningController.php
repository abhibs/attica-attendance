<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\BranchOpeningAdminAlert;
use App\Models\BranchOpeningAssignment;
use App\Models\BranchOpeningSetting;
use App\Models\Employee;
use App\Services\BranchOpeningAnalyticsService;
use App\Services\EmployeeNotificationDispatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BranchOpeningController extends Controller
{
    private const ADMIN_PHONE = '9686266994';
    private const OPENING_TIME_SUGGESTIONS = [
        '07:30' => '7:30',
        '09:00' => '9:00',
        '10:00' => '10:00',
    ];

    public function __construct(
        private readonly EmployeeNotificationDispatchService $notificationService,
        private readonly BranchOpeningAnalyticsService $branchOpeningAnalyticsService
    ) {
    }

    public function index(Request $request)
    {
        $branches = Branch::query()
            ->where('status', 1)
            ->orderBy('branchName')
            ->get();

        $filters = $request->validate([
            'branch_id' => ['nullable', 'string', 'max:50'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'branch_search' => ['nullable', 'string', 'max:150'],
        ]);
        $filteredBranches = $this->filterOpeningBranches($branches, $filters);
        $selectedBranchSource = $filteredBranches->isNotEmpty() ? $filteredBranches : $branches;

        $selectedBranchId = $this->selectedBranchId($request, $selectedBranchSource);
        $selectedBranch = $branches->first(
            fn (Branch $branch): bool => $this->clean($branch->branchId) === $selectedBranchId
        );

        $employees = $this->eligibleEmployees();
        $latestBranchByEmpId = $this->latestAttendanceBranchMap();
        $branchStaff = $this->branchStaff($employees, $selectedBranchId, $latestBranchByEmpId);
        $assignments = $this->assignmentMap($selectedBranchId);
        $openingSetting = $this->openingSetting($selectedBranchId);

        return view('admin.branch_opening.index', [
            'branches' => $branches,
            'branchRows' => $this->branchRows($filteredBranches, $employees, $latestBranchByEmpId),
            'selectedBranchId' => $selectedBranchId,
            'selectedBranch' => $selectedBranch,
            'branchStaff' => $branchStaff,
            'assignments' => $assignments,
            'openingTime' => $this->formatOpeningTime($openingSetting?->opening_time),
            'adminPhone' => $this->resolveAdminPhone($openingSetting?->admin_phone),
            'filters' => [
                'state' => $this->clean($filters['state'] ?? ''),
                'city' => $this->clean($filters['city'] ?? ''),
                'branch_search' => $this->clean($filters['branch_search'] ?? ''),
                'states' => $branches->pluck('state')->filter()->map(fn ($value) => $this->clean($value))->unique()->sort()->values(),
                'cities' => $branches->pluck('city')->filter()->map(fn ($value) => $this->clean($value))->unique()->sort()->values(),
            ],
            'openingTimeSuggestions' => self::OPENING_TIME_SUGGESTIONS,
            'assignmentTypes' => [
                BranchOpeningAssignment::TYPE_DOOR_KEY => 'Door Keys',
                BranchOpeningAssignment::TYPE_LOCKER_KEY => 'Locker Keys',
                BranchOpeningAssignment::TYPE_OPENER => 'Branch Openers',
            ],
            'activeAlerts' => BranchOpeningAdminAlert::query()
                ->where('status', BranchOpeningAdminAlert::STATUS_OVERDUE)
                ->latest('opening_date')
                ->limit(10)
                ->get(),
            'recentAlerts' => BranchOpeningAdminAlert::query()
                ->latest('updated_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'string', 'max:50'],
            'opening_time' => ['nullable', 'date_format:H:i'],
            'admin_phone' => ['nullable', 'string', 'max:30'],
            'assignments' => ['nullable', 'array'],
            'assignments.'.BranchOpeningAssignment::TYPE_DOOR_KEY => ['nullable', 'array'],
            'assignments.'.BranchOpeningAssignment::TYPE_DOOR_KEY.'.*' => ['integer', 'exists:employee,id'],
            'assignments.'.BranchOpeningAssignment::TYPE_LOCKER_KEY => ['nullable', 'array'],
            'assignments.'.BranchOpeningAssignment::TYPE_LOCKER_KEY.'.*' => ['integer', 'exists:employee,id'],
            'assignments.'.BranchOpeningAssignment::TYPE_OPENER => ['nullable', 'array'],
            'assignments.'.BranchOpeningAssignment::TYPE_OPENER.'.*' => ['integer', 'exists:employee,id'],
        ]);

        $branch = Branch::query()
            ->whereRaw('TRIM(branchId) = ?', [$this->extractBranchId($data['branch_id'])])
            ->where('status', 1)
            ->first();

        if (! $branch) {
            return back()->with('status', 'Selected branch could not be found.');
        }

        $branchId = $this->clean($branch->branchId);
        $assignments = collect($data['assignments'] ?? []);
        $adminId = auth('admin')->id();
        $now = now();

        $openingTime = $this->formatOpeningTime($data['opening_time'] ?? '');
        $adminPhone = $this->normalizeAdminPhone($data['admin_phone'] ?? '');

        DB::transaction(function () use ($branchId, $assignments, $adminId, $now, $openingTime, $adminPhone): void {
            if ($openingTime === '') {
                BranchOpeningSetting::query()
                    ->where('branch_id', $branchId)
                    ->delete();
            } else {
                BranchOpeningSetting::query()->updateOrCreate(
                    ['branch_id' => $branchId],
                    [
                        'opening_time' => $openingTime,
                        'admin_phone' => $adminPhone !== '' ? $adminPhone : self::ADMIN_PHONE,
                        'updated_by' => $adminId,
                    ]
                );
            }

            BranchOpeningAssignment::query()
                ->where('branch_id', $branchId)
                ->delete();

            $rows = collect(BranchOpeningAssignment::TYPES)
                ->flatMap(function (string $type) use ($assignments, $branchId, $adminId, $now): Collection {
                    return collect($assignments->get($type, []))
                        ->map(fn ($id): int => (int) $id)
                        ->filter()
                        ->unique()
                        ->map(fn (int $employeeId): array => [
                            'branch_id' => $branchId,
                            'employee_id' => $employeeId,
                            'assignment_type' => $type,
                            'assigned_by' => $adminId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                })
                ->values()
                ->all();

            if ($rows !== []) {
                BranchOpeningAssignment::query()->insert($rows);
            }
        });

        $openerIds = collect($assignments->get(BranchOpeningAssignment::TYPE_OPENER, []))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($openerIds->isNotEmpty()) {
            $openers = Employee::query()
                ->whereIn('id', $openerIds->all())
                ->get();

            $this->notificationService->sendToEmployees(
                $openers,
                'Branch opening assigned',
                $this->openerNotificationBody($branch, $openingTime, $adminPhone !== '' ? $adminPhone : self::ADMIN_PHONE),
                $adminId
            );
        }

        return redirect()
            ->route('admin-branch-opening-index', ['branch_id' => $branchId])
            ->with('status', 'Branch opening and key assignments saved.');
    }

    private function selectedBranchId(Request $request, Collection $branches): string
    {
        $requested = $this->extractBranchId($request->query('branch_id'));

        if ($requested !== '' && $branches->contains(
            fn (Branch $branch): bool => $this->clean($branch->branchId) === $requested
        )) {
            return $requested;
        }

        /** @var Branch|null $first */
        $first = $branches->first();

        return $first ? $this->clean($first->branchId) : '';
    }

    private function filterOpeningBranches(Collection $branches, array $filters): Collection
    {
        $state = strtolower($this->clean($filters['state'] ?? ''));
        $city = strtolower($this->clean($filters['city'] ?? ''));
        $branchSearch = strtolower($this->clean($filters['branch_search'] ?? ''));

        return $branches
            ->filter(function (Branch $branch) use ($state, $city, $branchSearch): bool {
                $branchId = strtolower($this->clean($branch->branchId));
                $branchName = strtolower($this->clean($branch->branchName));
                $branchCity = strtolower($this->clean($branch->city));
                $branchState = strtolower($this->clean($branch->state));

                if ($state !== '' && $branchState !== $state) {
                    return false;
                }

                if ($city !== '' && $branchCity !== $city) {
                    return false;
                }

                if (
                    $branchSearch !== ''
                    && ! str_contains($branchId, $branchSearch)
                    && ! str_contains($branchName, $branchSearch)
                ) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    private function branchRows(Collection $branches, Collection $employees, array $latestBranchByEmpId): Collection
    {
        $assignmentCounts = BranchOpeningAssignment::query()
            ->select('branch_id', 'assignment_type', DB::raw('COUNT(*) as total'))
            ->groupBy('branch_id', 'assignment_type')
            ->get()
            ->groupBy(fn (BranchOpeningAssignment $row): string => $this->clean($row->branch_id));
        $openingTimes = BranchOpeningSetting::query()
            ->pluck('opening_time', 'branch_id');

        return $branches
            ->map(function (Branch $branch) use ($employees, $latestBranchByEmpId, $assignmentCounts, $openingTimes): array {
                $branchId = $this->clean($branch->branchId);
                $counts = $assignmentCounts->get($branchId, collect())
                    ->keyBy('assignment_type');

                return [
                    'branch' => $branch,
                    'branch_id' => $branchId,
                    'staff_count' => $this->branchStaff($employees, $branchId, $latestBranchByEmpId)->count(),
                    'door_key_count' => (int) optional($counts->get(BranchOpeningAssignment::TYPE_DOOR_KEY))->total,
                    'locker_key_count' => (int) optional($counts->get(BranchOpeningAssignment::TYPE_LOCKER_KEY))->total,
                    'opener_count' => (int) optional($counts->get(BranchOpeningAssignment::TYPE_OPENER))->total,
                    'opening_time' => $this->formatOpeningTime($openingTimes->get($branchId)),
                ];
            });
    }

    private function branchStaff(Collection $employees, string $branchId, array $latestBranchByEmpId): Collection
    {
        if ($branchId === '') {
            return collect();
        }

        return $employees
            ->filter(function (Employee $employee) use ($branchId, $latestBranchByEmpId): bool {
                $empId = $this->clean($employee->empId);
                $employeeBranchId = $this->clean($employee->last_login_branch_id)
                    ?: ($latestBranchByEmpId[$empId] ?? '');

                return $employeeBranchId === $branchId;
            })
            ->values();
    }

    private function assignmentMap(string $branchId): array
    {
        $map = collect(BranchOpeningAssignment::TYPES)
            ->mapWithKeys(fn (string $type): array => [$type => []])
            ->all();

        if ($branchId === '') {
            return $map;
        }

        BranchOpeningAssignment::query()
            ->where('branch_id', $branchId)
            ->get(['employee_id', 'assignment_type'])
            ->each(function (BranchOpeningAssignment $assignment) use (&$map): void {
                $type = $this->clean($assignment->assignment_type);

                if (array_key_exists($type, $map)) {
                    $map[$type][] = (int) $assignment->employee_id;
                }
            });

        return $map;
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

    private function branchLabel(Branch $branch): string
    {
        return trim($this->clean($branch->branchId).' - '.$this->clean($branch->branchName), ' -');
    }

    private function openerNotificationBody(Branch $branch, string $openingTime, string $adminPhone): string
    {
        $branchLabel = $this->branchLabel($branch);

        if ($openingTime !== '') {
            return sprintf(
                'You are assigned to open %s at %s. If you are late, call your admin at %s.',
                $branchLabel,
                $openingTime,
                $adminPhone
            );
        }

        return sprintf(
            'You are assigned to open %s. If you are late, call your admin at %s.',
            $branchLabel,
            $adminPhone
        );
    }

    public function timings(Request $request)
    {
        $timezone = config('app.timezone', 'Asia/Kolkata');
        $today = Carbon::today($timezone);
        $branches = Branch::query()
            ->where('status', 1)
            ->orderBy('branchName')
            ->get(['branchId', 'branchName']);

        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'branch_id' => ['nullable', 'string', 'max:50'],
        ]);

        $selectedMonth = ! empty($data['month'])
            ? Carbon::createFromFormat('Y-m', $data['month'], $timezone)->startOfMonth()
            : $today->copy()->startOfMonth();

        $defaultFrom = $selectedMonth->copy()->startOfMonth();
        $defaultTo = $selectedMonth->isSameMonth($today)
            ? $today->copy()
            : $selectedMonth->copy()->endOfMonth();

        $from = ! empty($data['from'])
            ? Carbon::createFromFormat('Y-m-d', $data['from'], $timezone)->startOfDay()
            : $defaultFrom;
        $to = ! empty($data['to'])
            ? Carbon::createFromFormat('Y-m-d', $data['to'], $timezone)->startOfDay()
            : $defaultTo;

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $selectedBranchId = $this->extractBranchId($data['branch_id'] ?? '');
        if ($selectedBranchId !== '' && ! $branches->contains(
            fn (Branch $branch): bool => $this->clean($branch->branchId) === $selectedBranchId
        )) {
            $selectedBranchId = '';
        }

        $this->branchOpeningAnalyticsService->syncRange($from, $to);
        $report = $this->branchOpeningAnalyticsService->reportData($from, $to, $selectedBranchId);

        return view('admin.branch_opening.timings', [
            'branches' => $branches,
            'selectedMonth' => $selectedMonth->format('Y-m'),
            'selectedBranchId' => $selectedBranchId,
            'fromDate' => $from->toDateString(),
            'toDate' => $to->toDateString(),
            'metrics' => $report['metrics'],
            'rows' => $report['rows'],
            'mostLateBranches' => $report['most_late_branches'],
            'shortestOpenTimes' => $report['shortest_open_times'],
        ]);
    }

    private function openingSetting(string $branchId): ?BranchOpeningSetting
    {
        if ($branchId === '') {
            return null;
        }

        return BranchOpeningSetting::query()
            ->where('branch_id', $branchId)
            ->first();
    }

    private function formatOpeningTime($value): string
    {
        $time = $this->clean($value);

        if ($time === '') {
            return '';
        }

        try {
            return Carbon::createFromFormat('H:i', substr($time, 0, 5))->format('H:i');
        } catch (\Throwable) {
            return '';
        }
    }

    private function extractBranchId($value): string
    {
        $normalized = $this->clean($value);
        if ($normalized === '') {
            return '';
        }

        $parts = preg_split('/\s*-\s*/', $normalized, 2);

        return $this->clean($parts[0] ?? $normalized);
    }

    private function clean($value): string
    {
        return trim((string) $value);
    }

    private function normalizeAdminPhone($value): string
    {
        $phone = preg_replace('/[^0-9+\-\s]/', '', trim((string) $value));

        return trim((string) $phone);
    }

    private function resolveAdminPhone($value): string
    {
        $phone = $this->normalizeAdminPhone($value);

        return $phone !== '' ? $phone : self::ADMIN_PHONE;
    }
}
