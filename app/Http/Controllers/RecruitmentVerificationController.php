<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentFormLink;
use App\Services\RecruitmentImageService;
use App\Services\RecruitmentResumeAutofillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RecruitmentVerificationController extends Controller
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
    private const DEFAULT_INTERVIEW_QUESTIONS = [
        'Tell us about yourself and the work you handled most recently.',
        'Why do you want to join Attica Gold for this position?',
        'What are your strongest skills for this role?',
        'Describe a difficult customer or workplace situation and how you handled it.',
        'What targets or responsibilities have you managed in your last job?',
        'How do you stay disciplined with attendance, reporting, and follow-up?',
        'What do you expect from your next manager and workplace?',
        'Why are you looking for a job change right now?',
        'What makes you different from other candidates applying for this role?',
        'If selected, how soon can you join and what support would you need to start?'
    ];
    private const HIRING_LOCATION_OPTIONS = [
        'Karnataka' => [
            'Bengaluru', 'Mysuru', 'Mangaluru', 'Hubballi', 'Dharwad', 'Belagavi',
            'Ballari', 'Davanagere', 'Shivamogga', 'Tumakuru', 'Udupi', 'Vijayapura',
            'Kalaburagi', 'Hassan', 'Raichur', 'Bidar', 'Kolar', 'Chitradurga',
        ],
        'Tamil Nadu' => [
            'Chennai', 'Coimbatore', 'Madurai', 'Salem', 'Tiruchirappalli', 'Tiruppur',
            'Erode', 'Vellore', 'Tirunelveli', 'Thoothukudi', 'Hosur', 'Dindigul',
            'Thanjavur', 'Karur', 'Namakkal', 'Cuddalore', 'Kanchipuram', 'Nagercoil',
        ],
        'Telangana' => [
            'Hyderabad', 'Secunderabad', 'Warangal', 'Karimnagar', 'Nizamabad', 'Khammam',
            'Nalgonda', 'Mahbubnagar', 'Adilabad', 'Siddipet', 'Suryapet', 'Jagtial',
            'Kamareddy', 'Medchal', 'Sangareddy', 'Ramagundam',
        ],
        'Andhra Pradesh' => [
            'Vijayawada', 'Visakhapatnam', 'Guntur', 'Tirupati', 'Kurnool', 'Nellore',
            'Rajahmundry', 'Kakinada', 'Kadapa', 'Anantapur', 'Ongole', 'Eluru',
            'Srikakulam', 'Vizianagaram', 'Chittoor', 'Machilipatnam', 'Bhimavaram',
        ],
        'Pondicherry' => [
            'Puducherry', 'Karaikal', 'Mahe', 'Yanam',
        ],
    ];

    public function __construct(
        private readonly RecruitmentImageService $imageService,
        private readonly RecruitmentResumeAutofillService $resumeAutofillService
    ) {
    }

    public function hiringForm(Request $request, ?string $token = null): View
    {
        $formLink = $this->resolveHiringFormLink($token);
        $sourceSubmission = $this->findSourceSubmission($request->query('resubmission'), $formLink);

        return view('recruitment.hiring_form', [
            'formLink' => $formLink,
            'branches' => $this->activeBranches(),
            'hiringLocationOptions' => self::HIRING_LOCATION_OPTIONS,
            'requiresVideoInterview' => $formLink->requiresVideoInterview(),
            'noticePeriodOptions' => self::NOTICE_PERIOD_OPTIONS,
            'genderOptions' => self::GENDER_OPTIONS,
            'maritalStatusOptions' => self::MARITAL_STATUS_OPTIONS,
            'relationshipOptions' => self::RELATIONSHIP_OPTIONS,
            'payload' => $sourceSubmission?->hiring_payload ?? [],
            'positionOptions' => $this->positionOptionsForFormLink($formLink),
            'questionBank' => $this->resolvedQuestionBank($formLink),
            'resubmissionOf' => $sourceSubmission,
        ]);
    }

    public function hiringUpdateForm(string $token): View
    {
        $sourceSubmission = RecruitmentCandidate::query()
            ->with('formLink')
            ->where('hiring_update_token', trim($token))
            ->whereNotNull('submission_code')
            ->firstOrFail();

        $formLink = $sourceSubmission->formLink;
        abort_unless($formLink instanceof RecruitmentFormLink && $formLink->is_active, 404);

        return view('recruitment.hiring_form', [
            'formLink' => $formLink,
            'branches' => $this->activeBranches(),
            'hiringLocationOptions' => self::HIRING_LOCATION_OPTIONS,
            'requiresVideoInterview' => $formLink->requiresVideoInterview(),
            'noticePeriodOptions' => self::NOTICE_PERIOD_OPTIONS,
            'genderOptions' => self::GENDER_OPTIONS,
            'maritalStatusOptions' => self::MARITAL_STATUS_OPTIONS,
            'relationshipOptions' => self::RELATIONSHIP_OPTIONS,
            'payload' => $sourceSubmission->hiring_payload ?? [],
            'positionOptions' => $this->positionOptionsForFormLink($formLink),
            'questionBank' => $this->resolvedQuestionBank($formLink),
            'resubmissionOf' => $sourceSubmission,
        ]);
    }

    public function submitHiringForm(Request $request, string $token): RedirectResponse
    {
        $formLink = $this->findFormLink($token);
        $data = $this->validateHiring($request, $formLink);
        $sourceSubmission = $this->findSourceSubmission($data['resubmission_submission_code'] ?? null, $formLink);

        $candidatePhotoPath = $this->imageService->storeDataUri(
            $data['candidate_photo_data'] ?? null,
            'recruitment/hiring',
            'candidate'
        );

        $resumeFilePath = null;
        $resumeOriginalName = null;

        if ($request->hasFile('resume_file')) {
            $resumeFile = $request->file('resume_file');
            $resumeFilePath = $this->imageService->storeUploadedFile($resumeFile, 'recruitment/resumes', 'resume');
            $resumeOriginalName = $resumeFile->getClientOriginalName();
        }

        $interviewVideoPath = null;
        $interviewVideoOriginalName = null;

        if ($request->hasFile('interview_video_file')) {
            $interviewVideo = $request->file('interview_video_file');
            $interviewVideoPath = $this->imageService->storeUploadedFile($interviewVideo, 'recruitment/interviews', 'interview');
            $interviewVideoOriginalName = $interviewVideo->getClientOriginalName();
        }

        $payload = $this->buildHiringPayload($data, $formLink);
        $whatsappNumber = (bool) ($data['is_whatsapp_same_as_contact'] ?? true)
            ? trim((string) $data['contact_number'])
            : trim((string) ($data['whatsapp_number'] ?? ''));
        $aadhaarNumber = $this->normalizedAadhaarNumber($data['aadhaar_number'] ?? null);

        $candidate = RecruitmentCandidate::query()->create([
            'recruitment_form_link_id' => $formLink->id,
            'resubmission_of_candidate_id' => $sourceSubmission?->id,
            'public_token' => Str::lower((string) Str::uuid()),
            'submission_code' => $this->generateUniqueSubmissionCode(),
            'status' => RecruitmentCandidate::STATUS_HIRING_SUBMITTED,
            'position_applied_for' => trim((string) $payload['position_applied_for']),
            'candidate_name' => trim((string) $payload['candidate_name']),
            'contact_number' => trim((string) $payload['contact_number']),
            'whatsapp_number' => $whatsappNumber,
            'aadhaar_number' => $aadhaarNumber !== '' ? $aadhaarNumber : null,
            'is_whatsapp_same_as_contact' => (bool) ($data['is_whatsapp_same_as_contact'] ?? true),
            'email' => trim((string) $payload['email']),
            'candidate_photo_path' => $candidatePhotoPath,
            'resume_file_path' => $resumeFilePath,
            'resume_original_name' => $resumeOriginalName,
            'interview_video_path' => $interviewVideoPath,
            'interview_video_original_name' => $interviewVideoOriginalName,
            'interview_payload' => $this->buildInterviewPayload($data),
            'hiring_payload' => $payload,
            'created_by_admin_id' => $formLink->created_by_admin_id,
            'hiring_admin_name' => $this->adminDisplayName($formLink->createdByAdmin),
            'verification_shared_at' => now(config('app.timezone', 'Asia/Kolkata')),
            'submitted_at' => now(config('app.timezone', 'Asia/Kolkata')),
            'verified_at' => now(config('app.timezone', 'Asia/Kolkata')),
        ]);

        return redirect()
            ->route('recruitment-hiring-form-show', $formLink->public_token)
            ->with('status', 'Your hiring form has been submitted successfully.')
            ->with('submission_code', $candidate->submission_code);
    }

    public function autofillHiringFromResume(Request $request, string $token): JsonResponse
    {
        $this->findFormLink($token);

        $data = $request->validate([
            'resume_file' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,gif,bmp,webp,svg', 'max:10240'],
        ]);

        $parsed = $this->resumeAutofillService->parseUploadedResume($data['resume_file']);

        return response()->json([
            'ok' => true,
            'payload' => $parsed,
        ]);
    }

    public function onboardingForm(string $token): View
    {
        $candidate = $this->findCandidate($token);
        abort_unless(
            in_array($candidate->status, [
                RecruitmentCandidate::STATUS_JOINING_FORM_SHARED,
                RecruitmentCandidate::STATUS_JOINING_SUBMITTED,
                RecruitmentCandidate::STATUS_JOINING_UPDATE_REQUESTED,
            ], true),
            404
        );

        $branches = Branch::query()
            ->where('status', 1)
            ->orderBy('branchName')
            ->get(['branchId', 'branchName']);

        return view('recruitment.onboarding', [
            'candidate' => $candidate,
            'branches' => $branches,
            'noticePeriodOptions' => self::NOTICE_PERIOD_OPTIONS,
            'genderOptions' => self::GENDER_OPTIONS,
            'maritalStatusOptions' => self::MARITAL_STATUS_OPTIONS,
            'bloodGroupOptions' => self::BLOOD_GROUP_OPTIONS,
            'relationshipOptions' => self::RELATIONSHIP_OPTIONS,
            'documentKeys' => self::DOCUMENT_KEYS,
            'defaults' => $this->defaultOnboardingPayload($candidate),
            'isReadOnly' => ! in_array($candidate->status, [
                RecruitmentCandidate::STATUS_JOINING_FORM_SHARED,
                RecruitmentCandidate::STATUS_JOINING_SUBMITTED,
                RecruitmentCandidate::STATUS_JOINING_UPDATE_REQUESTED,
            ], true),
        ]);
    }

    public function onboardingUpdateForm(string $token): View
    {
        $candidate = RecruitmentCandidate::query()
            ->where('joining_update_token', trim($token))
            ->firstOrFail();
        abort_unless(
            in_array($candidate->status, [
                RecruitmentCandidate::STATUS_JOINING_FORM_SHARED,
                RecruitmentCandidate::STATUS_JOINING_SUBMITTED,
                RecruitmentCandidate::STATUS_JOINING_UPDATE_REQUESTED,
                RecruitmentCandidate::STATUS_JOINING_HOLD,
                RecruitmentCandidate::STATUS_JOINING_REJECTED,
            ], true),
            404
        );

        $branches = Branch::query()
            ->where('status', 1)
            ->orderBy('branchName')
            ->get(['branchId', 'branchName']);

        return view('recruitment.onboarding', [
            'candidate' => $candidate,
            'branches' => $branches,
            'noticePeriodOptions' => self::NOTICE_PERIOD_OPTIONS,
            'genderOptions' => self::GENDER_OPTIONS,
            'maritalStatusOptions' => self::MARITAL_STATUS_OPTIONS,
            'bloodGroupOptions' => self::BLOOD_GROUP_OPTIONS,
            'relationshipOptions' => self::RELATIONSHIP_OPTIONS,
            'documentKeys' => self::DOCUMENT_KEYS,
            'defaults' => $this->defaultOnboardingPayload($candidate),
            'isReadOnly' => false,
        ]);
    }

    public function submitOnboardingForm(Request $request, string $token): RedirectResponse
    {
        $candidate = $this->findCandidate($token);
        abort_unless(
            in_array($candidate->status, [
                RecruitmentCandidate::STATUS_JOINING_FORM_SHARED,
                RecruitmentCandidate::STATUS_JOINING_SUBMITTED,
                RecruitmentCandidate::STATUS_JOINING_UPDATE_REQUESTED,
            ], true),
            404
        );

        $data = $this->validateOnboarding($request);

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

        $candidate->update([
            'status' => RecruitmentCandidate::STATUS_JOINING_SUBMITTED,
            'employee_photo_path' => $employeePhotoPath,
            'document_photo_paths' => $documentPhotoPaths,
            'onboarding_payload' => $this->buildOnboardingPayload($data),
            'onboarding_completed_at' => now(config('app.timezone', 'Asia/Kolkata')),
        ]);

        return redirect()
            ->route('recruitment-onboarding-form-show', $candidate->public_token)
            ->with('status', 'Your onboarding form has been submitted to the joining team.');
    }

    private function findFormLink(string $token): RecruitmentFormLink
    {
        return RecruitmentFormLink::query()
            ->where('public_token', trim($token))
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function resolveHiringFormLink(?string $token = null): RecruitmentFormLink
    {
        $token = trim((string) $token);

        if ($token !== '') {
            return $this->findFormLink($token);
        }

        return RecruitmentFormLink::query()
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->firstOrFail();
    }

    private function findCandidate(string $token): RecruitmentCandidate
    {
        return RecruitmentCandidate::query()
            ->where('public_token', trim($token))
            ->firstOrFail();
    }

    private function findSourceSubmission(?string $submissionCode, RecruitmentFormLink $formLink): ?RecruitmentCandidate
    {
        $submissionCode = trim((string) $submissionCode);

        if ($submissionCode === '') {
            return null;
        }

        return RecruitmentCandidate::query()
            ->where('submission_code', $submissionCode)
            ->where('recruitment_form_link_id', $formLink->id)
            ->first();
    }

    private function validateHiring(Request $request, RecruitmentFormLink $formLink): array
    {
        $positionOptions = $this->positionOptionsForFormLink($formLink);
        $positionValidationOptions = $positionOptions !== []
            ? array_values(array_unique([...$positionOptions, 'other']))
            : [];

        return $request->validate([
            'position_applied_for' => array_filter([
                'required',
                'string',
                'max:255',
                $positionValidationOptions !== [] ? Rule::in($positionValidationOptions) : null,
            ]),
            'custom_position_applied_for' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf((string) $request->input('position_applied_for') === 'other'),
            ],
            'candidate_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(self::GENDER_OPTIONS)],
            'marital_status' => ['nullable', Rule::in(self::MARITAL_STATUS_OPTIONS)],
            'current_address' => ['nullable', 'string', 'max:2000'],
            'permanent_address' => ['nullable', 'string', 'max:2000'],
            'contact_number' => ['required', 'digits:10'],
            'is_whatsapp_same_as_contact' => ['required', 'boolean'],
            'whatsapp_number' => ['nullable', 'digits:10', Rule::requiredIf((string) $request->input('is_whatsapp_same_as_contact', '1') === '0')],
            'aadhaar_number' => ['nullable', 'digits:12'],
            'email' => ['nullable', 'email', 'max:255'],
            'physical_fitness' => ['nullable', 'boolean'],
            'physical_fitness_reason' => ['nullable', 'string', 'max:1000'],
            'own_two_wheeler' => ['nullable', 'boolean'],
            'know_attica' => ['nullable', 'string', 'max:1000'],
            'computer_knowledge' => ['nullable', 'string', 'max:1000'],
            'present_remuneration' => ['nullable', 'numeric', 'min:0'],
            'salary_expectation' => ['nullable', 'numeric', 'min:0'],
            'notice_period' => ['nullable', Rule::in(self::NOTICE_PERIOD_OPTIONS)],
            'preferred_work_location_state' => ['required', 'string', 'max:255'],
            'preferred_work_location_city' => ['required', 'string', 'max:255'],
            'place' => ['nullable', 'string', 'max:255'],
            'form_date' => ['nullable', 'date'],
            'signature' => ['required', 'string', 'max:255'],
            'candidate_photo_data' => ['nullable', 'string'],
            'resume_file' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,gif,bmp,webp,svg', 'max:10240'],
            'interview_video_file' => array_filter([
                $formLink->requiresVideoInterview() ? 'required' : 'nullable',
                'file',
                'mimetypes:video/webm,video/mp4,video/quicktime,video/x-matroska',
                'max:102400',
            ]),
            'interview_payload' => [$formLink->requiresVideoInterview() ? 'required' : 'nullable', 'string'],
            'resubmission_submission_code' => ['nullable', 'string', 'max:32'],
            'qualifications' => ['nullable', 'array'],
            'qualifications.*.examination' => ['nullable', 'string', 'max:255'],
            'qualifications.*.university' => ['nullable', 'string', 'max:255'],
            'qualifications.*.main_subject' => ['nullable', 'string', 'max:255'],
            'qualifications.*.year_of_passing' => ['nullable', 'digits:4'],
            'qualifications.*.percentage_obtained' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'work_experiences' => ['nullable', 'array'],
            'work_experiences.*.company_name' => ['nullable', 'string', 'max:255'],
            'work_experiences.*.designation' => ['nullable', 'string', 'max:255'],
            'work_experiences.*.experience' => ['nullable', 'numeric', 'min:0'],
            'languages_speak' => ['nullable', 'string', 'max:1000'],
            'languages_read' => ['nullable', 'string', 'max:1000'],
            'languages_write' => ['nullable', 'string', 'max:1000'],
            'references' => ['nullable', 'array'],
            'references.*.name' => ['nullable', 'string', 'max:255'],
            'references.*.contact_number' => ['nullable', 'digits:10'],
            'references.*.designation' => ['nullable', 'string', 'max:255'],
            'references.*.relationship' => ['nullable', Rule::in(self::RELATIONSHIP_OPTIONS)],
        ]);
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
            'references' => ['nullable', 'array'],
            'references.*.name' => ['nullable', 'string', 'max:255'],
            'references.*.contact_number' => ['nullable', 'digits:10'],
            'references.*.designation' => ['nullable', 'string', 'max:255'],
            'references.*.relationship' => ['nullable', Rule::in(self::RELATIONSHIP_OPTIONS)],
            'place' => ['nullable', 'string', 'max:255'],
            'form_date' => ['nullable', 'date'],
            'signature' => ['required', 'string', 'max:255'],
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

    private function buildHiringPayload(array $data, RecruitmentFormLink $formLink): array
    {
        $resolvedPosition = trim((string) ($data['position_applied_for'] ?? ''));

        if ($resolvedPosition === 'other') {
            $resolvedPosition = trim((string) ($data['custom_position_applied_for'] ?? ''));
        }

        $preferredWorkLocation = $this->resolveHiringLocationOption(
            $data['preferred_work_location_state'] ?? null,
            $data['preferred_work_location_city'] ?? null
        );

        return [
            'hiring_date' => optional($formLink->hiring_date)->toDateString(),
            'position_applied_for' => $resolvedPosition,
            'candidate_name' => trim((string) $data['candidate_name']),
            'date_of_birth' => $this->nullableDate($data['date_of_birth'] ?? null),
            'gender' => trim((string) ($data['gender'] ?? '')),
            'marital_status' => trim((string) ($data['marital_status'] ?? '')),
            'current_address' => trim((string) ($data['current_address'] ?? '')),
            'permanent_address' => trim((string) ($data['permanent_address'] ?? '')),
            'contact_number' => trim((string) ($data['contact_number'] ?? '')),
            'whatsapp_number' => (bool) ($data['is_whatsapp_same_as_contact'] ?? true)
                ? trim((string) ($data['contact_number'] ?? ''))
                : trim((string) ($data['whatsapp_number'] ?? '')),
            'is_whatsapp_same_as_contact' => (bool) ($data['is_whatsapp_same_as_contact'] ?? true),
            'aadhaar_number' => $this->normalizedAadhaarNumber($data['aadhaar_number'] ?? null),
            'email' => trim((string) ($data['email'] ?? '')),
            'physical_fitness' => array_key_exists('physical_fitness', $data) ? (bool) $data['physical_fitness'] : null,
            'physical_fitness_reason' => trim((string) ($data['physical_fitness_reason'] ?? '')),
            'own_two_wheeler' => array_key_exists('own_two_wheeler', $data) ? (bool) $data['own_two_wheeler'] : null,
            'know_attica' => trim((string) ($data['know_attica'] ?? '')),
            'qualifications' => $this->cleanRows($data['qualifications'] ?? [], [
                'examination',
                'university',
                'main_subject',
                'year_of_passing',
                'percentage_obtained',
            ]),
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
            'references' => $this->cleanRows($data['references'] ?? [], [
                'name',
                'contact_number',
                'designation',
                'relationship',
            ]),
            'preferred_work_location_state' => $preferredWorkLocation['state'],
            'preferred_work_location_city' => $preferredWorkLocation['city'],
            'preferred_work_location_branch_id' => '',
            'preferred_work_location_branch_name' => $preferredWorkLocation['city'],
            'preferred_work_location_branch_state' => $preferredWorkLocation['state'],
            'preferred_work_location_label' => $preferredWorkLocation['city'].', '.$preferredWorkLocation['state'],
            'place' => trim((string) ($data['place'] ?? '')),
            'form_date' => $this->nullableDate($data['form_date'] ?? null),
            'signature' => trim((string) ($data['signature'] ?? '')),
        ];
    }

    private function buildInterviewPayload(array $data): array
    {
        $decoded = json_decode((string) ($data['interview_payload'] ?? '{}'), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function buildOnboardingPayload(array $data): array
    {
        $documentChecklist = [];

        foreach (array_keys(self::DOCUMENT_KEYS) as $documentKey) {
            $documentChecklist[$documentKey] = (bool) data_get($data, 'document_checklist.'.$documentKey);
        }

        return [
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
        ];
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
            'fixed_salary' => $candidate->fixed_salary,
            'document_checklist' => [],
        ], $existing);
    }

    private function activeBranches()
    {
        return Branch::query()
            ->where('status', 1)
            ->orderBy('branchName')
            ->get(['branchId', 'branchName', 'state']);
    }

    private function resolveHiringLocationOption($state, $city): array
    {
        $state = trim((string) $state);
        $city = trim((string) $city);

        if ($state === '') {
            throw ValidationException::withMessages([
                'preferred_work_location_state' => 'Preferred work state is required.',
            ]);
        }

        if ($city === '') {
            throw ValidationException::withMessages([
                'preferred_work_location_city' => 'Preferred work city is required.',
            ]);
        }

        $matchedState = collect(array_keys(self::HIRING_LOCATION_OPTIONS))
            ->first(fn (string $option): bool => strcasecmp($option, $state) === 0);

        if ($matchedState === null) {
            throw ValidationException::withMessages([
                'preferred_work_location_state' => 'Selected preferred work state is invalid.',
            ]);
        }

        $matchedCity = collect(self::HIRING_LOCATION_OPTIONS[$matchedState] ?? [])
            ->first(fn (string $option): bool => strcasecmp($option, $city) === 0);

        if ($matchedCity === null) {
            throw ValidationException::withMessages([
                'preferred_work_location_city' => 'Selected preferred work city is invalid for the chosen state.',
            ]);
        }

        return [
            'state' => $matchedState,
            'city' => $matchedCity,
        ];
    }

    private function resolveActiveBranch(string $branchId): Branch
    {
        $branchId = trim($branchId);

        if ($branchId === '') {
            throw ValidationException::withMessages([
                'preferred_work_location_branch_id' => 'Preferred work location is required.',
            ]);
        }

        $branch = Branch::query()
            ->where('status', 1)
            ->whereRaw('TRIM(branchId) = ?', [$branchId])
            ->first();

        if (! $branch) {
            throw ValidationException::withMessages([
                'preferred_work_location_branch_id' => 'Selected preferred work location was not found in the active branch list.',
            ]);
        }

        return $branch;
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

    private function nullableDate($value): ?string
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        return Carbon::parse($trimmed, config('app.timezone', 'Asia/Kolkata'))->toDateString();
    }

    private function nullableDecimal($value): ?string
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '' || ! is_numeric($trimmed)) {
            return null;
        }

        return number_format((float) $trimmed, 2, '.', '');
    }

    private function normalizedAadhaarNumber(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return strlen($digits) === 12 ? $digits : '';
    }

    private function adminDisplayName($admin): ?string
    {
        if (! $admin) {
            return null;
        }

        $name = trim((string) ($admin->name ?? ''));
        $email = trim((string) ($admin->email ?? ''));
        $display = $name !== '' ? $name : $email;

        return $display !== '' ? $display : null;
    }

    private function resolvedQuestionBank(RecruitmentFormLink $formLink): array
    {
        $questionBank = collect($formLink->question_bank ?? [])
            ->mapWithKeys(function ($questions, $position): array {
                if (str_starts_with((string) $position, '__')) {
                    return [];
                }

                $cleanQuestions = collect((array) $questions)
                    ->map(fn ($question): string => trim((string) $question))
                    ->filter()
                    ->values()
                    ->all();

                return trim((string) $position) !== '' ? [$position => $cleanQuestions] : [];
            })
            ->filter(fn (array $questions): bool => count($questions) >= 5)
            ->all();

        $branchManagerKey = collect(array_keys($questionBank))
            ->first(fn (string $position): bool => strcasecmp($position, 'Branch Manager') === 0);
        $assistantBranchManagerKey = collect(array_keys($questionBank))
            ->first(fn (string $position): bool => strcasecmp($position, 'Assistant Branch Manager') === 0);

        if ($branchManagerKey && ! $assistantBranchManagerKey) {
            $questionBank['Assistant Branch Manager'] = $questionBank[$branchManagerKey];
        }

        if ($questionBank === []) {
            $questionBank = [
                '__default__' => self::DEFAULT_INTERVIEW_QUESTIONS,
            ];
        } else {
            $questionBank['__default__'] = self::DEFAULT_INTERVIEW_QUESTIONS;
        }

        return $questionBank;
    }

    private function positionOptionsForFormLink(RecruitmentFormLink $formLink): array
    {
        $positionOptions = $formLink->positionOptions();

        if ($positionOptions !== [] || ! $formLink->isWalkInForm()) {
            return $positionOptions;
        }

        return RecruitmentFormLink::query()
            ->where('is_active', true)
            ->where('id', '!=', $formLink->id)
            ->orderByDesc('created_at')
            ->get()
            ->flatMap(fn (RecruitmentFormLink $link): array => $link->positionOptions())
            ->unique(fn (string $position): string => Str::lower(trim($position)))
            ->values()
            ->all();
    }

    private function generateUniqueSubmissionCode(): string
    {
        do {
            $code = 'SUB-'.strtoupper(Str::random(8));
        } while (RecruitmentCandidate::query()->where('submission_code', $code)->exists());

        return $code;
    }
}
