<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeDetail;
use App\Models\OutsourceLocation;
use App\Models\RecruitmentCandidate;
use App\Services\EmployeeIdGenerator;
use App\Services\EmployeeImportService;
use App\Support\MayPfEligibility;
use App\Support\ProjectAsset;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeIdGenerator $employeeIdGenerator
    ) {}

    public function create()
    {
        return view('admin.employee.create', [
            'generatedEmpId' => $this->employeeIdGenerator->generate(),
            'outsourceLocations' => OutsourceLocation::query()
                ->where('status', 1)
                ->orderBy('location_code')
                ->get(['id', 'location_code', 'name', 'city', 'state']),
        ]);
    }

    public function import(Request $request, EmployeeImportService $importService): RedirectResponse
    {
        $data = $request->validate([
            'employee_file' => ['required', 'file', 'mimes:csv,xlsx,xls'],
        ]);

        try {
            $result = $importService->import($data['employee_file']);
        } catch (\Throwable $exception) {
            return redirect()
                ->back()
                ->withErrors(['employee_file' => $exception->getMessage()])
                ->withInput();
        }

        $message = sprintf(
            'Employee import completed. Inserted: %d | Updated: %d | Processed rows: %d | Skipped: %d',
            $result['inserted'],
            $result['updated'],
            $result['processed'],
            $result['skipped']
        );

        return redirect()
            ->back()
            ->with('status', $message);
    }


    public function store(Request $request)
    {
        $data = $request->validate([
            'empId' => [
                'nullable',
                'string',
                'max:255',
            ],
            'name' => ['required', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:50'],
            'mailId' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'designation' => ['required', 'string', 'max:255'],
            'salary' => ['nullable', 'numeric'],
            'doj' => ['nullable', 'date'],
            'shift_timing' => ['nullable', 'string', 'max:255'],
            'pf_eligible' => ['nullable', 'boolean'],
            'is_outsourced' => ['nullable', 'boolean'],
            'outsource_location_ids' => ['nullable', 'array'],
            'outsource_location_ids.*' => ['integer', 'exists:outsource_locations,id'],
        ], [
            'name.required' => 'Employee Name Required',
            'designation.required' => 'Employee designation Required',
        ]);

        $empId = $this->clean($data['empId'] ?? '');
        $conflicts = $this->employeeIdConflicts($empId);

        if ($conflicts !== []) {
            return $this->redirectBackWithEmployeeIdConflicts($empId, $conflicts);
        }

        $employee = new Employee();
        $employee->name = $this->clean($data['name'] ?? '');
        $employee->contact = $this->nullIfBlank($data['contact'] ?? null);
        $employee->mailId = $this->nullIfBlank($data['mailId'] ?? null);
        $employee->address = $this->nullIfBlank($data['address'] ?? null);
        $employee->designation = $this->clean($data['designation'] ?? '');
        $employee->salary = $this->nullIfBlank($data['salary'] ?? null);
        $employee->doj = $this->nullIfBlank($this->dateInputValue($data['doj'] ?? null));
        $employee->shift_timing = $this->nullIfBlank($data['shift_timing'] ?? null);
        $employee->is_outsourced = (bool) ($data['is_outsourced'] ?? false);
        if (Schema::hasColumn('employee', 'pf_eligible')) {
            $employee->pf_eligible = $this->nullableBoolean($data['pf_eligible'] ?? false);
        }
        $employee->advance = 0;

        $outsourceLocationIds = collect($data['outsource_location_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($employee->is_outsourced && $outsourceLocationIds === []) {
            return back()
                ->withErrors(['outsource_location_ids' => 'At least one outsource location is required for outsourced employees.'])
                ->withInput();
        }

        DB::transaction(function () use ($employee, $empId, $outsourceLocationIds): void {
            $this->saveEmployee($employee, $empId);
            $employee->outsourceLocations()->sync($employee->is_outsourced ? $outsourceLocationIds : []);
        });

        $notification = array(
            'message' => 'Employee Inserted Successfully',
            'alert-type' => 'success'
        );
        return redirect()->route('admin-employee-index')->with($notification);
    }


    public function index(Request $request)
    {
        $selectedState = $this->clean($request->query('state'));
        $selectedCity = $this->clean($request->query('city'));
        $selectedBranch = $this->clean($request->query('branch'));

        $datas = Employee::orderBy('empId', 'asc')
            ->get()
            ->each(function (Employee $employee): void {
                $employee->photo_url = $this->resolvePhotoUrl($employee->photo);
            });

        $this->attachRecruitmentJoiningDates($datas);
        $this->attachLastLoginBranchDetails($datas);

        $allBranches = Branch::query()
            ->orderBy('state')
            ->orderBy('city')
            ->orderBy('branchName')
            ->get(['branchId', 'branchName', 'city', 'state']);
        $branchesById = $allBranches->keyBy(fn (Branch $branch): string => $this->clean($branch->branchId));

        $filteredEmployees = $datas
            ->filter(function (Employee $employee) use ($selectedState, $selectedCity, $selectedBranch): bool {
                if ($selectedState !== '' && $this->stateFilterKey($employee->last_login_branch_state) !== $selectedState) {
                    return false;
                }

                if ($selectedCity !== '' && $this->cityFilterKey($employee->last_login_branch_city) !== $selectedCity) {
                    return false;
                }

                if ($selectedBranch !== '' && $this->clean($employee->last_login_branch_id) !== $selectedBranch) {
                    return false;
                }

                return true;
            })
            ->values();

        $regularEmployees = $filteredEmployees
            ->reject(fn (Employee $employee): bool => (bool) $employee->is_outsourced)
            ->values();
        $outsourceEmployees = $filteredEmployees
            ->filter(fn (Employee $employee): bool => (bool) $employee->is_outsourced)
            ->values();

        $employees = $regularEmployees
            ->reject(fn (Employee $employee): bool => $this->isInactiveEmployee($employee))
            ->values();
        $inactiveEmployees = $regularEmployees
            ->filter(fn (Employee $employee): bool => $this->isInactiveEmployee($employee))
            ->values();
        $onboardedCandidates = RecruitmentCandidate::query()
            ->with(['hiringAdmin', 'joiningAdmin'])
            ->where('status', RecruitmentCandidate::STATUS_ONBOARDED)
            ->orderByDesc('onboarding_completed_at')
            ->orderByDesc('updated_at')
            ->get();
        $existingEmployeesByEmpId = Employee::query()
            ->select(['id', 'empId', 'name', 'designation', 'doj'])
            ->get()
            ->keyBy(fn (Employee $employee): string => $this->clean($employee->empId));
        $onboardedCandidates = $onboardedCandidates
            ->each(function (RecruitmentCandidate $candidate) use ($branchesById): void {
                $payload = is_array($candidate->onboarding_payload) ? $candidate->onboarding_payload : [];
                $deployedBranchId = $this->clean(data_get($payload, 'deployed_branch_id'));
                $branch = $branchesById->get($deployedBranchId);

                $candidate->display_hiring_admin_name = $this->clean($candidate->hiring_admin_name)
                    ?: $this->clean($candidate->hiringAdmin?->name)
                    ?: $this->clean($candidate->hiringAdmin?->email)
                    ?: '--';
                $candidate->display_joining_admin_name = $this->clean($candidate->joining_admin_name)
                    ?: $this->clean($candidate->joiningAdmin?->name)
                    ?: $this->clean($candidate->joiningAdmin?->email)
                    ?: '--';
                $candidate->date_of_joining_input_value = $this->dateInputValue(data_get($payload, 'date_of_joining'));
                $candidate->deployed_branch_city = $this->clean($branch?->city);
                $candidate->deployed_branch_state = $this->clean($branch?->state);
                $candidate->deployed_branch_id = $deployedBranchId;
            })
            ->each(function (RecruitmentCandidate $candidate) use ($existingEmployeesByEmpId): void {
                $existingEmployee = $existingEmployeesByEmpId->get($this->clean($candidate->generated_emp_id));
                $candidateConflicts = $this->employeeIdConflicts($this->clean($candidate->generated_emp_id));
                $candidateConflicts = array_values(array_filter(
                    $candidateConflicts,
                    fn (array $conflict): bool => ($conflict['type'] ?? '') !== 'candidate'
                        || (int) ($conflict['record_id'] ?? 0) !== (int) $candidate->id
                ));

                $candidate->existing_employee = $existingEmployee;
                $candidate->employee_id_conflicts = $candidateConflicts;
                $candidate->has_existing_employee_id_conflict = $candidateConflicts !== [] || $existingEmployee !== null;
            })
            ->filter(function (RecruitmentCandidate $candidate) use ($selectedState, $selectedCity, $selectedBranch): bool {
                if ($selectedState !== '' && $this->stateFilterKey($candidate->deployed_branch_state) !== $selectedState) {
                    return false;
                }

                if ($selectedCity !== '' && $this->cityFilterKey($candidate->deployed_branch_city) !== $selectedCity) {
                    return false;
                }

                if ($selectedBranch !== '' && $this->clean($candidate->deployed_branch_id) !== $selectedBranch) {
                    return false;
                }

                return true;
            })
            ->values();
        $branches = Branch::query()
            ->where('status', 1)
            ->orderBy('branchName')
            ->get(['branchId', 'branchName', 'city', 'state']);

        $availableStates = $allBranches
            ->map(function (Branch $branch): array {
                return [
                    'key' => $this->stateFilterKey($branch->state),
                    'label' => $this->stateFilterLabel($branch->state),
                ];
            })
            ->filter(fn (array $state): bool => $state['key'] !== '')
            ->reject(fn (array $state): bool => $state['key'] === 'puducherry')
            ->unique('key')
            ->sortBy('label')
            ->values();

        $availableCities = $allBranches
            ->filter(function (Branch $branch) use ($selectedState): bool {
                return $selectedState === '' || $this->stateFilterKey($branch->state) === $selectedState;
            })
            ->map(function (Branch $branch): array {
                return [
                    'key' => $this->cityFilterKey($branch->city),
                    'label' => $this->cityFilterLabel($branch->city),
                ];
            })
            ->filter(fn (array $city): bool => $city['key'] !== '')
            ->unique('key')
            ->sortBy('label')
            ->values();

        $availableBranches = $allBranches
            ->filter(function (Branch $branch) use ($selectedState, $selectedCity): bool {
                if ($selectedState !== '' && $this->stateFilterKey($branch->state) !== $selectedState) {
                    return false;
                }

                if ($selectedCity !== '' && $this->cityFilterKey($branch->city) !== $selectedCity) {
                    return false;
                }

                return true;
            })
            ->values();

        $nextAvailableEmpIdSuggestions = $this->employeeIdGenerator->suggestions(2);
        $nextAvailableEmpId = $nextAvailableEmpIdSuggestions !== []
            ? (string) $nextAvailableEmpIdSuggestions[0]
            : $this->employeeIdGenerator->generate();

        return view('admin.employee.index', compact(
            'employees',
            'inactiveEmployees',
            'outsourceEmployees',
            'onboardedCandidates',
            'branches',
            'availableStates',
            'availableCities',
            'availableBranches',
            'selectedState',
            'selectedCity',
            'selectedBranch'
        ) + [
            'nextAvailableEmpId' => $nextAvailableEmpId,
            'nextAvailableEmpIdSuggestions' => $nextAvailableEmpIdSuggestions,
        ]);
    }

    private function stateFilterKey($value): string
    {
        $normalized = $this->normalizeFilterValue($value);

        if ($normalized === '') {
            return '';
        }

        $aliases = [
            'andhrapradesh' => 'andhra-pradesh',
            'andrapradesh' => 'andhra-pradesh',
            'andhrapradesh' => 'andhra-pradesh',
            'andhraprades' => 'andhra-pradesh',
            'andrapradesh' => 'andhra-pradesh',
            'andraprades' => 'andhra-pradesh',
            'telangana' => 'telangana',
            'telengana' => 'telangana',
        ];

        return $aliases[$normalized] ?? $normalized;
    }

    private function stateFilterLabel($value): string
    {
        return match ($this->stateFilterKey($value)) {
            'andhra-pradesh' => 'Andhra Pradesh',
            'telangana' => 'Telangana',
            default => $this->titleizeFilterValue($value),
        };
    }

    private function cityFilterKey($value): string
    {
        return $this->normalizeFilterValue($value);
    }

    private function cityFilterLabel($value): string
    {
        return $this->titleizeFilterValue($value);
    }

    private function normalizeFilterValue($value): string
    {
        $normalized = strtolower($this->clean($value));

        if ($normalized === '') {
            return '';
        }

        return preg_replace('/[^a-z0-9]+/', '', $normalized) ?? '';
    }

    private function titleizeFilterValue($value): string
    {
        $trimmed = $this->clean($value);

        if ($trimmed === '') {
            return '';
        }

        $normalizedSpacing = preg_replace('/\s+/', ' ', str_replace(['-', '_'], ' ', $trimmed)) ?? $trimmed;

        return ucwords(strtolower($normalizedSpacing));
    }

    private function isInactiveEmployee(Employee $employee): bool
    {
        return strcasecmp($this->clean($employee->status), 'Inactive') === 0;
    }


    public function edit($id)
    {
        $data = Employee::query()->findOrFail($id);
        $this->attachEmployeeDetails(collect([$data]));
        $this->attachLastLoginBranchDetails(collect([$data]));
        $mayPfEligible = MayPfEligibility::isEligible(
            Carbon::parse('2026-05-01'),
            $data->empId,
            $data->detail?->uanNumber,
            null
        );
        $employeeMedia = $this->employeeMediaFor($data);
        $candidateDateOfJoining = $this->dateInputValue(data_get($employeeMedia['candidate'] ?? null, 'onboarding_payload.date_of_joining'));
        $employeeDateOfJoining = $candidateDateOfJoining !== ''
            ? $candidateDateOfJoining
            : $this->dateInputValue($data->doj);
        $outsourceLocations = OutsourceLocation::query()
            ->where('status', 1)
            ->orderBy('location_code')
            ->get(['id', 'location_code', 'name', 'city', 'state']);
        $selectedOutsourceLocationIds = $data->outsourceLocations()
            ->pluck('outsource_locations.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return view('admin.employee.edit', compact(
            'data',
            'mayPfEligible',
            'employeeMedia',
            'candidateDateOfJoining',
            'employeeDateOfJoining',
            'outsourceLocations',
            'selectedOutsourceLocationIds'
        ));
    }

    public function update(Request $request)
    {
        $id = (int) $request->id;
        $employee = Employee::findOrFail($id);
        $previousEmpId = $this->clean($employee->empId);

        $data = $request->validate([
            'empId' => [
                'required',
                'string',
                'max:255',
            ],
            'name' => 'required',
            'contact' => ['nullable', 'string', 'max:50'],
            'mailId' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'designation' => ['nullable', 'string', 'max:255'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:10'],
            'salary' => ['nullable', 'numeric'],
            'doj' => ['nullable', 'date'],
            'shift_timing' => ['nullable', 'string', 'max:255'],
            'pf_eligible' => ['nullable', 'boolean'],
            'gender' => ['nullable', 'in:Male,Female,Other'],
            'marital_status' => ['nullable', 'in:Single,Married'],
            'imported_salary' => ['nullable', 'numeric', 'min:0'],
            'is_outsourced' => ['nullable', 'boolean'],
            'outsource_location_ids' => ['nullable', 'array'],
            'outsource_location_ids.*' => ['integer', 'exists:outsource_locations,id'],
        ], [
            'empId.required' => 'Employee ID Required',
            'name.required' => 'Employee Name Required',
        ]);

        $empId = $this->clean($data['empId'] ?? '');
        $conflicts = $empId === $previousEmpId
            ? []
            : $this->employeeIdConflicts($empId, $id);

        if ($conflicts !== []) {
            return $this->redirectBackWithEmployeeIdConflicts($empId, $conflicts);
        }

        $isOutsourced = (bool) ($data['is_outsourced'] ?? false);
        $outsourceLocationIds = collect($data['outsource_location_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($isOutsourced && $outsourceLocationIds === []) {
            return back()
                ->withErrors(['outsource_location_ids' => 'At least one outsource location is required for outsourced employees.'])
                ->withInput();
        }

        DB::transaction(function () use ($employee, $previousEmpId, $data, $empId, $isOutsourced, $outsourceLocationIds): void {
            $updates = [
                'empId' => $empId,
                'name' => $this->clean($data['name'] ?? ''),
                'contact' => $this->nullIfBlank($data['contact'] ?? null),
                'mailId' => $this->nullIfBlank($data['mailId'] ?? null),
                'address' => $this->nullIfBlank($data['address'] ?? null),
                'designation' => $this->nullIfBlank($data['designation'] ?? null),
                'rating' => $data['rating'] ?? null,
                'salary' => $this->nullIfBlank($data['salary'] ?? null),
                'doj' => $this->nullIfBlank($this->dateInputValue($data['doj'] ?? null)),
                'shift_timing' => $this->nullIfBlank($data['shift_timing'] ?? null),
                'gender' => $this->nullIfBlank($data['gender'] ?? null),
                'marital_status' => $this->nullIfBlank($data['marital_status'] ?? null),
                'is_outsourced' => $isOutsourced,
            ];

            if (Schema::hasColumn('employee', 'pf_eligible')) {
                $updates['pf_eligible'] = $this->nullableBoolean($data['pf_eligible'] ?? false);
            }

            $employee->update($updates);

            $this->updateImportedSalary($employee, $previousEmpId, $data['imported_salary'] ?? null);
            $employee->outsourceLocations()->sync($isOutsourced ? $outsourceLocationIds : []);
        });

        $notification = array(
            'message' => 'Employee Updated Successfully',
            'alert-type' => 'success'

        );

        return redirect()->route('admin-employee-index')->with($notification);
    }



    public function inactive(Request $request, $id)
    {
        if (! $request->isMethod('post')) {
            $notification = array(
                'message' => 'Please use the inactive popup and submit reason with last working date.',
                'alert-type' => 'error'
            );

            return redirect()->route('admin-employee-index')->with($notification);
        }

        $data = $request->validate([
            'inactive_reason' => ['required', 'string', 'max:2000'],
            'last_working_date' => ['required', 'date'],
        ], [
            'inactive_reason.required' => 'Inactive reason is required',
            'last_working_date.required' => 'Last working date is required',
        ]);

        $employee = Employee::findOrFail($id);
        $employee->update([
            'status' => 'Inactive',
            'inactive_reason' => trim($data['inactive_reason']),
            'last_working_date' => $data['last_working_date'],
        ]);
        $employee->tokens()->delete();

        $notification = array(
            'message' => 'Employee InActive Successfully',
            'alert-type' => 'error'

        );
        return redirect()->back()->with($notification);
    }

    public function active($id)
    {
        Employee::findOrFail($id)->update([
            'status' => 'Active',
            'inactive_reason' => null,
            'last_working_date' => null,
        ]);

        $notification = array(
            'message' => 'Employee Active Successfully',
            'alert-type' => 'success'

        );
        return redirect()->back()->with($notification);
    }

    public function delete($id)
    {

        Employee::findOrFail($id)->delete();

        $notification = array(
            'message' => 'Employee Deleted Successfully',
            'alert-type' => 'success'

        );

        return redirect()->back()->with($notification);
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


    private function attachLastLoginBranchDetails(Collection $employees): void
    {
        $empIds = $employees
            ->map(fn (Employee $employee): string => $this->clean($employee->empId))
            ->filter()
            ->unique()
            ->values();

        if ($empIds->isEmpty()) {
            return;
        }

        $latestLoginByEmpId = Attendance::query()
            ->whereIn('empId', $empIds->all())
            ->orderByDesc('id')
            ->get(['empId', 'check_in_branch_id', 'check_in_date', 'check_in_time'])
            ->keyBy(fn (Attendance $attendance): string => $this->clean($attendance->empId));

        $branchIds = collect($latestLoginByEmpId
            ->map(fn (Attendance $attendance): string => $this->clean($attendance->check_in_branch_id))
            ->all())
            ->merge($employees
                ->map(fn (Employee $employee): string => $this->clean($employee->last_login_branch_id))
                ->all())
            ->filter()
            ->unique()
            ->values();

        $branchesById = $branchIds->isEmpty()
            ? collect()
            : Branch::query()
                ->whereIn('branchId', $branchIds->all())
                ->get(['branchId', 'branchName', 'city', 'state'])
                ->keyBy(fn (Branch $branch): string => $this->clean($branch->branchId));
        $outsourceByCode = $branchIds->isEmpty()
            ? collect()
            : OutsourceLocation::query()
                ->whereIn('location_code', $branchIds->all())
                ->get(['location_code', 'name', 'city', 'state'])
                ->keyBy(fn (OutsourceLocation $location): string => $this->clean($location->location_code));

        $employees->each(function (Employee $employee) use ($latestLoginByEmpId, $branchesById, $outsourceByCode): void {
            $latestLogin = $latestLoginByEmpId->get($this->clean($employee->empId));
            $branchId = $this->clean($employee->last_login_branch_id)
                ?: $this->clean($latestLogin?->check_in_branch_id);
            $branch = $branchesById->get($branchId);
            $outsourceLocation = $outsourceByCode->get($branchId);

            $employee->last_login_branch_id = $branchId;
            $employee->last_login_branch_name = $this->clean($branch?->branchName)
                ?: $this->clean($outsourceLocation?->name);
            $employee->last_login_branch_city = $this->clean($branch?->city)
                ?: $this->clean($outsourceLocation?->city);
            $employee->last_login_branch_state = $this->clean($branch?->state)
                ?: $this->clean($outsourceLocation?->state);
        });
    }

    private function attachEmployeeDetails(Collection $employees): void
    {
        $empIds = $employees
            ->map(fn (Employee $employee): string => $this->clean($employee->empId))
            ->filter()
            ->unique()
            ->values();

        if ($empIds->isEmpty()) {
            return;
        }

        $detailsByEmployeeId = EmployeeDetail::query()
            ->whereIn(\DB::raw('TRIM(employeeId)'), $empIds->all())
            ->orderByDesc('id')
            ->get()
            ->unique(fn (EmployeeDetail $detail): string => $this->clean($detail->employeeId))
            ->keyBy(fn (EmployeeDetail $detail): string => $this->clean($detail->employeeId));

        $employees->each(function (Employee $employee) use ($detailsByEmployeeId): void {
            $employee->setRelation('detail', $detailsByEmployeeId->get($this->clean($employee->empId)));
        });
    }

    private function attachRecruitmentJoiningDates(Collection $employees): void
    {
        $empIds = $employees
            ->map(fn (Employee $employee): string => $this->clean($employee->empId))
            ->filter()
            ->unique()
            ->values();

        if ($empIds->isEmpty()) {
            return;
        }

        $candidatesByEmpId = RecruitmentCandidate::query()
            ->whereIn('generated_emp_id', $empIds->all())
            ->where('status', RecruitmentCandidate::STATUS_JOINED)
            ->orderByDesc('joined_at')
            ->orderByDesc('updated_at')
            ->get(['generated_emp_id', 'onboarding_payload'])
            ->unique(fn (RecruitmentCandidate $candidate): string => $this->clean($candidate->generated_emp_id))
            ->keyBy(fn (RecruitmentCandidate $candidate): string => $this->clean($candidate->generated_emp_id));

        $employees->each(function (Employee $employee) use ($candidatesByEmpId): void {
            /** @var RecruitmentCandidate|null $candidate */
            $candidate = $candidatesByEmpId->get($this->clean($employee->empId));
            $candidateDoj = $this->dateInputValue(data_get($candidate?->onboarding_payload, 'date_of_joining'));
            $employeeDoj = $this->dateInputValue($employee->doj);

            $employee->candidate_date_of_joining = $candidateDoj;
            $employee->display_date_of_joining = $candidateDoj !== '' ? $candidateDoj : $employeeDoj;
        });
    }

    private function employeeIdConflicts(string $empId, ?int $ignoreEmployeeId = null): array
    {
        if ($empId === '') {
            return [];
        }

        $employeeConflicts = Employee::query()
            ->when($ignoreEmployeeId !== null, fn ($query) => $query->where('id', '!=', $ignoreEmployeeId))
            ->whereRaw('TRIM(empId) = ?', [$empId])
            ->orderBy('name')
            ->get(['id', 'empId', 'name', 'designation', 'status', 'doj', 'last_login_branch_id'])
            ->map(function (Employee $employee): array {
                return [
                    'type' => 'employee',
                    'record_id' => (int) $employee->id,
                    'title' => trim((string) $employee->name) ?: 'Employee',
                    'location' => 'Employee master',
                    'details' => trim(implode(' | ', array_filter([
                        trim((string) $employee->designation) ?: null,
                        trim((string) $employee->status) ?: null,
                        $this->nullableDate($employee->doj) ? 'DOJ: '.$this->nullableDate($employee->doj) : null,
                        trim((string) $employee->last_login_branch_id) ? 'Last branch: '.trim((string) $employee->last_login_branch_id) : null,
                    ]))),
                ];
            });

        $candidateConflicts = RecruitmentCandidate::query()
            ->with(['hiringAdmin', 'joiningAdmin'])
            ->whereRaw('TRIM(generated_emp_id) = ?', [$empId])
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (RecruitmentCandidate $candidate): array {
                $joiningUser = trim((string) ($candidate->joining_admin_name ?: $candidate->joiningAdmin?->name ?: $candidate->joiningAdmin?->email));
                $hiringUser = trim((string) ($candidate->hiring_admin_name ?: $candidate->hiringAdmin?->name ?: $candidate->hiringAdmin?->email));
                $branch = trim((string) (data_get($candidate->onboarding_payload, 'deployed_branch_name') ?: data_get($candidate->onboarding_payload, 'deployed_branch_id')));

                return [
                    'type' => 'candidate',
                    'record_id' => (int) $candidate->id,
                    'title' => trim((string) $candidate->candidate_name) ?: 'Joining candidate',
                    'location' => 'Recruitment / Joining',
                    'details' => trim(implode(' | ', array_filter([
                        ucfirst(str_replace('_', ' ', trim((string) $candidate->status))),
                        $joiningUser !== '' ? 'Joining user: '.$joiningUser : null,
                        $hiringUser !== '' ? 'Hiring user: '.$hiringUser : null,
                        $branch !== '' ? 'Branch: '.$branch : null,
                    ]))),
                ];
            });

        return collect($employeeConflicts->all())
            ->merge($candidateConflicts->all())
            ->values()
            ->all();
    }

    private function redirectBackWithEmployeeIdConflicts(string $empId, array $conflicts): RedirectResponse
    {
        return redirect()
            ->back()
            ->withErrors(['empId' => 'Employee ID already exists. Use a different Employee ID; existing assignments cannot be cleared or reassigned.'])
            ->withInput()
            ->with('employee_id_conflicts', $conflicts)
            ->with('employee_id_conflict_value', $empId);
    }

    private function employeeMediaFor(Employee $employee): array
    {
        $empId = $this->clean($employee->empId);

        if ($empId === '') {
            return [
                'candidate' => null,
                'images' => [],
                'documents' => [],
                'videos' => [],
            ];
        }

        $candidate = RecruitmentCandidate::query()
            ->whereRaw('TRIM(generated_emp_id) = ?', [$empId])
            ->latest('updated_at')
            ->first();

        if (! $candidate) {
            return [
                'candidate' => null,
                'images' => [],
                'documents' => [],
                'videos' => [],
            ];
        }

        $images = [];
        $documents = [];
        $videos = [];

        if ($candidate->employee_photo_path) {
            $images[] = [
                'label' => 'Employee Photo',
                'url' => ProjectAsset::url($candidate->employee_photo_path),
            ];
        }

        if ($candidate->candidate_photo_path && $candidate->candidate_photo_path !== $candidate->employee_photo_path) {
            $images[] = [
                'label' => 'Candidate Photo',
                'url' => ProjectAsset::url($candidate->candidate_photo_path),
            ];
        }

        foreach (($candidate->document_photo_paths ?? []) as $key => $path) {
            $path = $this->clean($path);

            if ($path === '') {
                continue;
            }

            $images[] = [
                'label' => $this->documentPhotoLabels()[$key] ?? ucwords(str_replace('_', ' ', (string) $key)),
                'url' => ProjectAsset::url($path),
            ];
        }

        if ($candidate->resume_file_path) {
            $documents[] = [
                'label' => $candidate->resume_original_name ?: 'Resume',
                'url' => ProjectAsset::url($candidate->resume_file_path),
            ];
        }

        if ($candidate->interview_video_path) {
            $videos[] = [
                'label' => $candidate->interview_video_original_name ?: 'Interview Video',
                'url' => ProjectAsset::url($candidate->interview_video_path),
            ];
        }

        return [
            'candidate' => $candidate,
            'images' => $images,
            'documents' => $documents,
            'videos' => $videos,
        ];
    }

    private function documentPhotoLabels(): array
    {
        return [
            'aadhar_card' => 'Aadhar Card',
            'pan_card' => 'PAN Card',
            'voter_id' => 'Voter ID',
            'driving_license_copy' => 'Driving License Copy',
            'ration_card' => 'Ration Card',
            'rental_agreement' => 'Rental Agreement',
        ];
    }

    private function nullableDate($value): ?string
    {
        $trimmed = $this->clean($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function clean($value): string
    {
        return trim((string) $value);
    }

    private function nullIfBlank($value): ?string
    {
        $trimmed = $this->clean($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableBoolean($value): ?bool
    {
        $trimmed = $this->clean($value);

        if ($trimmed === '') {
            return null;
        }

        return filter_var($trimmed, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    private function dateInputValue($value): string
    {
        $trimmed = $this->clean($value);

        if ($trimmed === '') {
            return '';
        }

        $timezone = config('app.timezone', 'Asia/Kolkata');
        $formats = [
            'Y-m-d',
            'Y/m/d',
            'd-m-Y',
            'j-n-Y',
            'd/m/Y',
            'j/n/Y',
            'd.m.Y',
            'j.n.Y',
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $trimmed, $timezone);
                $errors = Carbon::getLastErrors();
            } catch (\Throwable) {
                continue;
            }

            if (
                ($errors['warning_count'] ?? 0) === 0
                && ($errors['error_count'] ?? 0) === 0
                && $date->format($format) === $trimmed
            ) {
                return $date->toDateString();
            }
        }

        try {
            return Carbon::parse($trimmed, $timezone)->toDateString();
        } catch (\Throwable) {
            return '';
        }
    }

    private function updateImportedSalary(Employee $employee, string $previousEmpId, mixed $value): void
    {
        $salary = $this->nullIfBlank($value);
        $currentEmpId = $this->clean($employee->empId);
        $employeeIds = array_values(array_unique(array_filter([$previousEmpId, $currentEmpId])));

        if ($salary === null && $previousEmpId === $currentEmpId) {
            return;
        }

        $detail = empty($employeeIds)
            ? null
            : EmployeeDetail::query()
                ->whereIn(\DB::raw('TRIM(employeeId)'), $employeeIds)
                ->orderByDesc('id')
                ->first();

        if (! $detail && $salary === null) {
            return;
        }

        $detail ??= new EmployeeDetail();
        $now = now();

        $detail->employeeId = $currentEmpId;
        $detail->empName = $this->clean($detail->empName) ?: $this->clean($employee->name);
        $detail->designation = $this->clean($detail->designation) ?: $this->clean($employee->designation);
        $detail->bankName = $this->clean($detail->bankName);
        $detail->bankAcNo = $this->clean($detail->bankAcNo);
        $detail->ifscCode = $this->clean($detail->ifscCode);
        $detail->passbookDoc = $this->clean($detail->passbookDoc);
        $detail->salary = $salary ?? (is_numeric($detail->salary) ? $detail->salary : 0);
        $detail->branchId = $this->clean($detail->branchId);
        $detail->status = $this->clean($detail->status) ?: 'Active';
        $detail->accountVerified = $this->clean($detail->accountVerified) ?: 'Pending';
        $detail->date = $detail->date ?: $now->toDateString();
        $detail->time = $detail->time ?: $now->format('H:i:s');
        $detail->totalWorkingDays = is_numeric($detail->totalWorkingDays) ? $detail->totalWorkingDays : 0;
        $detail->absentDays = is_numeric($detail->absentDays) ? $detail->absentDays : 0;
        $detail->presentDays = is_numeric($detail->presentDays) ? $detail->presentDays : 0;
        $detail->penalty = is_numeric($detail->penalty) ? $detail->penalty : 0;
        $detail->advanceSalary = is_numeric($detail->advanceSalary) ? $detail->advanceSalary : 0;
        $detail->finalSalary = is_numeric($detail->finalSalary) ? $detail->finalSalary : 0;
        $detail->salaryPaymentStatus = $this->clean($detail->salaryPaymentStatus);
        $detail->salaryPaidBy = $this->clean($detail->salaryPaidBy);
        $detail->salaryBankName = $this->clean($detail->salaryBankName);
        $detail->salaryProcessingBy = $this->clean($detail->salaryProcessingBy);
        $detail->salaryProcessingUser = $this->clean($detail->salaryProcessingUser);
        $detail->aadhaarNo = $this->clean($detail->aadhaarNo);
        $detail->uanNumber = $this->clean($detail->uanNumber);
        $detail->pfAmount = is_numeric($detail->pfAmount) ? $detail->pfAmount : (is_numeric($employee->pf) ? $employee->pf : 0);
        $detail->save();
    }

    private function saveEmployee(Employee $employee, mixed $requestedEmpId = null): void
    {
        $manualEmpId = $this->clean($requestedEmpId);

        if ($manualEmpId !== '') {
            $employee->empId = $manualEmpId;
            $employee->save();

            return;
        }

        $this->saveEmployeeWithGeneratedId($employee);
    }

    private function saveEmployeeWithGeneratedId(Employee $employee): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $employee->empId = $this->employeeIdGenerator->generate();

            try {
                $employee->save();

                return;
            } catch (QueryException $exception) {
                if (! $this->isEmployeeIdDuplicateException($exception)) {
                    throw $exception;
                }
            }
        }

        throw ValidationException::withMessages([
            'empId' => 'Unable to generate a unique employee ID. Please try again.',
        ]);
    }

    private function isEmployeeIdDuplicateException(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo;
        $driverCode = (string) ($errorInfo[1] ?? '');
        $message = strtolower($exception->getMessage());

        return $driverCode === '1062'
            || str_contains($message, 'employee_empid_unique')
            || str_contains($message, 'employee_emp_id_unique')
            || str_contains($message, 'unique constraint failed: employee.empid');
    }
}
