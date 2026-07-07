<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeBankDetailRequest;
use App\Models\EmployeeDetail;
use App\Services\EmployeeNotificationDispatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class BankDetailRequestController extends Controller
{
    private const TAB_PENDING_APPROVAL = 'pending_approval';
    private const TAB_VERIFICATION = 'verification';
    private const TAB_APPROVED = 'approved';

    public function __construct(
        private readonly EmployeeNotificationDispatchService $notificationDispatchService
    ) {
    }

    public function index(Request $request): View
    {
        $filters = [
            'tab' => $this->normalizeTab($request->input('tab')),
            'emp_id' => trim((string) $request->input('emp_id')),
        ];

        $summaryQuery = $this->baseRequestQuery()
            ->when(
                $filters['emp_id'] !== '',
                fn ($query) => $query->where('emp_id', 'like', '%'.$filters['emp_id'].'%')
            );

        $rows = $this->baseRequestQuery()
            ->when(
                $filters['emp_id'] !== '',
                fn ($query) => $query->where('emp_id', 'like', '%'.$filters['emp_id'].'%')
            )
            ->whereIn('status', $this->tabStatuses($filters['tab']))
            ->orderByRaw("FIELD(status, 'pending', 'approved', 'submitted', 'verified', 'rejected')")
            ->orderByDesc('id')
            ->get();

        $this->attachEmployeeDetails($rows);

        return view('admin.salary.bank_detail_requests', [
            'filters' => $filters,
            'rows' => $rows->map(fn (EmployeeBankDetailRequest $requestRecord): array => $this->rowPayload($requestRecord))->values(),
            'summary' => [
                'pending_approval' => (clone $summaryQuery)->where('status', EmployeeBankDetailRequest::STATUS_PENDING)->count(),
                'approved_waiting' => (clone $summaryQuery)->where('status', EmployeeBankDetailRequest::STATUS_APPROVED)->count(),
                'pending_verification' => (clone $summaryQuery)->where('status', EmployeeBankDetailRequest::STATUS_SUBMITTED)->count(),
            ],
        ]);
    }

    public function approve(Request $request, EmployeeBankDetailRequest $requestRecord): RedirectResponse
    {
        if ($this->isOutsourcedEmployeeRequest($requestRecord)) {
            return redirect()->route('admin-bank-detail-requests')->with('status', 'Outsourced employee requests are not managed from this salary module.');
        }

        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($requestRecord->status !== EmployeeBankDetailRequest::STATUS_PENDING) {
            return back()->with('status', 'Only pending requests can be approved.');
        }

        $this->approveRequest($requestRecord, trim((string) ($data['admin_note'] ?? '')) ?: null);

        return redirect()->route('admin-bank-detail-requests')->with('status', 'Bank detail request approved.');
    }

    public function reject(Request $request, EmployeeBankDetailRequest $requestRecord): RedirectResponse
    {
        if ($this->isOutsourcedEmployeeRequest($requestRecord)) {
            return redirect()->route('admin-bank-detail-requests')->with('status', 'Outsourced employee requests are not managed from this salary module.');
        }

        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! in_array($requestRecord->status, [EmployeeBankDetailRequest::STATUS_PENDING, EmployeeBankDetailRequest::STATUS_APPROVED, EmployeeBankDetailRequest::STATUS_SUBMITTED], true)) {
            return back()->with('status', 'This request cannot be rejected now.');
        }

        $this->rejectRequest($requestRecord, trim((string) ($data['admin_note'] ?? '')) ?: null);

        return redirect()->route('admin-bank-detail-requests')->with('status', 'Bank detail request rejected.');
    }

    public function verify(Request $request, EmployeeBankDetailRequest $requestRecord): RedirectResponse
    {
        if ($this->isOutsourcedEmployeeRequest($requestRecord)) {
            return redirect()->route('admin-bank-detail-requests')->with('status', 'Outsourced employee requests are not managed from this salary module.');
        }

        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($requestRecord->status !== EmployeeBankDetailRequest::STATUS_SUBMITTED) {
            return back()->with('status', 'Only submitted bank changes can be verified.');
        }

        $error = $this->verifyRequest($requestRecord, trim((string) ($data['admin_note'] ?? '')) ?: null);

        if ($error !== null) {
            return back()->with('status', $error);
        }

        return redirect()->route('admin-bank-detail-requests')->with('status', 'Bank detail changes verified.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tab' => ['required', 'string'],
            'bulk_action' => ['required', 'in:approve,reject,verify'],
            'scope' => ['required', 'in:selected,all'],
            'request_ids' => ['nullable', 'array'],
            'request_ids.*' => ['integer'],
            'emp_id' => ['nullable', 'string', 'max:100'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $tab = $this->normalizeTab($data['tab'] ?? self::TAB_PENDING_APPROVAL);
        $action = (string) $data['bulk_action'];
        $scope = (string) $data['scope'];
        $empId = trim((string) ($data['emp_id'] ?? ''));
        $ids = collect($data['request_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if (! $this->isBulkActionAllowedForTab($action, $tab)) {
            return redirect()
                ->route('admin-bank-detail-requests', ['tab' => $tab, 'emp_id' => $empId])
                ->with('status', 'That bulk action is not available for this tab.');
        }

        if ($scope === 'selected' && $ids->isEmpty()) {
            return redirect()
                ->route('admin-bank-detail-requests', ['tab' => $tab, 'emp_id' => $empId])
                ->with('status', 'Select at least one bank detail request.');
        }

        $records = $this->baseRequestQuery()
            ->whereIn('status', $this->eligibleStatusesForBulkAction($action))
            ->when(
                $scope === 'selected',
                fn ($query) => $query->whereIn('id', $ids->all())
            )
            ->when(
                $scope === 'all',
                fn ($query) => $query->whereIn('status', $this->tabStatuses($tab))
            )
            ->when(
                $empId !== '',
                fn ($query) => $query->where('emp_id', 'like', '%'.$empId.'%')
            )
            ->orderBy('id')
            ->get();

        if ($records->isEmpty()) {
            return redirect()
                ->route('admin-bank-detail-requests', ['tab' => $tab, 'emp_id' => $empId])
                ->with('status', 'No eligible bank detail requests were found for this bulk action.');
        }

        $adminNote = trim((string) ($data['admin_note'] ?? '')) ?: null;
        $updated = 0;
        $skipped = 0;

        foreach ($records as $requestRecord) {
            $error = match ($action) {
                'approve' => $this->approveRequest($requestRecord, $adminNote),
                'reject' => $this->rejectRequest($requestRecord, $adminNote),
                'verify' => $this->verifyRequest($requestRecord, $adminNote),
            };

            if ($error === null) {
                $updated++;
            } else {
                $skipped++;
            }
        }

        $message = $this->bulkSuccessMessage($action, $updated);

        if ($skipped > 0) {
            $message .= ' Skipped '.$skipped.' request'.($skipped === 1 ? '' : 's').'.';
        }

        return redirect()
            ->route('admin-bank-detail-requests', ['tab' => $tab, 'emp_id' => $empId])
            ->with('status', $message);
    }

    private function approveRequest(EmployeeBankDetailRequest $requestRecord, ?string $adminNote): ?string
    {
        if ($this->isOutsourcedEmployeeRequest($requestRecord)) {
            return 'Outsourced employee requests are not managed from this salary module.';
        }

        if ($requestRecord->status !== EmployeeBankDetailRequest::STATUS_PENDING) {
            return 'Only pending requests can be approved.';
        }

        $admin = auth('admin')->user();
        $requestRecord->update([
            'status' => EmployeeBankDetailRequest::STATUS_APPROVED,
            'admin_note' => $adminNote,
            'approved_by' => trim((string) ($admin->email ?? $admin->name ?? 'admin')),
            'approved_at' => now(),
        ]);

        if ($requestRecord->employee) {
            $this->notificationDispatchService->sendToEmployee(
                $requestRecord->employee,
                'Bank Edit Request Approved',
                'Your bank detail edit request was approved. You can now update your bank details in the employee app.',
                auth('admin')->id()
            );
        }

        return null;
    }

    private function rejectRequest(EmployeeBankDetailRequest $requestRecord, ?string $adminNote): ?string
    {
        if ($this->isOutsourcedEmployeeRequest($requestRecord)) {
            return 'Outsourced employee requests are not managed from this salary module.';
        }

        if (! in_array($requestRecord->status, [EmployeeBankDetailRequest::STATUS_PENDING, EmployeeBankDetailRequest::STATUS_APPROVED, EmployeeBankDetailRequest::STATUS_SUBMITTED], true)) {
            return 'This request cannot be rejected now.';
        }

        $wasSubmitted = $requestRecord->status === EmployeeBankDetailRequest::STATUS_SUBMITTED;
        $admin = auth('admin')->user();

        $requestRecord->update([
            'status' => EmployeeBankDetailRequest::STATUS_REJECTED,
            'admin_note' => $adminNote,
            'approved_by' => trim((string) ($admin->email ?? $admin->name ?? 'admin')),
            'approved_at' => now(),
        ]);

        if ($requestRecord->employee) {
            $message = $wasSubmitted
                ? 'Your submitted bank detail changes were rejected.'
                : 'Your bank detail edit request was rejected.';

            if ($adminNote !== null) {
                $message .= ' Note: '.$adminNote;
            }

            $this->notificationDispatchService->sendToEmployee(
                $requestRecord->employee,
                $wasSubmitted ? 'Bank Detail Changes Rejected' : 'Bank Edit Request Rejected',
                $message,
                auth('admin')->id()
            );
        }

        return null;
    }

    private function verifyRequest(EmployeeBankDetailRequest $requestRecord, ?string $adminNote): ?string
    {
        if ($this->isOutsourcedEmployeeRequest($requestRecord)) {
            return 'Outsourced employee requests are not managed from this salary module.';
        }

        if ($requestRecord->status !== EmployeeBankDetailRequest::STATUS_SUBMITTED) {
            return 'Only submitted bank changes can be verified.';
        }

        $employee = $requestRecord->employee;

        if (! $employee) {
            return 'Employee record is missing for this request.';
        }

        $detail = $this->employeeDetail($employee) ?? new EmployeeDetail();
        $branchId = $this->latestAttendanceBranchId($employee);
        $now = Carbon::now(config('app.timezone', 'Asia/Kolkata'));

        $detail->employeeId = trim((string) $employee->empId);
        $detail->empName = trim((string) ($requestRecord->requested_emp_name ?: $detail->empName ?: $employee->name));
        $detail->designation = trim((string) ($detail->designation ?: $employee->designation));
        $detail->bankName = trim((string) ($requestRecord->requested_bank_name ?: $detail->bankName));
        $detail->bankAcNo = trim((string) ($requestRecord->requested_bank_ac_no ?: $detail->bankAcNo));
        $detail->ifscCode = trim((string) ($requestRecord->requested_ifsc_code ?: $detail->ifscCode));
        $detail->uanNumber = trim((string) ($requestRecord->requested_uan_number ?: $detail->uanNumber));
        $detail->passbookDoc = trim((string) ($requestRecord->requested_passbook_doc ?: $detail->passbookDoc));
        $detail->salary = is_numeric($detail->salary) ? $detail->salary : (is_numeric($employee->salary) ? $employee->salary : 0);
        $detail->branchId = trim((string) ($detail->branchId ?: $branchId));
        $detail->status = trim((string) ($detail->status ?: 'Active'));
        $detail->accountVerified = 'Verified';
        $detail->date = $detail->date ?: $now->toDateString();
        $detail->time = $detail->time ?: $now->format('H:i:s');
        $detail->totalWorkingDays = is_numeric($detail->totalWorkingDays) ? $detail->totalWorkingDays : 0;
        $detail->absentDays = is_numeric($detail->absentDays) ? $detail->absentDays : 0;
        $detail->presentDays = is_numeric($detail->presentDays) ? $detail->presentDays : 0;
        $detail->penalty = is_numeric($detail->penalty) ? $detail->penalty : 0;
        $detail->advanceSalary = is_numeric($detail->advanceSalary) ? $detail->advanceSalary : 0;
        $detail->finalSalary = is_numeric($detail->finalSalary) ? $detail->finalSalary : 0;
        $detail->salaryPaymentStatus = trim((string) ($detail->salaryPaymentStatus ?? ''));
        $detail->salaryPaidBy = trim((string) ($detail->salaryPaidBy ?? ''));
        $detail->salaryBankName = trim((string) ($detail->salaryBankName ?? ''));
        $detail->salaryProcessingBy = trim((string) ($detail->salaryProcessingBy ?? ''));
        $detail->salaryProcessingUser = trim((string) ($detail->salaryProcessingUser ?? ''));
        $detail->aadhaarNo = trim((string) ($detail->aadhaarNo ?? ''));
        $detail->pfAmount = is_numeric($detail->pfAmount) ? $detail->pfAmount : (is_numeric($employee->pf) ? $employee->pf : 0);
        $detail->save();

        $admin = auth('admin')->user();
        $requestRecord->update([
            'status' => EmployeeBankDetailRequest::STATUS_VERIFIED,
            'admin_note' => $adminNote,
            'verified_by' => trim((string) ($admin->email ?? $admin->name ?? 'admin')),
            'verified_at' => now(),
        ]);

        $message = 'Your bank detail changes were verified successfully.';

        if ($adminNote !== null) {
            $message .= ' Note: '.$adminNote;
        }

        $this->notificationDispatchService->sendToEmployee(
            $employee,
            'Bank Details Verified',
            $message,
            auth('admin')->id()
        );

        return null;
    }

    private function baseRequestQuery()
    {
        return EmployeeBankDetailRequest::query()
            ->with('employee:id,empId,name,designation,is_outsourced')
            ->whereHas('employee', function ($query): void {
                $query->where(function ($employeeQuery): void {
                    $employeeQuery->where('is_outsourced', false)
                        ->orWhereNull('is_outsourced');
                });
            });
    }

    private function normalizeTab(mixed $tab): string
    {
        $tab = trim((string) $tab);

        return in_array($tab, [self::TAB_PENDING_APPROVAL, self::TAB_VERIFICATION, self::TAB_APPROVED], true)
            ? $tab
            : self::TAB_PENDING_APPROVAL;
    }

    private function tabStatuses(string $tab): array
    {
        return match ($tab) {
            self::TAB_VERIFICATION => [EmployeeBankDetailRequest::STATUS_SUBMITTED],
            self::TAB_APPROVED => [EmployeeBankDetailRequest::STATUS_APPROVED],
            default => [EmployeeBankDetailRequest::STATUS_PENDING],
        };
    }

    private function isBulkActionAllowedForTab(string $action, string $tab): bool
    {
        return match ($tab) {
            self::TAB_VERIFICATION => in_array($action, ['verify', 'reject'], true),
            self::TAB_APPROVED => $action === 'reject',
            default => in_array($action, ['approve', 'reject'], true),
        };
    }

    private function eligibleStatusesForBulkAction(string $action): array
    {
        return match ($action) {
            'approve' => [EmployeeBankDetailRequest::STATUS_PENDING],
            'verify' => [EmployeeBankDetailRequest::STATUS_SUBMITTED],
            default => [
                EmployeeBankDetailRequest::STATUS_PENDING,
                EmployeeBankDetailRequest::STATUS_APPROVED,
                EmployeeBankDetailRequest::STATUS_SUBMITTED,
            ],
        };
    }

    private function bulkSuccessMessage(string $action, int $updated): string
    {
        $label = match ($action) {
            'approve' => 'approved',
            'verify' => 'verified',
            default => 'rejected',
        };

        return $updated.' bank detail request'.($updated === 1 ? '' : 's').' '.$label.'.';
    }

    private function attachEmployeeDetails(Collection $requests): void
    {
        $employees = $requests
            ->map(fn (EmployeeBankDetailRequest $requestRecord): ?Employee => $requestRecord->employee)
            ->filter()
            ->values();

        if ($employees->isEmpty()) {
            return;
        }

        $detailsByEmpId = EmployeeDetail::query()
            ->whereIn('employeeId', $employees->pluck('empId')->map(fn ($empId): string => trim((string) $empId))->filter()->values()->all())
            ->orderByDesc('id')
            ->get()
            ->unique(fn (EmployeeDetail $detail): string => trim((string) $detail->employeeId))
            ->keyBy(fn (EmployeeDetail $detail): string => trim((string) $detail->employeeId));

        foreach ($employees as $employee) {
            $employee->setRelation('detail', $detailsByEmpId->get(trim((string) $employee->empId)));
        }
    }

    private function rowPayload(EmployeeBankDetailRequest $requestRecord): array
    {
        $employee = $requestRecord->employee;
        $detail = $employee?->detail;

        return [
            'id' => $requestRecord->id,
            'status' => trim((string) $requestRecord->status),
            'emp_id' => trim((string) $requestRecord->emp_id),
            'employee_name' => trim((string) ($employee?->name ?? '')) ?: '--',
            'designation' => trim((string) ($employee?->designation ?? '')) ?: '--',
            'request_note' => trim((string) $requestRecord->request_note),
            'admin_note' => trim((string) $requestRecord->admin_note),
            'current_account_name' => trim((string) ($detail?->empName ?? '')),
            'current_bank_name' => trim((string) ($detail?->bankName ?? '')),
            'current_bank_ac_no' => trim((string) ($detail?->bankAcNo ?? '')),
            'current_ifsc_code' => trim((string) ($detail?->ifscCode ?? '')),
            'current_uan_number' => trim((string) ($detail?->uanNumber ?? '')),
            'requested_account_name' => trim((string) $requestRecord->requested_emp_name),
            'requested_bank_name' => trim((string) $requestRecord->requested_bank_name),
            'requested_bank_ac_no' => trim((string) $requestRecord->requested_bank_ac_no),
            'requested_ifsc_code' => trim((string) $requestRecord->requested_ifsc_code),
            'requested_uan_number' => trim((string) $requestRecord->requested_uan_number),
            'created_at' => $requestRecord->created_at?->format('d M Y h:i A') ?: '--',
            'approved_at' => $requestRecord->approved_at?->format('d M Y h:i A') ?: '--',
            'submitted_at' => $requestRecord->submitted_at?->format('d M Y h:i A') ?: '--',
            'verified_at' => $requestRecord->verified_at?->format('d M Y h:i A') ?: '--',
        ];
    }

    private function employeeDetail(Employee $employee): ?EmployeeDetail
    {
        return EmployeeDetail::query()
            ->whereRaw('TRIM(employeeId) = ?', [trim((string) $employee->empId)])
            ->orderByDesc('id')
            ->first();
    }

    private function latestAttendanceBranchId(Employee $employee): string
    {
        $attendance = Attendance::query()
            ->whereRaw('TRIM(empId) = ?', [trim((string) $employee->empId)])
            ->latest('id')
            ->first(['check_in_branch_id', 'check_out_branch_id']);

        if (! $attendance) {
            return '';
        }

        return trim((string) ($attendance->check_out_branch_id ?: $attendance->check_in_branch_id));
    }

    private function isOutsourcedEmployeeRequest(EmployeeBankDetailRequest $requestRecord): bool
    {
        return (bool) ($requestRecord->employee?->is_outsourced ?? false);
    }
}
