<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentCandidateActivityLog;
use App\Models\RecruitmentFormLink;
use App\Services\AtticaGoldEmployeeSyncService;
use App\Services\EmployeeIdGenerator;
use App\Services\RecruitmentImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RecruitmentController extends Controller
{
    private const NOTICE_PERIOD_OPTIONS = ['Immediate', '15 Days', '1 Month'];
    private const GENDER_OPTIONS = ['Male', 'Female', 'Other'];
    private const MARITAL_STATUS_OPTIONS = ['Single', 'Married'];
    private const BLOOD_GROUP_OPTIONS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    private const RELATIONSHIP_OPTIONS = ['Father', 'Mother', 'Spouse', 'Brother', 'Sister', 'Friend', 'Relative', 'Guardian', 'Other'];
    private const DOCUMENT_KEYS = [
        'aadhar_card' => 'Aadhar Card',
        'pan_card' => 'PAN Card',
        'voter_id' => 'Voter ID',
        'driving_license_copy' => 'Driving License Copy',
        'ration_card' => 'Ration Card',
        'rental_agreement' => 'Rental Agreement',
    ];
    private const HIRING_PLACE_TABS = [
        'karnataka' => 'Karnataka',
        'ap' => 'AP',
        'tn' => 'TN',
        'ts' => 'TS',
        'pondicherry' => 'Pondicherry',
        'other' => 'Other',
    ];

    public function __construct(
        private readonly RecruitmentImageService $imageService,
        private readonly EmployeeIdGenerator $employeeIdGenerator
    ) {
    }

    public function hiringIndex(Request $request): View
    {
        /** @var Admin|null $admin */
        $admin = $request->user('admin');
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(array_keys($this->hiringStatusFilterOptions()))],
            'place_tab' => ['nullable', Rule::in(array_keys(self::HIRING_PLACE_TABS))],
        ]);

        $formLinkQuery = RecruitmentFormLink::query()
            ->with('createdByAdmin')
            ->orderByDesc('created_at');

        $this->applyFormLinkAdminPositionFilter($formLinkQuery, $admin);

        $formLinks = $formLinkQuery
            ->get()
            ->each(function (RecruitmentFormLink $formLink) use ($admin): void {
                $submissionQuery = $formLink->submissions()->whereNotNull('submission_code');
                $this->applyCandidateAdminPositionFilter($submissionQuery, $admin);

                $formLink->positions_count = count($formLink->positionOptions());
                $formLink->submissions_count = $submissionQuery->count();
            });

        $candidateQuery = RecruitmentCandidate::query()
            ->with(['hiringAdmin', 'joiningAdmin', 'formLink', 'resubmissionOf'])
            ->whereNotNull('submission_code')
            ->whereNotIn('id', RecruitmentCandidate::query()
                ->select('resubmission_of_candidate_id')
                ->whereNotNull('resubmission_of_candidate_id'))
            ->orderByDesc('created_at');

        $this->applyCandidateAdminPositionFilter($candidateQuery, $admin);

        $candidates = $candidateQuery
            ->get()
            ->each(function (RecruitmentCandidate $candidate): void {
                $candidate->display_hiring_date = $this->candidateHiringDate($candidate) ?: '--';
                $preferredWorkLocationLabel = trim((string) data_get($candidate->hiring_payload, 'preferred_work_location_label'));
                $preferredWorkLocationState = trim((string) (
                    data_get($candidate->hiring_payload, 'preferred_work_location_state')
                    ?: data_get($candidate->hiring_payload, 'preferred_work_location_branch_state')
                ));
                $preferredWorkLocationCity = trim((string) (
                    data_get($candidate->hiring_payload, 'preferred_work_location_city')
                    ?: data_get($candidate->hiring_payload, 'preferred_work_location_branch_name')
                ));
                $place = trim((string) data_get($candidate->hiring_payload, 'place'));
                $candidate->aadhaar_number = $this->candidateAadhaarNumber($candidate);
                if ($preferredWorkLocationLabel === '' && ($preferredWorkLocationCity !== '' || $preferredWorkLocationState !== '')) {
                    $preferredWorkLocationLabel = collect([$preferredWorkLocationCity, $preferredWorkLocationState])
                        ->filter(fn (string $value): bool => $value !== '')
                        ->implode(', ');
                }
                $candidate->hiring_place = $preferredWorkLocationLabel !== '' ? $preferredWorkLocationLabel : ($place !== '' ? $place : 'Undisclosed');
                $candidate->hiring_place_tab = $this->resolveHiringPlaceTab($preferredWorkLocationState !== '' ? $preferredWorkLocationState : $place);
            });

        $this->attachHiringDuplicateMetadata($candidates);

        $candidates = $this->latestCandidatesPerAadhaar($candidates);

        $candidates = $candidates
            ->filter(function (RecruitmentCandidate $candidate) use ($filters): bool {
                $statusFilter = trim((string) ($filters['status'] ?? ''));
                $candidateDate = $this->candidateHiringDate($candidate);

                if ($statusFilter === '' && $candidate->status === RecruitmentCandidate::STATUS_HIRING_REJECTED) {
                    return false;
                }

                if ($statusFilter !== '') {
                    if ($statusFilter === 'moved_to_joining') {
                        if (! $this->isMovedToJoiningStatus($candidate->status)) {
                            return false;
                        }
                    } elseif ($candidate->status !== $statusFilter) {
                        return false;
                    }
                }

                if (($filters['date_from'] ?? null) && ($candidateDate === null || $candidateDate < $filters['date_from'])) {
                    return false;
                }

                if (($filters['date_to'] ?? null) && ($candidateDate === null || $candidateDate > $filters['date_to'])) {
                    return false;
                }

                return true;
            })
            ->values();

        $placeTabCounts = array_fill_keys(array_keys(self::HIRING_PLACE_TABS), 0);

        foreach ($candidates as $candidate) {
            $placeTabCounts[$candidate->hiring_place_tab] = ($placeTabCounts[$candidate->hiring_place_tab] ?? 0) + 1;
        }

        $activePlaceTab = trim((string) ($filters['place_tab'] ?? ''));

        if ($activePlaceTab === '') {
            $activePlaceTab = collect(array_keys(self::HIRING_PLACE_TABS))
                ->first(fn (string $tabKey): bool => ($placeTabCounts[$tabKey] ?? 0) > 0, 'karnataka');
        }

        $candidates = $candidates
            ->filter(fn (RecruitmentCandidate $candidate): bool => $candidate->hiring_place_tab === $activePlaceTab)
            ->values();

        return view('admin.recruitment.hiring_index', [
            'formLinks' => $formLinks,
            'candidates' => $candidates,
            'filters' => [
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
                'status' => $filters['status'] ?? '',
                'place_tab' => $activePlaceTab,
            ],
            'statusOptions' => $this->hiringStatusFilterOptions(),
            'placeTabs' => self::HIRING_PLACE_TABS,
            'placeTabCounts' => $placeTabCounts,
        ]);
    }

    public function hiringCreate(): View
    {
        return view('admin.recruitment.hiring_create', [
            'genericQuestions' => $this->genericInterviewQuestions(),
        ]);
    }

    public function hiringStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'hiring_date' => ['required', 'date'],
            'is_walkin_form' => ['nullable', 'boolean'],
            'question_groups' => ['nullable', 'array'],
            'question_groups.*.position' => ['nullable', 'string', 'max:255'],
            'question_groups.*.questions' => ['nullable', 'string'],
        ]);

        /** @var Admin $admin */
        $admin = $request->user('admin');
        $questionBank = $this->questionBankFromRequest($data['question_groups'] ?? [], (bool) ($data['is_walkin_form'] ?? false));

        if ((bool) ($data['is_walkin_form'] ?? false)) {
            $questionBank['__meta__'] = [
                'is_walkin_form' => true,
            ];
        }

        RecruitmentFormLink::query()->create([
            'title' => trim((string) $data['title']),
            'public_token' => (string) \Illuminate\Support\Str::lower((string) \Illuminate\Support\Str::uuid()),
            'hiring_date' => $this->nullableDate($data['hiring_date'] ?? null),
            'question_bank' => $questionBank,
            'is_active' => true,
            'created_by_admin_id' => $admin->id,
        ]);

        return redirect()
            ->route('admin-hiring-index')
            ->with('status', 'Static hiring form link created. Share the same link with as many candidates as needed. Every submission will now create its own unique row.');
    }

    public function hiringShow(int $candidateId): View
    {
        $candidate = $this->findCandidate($candidateId);

        return view('admin.recruitment.hiring_show', [
            'candidate' => $candidate,
            'duplicates' => $this->findHiringDuplicateCandidates($candidate),
        ]);
    }

    public function updateHiringDecision(Request $request, int $candidateId): RedirectResponse|JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $candidate = $this->findCandidate($candidateId);
        $data = $request->validate([
            'action' => ['required', Rule::in(['select', 'hold', 'reject', 'resend'])],
        ]);

        $status = match ($data['action']) {
            'select' => RecruitmentCandidate::STATUS_HIRING_SELECTED,
            'hold' => RecruitmentCandidate::STATUS_HIRING_HOLD,
            'reject' => RecruitmentCandidate::STATUS_HIRING_REJECTED,
            'resend' => RecruitmentCandidate::STATUS_HIRING_UPDATE_REQUESTED,
        };

        $updates = [
            'status' => $status,
        ];
        $shareUrl = null;
        $beforeStatus = $candidate->status;
        $beforeHiringAdminName = $candidate->hiring_admin_name;

        $updates = array_merge($updates, $this->candidateAdminSnapshotUpdates($admin, 'hiring'));

        if ($data['action'] === 'resend') {
            $updates['hiring_update_token'] = Str::lower((string) Str::uuid());
            $updates['hiring_update_requested_at'] = now(config('app.timezone', 'Asia/Kolkata'));
            $shareUrl = route('recruitment-hiring-update-link', $updates['hiring_update_token']);
        }

        $candidate->update($updates);
        $this->logCandidateActivity($candidate, $admin, 'hiring_'.$data['action'], array_filter([
            'status' => $this->makeChangeEntry('Status', $beforeStatus, $candidate->status),
            'hiring_admin_name' => $this->makeChangeEntry('Hiring User', $beforeHiringAdminName, $candidate->hiring_admin_name),
            'hiring_update_link' => $this->makeChangeEntry('Hiring Update Link', null, $shareUrl),
        ]));

        $message = match ($data['action']) {
            'select' => 'Candidate selected and moved to the joining team.',
            'hold' => 'Candidate marked as hold.',
            'reject' => 'Candidate rejected.',
            'resend' => 'Hiring form reopened with a new update link for this submission.',
        };

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'status' => $status,
                'share_url' => $shareUrl,
            ]);
        }

        return redirect()
            ->route('admin-hiring-index')
            ->with('status', $message);
    }

    public function joiningIndex(Request $request): View
    {
        /** @var Admin|null $admin */
        $admin = $request->user('admin');
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(array_keys($this->joiningStatusFilterOptions()))],
        ]);

        $candidateQuery = RecruitmentCandidate::query()
            ->with(['hiringAdmin', 'joiningAdmin', 'formLink'])
            ->whereNotNull('submission_code')
            ->whereIn('status', [
                RecruitmentCandidate::STATUS_HIRING_SELECTED,
                RecruitmentCandidate::STATUS_JOINING_FORM_SHARED,
                RecruitmentCandidate::STATUS_JOINING_SUBMITTED,
                RecruitmentCandidate::STATUS_JOINING_HOLD,
                RecruitmentCandidate::STATUS_JOINING_REJECTED,
                RecruitmentCandidate::STATUS_JOINING_UPDATE_REQUESTED,
                RecruitmentCandidate::STATUS_ONBOARDED,
                RecruitmentCandidate::STATUS_JOINED,
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at');

        $this->applyCandidateAdminPositionFilter($candidateQuery, $admin);

        $candidates = $candidateQuery
            ->get()
            ->each(function (RecruitmentCandidate $candidate): void {
                $candidate->display_joining_date = $this->candidateJoiningDate($candidate) ?: '--';
                $candidate->display_hiring_admin_name = $candidate->hiring_admin_name ?: ($candidate->hiringAdmin?->name ?: $candidate->hiringAdmin?->email ?: '--');
                $candidate->display_joining_admin_name = $candidate->joining_admin_name ?: ($candidate->joiningAdmin?->name ?: $candidate->joiningAdmin?->email ?: '--');
                $candidate->display_hr_admin_name = $candidate->hr_admin_name ?: '--';
            })
            ->filter(function (RecruitmentCandidate $candidate) use ($filters): bool {
                $statusFilter = trim((string) ($filters['status'] ?? ''));
                $candidateDate = $this->candidateJoiningDate($candidate);

                if ($statusFilter === '' && $candidate->status === RecruitmentCandidate::STATUS_JOINING_REJECTED) {
                    return false;
                }

                if ($statusFilter !== '' && $candidate->status !== $statusFilter) {
                    return false;
                }

                if (($filters['date_from'] ?? null) && ($candidateDate === null || $candidateDate < $filters['date_from'])) {
                    return false;
                }

                if (($filters['date_to'] ?? null) && ($candidateDate === null || $candidateDate > $filters['date_to'])) {
                    return false;
                }

                return true;
            })
            ->values();

        return view('admin.recruitment.joining_index', [
            'candidates' => $candidates,
            'branches' => Branch::query()
                ->where('status', 1)
                ->orderBy('branchName')
                ->get(['branchId', 'branchName']),
            'filters' => [
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'statusOptions' => $this->joiningStatusFilterOptions(),
        ]);
    }

    public function startOnboarding(Request $request, int $candidateId): RedirectResponse|JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $candidate = $this->findCandidate($candidateId);

        abort_unless(
            in_array($candidate->status, [
                RecruitmentCandidate::STATUS_HIRING_SELECTED,
                RecruitmentCandidate::STATUS_JOINING_FORM_SHARED,
                RecruitmentCandidate::STATUS_JOINING_UPDATE_REQUESTED,
                RecruitmentCandidate::STATUS_JOINING_HOLD,
                RecruitmentCandidate::STATUS_JOINING_SUBMITTED,
            ], true),
            404
        );

        $beforeStatus = $candidate->status;
        $beforeJoiningAdminName = $candidate->joining_admin_name;
        $beforeHrAdminName = $candidate->hr_admin_name;

        $candidate->update(array_merge([
            'status' => in_array($candidate->status, [
                RecruitmentCandidate::STATUS_JOINING_SUBMITTED,
                RecruitmentCandidate::STATUS_JOINING_HOLD,
            ], true) ? $candidate->status : RecruitmentCandidate::STATUS_JOINING_FORM_SHARED,
            'joining_admin_id' => $candidate->joining_admin_id ?: $admin->id,
            'joining_started_at' => $candidate->joining_started_at ?: now(config('app.timezone', 'Asia/Kolkata')),
        ], $this->candidateAdminSnapshotUpdates($admin, 'joining')));

        $this->logCandidateActivity($candidate, $admin, 'joining_start', array_filter([
            'status' => $this->makeChangeEntry('Status', $beforeStatus, $candidate->status),
            'joining_admin_name' => $this->makeChangeEntry('Joining User', $beforeJoiningAdminName, $candidate->joining_admin_name),
            'hr_admin_name' => $this->makeChangeEntry('HRManager', $beforeHrAdminName, $candidate->hr_admin_name),
        ]));

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Onboarding form is ready to share with the candidate.',
                'status' => $candidate->status,
            ]);
        }

        return redirect()
            ->route('admin-joining-form', $candidate->id)
            ->with('status', 'Onboarding form is ready to share with the candidate.');
    }

    public function editOnboarding(int $candidateId): View
    {
        $candidate = $this->findCandidate($candidateId);
        abort_unless($candidate->isReadyForJoining(), 404);

        $branches = Branch::query()
            ->where('status', 1)
            ->orderBy('branchName')
            ->get(['branchId', 'branchName']);

        return view('admin.recruitment.joining_form', [
            'candidate' => $candidate,
            'branches' => $branches,
            'noticePeriodOptions' => self::NOTICE_PERIOD_OPTIONS,
            'genderOptions' => self::GENDER_OPTIONS,
            'maritalStatusOptions' => self::MARITAL_STATUS_OPTIONS,
            'bloodGroupOptions' => self::BLOOD_GROUP_OPTIONS,
            'relationshipOptions' => self::RELATIONSHIP_OPTIONS,
            'documentKeys' => self::DOCUMENT_KEYS,
            'defaults' => $this->defaultOnboardingPayload($candidate),
        ]);
    }

    public function storeOnboarding(Request $request, int $candidateId): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $candidate = $this->findCandidate($candidateId);
        abort_unless($candidate->isReadyForJoining(), 404);

        $data = $this->validateOnboarding($request);
        $beforeStatus = $candidate->status;
        $beforePayload = $candidate->onboarding_payload ?? [];
        $beforeEmployeePhotoPath = $candidate->employee_photo_path;
        $beforeDocumentPhotoPaths = $candidate->document_photo_paths ?? [];
        $beforeFixedSalary = $candidate->fixed_salary;
        $beforeJoiningAdminName = $candidate->joining_admin_name;
        $beforeHrAdminName = $candidate->hr_admin_name;
        $onboardingPayload = $this->buildOnboardingPayload($data);
        $fixedSalary = $this->nullableDecimal($data['fixed_salary'] ?? null);

        $employeePhotoPath = $this->imageService->storeDataUri(
            $data['employee_photo_data'] ?? null,
            'recruitment/onboarding',
            'employee'
        ) ?: $candidate->employee_photo_path;

        $documentPhotoPaths = $candidate->document_photo_paths ?? [];

        foreach (array_keys(self::DOCUMENT_KEYS) as $documentKey) {
            $newPath = $this->imageService->storeDataUri(
                data_get($data, 'document_photo_data.'.$documentKey),
                'recruitment/onboarding/documents',
                $documentKey
            );

            if ($newPath) {
                $documentPhotoPaths[$documentKey] = $newPath;
            }
        }

        $nextStatus = in_array($candidate->status, [
            RecruitmentCandidate::STATUS_ONBOARDED,
            RecruitmentCandidate::STATUS_JOINED,
        ], true) ? $candidate->status : RecruitmentCandidate::STATUS_JOINING_SUBMITTED;

        $candidate->update(array_merge([
            'status' => $nextStatus,
            'joining_admin_id' => $candidate->joining_admin_id ?: $admin->id,
            'joining_started_at' => $candidate->joining_started_at ?: now(config('app.timezone', 'Asia/Kolkata')),
            'employee_photo_path' => $employeePhotoPath,
            'document_photo_paths' => $documentPhotoPaths,
            'onboarding_payload' => $onboardingPayload,
            'fixed_salary' => $fixedSalary,
            'onboarding_completed_at' => now(config('app.timezone', 'Asia/Kolkata')),
        ], $this->candidateAdminSnapshotUpdates($admin, 'joining')));

        if ($candidate->status === RecruitmentCandidate::STATUS_JOINED && $candidate->generated_emp_id) {
            Employee::query()
                ->where('empId', $candidate->generated_emp_id)
                ->update([
                    'salary' => $fixedSalary,
                ]);
        }

        $changes = array_filter([
            'status' => $this->makeChangeEntry('Status', $beforeStatus, $candidate->status),
            'fixed_salary' => $this->makeChangeEntry('Fixed Salary', $beforeFixedSalary, $candidate->fixed_salary),
            'joining_admin_name' => $this->makeChangeEntry('Joining User', $beforeJoiningAdminName, $candidate->joining_admin_name),
            'hr_admin_name' => $this->makeChangeEntry('HRManager', $beforeHrAdminName, $candidate->hr_admin_name),
            'employee_photo_path' => $this->makeChangeEntry('Employee Photo', $beforeEmployeePhotoPath, $employeePhotoPath),
            'document_photo_paths' => $this->makeChangeEntry('Document Photos', $beforeDocumentPhotoPaths, $documentPhotoPaths),
            'onboarding_payload' => $this->makePayloadChangeEntry('Onboarding Details', $beforePayload, $onboardingPayload, $this->onboardingFieldLabels()),
        ]);

        if ($candidate->status === RecruitmentCandidate::STATUS_JOINED && $candidate->generated_emp_id) {
            $changes['employee_salary_sync'] = [
                'label' => 'Employee Salary Sync',
                'changed' => [$candidate->generated_emp_id],
            ];
        }

        $this->logCandidateActivity($candidate, $admin, 'joining_form_saved', $changes);

        return redirect()
            ->route('admin-joining-form', $candidate->id)
            ->with('status', 'Onboarding details saved.');
    }

    public function updateJoiningDecision(Request $request, int $candidateId): RedirectResponse|JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $candidate = $this->findCandidate($candidateId);
        $data = $request->validate([
            'action' => ['required', Rule::in(['onboarded', 'hold', 'reject', 'resend'])],
            'date_of_joining' => ['nullable', 'date'],
            'appointed_designation' => ['nullable', 'string', 'max:255'],
            'deployed_branch_id' => ['nullable', 'string', 'max:255'],
            'shift_timing' => ['nullable', 'string', 'max:255'],
            'fixed_salary' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($data['action'] === 'onboarded') {
            $requiredAssignmentFields = [
                'date_of_joining' => 'Date of joining is required before marking candidate as onboarded.',
                'appointed_designation' => 'Appointed designation is required before marking candidate as onboarded.',
                'deployed_branch_id' => 'Deployed branch is required before marking candidate as onboarded.',
                'shift_timing' => 'Shift timing is required before marking candidate as onboarded.',
                'fixed_salary' => 'Fixed salary is required before marking candidate as onboarded.',
            ];

            foreach ($requiredAssignmentFields as $field => $message) {
                $value = $data[$field] ?? null;

                if ($value === null || trim((string) $value) === '') {
                    throw ValidationException::withMessages([$field => $message]);
                }
            }
        }

        $updates = [];
        $message = 'Joining status updated.';
        $shareUrl = null;
        $beforeStatus = $candidate->status;
        $beforeFixedSalary = $candidate->fixed_salary;
        $beforeJoiningAdminName = $candidate->joining_admin_name;
        $beforeHrAdminName = $candidate->hr_admin_name;
        $beforeOnboardingPayload = $candidate->onboarding_payload ?? [];

        if ($data['action'] === 'onboarded') {
            $onboardingPayload = $this->mergeOnboardingAssignmentData(
                $candidate->onboarding_payload ?? [],
                $data
            );

            $updates['status'] = RecruitmentCandidate::STATUS_ONBOARDED;
            $updates['onboarding_completed_at'] = $candidate->onboarding_completed_at ?: now(config('app.timezone', 'Asia/Kolkata'));
            $updates['onboarding_payload'] = $onboardingPayload;
            $updates['fixed_salary'] = $this->nullableDecimal($data['fixed_salary'] ?? data_get($onboardingPayload, 'fixed_salary'));
            $message = $candidate->generated_emp_id
                ? 'Candidate marked as onboarded. Assigned Employee ID '.$candidate->generated_emp_id.' is retained.'
                : 'Candidate marked as onboarded. Assign the Employee ID in the Newly Onboarded tab before marking on duty.';
        }

        if ($data['action'] === 'hold') {
            $updates['status'] = RecruitmentCandidate::STATUS_JOINING_HOLD;
            $message = 'Candidate marked as hold in joining review.';
        }

        if ($data['action'] === 'reject') {
            $updates['status'] = RecruitmentCandidate::STATUS_JOINING_REJECTED;
            $message = 'Candidate rejected in joining review.';
        }

        if ($data['action'] === 'resend') {
            $updates['status'] = RecruitmentCandidate::STATUS_JOINING_UPDATE_REQUESTED;
            $updates['joining_update_token'] = Str::lower((string) Str::uuid());
            $updates['joining_update_requested_at'] = now(config('app.timezone', 'Asia/Kolkata'));
            $shareUrl = route('recruitment-onboarding-update-link', $updates['joining_update_token']);
            $message = 'Onboarding form reopened with a new update link for this submission.';
        }

        $updates = array_merge($updates, $this->candidateAdminSnapshotUpdates($admin, 'joining'));
        $candidate->update($updates);
        $this->logCandidateActivity($candidate, $admin, 'joining_'.$data['action'], array_filter([
            'status' => $this->makeChangeEntry('Status', $beforeStatus, $candidate->status),
            'fixed_salary' => $this->makeChangeEntry('Fixed Salary', $beforeFixedSalary, $candidate->fixed_salary),
            'joining_admin_name' => $this->makeChangeEntry('Joining User', $beforeJoiningAdminName, $candidate->joining_admin_name),
            'hr_admin_name' => $this->makeChangeEntry('HRManager', $beforeHrAdminName, $candidate->hr_admin_name),
            'joining_update_link' => $this->makeChangeEntry('Joining Update Link', null, $shareUrl),
            'onboarding_payload' => isset($onboardingPayload)
                ? $this->makePayloadChangeEntry('Onboarding Assignment', $beforeOnboardingPayload, $onboardingPayload, $this->onboardingFieldLabels())
                : null,
        ]));

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'status' => $candidate->status,
                'share_url' => $shareUrl,
            ]);
        }

        return redirect()
            ->route('admin-joining-index')
            ->with('status', $message);
    }

    public function markJoined(
        Request $request,
        int $candidateId,
        AtticaGoldEmployeeSyncService $employeeSyncService
    ): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $candidate = $this->findCandidate($candidateId);
        abort_unless(
            in_array($candidate->status, [
                RecruitmentCandidate::STATUS_ONBOARDED,
                RecruitmentCandidate::STATUS_ONBOARDING_COMPLETED,
            ], true),
            404
        );

        $request->merge([
            'date_of_joining' => $this->normalizeDateInput($request->input('date_of_joining')),
        ]);

        $data = $request->validate([
            'generated_emp_id' => ['required', 'string', 'max:255'],
            'date_of_joining' => ['required', 'date'],
            'appointed_designation' => ['required', 'string', 'max:255'],
            'deployed_branch_id' => ['required', 'string', 'max:255'],
            'shift_timing' => ['required', 'string', 'max:255'],
            'fixed_salary' => ['nullable', 'numeric', 'min:0'],
        ], [
            'generated_emp_id.required' => 'Employee ID is required.',
            'date_of_joining.required' => 'Date of joining is required.',
            'date_of_joining.date' => 'Date of joining must be a valid date.',
            'appointed_designation.required' => 'Appointed designation is required.',
            'deployed_branch_id.required' => 'Deployed branch is required.',
            'shift_timing.required' => 'Shift timing is required.',
        ]);

        $generatedEmpId = trim((string) $data['generated_emp_id']);
        $conflicts = $this->employeeIdConflicts($generatedEmpId, null, $candidate->id);

        if ($conflicts !== []) {
            return $this->redirectBackWithOnboardedEmployeeIdConflicts(
                $candidate,
                $generatedEmpId,
                $conflicts,
                'Employee ID is already assigned. Use a different Employee ID; existing assignments cannot be cleared or reassigned.'
            );
        }

        $this->ensureCandidateEmployeeIdIsAvailable($generatedEmpId, $candidate);

        $branchId = trim((string) $data['deployed_branch_id']);
        $branch = Branch::query()
            ->whereRaw('TRIM(branchId) = ?', [$branchId])
            ->first();

        if (! $branch) {
            return redirect()
                ->route('admin-employee-index', ['tab' => 'onboarded'])
                ->withErrors(['deployed_branch_id' => 'Selected branch was not found.'])
                ->withInput();
        }

        $onboarding = array_merge($candidate->onboarding_payload ?? [], [
            'date_of_joining' => $this->nullableDate($data['date_of_joining']),
            'appointed_designation' => trim((string) $data['appointed_designation']),
            'deployed_branch_id' => trim((string) $branch->branchId),
            'deployed_branch_name' => trim((string) $branch->branchName),
            'shift_timing' => trim((string) $data['shift_timing']),
        ]);
        $fixedSalary = $this->nullableDecimal($data['fixed_salary'] ?? data_get($onboarding, 'fixed_salary'));
        $onboarding['fixed_salary'] = $fixedSalary;
        $employee = null;
        $beforeStatus = $candidate->status;
        $beforeGeneratedEmpId = $candidate->generated_emp_id;
        $beforeFixedSalary = $candidate->fixed_salary;
        $beforeJoiningAdminName = $candidate->joining_admin_name;
        $beforeHrAdminName = $candidate->hr_admin_name;
        $beforeOnboarding = $candidate->onboarding_payload ?? [];
        $adminSnapshots = $this->candidateAdminSnapshotUpdates($admin, 'joining');

        DB::transaction(function () use (&$employee, $candidate, $generatedEmpId, $onboarding, $adminSnapshots, $fixedSalary): void {
            $employee = Employee::query()->firstOrNew([
                'empId' => $generatedEmpId,
            ]);

            $employee->empId = $generatedEmpId;
            $employee->name = trim((string) (data_get($onboarding, 'full_name_as_per_aadhar') ?: $candidate->candidate_name));
            $employee->contact = trim((string) (data_get($onboarding, 'contact_number') ?: $candidate->contact_number));
            $employee->mailId = trim((string) (data_get($onboarding, 'personal_email_id') ?: $candidate->email));
            $employee->address = $this->composeEmployeeAddress($onboarding);
            $employee->location = trim((string) data_get($onboarding, 'deployed_branch_name'));
            $employee->photo = $candidate->employee_photo_path ?: $candidate->candidate_photo_path;
            $employee->designation = trim((string) (data_get($onboarding, 'appointed_designation') ?: $candidate->position_applied_for));
            $employee->status = 'Active';
            $employee->doj = $this->nullableDate(data_get($onboarding, 'date_of_joining'));
            $employee->shift_timing = trim((string) data_get($onboarding, 'shift_timing'));
            $employee->gender = trim((string) data_get($onboarding, 'gender'));
            $employee->marital_status = trim((string) data_get($onboarding, 'marital_status'));
            $employee->date_of_birth = $this->nullableDate(data_get($onboarding, 'date_of_birth'));
            $employee->advance = $employee->exists ? $employee->advance : 0;
            $employee->pf = $employee->exists ? $employee->pf : 0;
            $employee->salary = $fixedSalary !== null ? $fixedSalary : ($employee->exists ? $employee->salary : null);
            $employee->remark = 'Joined via recruitment workflow #'.$candidate->id;
            $employee->save();

            $candidate->update([
                'status' => RecruitmentCandidate::STATUS_JOINED,
                'generated_emp_id' => $generatedEmpId,
                'onboarding_payload' => $onboarding,
                'fixed_salary' => $fixedSalary,
                'joining_admin_name' => $adminSnapshots['joining_admin_name'] ?? $candidate->joining_admin_name,
                'hr_admin_name' => $adminSnapshots['hr_admin_name'] ?? $candidate->hr_admin_name,
                'joined_at' => now(config('app.timezone', 'Asia/Kolkata')),
            ]);
        });

        $this->logCandidateActivity($candidate, $admin, 'mark_joined', array_filter([
            'status' => $this->makeChangeEntry('Status', $beforeStatus, $candidate->status),
            'generated_emp_id' => $this->makeChangeEntry('Employee ID', $beforeGeneratedEmpId, $candidate->generated_emp_id),
            'fixed_salary' => $this->makeChangeEntry('Fixed Salary', $beforeFixedSalary, $candidate->fixed_salary),
            'joining_admin_name' => $this->makeChangeEntry('Joining User', $beforeJoiningAdminName, $candidate->joining_admin_name),
            'hr_admin_name' => $this->makeChangeEntry('HRManager', $beforeHrAdminName, $candidate->hr_admin_name),
            'onboarding_payload' => $this->makePayloadChangeEntry('Joining Assignment', $beforeOnboarding, $candidate->onboarding_payload ?? [], $this->onboardingFieldLabels()),
        ]));

        $message = 'Employee record created for onboarded candidate. Employee ID: '.$generatedEmpId;
        $alertType = 'success';

        try {
            $syncResult = $employeeSyncService->sync($employee, $branch);

            if (($syncResult['synced'] ?? false) === true) {
                $message .= ' atticagold sync completed.';
            } elseif (($syncResult['enabled'] ?? false) === false) {
                $message .= ' atticagold sync is not configured.';
                $alertType = 'warning';
            }
        } catch (\Throwable $exception) {
            report($exception);
            $message .= ' Local employee was created, but atticagold sync failed.';
            $alertType = 'warning';
        }

        return redirect()
            ->route('admin-employee-index', ['tab' => 'onboarded'])
            ->with([
                'message' => $message,
                'alert-type' => $alertType,
            ]);
    }

    public function deleteOnboarded(int $candidateId): RedirectResponse
    {
        $candidate = $this->findCandidate($candidateId);
        abort_unless(
            in_array($candidate->status, [
                RecruitmentCandidate::STATUS_ONBOARDED,
                RecruitmentCandidate::STATUS_ONBOARDING_COMPLETED,
            ], true),
            404
        );

        $candidateName = trim((string) $candidate->candidate_name) ?: 'Candidate';
        $generatedEmpId = trim((string) $candidate->generated_emp_id);
        $candidate->delete();

        $message = 'Newly onboarded entry deleted for '.$candidateName;

        if ($generatedEmpId !== '') {
            $message .= ' (Employee ID '.$generatedEmpId.')';
        }

        $message .= '.';

        return redirect()
            ->route('admin-employee-index', ['tab' => 'onboarded'])
            ->with([
                'message' => $message,
                'alert-type' => 'success',
            ]);
    }

    private function employeeIdConflicts(string $empId, ?int $ignoreEmployeeId = null, ?int $ignoreCandidateId = null): array
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
            ->when($ignoreCandidateId !== null, fn ($query) => $query->where('id', '!=', $ignoreCandidateId))
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

    private function redirectBackWithOnboardedEmployeeIdConflicts(
        RecruitmentCandidate $candidate,
        string $empId,
        array $conflicts,
        string $message
    ): RedirectResponse {
        return redirect()
            ->route('admin-employee-index', ['tab' => 'onboarded'])
            ->withErrors(['generated_emp_id' => $message])
            ->withInput()
            ->with('onboarded_duplicate_conflicts', [
                'candidate_id' => $candidate->id,
                'employee_id' => $empId,
                'conflicts' => $conflicts,
            ]);
    }

    private function ensureCandidateEmployeeIdIsAvailable(string $empId, RecruitmentCandidate $candidate): void
    {
        if ($empId === '') {
            throw ValidationException::withMessages([
                'generated_emp_id' => 'Employee ID is required.',
            ]);
        }

        $candidateExists = RecruitmentCandidate::query()
            ->where('id', '!=', $candidate->id)
            ->whereRaw('TRIM(generated_emp_id) = ?', [$empId])
            ->exists();

        if ($candidateExists) {
            throw ValidationException::withMessages([
                'generated_emp_id' => 'Employee ID is already assigned to another recruitment candidate.',
            ]);
        }
    }

    private function findCandidate(int $candidateId): RecruitmentCandidate
    {
        /** @var Admin|null $admin */
        $admin = Auth::guard('admin')->user();
        $query = RecruitmentCandidate::query()
            ->with(['hiringAdmin', 'joiningAdmin', 'formLink', 'resubmissionOf', 'activityLogs.admin']);

        $this->applyCandidateAdminPositionFilter($query, $admin);

        return $query->findOrFail($candidateId);
    }

    private function validateOnboarding(Request $request): array
    {
        return $request->validate([
            'full_name_as_per_aadhar' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(self::GENDER_OPTIONS)],
            'marital_status' => ['nullable', Rule::in(self::MARITAL_STATUS_OPTIONS)],
            'spouse_name' => ['nullable', 'string', 'max:255'],
            'number_of_children' => ['nullable', 'integer', 'min:0'],
            'personal_email_id' => ['nullable', 'email', 'max:255'],
            'present_address' => ['nullable', 'string', 'max:2000'],
            'present_city' => ['nullable', 'string', 'max:255'],
            'present_pin_code' => ['nullable', 'digits_between:4,10'],
            'rented_owner_name' => ['nullable', 'string', 'max:255'],
            'rented_owner_contact_number' => ['nullable', 'digits:10'],
            'permanent_address' => ['nullable', 'string', 'max:2000'],
            'permanent_city' => ['nullable', 'string', 'max:255'],
            'permanent_pin_code' => ['nullable', 'digits_between:4,10'],
            'contact_number' => ['required', 'digits:10'],
            'blood_group' => ['nullable', Rule::in(self::BLOOD_GROUP_OPTIONS)],
            'referred_by' => ['nullable', 'string', 'max:255'],
            'religion' => ['nullable', 'string', 'max:255'],
            'caste' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'previous_designation' => ['nullable', 'string', 'max:255'],
            'relieving_date' => ['nullable', 'date'],
            'years_of_experience' => ['nullable', 'numeric', 'min:0'],
            'reporting_officer_name' => ['nullable', 'string', 'max:255'],
            'reporting_officer_contact_number' => ['nullable', 'digits:10'],
            'computer_knowledge' => ['nullable', 'string', 'max:1000'],
            'work_experiences' => ['nullable', 'array'],
            'work_experiences.*.company_name' => ['nullable', 'string', 'max:255'],
            'work_experiences.*.designation' => ['nullable', 'string', 'max:255'],
            'work_experiences.*.experience' => ['nullable', 'numeric', 'min:0'],
            'languages_speak' => ['nullable', 'string', 'max:1000'],
            'languages_read' => ['nullable', 'string', 'max:1000'],
            'languages_write' => ['nullable', 'string', 'max:1000'],
            'present_remuneration' => ['nullable', 'numeric', 'min:0'],
            'salary_expectation' => ['nullable', 'numeric', 'min:0'],
            'notice_period' => ['nullable', Rule::in(self::NOTICE_PERIOD_OPTIONS)],
            'fixed_salary' => ['nullable', 'numeric', 'min:0'],
            'references' => ['nullable', 'array'],
            'references.*.name' => ['nullable', 'string', 'max:255'],
            'references.*.contact_number' => ['nullable', 'digits:10'],
            'references.*.designation' => ['nullable', 'string', 'max:255'],
            'references.*.relationship' => ['nullable', Rule::in(self::RELATIONSHIP_OPTIONS)],
            'place' => ['nullable', 'string', 'max:255'],
            'form_date' => ['nullable', 'date'],
            'signature' => ['required', 'string', 'max:255'],
            'date_of_joining' => ['nullable', 'date'],
            'appointed_designation' => ['nullable', 'string', 'max:255'],
            'deployed_branch_id' => ['nullable', 'string', 'max:255'],
            'shift_timing' => ['nullable', 'string', 'max:255'],
            'emergency_contact_number' => ['nullable', 'digits:10'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_relationship' => ['nullable', Rule::in(self::RELATIONSHIP_OPTIONS)],
            'emergency_reference_name' => ['nullable', 'string', 'max:255'],
            'emergency_reference_contact_number' => ['nullable', 'digits:10'],
            'emergency_reference_designation' => ['nullable', 'string', 'max:255'],
            'document_checklist' => ['nullable', 'array'],
            'document_checklist.*' => ['nullable', 'boolean'],
            'employee_photo_data' => ['nullable', 'string'],
            'document_photo_data' => ['nullable', 'array'],
            'document_photo_data.*' => ['nullable', 'string'],
        ]);
    }

    private function buildOnboardingPayload(array $data): array
    {
        $documentChecklist = [];

        foreach (array_keys(self::DOCUMENT_KEYS) as $documentKey) {
            $documentChecklist[$documentKey] = (bool) data_get($data, 'document_checklist.'.$documentKey);
        }

        return $this->mergeOnboardingAssignmentData([
            'full_name_as_per_aadhar' => trim((string) ($data['full_name_as_per_aadhar'] ?? '')),
            'father_name' => trim((string) ($data['father_name'] ?? '')),
            'mother_name' => trim((string) ($data['mother_name'] ?? '')),
            'date_of_birth' => $this->nullableDate($data['date_of_birth'] ?? null),
            'gender' => trim((string) ($data['gender'] ?? '')),
            'marital_status' => trim((string) ($data['marital_status'] ?? '')),
            'spouse_name' => trim((string) ($data['spouse_name'] ?? '')),
            'number_of_children' => trim((string) ($data['number_of_children'] ?? '')),
            'personal_email_id' => trim((string) ($data['personal_email_id'] ?? '')),
            'present_address' => trim((string) ($data['present_address'] ?? '')),
            'present_city' => trim((string) ($data['present_city'] ?? '')),
            'present_pin_code' => trim((string) ($data['present_pin_code'] ?? '')),
            'rented_owner_name' => trim((string) ($data['rented_owner_name'] ?? '')),
            'rented_owner_contact_number' => trim((string) ($data['rented_owner_contact_number'] ?? '')),
            'permanent_address' => trim((string) ($data['permanent_address'] ?? '')),
            'permanent_city' => trim((string) ($data['permanent_city'] ?? '')),
            'permanent_pin_code' => trim((string) ($data['permanent_pin_code'] ?? '')),
            'contact_number' => trim((string) ($data['contact_number'] ?? '')),
            'blood_group' => trim((string) ($data['blood_group'] ?? '')),
            'referred_by' => trim((string) ($data['referred_by'] ?? '')),
            'religion' => trim((string) ($data['religion'] ?? '')),
            'caste' => trim((string) ($data['caste'] ?? '')),
            'company_name' => trim((string) ($data['company_name'] ?? '')),
            'previous_designation' => trim((string) ($data['previous_designation'] ?? '')),
            'relieving_date' => $this->nullableDate($data['relieving_date'] ?? null),
            'years_of_experience' => trim((string) ($data['years_of_experience'] ?? '')),
            'reporting_officer_name' => trim((string) ($data['reporting_officer_name'] ?? '')),
            'reporting_officer_contact_number' => trim((string) ($data['reporting_officer_contact_number'] ?? '')),
            'computer_knowledge' => trim((string) ($data['computer_knowledge'] ?? '')),
            'work_experiences' => $this->cleanRows($data['work_experiences'] ?? [], [
                'company_name',
                'designation',
                'experience',
            ]),
            'languages_speak' => trim((string) ($data['languages_speak'] ?? '')),
            'languages_read' => trim((string) ($data['languages_read'] ?? '')),
            'languages_write' => trim((string) ($data['languages_write'] ?? '')),
            'present_remuneration' => $this->nullableDecimal($data['present_remuneration'] ?? null),
            'salary_expectation' => $this->nullableDecimal($data['salary_expectation'] ?? null),
            'notice_period' => trim((string) ($data['notice_period'] ?? '')),
            'fixed_salary' => $this->nullableDecimal($data['fixed_salary'] ?? null),
            'references' => $this->cleanRows($data['references'] ?? [], [
                'name',
                'contact_number',
                'designation',
                'relationship',
            ]),
            'place' => trim((string) ($data['place'] ?? '')),
            'form_date' => $this->nullableDate($data['form_date'] ?? null),
            'signature' => trim((string) ($data['signature'] ?? '')),
            'emergency_contact_number' => trim((string) ($data['emergency_contact_number'] ?? '')),
            'emergency_contact_name' => trim((string) ($data['emergency_contact_name'] ?? '')),
            'emergency_relationship' => trim((string) ($data['emergency_relationship'] ?? '')),
            'emergency_reference_name' => trim((string) ($data['emergency_reference_name'] ?? '')),
            'emergency_reference_contact_number' => trim((string) ($data['emergency_reference_contact_number'] ?? '')),
            'emergency_reference_designation' => trim((string) ($data['emergency_reference_designation'] ?? '')),
            'document_checklist' => $documentChecklist,
        ], $data);
    }

    private function defaultOnboardingPayload(RecruitmentCandidate $candidate): array
    {
        $existing = $candidate->onboarding_payload ?? [];
        $hiring = $candidate->hiring_payload ?? [];

        return array_merge([
            'full_name_as_per_aadhar' => $candidate->candidate_name,
            'date_of_birth' => data_get($hiring, 'date_of_birth'),
            'gender' => data_get($hiring, 'gender'),
            'marital_status' => data_get($hiring, 'marital_status'),
            'personal_email_id' => $candidate->email,
            'present_address' => data_get($hiring, 'current_address'),
            'permanent_address' => data_get($hiring, 'permanent_address'),
            'contact_number' => $candidate->contact_number,
            'computer_knowledge' => data_get($hiring, 'computer_knowledge'),
            'work_experiences' => data_get($hiring, 'work_experiences', [['company_name' => '', 'designation' => '', 'experience' => '']]),
            'languages_speak' => data_get($hiring, 'languages_speak'),
            'languages_read' => data_get($hiring, 'languages_read'),
            'languages_write' => data_get($hiring, 'languages_write'),
            'present_remuneration' => data_get($hiring, 'present_remuneration'),
            'salary_expectation' => data_get($hiring, 'salary_expectation'),
            'notice_period' => data_get($hiring, 'notice_period'),
            'references' => data_get($hiring, 'references', [['name' => '', 'contact_number' => '', 'designation' => '', 'relationship' => '']]),
            'place' => data_get($hiring, 'place'),
            'form_date' => data_get($hiring, 'form_date') ?: now(config('app.timezone', 'Asia/Kolkata'))->toDateString(),
            'signature' => data_get($hiring, 'signature'),
            'appointed_designation' => $candidate->position_applied_for,
            'fixed_salary' => $candidate->fixed_salary,
            'document_checklist' => [],
        ], $existing);
    }

    private function mergeOnboardingAssignmentData(array $payload, array $data): array
    {
        $dateOfJoining = trim((string) ($data['date_of_joining'] ?? ''));
        $appointedDesignation = trim((string) ($data['appointed_designation'] ?? ''));
        $shiftTiming = trim((string) ($data['shift_timing'] ?? ''));
        $branchId = trim((string) ($data['deployed_branch_id'] ?? ''));
        $hasFixedSalary = array_key_exists('fixed_salary', $data);

        if ($dateOfJoining !== '') {
            $payload['date_of_joining'] = $this->nullableDate($dateOfJoining);
        }

        if ($appointedDesignation !== '') {
            $payload['appointed_designation'] = $appointedDesignation;
        }

        if ($shiftTiming !== '') {
            $payload['shift_timing'] = $shiftTiming;
        }

        if ($hasFixedSalary) {
            $payload['fixed_salary'] = $this->nullableDecimal($data['fixed_salary'] ?? null);
        }

        if ($branchId !== '') {
            $branch = Branch::query()
                ->whereRaw('TRIM(branchId) = ?', [$branchId])
                ->first();

            if (! $branch) {
                throw ValidationException::withMessages([
                    'deployed_branch_id' => 'Selected branch was not found.',
                ]);
            }

            $payload['deployed_branch_id'] = trim((string) $branch->branchId);
            $payload['deployed_branch_name'] = trim((string) $branch->branchName);
        }

        return $payload;
    }

    private function cleanRows(array $rows, array $keys): array
    {
        return collect($rows)
            ->map(function ($row) use ($keys): array {
                return collect($keys)
                    ->mapWithKeys(fn (string $key): array => [$key => trim((string) data_get($row, $key))])
                    ->all();
            })
            ->filter(function (array $row): bool {
                return collect($row)->contains(fn (?string $value): bool => trim((string) $value) !== '');
            })
            ->values()
            ->all();
    }

    private function candidateAdminSnapshotUpdates(?Admin $admin, string $stage): array
    {
        $displayName = $this->adminDisplayName($admin);
        $roleKey = $this->adminRoleKey($admin);

        if ($displayName === null) {
            return [];
        }

        $updates = [];

        if ($stage === 'hiring' && $roleKey === Admin::ROLE_HIRING) {
            $updates['hiring_admin_name'] = $displayName;
        }

        if ($stage === 'joining' && $roleKey === Admin::ROLE_JOINING) {
            $updates['joining_admin_name'] = $displayName;
        }

        if (in_array($roleKey, [Admin::ROLE_HR_ADMIN, Admin::ROLE_SUBHR], true)) {
            $updates['hr_admin_name'] = $displayName;
        }

        return $updates;
    }

    private function adminDisplayName(?Admin $admin): ?string
    {
        if (! $admin) {
            return null;
        }

        $name = trim((string) ($admin->name ?? ''));
        $email = trim((string) ($admin->email ?? ''));
        $display = $name !== '' ? $name : $email;

        return $display !== '' ? $display : null;
    }

    private function adminRoleKey(?Admin $admin): string
    {
        $role = strtolower(trim((string) ($admin?->role ?? '')));

        return $role !== '' ? $role : Admin::ROLE_HR_ADMIN;
    }

    private function applyCandidateAdminPositionFilter($query, ?Admin $admin): void
    {
        $position = $this->restrictedAdminPosition($admin);

        if ($position === null) {
            return;
        }

        if ($position === '') {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereRaw('LOWER(TRIM(position_applied_for)) = ?', [$position]);
    }

    private function applyFormLinkAdminPositionFilter($query, ?Admin $admin): void
    {
        $position = $this->restrictedAdminPosition($admin);

        if ($position === null) {
            return;
        }

        if ($position === '') {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($query) use ($position): void {
            $query
                ->whereNull('question_bank')
                ->orWhere('question_bank', '')
                ->orWhere('question_bank', '[]')
                ->orWhere('question_bank', '{}')
                ->orWhereRaw('LOWER(question_bank) LIKE ?', ['%"'.$this->escapeJsonLikeValue($position).'"%']);
        });
    }

    private function restrictedAdminPosition(?Admin $admin): ?string
    {
        if (! $admin) {
            return null;
        }

        if ($this->adminRoleKey($admin) === Admin::ROLE_SUBHR) {
            return null;
        }

        $position = $this->normalizeRecruitmentPosition($admin->position ?? '');

        return $position !== '' ? $position : null;
    }

    private function normalizeRecruitmentPosition(?string $position): string
    {
        $position = preg_replace('/\s+/', ' ', trim((string) $position)) ?? '';

        return Str::lower($position);
    }

    private function escapeJsonLikeValue(string $value): string
    {
        return addcslashes($value, '\\%_"');
    }

    private function logCandidateActivity(RecruitmentCandidate $candidate, ?Admin $admin, string $action, array $changes): void
    {
        if ($changes === []) {
            return;
        }

        RecruitmentCandidateActivityLog::query()->create([
            'recruitment_candidate_id' => $candidate->id,
            'admin_id' => $admin?->id,
            'actor_name' => $this->adminDisplayName($admin),
            'actor_role' => $this->adminRoleKey($admin),
            'action' => $action,
            'remarks' => $this->buildAuditRemarks($changes),
            'changed_fields' => $changes,
        ]);
    }

    private function makeChangeEntry(string $label, $oldValue, $newValue): ?array
    {
        if ($this->normalizeAuditValue($oldValue) === $this->normalizeAuditValue($newValue)) {
            return null;
        }

        return [
            'label' => $label,
            'old' => $this->formatAuditValue($oldValue),
            'new' => $this->formatAuditValue($newValue),
        ];
    }

    private function makePayloadChangeEntry(string $label, array $before, array $after, array $fieldLabels): ?array
    {
        $changedLabels = [];

        foreach ($fieldLabels as $field => $fieldLabel) {
            if ($this->normalizeAuditValue(data_get($before, $field)) !== $this->normalizeAuditValue(data_get($after, $field))) {
                $changedLabels[] = $fieldLabel;
            }
        }

        if ($changedLabels === []) {
            return null;
        }

        return [
            'label' => $label,
            'changed' => $changedLabels,
        ];
    }

    private function buildAuditRemarks(array $changes): string
    {
        return collect($changes)
            ->map(function (array $change): string {
                $label = $change['label'] ?? 'Field';

                if (array_key_exists('old', $change) && array_key_exists('new', $change)) {
                    return $label.' changed from '.$change['old'].' to '.$change['new'];
                }

                if (! empty($change['changed']) && is_array($change['changed'])) {
                    return $label.' updated: '.implode(', ', $change['changed']);
                }

                return $label.' updated';
            })
            ->implode('; ');
    }

    private function normalizeAuditValue($value): string
    {
        if (is_array($value)) {
            return json_encode($this->sortRecursive($value));
        }

        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    private function formatAuditValue($value): string
    {
        if (is_array($value)) {
            return json_encode($this->sortRecursive($value));
        }

        $stringValue = trim((string) $value);

        return $stringValue !== '' ? $stringValue : '--';
    }

    private function sortRecursive(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortRecursive($item);
            }
        }

        ksort($value);

        return $value;
    }

    private function onboardingFieldLabels(): array
    {
        return [
            'full_name_as_per_aadhar' => 'Full Name',
            'father_name' => 'Father Name',
            'mother_name' => 'Mother Name',
            'date_of_birth' => 'Date of Birth',
            'gender' => 'Gender',
            'marital_status' => 'Marital Status',
            'spouse_name' => 'Spouse Name',
            'number_of_children' => 'Number of Children',
            'personal_email_id' => 'Personal Email',
            'present_address' => 'Present Address',
            'present_city' => 'Present City',
            'present_pin_code' => 'Present PIN Code',
            'rented_owner_name' => 'Owner Name',
            'rented_owner_contact_number' => 'Owner Contact Number',
            'permanent_address' => 'Permanent Address',
            'permanent_city' => 'Permanent City',
            'permanent_pin_code' => 'Permanent PIN Code',
            'contact_number' => 'Contact Number',
            'blood_group' => 'Blood Group',
            'referred_by' => 'Referred By',
            'religion' => 'Religion',
            'caste' => 'Caste',
            'company_name' => 'Company Name',
            'previous_designation' => 'Previous Designation',
            'relieving_date' => 'Relieving Date',
            'years_of_experience' => 'Years of Experience',
            'reporting_officer_name' => 'Reporting Officer Name',
            'reporting_officer_contact_number' => 'Reporting Officer Contact Number',
            'computer_knowledge' => 'Computer Knowledge',
            'work_experiences' => 'Work Experience',
            'languages_speak' => 'Languages Speak',
            'languages_read' => 'Languages Read',
            'languages_write' => 'Languages Write',
            'present_remuneration' => 'Present Remuneration',
            'salary_expectation' => 'Salary Expectation',
            'notice_period' => 'Notice Period',
            'fixed_salary' => 'Fixed Salary',
            'references' => 'References',
            'place' => 'Place',
            'form_date' => 'Form Date',
            'signature' => 'Signature',
            'emergency_contact_number' => 'Emergency Contact Number',
            'emergency_contact_name' => 'Emergency Contact Name',
            'emergency_relationship' => 'Emergency Relationship',
            'emergency_reference_name' => 'Emergency Reference Name',
            'emergency_reference_contact_number' => 'Emergency Reference Contact Number',
            'emergency_reference_designation' => 'Emergency Reference Designation',
            'document_checklist' => 'Document Checklist',
            'date_of_joining' => 'Date of Joining',
            'appointed_designation' => 'Appointed Designation',
            'deployed_branch_id' => 'Deployed Branch',
            'deployed_branch_name' => 'Deployed Branch Name',
            'shift_timing' => 'Shift Timing',
        ];
    }

    private function candidateHiringDate(RecruitmentCandidate $candidate): ?string
    {
        return $this->nullableDate(
            optional($candidate->submitted_at)->toDateString()
                ?: optional($candidate->verification_shared_at)->toDateString()
                ?: optional($candidate->created_at)->toDateString()
                ?: data_get($candidate->hiring_payload, 'hiring_date')
                ?: optional($candidate->formLink?->hiring_date)->toDateString()
        );
    }

    private function candidateJoiningDate(RecruitmentCandidate $candidate): ?string
    {
        $markedJoiningDate = $this->nullableDate(data_get($candidate->onboarding_payload, 'date_of_joining'));

        if ($markedJoiningDate !== null) {
            return $markedJoiningDate;
        }

        return $this->nullableDate(
            optional($candidate->joining_started_at)->toDateString()
                ?: optional($candidate->onboarding_completed_at)->toDateString()
                ?: optional($candidate->updated_at)->toDateString()
        );
    }

    private function isMovedToJoiningStatus(?string $status): bool
    {
        return in_array($status, [
            RecruitmentCandidate::STATUS_JOINING_FORM_SHARED,
            RecruitmentCandidate::STATUS_JOINING_SUBMITTED,
            RecruitmentCandidate::STATUS_JOINING_HOLD,
            RecruitmentCandidate::STATUS_JOINING_REJECTED,
            RecruitmentCandidate::STATUS_JOINING_UPDATE_REQUESTED,
            RecruitmentCandidate::STATUS_ONBOARDED,
            RecruitmentCandidate::STATUS_JOINED,
        ], true);
    }

    private function hiringStatusFilterOptions(): array
    {
        return [
            RecruitmentCandidate::STATUS_HIRING_FORM_SHARED => 'Form Shared',
            RecruitmentCandidate::STATUS_HIRING_SUBMITTED => 'Submitted',
            RecruitmentCandidate::STATUS_HIRING_SELECTED => 'Selected',
            RecruitmentCandidate::STATUS_HIRING_HOLD => 'Hold',
            RecruitmentCandidate::STATUS_HIRING_REJECTED => 'Rejected',
            RecruitmentCandidate::STATUS_HIRING_UPDATE_REQUESTED => 'Update Requested',
            'moved_to_joining' => 'Moved To Joining',
        ];
    }

    private function joiningStatusFilterOptions(): array
    {
        return [
            RecruitmentCandidate::STATUS_HIRING_SELECTED => 'Selected',
            RecruitmentCandidate::STATUS_JOINING_FORM_SHARED => 'Form Shared',
            RecruitmentCandidate::STATUS_JOINING_SUBMITTED => 'Submitted',
            RecruitmentCandidate::STATUS_JOINING_HOLD => 'Hold',
            RecruitmentCandidate::STATUS_JOINING_REJECTED => 'Rejected',
            RecruitmentCandidate::STATUS_JOINING_UPDATE_REQUESTED => 'Update Requested',
            RecruitmentCandidate::STATUS_ONBOARDED => 'Onboarded',
            RecruitmentCandidate::STATUS_JOINED => 'Joined',
        ];
    }

    private function resolveHiringPlaceTab(?string $place): string
    {
        $normalized = strtolower(trim((string) $place));

        if ($normalized === '') {
            return 'other';
        }

        $tokenized = preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?? $normalized;
        $compact = str_replace(' ', '', $tokenized);
        $tokens = array_values(array_filter(explode(' ', $tokenized)));

        $matchesToken = static fn (string $token) => in_array($token, $tokens, true);
        $containsAny = static fn (array $needles) => collect($needles)->contains(
            fn (string $needle): bool => str_contains($tokenized, $needle) || str_contains($compact, str_replace(' ', '', $needle))
        );

        if (
            $containsAny([
                'karnataka', 'bangalore', 'bengaluru', 'mysore', 'mysuru', 'mangalore', 'mangaluru',
                'hubli', 'hubballi', 'dharwad', 'belgaum', 'belagavi', 'udupi', 'tumkur', 'tumakuru',
                'gulbarga', 'kalaburagi', 'shimoga', 'shivamogga', 'davanagere', 'ballari', 'bellary',
                'bijapur', 'vijayapura', 'hassan', 'raichur', 'bidar', 'kolar', 'chitradurga',
            ]) || $matchesToken('ka')
        ) {
            return 'karnataka';
        }

        if (
            $containsAny([
                'andhra pradesh', 'andhra', 'vijayawada', 'visakhapatnam', 'vizag', 'guntur', 'tirupati',
                'kurnool', 'nellore', 'rajahmundry', 'kakinada', 'kadapa', 'cuddapah', 'anantapur',
                'ongole', 'eluru', 'srikakulam', 'vizianagaram', 'chittoor', 'amaravati',
                'machilipatnam', 'bhimavaram', 'tenali',
            ]) || $matchesToken('ap')
        ) {
            return 'ap';
        }

        if (
            $containsAny([
                'tamil nadu', 'tamilnadu', 'chennai', 'coimbatore', 'madurai', 'salem', 'tiruchirappalli',
                'trichy', 'erode', 'vellore', 'tirunelveli', 'thoothukudi', 'tuticorin', 'hosur',
                'thanjavur', 'dindigul', 'cuddalore', 'karur', 'namakkal', 'kanchipuram', 'tiruppur',
            ]) || $matchesToken('tn')
        ) {
            return 'tn';
        }

        if (
            $containsAny([
                'telangana', 'hyderabad', 'secunderabad', 'warangal', 'karimnagar', 'nizamabad',
                'khammam', 'nalgonda', 'mahbubnagar', 'adilabad', 'medchal', 'rangareddy',
                'ranga reddy', 'sangareddy', 'siddipet', 'suryapet', 'jagtial', 'kamareddy',
            ]) || $matchesToken('ts')
        ) {
            return 'ts';
        }

        if (
            $containsAny([
                'pondicherry', 'puducherry', 'karaikal', 'mahe', 'yanam',
            ]) || $matchesToken('py')
        ) {
            return 'pondicherry';
        }

        return 'other';
    }

    private function attachHiringDuplicateMetadata($candidates): void
    {
        $groups = collect($candidates)
            ->groupBy(function (RecruitmentCandidate $candidate): string {
                return $this->candidateAadhaarNumber($candidate);
            })
            ->filter(fn ($items, string $aadhaar): bool => $aadhaar !== '' && $items->count() > 1);

        foreach ($candidates as $candidate) {
            $normalizedAadhaar = $this->candidateAadhaarNumber($candidate);
            $duplicates = ($groups[$normalizedAadhaar] ?? collect())
                ->filter(fn (RecruitmentCandidate $item): bool => $item->id !== $candidate->id)
                ->values();

            $candidate->duplicate_candidates = $duplicates;
            $candidate->duplicate_count = $duplicates->count();
            $candidate->has_duplicate_aadhaar = $duplicates->isNotEmpty();
        }
    }

    private function latestCandidatesPerAadhaar($candidates)
    {
        $seenAadhaar = [];

        return collect($candidates)
            ->filter(function (RecruitmentCandidate $candidate) use (&$seenAadhaar): bool {
                $normalizedAadhaar = $this->candidateAadhaarNumber($candidate);

                if ($normalizedAadhaar === '') {
                    return true;
                }

                if (isset($seenAadhaar[$normalizedAadhaar])) {
                    return false;
                }

                $seenAadhaar[$normalizedAadhaar] = true;

                return true;
            })
            ->values();
    }

    private function findHiringDuplicateCandidates(RecruitmentCandidate $candidate)
    {
        $aadhaar = $this->candidateAadhaarNumber($candidate);

        if ($aadhaar === '') {
            return collect();
        }

        /** @var Admin|null $admin */
        $admin = Auth::guard('admin')->user();
        $query = RecruitmentCandidate::query()
            ->with(['hiringAdmin', 'formLink'])
            ->whereNotNull('submission_code')
            ->where('id', '!=', $candidate->id)
            ->orderByDesc('created_at');

        $this->applyCandidateAdminPositionFilter($query, $admin);

        return $query
            ->get()
            ->filter(function (RecruitmentCandidate $item) use ($aadhaar): bool {
                return $this->candidateAadhaarNumber($item) === $aadhaar;
            })
            ->values();
    }

    private function candidateAadhaarNumber(RecruitmentCandidate $candidate): string
    {
        return $this->normalizedAadhaarNumber($candidate->aadhaar_number ?: data_get($candidate->hiring_payload, 'aadhaar_number'));
    }

    private function normalizedAadhaarNumber(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return strlen($digits) === 12 ? $digits : '';
    }

    private function questionBankFromRequest(array $groups, bool $allowShortQuestionSets = false): array
    {
        $questionBank = collect($groups)
            ->mapWithKeys(function (array $group): array {
                $position = trim((string) ($group['position'] ?? ''));
                $normalizedPosition = strtolower($position);
                $questions = preg_split('/\r\n|\r|\n/', (string) ($group['questions'] ?? '')) ?: [];
                $questions = collect($questions)
                    ->map(fn ($question): string => trim((string) $question))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                return $position !== '' && ! in_array($normalizedPosition, ['other', 'others'], true)
                    ? [$position => $questions]
                    : [];
            })
            ->filter(fn (array $questions): bool => $allowShortQuestionSets || count($questions) > 0)
            ->all();

        foreach ($questionBank as $position => $questions) {
            if (! $allowShortQuestionSets && count($questions) < 5) {
                throw ValidationException::withMessages([
                    'question_groups' => 'Each configured position must have at least 5 interview questions.',
                ]);
            }
        }

        return $questionBank;
    }

    private function genericInterviewQuestions(): array
    {
        return [
            'Tell us about yourself and the work you handled most recently.',
            'Why do you want to join Attica Gold for this role?',
            'What strengths make you suitable for this position?',
            'Describe a difficult customer or workplace situation and how you handled it.',
            'What targets or responsibilities did you handle in your previous role?',
            'How do you stay disciplined with attendance, reporting, and follow-up?',
            'Why are you looking for a change right now?',
            'What are your salary expectations and joining timeline?',
            'What kind of work environment helps you perform best?',
            'What should we know about you that is not visible in your resume?',
        ];
    }

    private function nullableDate($value): ?string
    {
        $trimmed = $this->normalizeDateInput($value);

        if ($trimmed === '') {
            return null;
        }

        return Carbon::parse($trimmed, config('app.timezone', 'Asia/Kolkata'))->toDateString();
    }

    private function normalizeDateInput($value): string
    {
        $trimmed = trim((string) $value);

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

        return $trimmed;
    }

    private function nullableDecimal($value): ?string
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '' || ! is_numeric($trimmed)) {
            return null;
        }

        return number_format((float) $trimmed, 2, '.', '');
    }

    private function composeEmployeeAddress(array $onboarding): string
    {
        $parts = [
            trim((string) data_get($onboarding, 'present_address')),
            trim((string) data_get($onboarding, 'present_city')),
            trim((string) data_get($onboarding, 'present_pin_code')),
        ];

        return collect($parts)
            ->filter(fn (string $part): bool => $part !== '')
            ->implode(', ');
    }

    private function generateUniqueEmployeeId(): string
    {
        return $this->employeeIdGenerator->generate();
    }
}
