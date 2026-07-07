<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeAdvanceTransaction;
use App\Models\EmployeeBankDetailRequest;
use App\Models\EmployeeDetail;
use App\Models\OutsourceLocation;
use App\Services\EmployeeBranchOpeningService;
use App\Support\MayPfEligibility;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Facades\Image;

class EmployeeAuthController extends Controller
{
    public function __construct(
        private readonly EmployeeBranchOpeningService $employeeBranchOpeningService
    ) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'branchId' => ['required', 'string'],
            'empId' => ['required', 'string'],
        ]);

        $branchId = trim($credentials['branchId']);
        $empId = trim($credentials['empId']);

        $employee = Employee::query()
            ->whereRaw('TRIM(empId) = ?', [$empId])
            ->first();

        if (! $employee) {
            throw ValidationException::withMessages([
                'branchId' => ['Invalid branch ID or employee ID.'],
            ]);
        }

        if ($employee->isInactive()) {
            throw ValidationException::withMessages([
                'empId' => ['This employee account is inactive. Please contact HR.'],
            ]);
        }

        $branch = null;
        $outsourceLocation = null;

        if ($this->isOutsourcedEmployee($employee)) {
            $outsourceLocation = $this->findOutsourceLocation($branchId, $employee);
        } else {
            $branch = $this->findBranch($branchId);
        }

        if (! $branch && ! $outsourceLocation) {
            throw ValidationException::withMessages([
                'branchId' => ['Invalid branch ID or employee ID.'],
            ]);
        }

        $employee->last_login_branch_id = $branchId;
        $employee->last_login_at = Carbon::now(config('app.timezone', 'Asia/Kolkata'));
        $employee->save();

        $employee->tokens()->delete();
        $token = $employee->createToken('flutter-login', ['branch:' . $branchId])->plainTextToken;

        return response()->json([
            'token' => $token,
            'employee' => $this->employeePayload($employee, $branch, $outsourceLocation, $branchId),
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        $employee = $request->user();

        abort_unless($employee instanceof Employee, 401);

        $branchId = $this->resolveBranchId($employee, $request);
        $locationDetails = $this->resolveLocationDetails($employee, $branchId);

        return response()->json([
            'employee' => $this->employeePayload(
                $employee,
                $locationDetails['branch'],
                $locationDetails['outsourceLocation'],
                $branchId
            ),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $employee = $request->user();

        abort_unless($employee instanceof Employee, 401);

        $hasAadhaarInput = $request->exists('aadhaarNumber');
        $hasPanInput = $request->exists('panNumber');
        $panNumber = strtoupper($this->clean($request->input('panNumber')));
        $request->merge([
            'panNumber' => $panNumber !== '' ? $panNumber : null,
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:50'],
            'mailId' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'dateOfBirth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'gender' => ['nullable', 'string', 'in:Male,Female,Other'],
            'maritalStatus' => ['nullable', 'string', 'in:Single,Married,Divorced,Widowed'],
            'aadhaarNumber' => ['nullable', 'digits:12'],
            'panNumber' => ['nullable', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
        ]);

        $employee->name = $this->clean($data['name'] ?? null);
        $employee->contact = $this->clean($data['contact'] ?? null);
        $employee->mailId = $this->clean($data['mailId'] ?? null);
        $employee->address = $this->clean($data['address'] ?? null);
        $employee->date_of_birth = $this->normalizeDate($data['dateOfBirth'] ?? null);
        $employee->gender = $this->clean($data['gender'] ?? null);
        $employee->marital_status = $this->clean($data['maritalStatus'] ?? null);
        $employee->save();

        if ($hasAadhaarInput || $hasPanInput) {
            $detail = $this->employeeDetail($employee) ?? $this->newEmployeeDetail($employee);
            if ($hasAadhaarInput) {
                $detail->aadhaarNo = $this->clean($data['aadhaarNumber'] ?? null);
            }
            if ($hasPanInput && Schema::hasColumn('employeeDetails', 'panNo')) {
                $detail->panNo = strtoupper($this->clean($data['panNumber'] ?? null));
            }
            $detail->save();
        }

        $branchId = $this->resolveBranchId($employee, $request);
        $locationDetails = $this->resolveLocationDetails($employee, $branchId);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'employee' => $this->employeePayload(
                $employee,
                $locationDetails['branch'],
                $locationDetails['outsourceLocation'],
                $branchId
            ),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function updatePhoto(Request $request): JsonResponse
    {
        $employee = $request->user();

        abort_unless($employee instanceof Employee, 401);

        $request->validate([
            'photo' => ['required', 'image', 'max:5120'],
        ]);

        $employee->photo = $this->storeEmployeePhoto(
            $request->file('photo'),
            $employee,
            $employee->photo
        );
        $employee->save();

        $branchId = $this->resolveBranchId($employee, $request);
        $locationDetails = $this->resolveLocationDetails($employee, $branchId);

        return response()->json([
            'message' => 'Profile photo updated successfully.',
            'employee' => $this->employeePayload(
                $employee,
                $locationDetails['branch'],
                $locationDetails['outsourceLocation'],
                $branchId
            ),
        ]);
    }

    private function employeePayload(
        Employee $employee,
        ?Branch $branch = null,
        ?OutsourceLocation $outsourceLocation = null,
        ?string $branchId = null
    ): array {
        $effectiveAdvance = $this->syncEffectiveAdvance($employee);
        $detail = $this->employeeDetail($employee);
        $bankDetailRequest = $this->latestBankDetailRequest($employee);
        $resolvedBranchId = $this->clean($branch?->branchId)
            ?: $this->clean($outsourceLocation?->location_code)
            ?: $this->clean($branchId)
            ?: $this->latestAttendanceBranchId($employee);
        $branchOpening = $this->branchOpeningPayload($resolvedBranchId, $employee);
        $salary = is_numeric($detail?->salary)
            ? (int) round((float) $detail->salary)
            : $this->parseNullableInt($employee->salary);
        $configuredPf = is_numeric($detail?->pfAmount)
            ? (float) $detail->pfAmount
            : (float) ($this->parseNullableInt($employee->pf) ?? 0);
        $pf = (int) round(MayPfEligibility::deductionFor(
            Carbon::now(config('app.timezone', 'Asia/Kolkata'))->startOfMonth(),
            $employee->empId,
            $detail?->uanNumber,
            $configuredPf,
            $employee->pf_eligible
        ));

        return [
            'id' => $employee->id,
            'branchId' => $resolvedBranchId,
            'branchTableId' => $branch?->id,
            'branchName' => $this->clean($branch?->branchName) ?: $this->clean($outsourceLocation?->name),
            'branchLatitude' => $this->parseNullableFloat($branch?->latitude ?? $outsourceLocation?->latitude),
            'branchLongitude' => $this->parseNullableFloat($branch?->longitude ?? $outsourceLocation?->longitude),
            'empId' => $this->clean($employee->empId),
            'name' => $this->clean($employee->name),
            'contact' => $this->clean($employee->contact),
            'mailId' => $this->clean($employee->mailId),
            'address' => $this->clean($employee->address),
            'dateOfBirth' => $this->normalizeDate($employee->date_of_birth),
            'gender' => $this->clean($employee->gender),
            'maritalStatus' => $this->clean($employee->marital_status),
            'aadhaarNumber' => $this->clean($detail?->aadhaarNo),
            'panNumber' => Schema::hasColumn('employeeDetails', 'panNo') ? $this->clean($detail?->panNo) : '',
            'location' => $this->clean($employee->location),
            'designation' => $this->clean($employee->designation),
            'photo' => $this->clean($employee->photo),
            'photoUrl' => $this->resolvePhotoUrl($employee->photo),
            'rating' => (int) ($employee->rating ?? 0),
            'status' => $this->clean($employee->status),
            'isOutsourced' => $this->isOutsourcedEmployee($employee),
            'outsourceLocations' => $this->employeeOutsourceLocationsPayload($employee),
            'isNightShift' => (bool) $employee->is_night_shift,
            'shiftTiming' => $this->clean($employee->shift_timing),
            'salary' => $salary,
            'advance' => $this->parseNullableInt($effectiveAdvance),
            'pf' => $pf,
            'bankDetails' => $this->bankDetailsPayload($detail, $bankDetailRequest),
            'isBranchOpeningAssigned' => $branchOpening['is_assigned'],
            'isBranchOpeningEmployee' => $branchOpening['is_opener'],
            'branchOpeningHasDoorKey' => $branchOpening['has_door_key'],
            'branchOpeningHasLockerKey' => $branchOpening['has_locker_key'],
            'branchOpeningTime' => $branchOpening['opening_time'],
            'branchOpeningAdminPhone' => $branchOpening['admin_phone'],
            'branchOpeningStatus' => $branchOpening['status'],
            'branchOpeningOpenedAt' => $branchOpening['opened_at'],
            'branchOpeningOpenedByLabel' => $branchOpening['opened_by_label'],
            'branchOpeningClosedAt' => $branchOpening['closed_at'],
            'branchOpeningClosedByLabel' => $branchOpening['closed_by_label'],
            'branchOpeningCanMarkOpened' => $branchOpening['can_mark_opened'],
            'branchOpeningCanMarkClosed' => $branchOpening['can_mark_closed'],
            'branchOpeningReminderStartMinutes' => 120,
            'branchOpeningReminderIntervalMinutes' => 15,
            'branchOpeningNotificationsManagedByServer' => $branchOpening['managed_by_server'],
        ];
    }

    private function bankDetailsPayload(
        ?EmployeeDetail $detail,
        ?EmployeeBankDetailRequest $requestRecord
    ): array {
        $status = strtolower($this->clean($requestRecord?->status));
        $requestStatus = $status !== '' ? $status : 'none';
        $hasPendingSubmittedChanges = in_array($requestStatus, [
            EmployeeBankDetailRequest::STATUS_APPROVED,
            EmployeeBankDetailRequest::STATUS_SUBMITTED,
        ], true);
        $verificationStatus = $requestStatus === EmployeeBankDetailRequest::STATUS_SUBMITTED
            ? 'Pending'
            : ($this->clean($detail?->accountVerified) ?: ($requestStatus === EmployeeBankDetailRequest::STATUS_VERIFIED ? 'Verified' : 'Not Submitted'));

        return [
            'accountName' => $this->clean($detail?->empName),
            'bankName' => $this->clean($detail?->bankName),
            'bankAccountNumber' => $this->clean($detail?->bankAcNo),
            'ifscCode' => $this->clean($detail?->ifscCode),
            'uanNumber' => $this->clean($detail?->uanNumber),
            'passbookDocUrl' => $this->passbookDocumentUrl($detail?->passbookDoc),
            'verificationStatus' => $verificationStatus,
            'requestStatus' => $requestStatus,
            'requestNote' => $requestStatus === EmployeeBankDetailRequest::STATUS_VERIFIED ? '' : $this->clean($requestRecord?->request_note),
            'adminNote' => $requestStatus === EmployeeBankDetailRequest::STATUS_VERIFIED ? '' : $this->clean($requestRecord?->admin_note),
            'canRequestEdit' => in_array($requestStatus, ['none', EmployeeBankDetailRequest::STATUS_REJECTED, EmployeeBankDetailRequest::STATUS_VERIFIED], true),
            'canEdit' => $requestStatus === EmployeeBankDetailRequest::STATUS_APPROVED,
            'pendingAccountName' => $hasPendingSubmittedChanges ? $this->clean($requestRecord?->requested_emp_name) : '',
            'pendingBankName' => $hasPendingSubmittedChanges ? $this->clean($requestRecord?->requested_bank_name) : '',
            'pendingBankAccountNumber' => $hasPendingSubmittedChanges ? $this->clean($requestRecord?->requested_bank_ac_no) : '',
            'pendingIfscCode' => $hasPendingSubmittedChanges ? $this->clean($requestRecord?->requested_ifsc_code) : '',
            'pendingUanNumber' => $hasPendingSubmittedChanges ? $this->clean($requestRecord?->requested_uan_number) : '',
            'pendingPassbookDocUrl' => $hasPendingSubmittedChanges ? $this->resolvePhotoUrl($requestRecord?->requested_passbook_doc) : '',
        ];
    }

    private function syncEffectiveAdvance(Employee $employee): int
    {
        $effectiveAdvance = (int) round((float) EmployeeAdvanceTransaction::query()
            ->where('employee_id', $employee->id)
            ->sum('amount'));

        $storedAdvance = is_numeric($employee->advance)
            ? (int) round((float) $employee->advance)
            : 0;

        if ($storedAdvance !== $effectiveAdvance) {
            $employee->advance = $effectiveAdvance;
            $employee->save();
        }

        return $effectiveAdvance;
    }

    private function branchOpeningPayload(string $branchId, Employee $employee): array
    {
        return $this->employeeBranchOpeningService->payloadForEmployee(
            $branchId,
            $employee
        );
    }

    private function clean(?string $value): string
    {
        return trim((string) $value);
    }

    private function parseNullableFloat($value): ?float
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '' || ! is_numeric($trimmed)) {
            return null;
        }

        return (float) $trimmed;
    }

    private function parseNullableInt($value): ?int
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '' || ! is_numeric($trimmed)) {
            return null;
        }

        return (int) $trimmed;
    }

    private function normalizeDate($value): ?string
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        return Carbon::parse($trimmed)->toDateString();
    }

    private function resolveBranchId(Employee $employee, Request $request): string
    {
        $branchId = $this->branchIdFromToken($request);

        if ($branchId !== '') {
            return $branchId;
        }

        return $this->latestAttendanceBranchId($employee);
    }

    private function branchIdFromToken(Request $request): string
    {
        $token = $request->user()?->currentAccessToken();
        $abilities = is_array($token?->abilities) ? $token->abilities : [];

        foreach ($abilities as $ability) {
            if (str_starts_with((string) $ability, 'branch:')) {
                return $this->clean(substr((string) $ability, 7));
            }
        }

        return '';
    }

    private function latestAttendanceBranchId(Employee $employee): string
    {
        $attendance = Attendance::query()
            ->where('empId', $this->clean($employee->empId))
            ->latest('id')
            ->first(['check_in_branch_id', 'check_out_branch_id']);

        if (! $attendance) {
            return '';
        }

        $branchId = $this->clean($attendance->check_out_branch_id);

        if ($branchId !== '') {
            return $branchId;
        }

        return $this->clean($attendance->check_in_branch_id);
    }

    private function findBranch(?string $branchId): ?Branch
    {
        $branchId = $this->clean($branchId);

        if ($branchId === '') {
            return null;
        }

        return Branch::query()
            ->select(['id', 'branchId', 'branchName', 'latitude', 'longitude'])
            ->whereRaw('TRIM(branchId) = ?', [$branchId])
            ->first();
    }

    private function findOutsourceLocation(?string $locationCode, ?Employee $employee = null): ?OutsourceLocation
    {
        $locationCode = $this->clean($locationCode);

        if ($locationCode === '') {
            return null;
        }

        $query = OutsourceLocation::query()
            ->whereRaw('TRIM(location_code) = ?', [$locationCode])
            ->where('status', 1);

        if ($employee) {
            $query->whereHas('employees', function ($employeeQuery) use ($employee): void {
                $employeeQuery->where('employee.id', $employee->id);
            });
        }

        return $query->first();
    }

    private function resolveLocationDetails(Employee $employee, string $branchId): array
    {
        $branch = null;
        $outsourceLocation = null;

        if ($this->isOutsourcedEmployee($employee)) {
            $outsourceLocation = $this->findOutsourceLocation($branchId, $employee);
            if (! $outsourceLocation) {
                $outsourceLocation = $employee->outsourceLocations()
                    ->where('status', 1)
                    ->orderBy('location_code')
                    ->first();
            }
        } else {
            $branch = $this->findBranch($branchId);
        }

        return [
            'branch' => $branch,
            'outsourceLocation' => $outsourceLocation,
        ];
    }

    private function isOutsourcedEmployee(Employee $employee): bool
    {
        return (bool) $employee->is_outsourced;
    }

    private function employeeOutsourceLocationsPayload(Employee $employee): array
    {
        if (! $this->isOutsourcedEmployee($employee)) {
            return [];
        }

        return $employee->outsourceLocations()
            ->where('status', 1)
            ->orderBy('location_code')
            ->get()
            ->map(function (OutsourceLocation $location): array {
                return [
                    'id' => (int) $location->id,
                    'locationCode' => $this->clean($location->location_code),
                    'name' => $this->clean($location->name),
                    'latitude' => $this->parseNullableFloat($location->latitude),
                    'longitude' => $this->parseNullableFloat($location->longitude),
                    'addressline' => $this->clean($location->addressline),
                    'area' => $this->clean($location->area),
                    'city' => $this->clean($location->city),
                    'state' => $this->clean($location->state),
                    'pincode' => $this->clean($location->pincode),
                    'url' => $this->clean($location->url),
                ];
            })
            ->values()
            ->all();
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

    private function passbookDocumentUrl(?string $path): string
    {
        $trimmed = trim((string) $path);

        if ($trimmed === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $trimmed) === 1) {
            return $trimmed;
        }

        $normalizedPath = ltrim(str_replace('\\', '/', $trimmed), '/');

        if (str_starts_with($normalizedPath, 'public/')) {
            $normalizedPath = substr($normalizedPath, 7);
        }

        if (! str_contains($normalizedPath, '/')) {
            $normalizedPath = 'EmployeeDocuments/'.$normalizedPath;
        }

        return function_exists('project_asset')
            ? \project_asset($normalizedPath)
            : asset($normalizedPath);
    }

    private function employeeDetail(Employee $employee): ?EmployeeDetail
    {
        return EmployeeDetail::query()
            ->whereRaw('TRIM(employeeId) = ?', [$this->clean($employee->empId)])
            ->orderByDesc('id')
            ->first();
    }

    private function newEmployeeDetail(Employee $employee): EmployeeDetail
    {
        $detail = new EmployeeDetail();
        $now = Carbon::now(config('app.timezone', 'Asia/Kolkata'));

        $detail->employeeId = $this->clean($employee->empId);
        $detail->empName = $this->clean($employee->name);
        $detail->designation = $this->clean($employee->designation);
        $detail->bankName = '';
        $detail->bankAcNo = '';
        $detail->ifscCode = '';
        $detail->passbookDoc = '';
        $detail->salary = is_numeric($employee->salary) ? $employee->salary : 0;
        $detail->branchId = $this->latestAttendanceBranchId($employee);
        $detail->status = 'Active';
        $detail->accountVerified = 'Pending';
        $detail->date = $now->toDateString();
        $detail->time = $now->format('H:i:s');
        $detail->totalWorkingDays = 0;
        $detail->absentDays = 0;
        $detail->presentDays = 0;
        $detail->penalty = 0;
        $detail->advanceSalary = 0;
        $detail->finalSalary = 0;
        $detail->salaryPaymentStatus = '';
        $detail->salaryPaidBy = '';
        $detail->salaryBankName = '';
        $detail->salaryProcessingBy = '';
        $detail->salaryProcessingUser = '';
        $detail->aadhaarNo = '';
        if (Schema::hasColumn('employeeDetails', 'panNo')) {
            $detail->panNo = '';
        }
        $detail->uanNumber = '';
        $detail->pfAmount = is_numeric($employee->pf) ? $employee->pf : 0;

        return $detail;
    }

    private function latestBankDetailRequest(Employee $employee): ?EmployeeBankDetailRequest
    {
        return EmployeeBankDetailRequest::query()
            ->where('employee_id', $employee->id)
            ->latest('id')
            ->first();
    }

    private function storeEmployeePhoto($image, Employee $employee, ?string $existingPhoto): string
    {
        $directory = public_path('storage/ProfileImage');

        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0777, true);
        }

        $existingPath = trim((string) $existingPhoto);
        if ($existingPath !== '') {
            $absoluteExistingPath = public_path(ltrim($existingPath, '/'));
            if (File::exists($absoluteExistingPath)) {
                File::delete($absoluteExistingPath);
            }
        }

        $extension = strtolower($image->getClientOriginalExtension() ?: 'jpg');
        $empId = preg_replace('/[^A-Za-z0-9_-]+/', '-', $this->clean($employee->empId));
        $filename = sprintf(
            'employee_%s_%s_%s.%s',
            $empId ?: 'employee',
            Carbon::now(config('app.timezone', 'Asia/Kolkata'))->format('YmdHis'),
            bin2hex(random_bytes(4)),
            $extension
        );

        Image::make($image)
            ->orientate()
            ->fit(512, 512)
            ->save($directory . '/' . $filename);

        return 'storage/ProfileImage/' . $filename;
    }
}
