<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeAdvanceRequest;
use App\Models\EmployeeAdvanceTransaction;
use App\Models\OutsourceLocation;
use App\Services\AdvanceImportService;
use App\Support\ExcelTextValue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalaryController extends Controller
{
    private const HEAD_OFFICE_BRANCH_ID = 'AGPL000';

    private const HEAD_OFFICE_BRANCH_NAME = 'Head Office';

    public function accountDetails(Request $request): View
    {
        $filters = $this->accountDetailsFilters($request);
        [$rows, $branchOptions, $stateOptions] = $this->accountDetailsRows($filters);

        return view('admin.salary.account_details', [
            'filters' => $filters,
            'rows' => $rows,
            'branchOptions' => $branchOptions,
            'stateOptions' => $stateOptions,
        ]);
    }

    public function accountDetailsExport(Request $request): StreamedResponse
    {
        $filters = $this->accountDetailsFilters($request);
        [$rows] = $this->accountDetailsRows($filters);
        $fileName = 'employee-account-details-'.now(config('app.timezone', 'Asia/Kolkata'))->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Emp ID',
                'Name',
                'Designation',
                'Employee Type',
                'Branch ID',
                'Branch Name',
                'State',
                'City',
                'Bank Name',
                'Name as per A/C',
                'A/C Number',
                'IFSC Code',
                'UAN Number',
            ]);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row['emp_id'],
                    $row['employee_name'],
                    $row['designation'],
                    $row['employee_type'],
                    $row['branch_id'],
                    $row['branch_name'],
                    $row['state'],
                    $row['city'],
                    $row['bank_name'],
                    $row['account_name'],
                    ExcelTextValue::forCsv($this->clean($row['bank_account_number'])),
                    $row['ifsc_code'],
                    $row['uan_number'],
                ]);
            }

            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function advanceDetails(Request $request): View
    {
        $data = $request->validate([
            'advance_date' => ['nullable', 'date'],
        ]);
        $today = now(config('app.timezone', 'Asia/Kolkata'))->startOfDay();
        $selectedAdvanceDate = ! empty($data['advance_date'])
            ? Carbon::parse($data['advance_date'], config('app.timezone', 'Asia/Kolkata'))->startOfDay()
            : $today;
        $currentAdvanceWindow = $this->advanceRangeForDate($selectedAdvanceDate);
        $hasOpenCurrentAdvanceWindow = $currentAdvanceWindow !== null;
        $displayAdvanceWindow = $currentAdvanceWindow ?? $this->advancePayrollRange(
            $selectedAdvanceDate->copy()->startOfMonth()
        );

        $employees = Employee::query()
            ->where(function ($query): void {
                $query->where('is_outsourced', false)
                    ->orWhereNull('is_outsourced');
            })
            ->with([
                'detail',
                'advanceTransactions' => fn ($query) => $query
                    ->select(['id', 'employee_id', 'advance_date'])
                    ->orderByDesc('advance_date')
                    ->orderByDesc('id'),
            ])
            ->withCount('advanceTransactions')
            ->withSum([
                'advanceTransactions as current_advance_total' => function ($query) use ($currentAdvanceWindow, $hasOpenCurrentAdvanceWindow): void {
                    if (! $hasOpenCurrentAdvanceWindow) {
                        $query->whereRaw('1 = 0');

                        return;
                    }

                    $query->whereBetween('advance_date', [
                        $currentAdvanceWindow['start']->toDateString(),
                        $currentAdvanceWindow['end']->toDateString(),
                    ]);
                },
            ], 'amount')
            ->withSum('advanceTransactions as ledger_advance_total', 'amount')
            ->orderBy('empId', 'asc')
            ->get();
        $pendingAdvanceRequests = EmployeeAdvanceRequest::query()
            ->with('employee:id,empId,name,designation,is_outsourced')
            ->where('status', EmployeeAdvanceRequest::STATUS_PENDING)
            ->whereHas('employee', function ($query): void {
                $query->where(function ($employeeQuery): void {
                    $employeeQuery->where('is_outsourced', false)
                        ->orWhereNull('is_outsourced');
                });
            })
            ->orderByDesc('id')
            ->get();

        $employeesWithAdvanceCount = $employees
            ->filter(function (Employee $employee): bool {
                $advanceTotal = is_numeric($employee->current_advance_total ?? null)
                    ? (float) $employee->current_advance_total
                    : 0.0;

                return $advanceTotal > 0;
            })
            ->count();

        return view('admin.salary.advance_details', [
            'employees' => $employees,
            'employeesWithAdvanceCount' => $employeesWithAdvanceCount,
            'pendingAdvanceRequests' => $pendingAdvanceRequests,
            'defaultAdvanceDate' => $selectedAdvanceDate->toDateString(),
            'currentAdvanceWindowStart' => $displayAdvanceWindow['start']->toDateString(),
            'currentAdvanceWindowEnd' => $displayAdvanceWindow['end']->toDateString(),
            'hasOpenCurrentAdvanceWindow' => $hasOpenCurrentAdvanceWindow,
        ]);
    }

    public function verifyAdvanceRequest(Request $request, EmployeeAdvanceRequest $advanceRequest): RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($advanceRequest->status !== EmployeeAdvanceRequest::STATUS_PENDING) {
            return redirect()
                ->route('admin-salary-advance')
                ->with('flash_warning', 'Only pending advance requests can be verified.');
        }

        $employee = $advanceRequest->employee;

        if (! $employee || (bool) $employee->is_outsourced) {
            return redirect()
                ->route('admin-salary-advance')
                ->with('flash_warning', 'Invalid employee linked to this advance request.');
        }

        $admin = auth('admin')->user();
        $adminIdentity = trim((string) ($admin->email ?? $admin->name ?? 'admin'));
        $adminNote = trim((string) ($data['admin_note'] ?? '')) ?: null;

        DB::transaction(function () use ($advanceRequest, $employee, $adminIdentity, $adminNote): void {
            EmployeeAdvanceTransaction::query()->create([
                'employee_id' => $employee->id,
                'emp_id' => trim((string) $employee->empId),
                'advance_date' => Carbon::parse($advanceRequest->request_date, config('app.timezone', 'Asia/Kolkata'))->toDateString(),
                'amount' => (float) $advanceRequest->amount,
                'source_type' => 'request_verified',
                'remarks' => $adminNote
                    ? 'Verified employee advance request: '.$adminNote
                    : 'Verified employee advance request',
            ]);

            $advanceRequest->update([
                'status' => EmployeeAdvanceRequest::STATUS_VERIFIED,
                'admin_note' => $adminNote,
                'verified_by' => $adminIdentity,
                'verified_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
            ]);

            $this->refreshAdvanceTotal($employee->fresh());
        });

        return redirect()
            ->route('admin-salary-advance')
            ->with('flash_success', 'Advance request verified and added to salary advance details.');
    }

    public function rejectAdvanceRequest(Request $request, EmployeeAdvanceRequest $advanceRequest): RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($advanceRequest->status !== EmployeeAdvanceRequest::STATUS_PENDING) {
            return redirect()
                ->route('admin-salary-advance')
                ->with('flash_warning', 'Only pending advance requests can be rejected.');
        }

        $employee = $advanceRequest->employee;

        if (! $employee || (bool) $employee->is_outsourced) {
            return redirect()
                ->route('admin-salary-advance')
                ->with('flash_warning', 'Invalid employee linked to this advance request.');
        }

        $admin = auth('admin')->user();
        $adminIdentity = trim((string) ($admin->email ?? $admin->name ?? 'admin'));
        $adminNote = trim((string) ($data['admin_note'] ?? '')) ?: null;

        $advanceRequest->update([
            'status' => EmployeeAdvanceRequest::STATUS_REJECTED,
            'admin_note' => $adminNote,
            'rejected_by' => $adminIdentity,
            'rejected_at' => now(),
            'verified_by' => null,
            'verified_at' => null,
        ]);

        return redirect()
            ->route('admin-salary-advance')
            ->with('flash_success', 'Advance request rejected.');
    }

    public function updateAdvanceDetails(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employee,id'],
            'advance_date' => ['required', 'date'],
            'advance' => ['required', 'numeric', 'min:0'],
            'pf' => ['nullable', 'numeric', 'min:0'],
        ]);

        $employee = Employee::query()
            ->whereKey($data['employee_id'])
            ->where(function ($query): void {
                $query->where('is_outsourced', false)
                    ->orWhereNull('is_outsourced');
            })
            ->firstOrFail();
        if ((float) $data['advance'] > 0) {
            $advanceDate = Carbon::parse($data['advance_date'], config('app.timezone', 'Asia/Kolkata'))->startOfDay();

            if ($this->advanceRangeForDate($advanceDate) === null) {
                throw ValidationException::withMessages([
                    'advance_date' => 'Advance date must be between the 13th and month end, or between the 1st and 11th.',
                ]);
            }

            EmployeeAdvanceTransaction::query()->create([
                'employee_id' => $employee->id,
                'emp_id' => trim((string) $employee->empId),
                'advance_date' => $advanceDate->toDateString(),
                'amount' => $data['advance'],
                'source_type' => 'manual',
                'remarks' => 'Manual advance entry from salary module',
            ]);
        }

        if (array_key_exists('pf', $data) && $data['pf'] !== null) {
            $employee->pf = $data['pf'];
            $employee->save();
        }

        $this->refreshAdvanceTotal($employee);

        return redirect()
            ->route('admin-salary-advance', ['advance_date' => $data['advance_date']])
            ->with('flash_success', sprintf(
                'Advance of Rs %s saved for %s.',
                number_format((float) $data['advance'], 2),
                Carbon::parse($data['advance_date'])->format('d-m-Y')
            ));
    }

    public function importAdvanceDetails(Request $request, AdvanceImportService $importService): RedirectResponse
    {
        $token = trim((string) $request->input('import_token'));
        $isConfirmingConflicts = $request->boolean('confirm_conflicts');
        $isCancellingPendingImport = $request->boolean('cancel_pending_import');

        if ($isCancellingPendingImport) {
            $importService->discardPreparedImport($token);

            return redirect()
                ->route('admin-salary-advance')
                ->with('flash_warning', 'Pending advance import cancelled.');
        }

        if ($token === '') {
            $data = $request->validate([
                'advance_file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls'],
            ]);
        } else {
            $data = ['import_token' => $token];
        }

        try {
            if ($token !== '') {
                $result = $importService->importPrepared($token, $isConfirmingConflicts);
            } else {
                $pendingImport = $importService->prepareImport($data['advance_file']);

                if ($pendingImport['conflicts'] !== []) {
                    return redirect()
                        ->route('admin-salary-advance')
                        ->with(
                            'flash_warning',
                            sprintf(
                                'Manual advance conflicts were found in %d imported row(s). Review them below and confirm before adding both entries.',
                                count($pendingImport['conflicts'])
                            )
                        )
                        ->with('advance_import_pending', $pendingImport)
                        ->with('advance_import_skipped_details', $pendingImport['skipped_details'] ?? []);
                }

                $result = $importService->importPrepared($pendingImport['token']);
            }
        } catch (\Throwable $exception) {
            return back()
                ->withErrors(['advance_file' => 'Import failed: '.$exception->getMessage()])
                ->withInput();
        }

        $rowsNotInserted = count($result['skipped_details'] ?? []);
        $message = sprintf(
            'Advance import completed. Inserted: %d | Duplicate rows skipped: %d | Processed rows: %d | Rows not inserted: %d',
            $result['inserted'],
            $result['duplicates'],
            $result['rows'],
            $rowsNotInserted
        );

        if (($result['confirmed_conflicts'] ?? 0) > 0) {
            $message .= sprintf(' | Manual conflicts approved and added: %d', $result['confirmed_conflicts']);
        }

        $response = redirect()
            ->route('admin-salary-advance')
            ->with('flash_success', $message);

        if ($result['missing_ids'] !== []) {
            $preview = array_slice($result['missing_ids'], 0, 10);
            $suffix = count($result['missing_ids']) > count($preview) ? ' ...' : '';
            $response = $response->with(
                'flash_warning',
                'Employee IDs not found: '.implode(', ', $preview).$suffix
            );
        }

        if (($result['skipped_details'] ?? []) !== []) {
            $response = $response->with('advance_import_skipped_details', $result['skipped_details']);
        }

        return $response;
    }

    public function advanceHistory(Request $request, string $empId): View
    {
        $employee = Employee::query()
            ->whereRaw('TRIM(empId) = ?', [trim($empId)])
            ->where(function ($query): void {
                $query->where('is_outsourced', false)
                    ->orWhereNull('is_outsourced');
            })
            ->firstOrFail();

        $advanceSummary = EmployeeAdvanceTransaction::query()
            ->where('employee_id', $employee->id)
            ->selectRaw('COALESCE(SUM(amount), 0) as total_advance, MIN(advance_date) as first_advance_date, MAX(advance_date) as last_advance_date')
            ->first();

        $today = now(config('app.timezone', 'Asia/Kolkata'))->toDateString();
        $defaultFrom = trim((string) ($advanceSummary?->first_advance_date ?? '')) ?: $today;
        $defaultTo = trim((string) ($advanceSummary?->last_advance_date ?? '')) ?: $today;
        $fromDate = trim((string) $request->input('from_date', $defaultFrom)) ?: $defaultFrom;
        $toDate = trim((string) $request->input('to_date', $defaultTo)) ?: $defaultTo;

        if ($toDate < $fromDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $transactions = EmployeeAdvanceTransaction::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('advance_date', [$fromDate, $toDate])
            ->orderByDesc('advance_date')
            ->orderByDesc('id')
            ->get();

        return view('admin.salary.advance_history', [
            'employee' => $employee,
            'currentAdvance' => (float) ($advanceSummary?->total_advance ?? 0),
            'filteredAdvance' => (float) $transactions->sum('amount'),
            'transactions' => $transactions,
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
        ]);
    }

    public function mergeAdvanceHistory(Request $request, string $empId): RedirectResponse
    {
        $employee = Employee::query()
            ->whereRaw('TRIM(empId) = ?', [trim($empId)])
            ->where(function ($query): void {
                $query->where('is_outsourced', false)
                    ->orWhereNull('is_outsourced');
            })
            ->firstOrFail();

        $data = $request->validate([
            'transaction_ids' => ['required', 'array', 'min:2'],
            'transaction_ids.*' => ['required', 'integer'],
            'merge_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
        ]);

        $selectedTransactions = EmployeeAdvanceTransaction::query()
            ->where('employee_id', $employee->id)
            ->whereIn('id', $data['transaction_ids'])
            ->orderBy('advance_date')
            ->orderBy('id')
            ->get();

        if ($selectedTransactions->count() < 2) {
            return back()
                ->withErrors([
                    'transaction_ids' => 'Select at least two advance entries for the same employee to merge them.',
                ])
                ->withInput();
        }

        $mergedAmount = (float) $selectedTransactions->sum('amount');
        $mergeDate = Carbon::parse($data['merge_date'], config('app.timezone', 'Asia/Kolkata'))->toDateString();
        $remarks = trim((string) ($data['remarks'] ?? ''));
        $remarks = $remarks !== ''
            ? $remarks
            : sprintf(
                'Merged %d advance entries into one total of Rs %s',
                $selectedTransactions->count(),
                number_format($mergedAmount, 2, '.', '')
            );

        DB::transaction(function () use ($employee, $selectedTransactions, $mergeDate, $mergedAmount, $remarks): void {
            EmployeeAdvanceTransaction::query()->create([
                'employee_id' => $employee->id,
                'emp_id' => trim((string) $employee->empId),
                'advance_date' => $mergeDate,
                'amount' => $mergedAmount,
                'source_type' => 'manual',
                'remarks' => $remarks,
            ]);

            EmployeeAdvanceTransaction::query()
                ->whereIn('id', $selectedTransactions->pluck('id')->all())
                ->delete();

            $this->refreshAdvanceTotal($employee->fresh());
        });

        $redirectParams = ['empId' => $employee->empId];

        if (! empty($data['from_date'])) {
            $redirectParams['from_date'] = $data['from_date'];
        }

        if (! empty($data['to_date'])) {
            $redirectParams['to_date'] = $data['to_date'];
        }

        return redirect()
            ->route('admin-salary-advance-history', $redirectParams)
            ->with(
                'flash_success',
                sprintf(
                    'Merged %d advance entries into one summed advance of Rs %s.',
                    $selectedTransactions->count(),
                    number_format($mergedAmount, 0)
                )
            );
    }

    private function refreshAdvanceTotal(Employee $employee): void
    {
        $employee->advance = (float) EmployeeAdvanceTransaction::query()
            ->where('employee_id', $employee->id)
            ->sum('amount');
        $employee->save();
    }

    private function accountDetailsFilters(Request $request): array
    {
        return [
            'name' => $this->clean($request->input('name')),
            'branch_id' => $this->clean($request->input('branch_id')),
            'state' => $this->clean($request->input('state')),
        ];
    }

    private function accountDetailsRows(array $filters): array
    {
        [$locationMap, $branchOptions, $stateOptions] = $this->accountLocationDirectory();

        $employees = Employee::query()
            ->with('detail')
            ->when($filters['name'] !== '', function ($query) use ($filters): void {
                $query->where('name', 'like', '%'.$filters['name'].'%');
            })
            ->orderBy('name')
            ->orderBy('empId')
            ->get(['id', 'empId', 'name', 'designation', 'is_outsourced', 'last_login_branch_id']);

        $cleanEmpIds = $employees
            ->map(fn (Employee $employee): string => $this->clean($employee->empId))
            ->filter()
            ->unique()
            ->values();
        $latestBranchIdsByEmpId = $this->latestBranchIdByEmpId($cleanEmpIds);
        [$locationMap, $branchOptions] = $this->appendObservedBranchOptions(
            $employees,
            $latestBranchIdsByEmpId,
            $locationMap,
            $branchOptions
        );

        $rows = $employees
            ->map(function (Employee $employee) use ($latestBranchIdsByEmpId, $locationMap): array {
                $empId = $this->clean($employee->empId);
                $detail = $employee->detail;
                $branchId = $latestBranchIdsByEmpId[$empId] ?? $this->clean($employee->last_login_branch_id);
                $location = $locationMap->get($branchId, [
                    'name' => $branchId === self::HEAD_OFFICE_BRANCH_ID ? self::HEAD_OFFICE_BRANCH_NAME : '',
                    'state' => '',
                    'city' => '',
                ]);

                return [
                    'emp_id' => $empId,
                    'employee_name' => $this->clean($employee->name),
                    'designation' => $this->clean($employee->designation),
                    'employee_type' => (bool) $employee->is_outsourced ? 'Outsource' : 'Regular',
                    'branch_id' => $branchId,
                    'branch_name' => $this->clean($location['name'] ?? ''),
                    'state' => $this->clean($location['state'] ?? ''),
                    'city' => $this->clean($location['city'] ?? ''),
                    'bank_name' => $this->clean($detail?->bankName),
                    'account_name' => $this->clean($detail?->empName),
                    'bank_account_number' => $this->clean($detail?->bankAcNo),
                    'ifsc_code' => $this->clean($detail?->ifscCode),
                    'uan_number' => $this->clean($detail?->uanNumber),
                ];
            })
            ->filter(function (array $row) use ($filters): bool {
                if ($filters['branch_id'] !== '' && strcasecmp($row['branch_id'], $filters['branch_id']) !== 0) {
                    return false;
                }

                if ($filters['state'] !== '' && strcasecmp($row['state'], $filters['state']) !== 0) {
                    return false;
                }

                return true;
            })
            ->values();

        return [$rows, $branchOptions, $stateOptions];
    }

    private function accountLocationDirectory(): array
    {
        $branchLocations = Branch::query()
            ->select(['branchId', 'branchName', 'state', 'city'])
            ->get()
            ->map(function (Branch $branch): array {
                return [
                    'code' => $this->clean($branch->branchId),
                    'name' => $this->clean($branch->branchName),
                    'state' => $this->clean($branch->state),
                    'city' => $this->clean($branch->city),
                ];
            })
            ->filter(fn (array $item): bool => $item['code'] !== '')
            ->values();

        $outsourceLocations = OutsourceLocation::query()
            ->select(['location_code', 'name', 'state', 'city'])
            ->get()
            ->map(function (OutsourceLocation $location): array {
                return [
                    'code' => $this->clean($location->location_code),
                    'name' => $this->clean($location->name),
                    'state' => $this->clean($location->state),
                    'city' => $this->clean($location->city),
                ];
            })
            ->filter(fn (array $item): bool => $item['code'] !== '')
            ->values();

        $allLocations = $branchLocations
            ->concat($outsourceLocations)
            ->unique(fn (array $item): string => $item['code'])
            ->values();

        if (! $allLocations->contains(fn (array $item): bool => $item['code'] === self::HEAD_OFFICE_BRANCH_ID)) {
            $allLocations->prepend([
                'code' => self::HEAD_OFFICE_BRANCH_ID,
                'name' => self::HEAD_OFFICE_BRANCH_NAME,
                'state' => '',
                'city' => '',
            ]);
        }

        $locationMap = $allLocations->keyBy(fn (array $item): string => $item['code']);
        $stateOptions = $allLocations
            ->pluck('state')
            ->filter(fn (?string $state): bool => $this->clean($state) !== '')
            ->map(fn (?string $state): string => $this->clean($state))
            ->unique()
            ->sort()
            ->values();
        $branchOptions = $allLocations
            ->sortBy(function (array $item): string {
                $code = $this->clean((string) ($item['code'] ?? ''));
                $name = $this->clean((string) ($item['name'] ?? ''));
                $priority = strcasecmp($code, self::HEAD_OFFICE_BRANCH_ID) === 0 ? '0' : '1';

                return $priority.'|'.strtolower($code).'|'.strtolower($name);
            })
            ->values();

        return [$locationMap, $branchOptions, $stateOptions];
    }

    private function latestBranchIdByEmpId(Collection $cleanEmpIds): array
    {
        if ($cleanEmpIds->isEmpty()) {
            return [];
        }

        $latestAttendanceIds = DB::table('attendance')
            ->selectRaw('MAX(id) as latest_id')
            ->whereIn(DB::raw('TRIM(empId)'), $cleanEmpIds->all())
            ->groupByRaw('TRIM(empId)')
            ->pluck('latest_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values();

        if ($latestAttendanceIds->isEmpty()) {
            return [];
        }

        return Attendance::query()
            ->whereIn('id', $latestAttendanceIds->all())
            ->get(['empId', 'check_in_branch_id', 'check_out_branch_id'])
            ->mapWithKeys(function (Attendance $attendance): array {
                $empId = $this->clean($attendance->empId);
                $branchId = $this->clean($attendance->check_out_branch_id)
                    ?: $this->clean($attendance->check_in_branch_id);

                return $empId !== '' ? [$empId => $branchId] : [];
            })
            ->all();
    }

    private function appendObservedBranchOptions(
        Collection $employees,
        array $latestBranchIdsByEmpId,
        Collection $locationMap,
        Collection $branchOptions
    ): array {
        $observedBranchIds = collect($latestBranchIdsByEmpId)
            ->values()
            ->merge($employees->pluck('last_login_branch_id')->map(fn ($value): string => $this->clean((string) $value)))
            ->map(fn ($value): string => $this->clean((string) $value))
            ->filter()
            ->unique()
            ->values();

        if ($observedBranchIds->isEmpty()) {
            return [$locationMap, $branchOptions];
        }

        $missingLocations = $observedBranchIds
            ->filter(fn (string $branchId): bool => ! $locationMap->has($branchId))
            ->map(function (string $branchId): array {
                return [
                    'code' => $branchId,
                    'name' => $branchId === self::HEAD_OFFICE_BRANCH_ID ? self::HEAD_OFFICE_BRANCH_NAME : '',
                    'state' => '',
                    'city' => '',
                ];
            })
            ->values();

        if ($missingLocations->isEmpty()) {
            return [$locationMap, $branchOptions];
        }

        $updatedBranchOptions = $branchOptions
            ->concat($missingLocations)
            ->unique(fn (array $item): string => $this->clean((string) ($item['code'] ?? '')))
            ->sortBy(function (array $item): string {
                $code = $this->clean((string) ($item['code'] ?? ''));
                $name = $this->clean((string) ($item['name'] ?? ''));
                $priority = strcasecmp($code, self::HEAD_OFFICE_BRANCH_ID) === 0 ? '0' : '1';

                return $priority.'|'.strtolower($code).'|'.strtolower($name);
            })
            ->values();

        $updatedLocationMap = $updatedBranchOptions
            ->keyBy(fn (array $item): string => $this->clean((string) ($item['code'] ?? '')));

        return [$updatedLocationMap, $updatedBranchOptions];
    }

    private function clean(?string $value): string
    {
        return trim((string) $value);
    }

    private function advancePayrollRange(Carbon $payrollMonth): array
    {
        $anchor = $payrollMonth->copy()->startOfMonth();

        return [
            'start' => $anchor->copy()->day(13)->startOfDay(),
            'end' => $anchor->copy()->addMonthNoOverflow()->day(11)->startOfDay(),
        ];
    }

    private function advanceRangeForDate(Carbon $advanceDate): ?array
    {
        $date = $advanceDate->copy()->startOfDay();

        if ($date->day <= 11) {
            return $this->advancePayrollRange($date->copy()->subMonthNoOverflow()->startOfMonth());
        }

        if ($date->day >= 13) {
            return $this->advancePayrollRange($date->copy()->startOfMonth());
        }

        return null;
    }
}
