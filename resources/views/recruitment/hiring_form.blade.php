@php
    $qualifications = old('qualifications', data_get($payload, 'qualifications', [['examination' => '', 'university' => '', 'main_subject' => '', 'year_of_passing' => '', 'percentage_obtained' => '']]));
    $workExperiences = old('work_experiences', data_get($payload, 'work_experiences', [['company_name' => '', 'designation' => '', 'experience' => '']]));
    $references = old('references', data_get($payload, 'references', [['name' => '', 'contact_number' => '', 'designation' => '', 'relationship' => '']]));
    $branches = $branches ?? collect();
    $hiringLocationOptions = $hiringLocationOptions ?? [];
    $stateOptions = array_keys($hiringLocationOptions);
    $positionOptions = $positionOptions ?? [];
    $requiresVideoInterview = (bool) ($requiresVideoInterview ?? true);
    $storedPosition = old('position_applied_for', data_get($payload, 'position_applied_for'));
    $selectedPosition = $storedPosition;
    $customPosition = old('custom_position_applied_for', '');

    if ($positionOptions !== [] && trim((string) $storedPosition) !== '') {
        $normalizedStoredPosition = strtolower(trim((string) $storedPosition));

        if ($normalizedStoredPosition === 'other') {
            $selectedPosition = 'other';
        } elseif (! in_array($storedPosition, $positionOptions, true)) {
            $selectedPosition = 'other';
            $customPosition = $customPosition !== '' ? $customPosition : $storedPosition;
        }
    } elseif (! $requiresVideoInterview && trim((string) $storedPosition) !== '') {
        $selectedPosition = 'other';
        if (strtolower(trim((string) $storedPosition)) !== 'other') {
            $customPosition = $customPosition !== '' ? $customPosition : $storedPosition;
        }
    }

    $isWhatsappSame = old('is_whatsapp_same_as_contact', data_get($payload, 'is_whatsapp_same_as_contact', true));
    $submissionCode = session('submission_code');
    $preferredWorkLocationState = old('preferred_work_location_state', data_get($payload, 'preferred_work_location_state', data_get($payload, 'preferred_work_location_branch_state')));
    $preferredWorkLocationCity = old('preferred_work_location_city', data_get($payload, 'preferred_work_location_city', data_get($payload, 'preferred_work_location_branch_name')));
    $showPositionDropdown = $positionOptions !== [] || ! $requiresVideoInterview;
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $requiresVideoInterview ? 'Attica Hiring Form' : 'Attica Walk-In Form' }}</title>
    <link rel="icon" href="{{ \App\Support\ProjectAsset::url('admin/assets/images/attica_favicon.png') }}?v=1" type="image/png">
    <link href="{{ \App\Support\ProjectAsset::url('admin/assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <style>
        body { background: #f6f7fb; }
        .page-shell { max-width: 1180px; margin: 2rem auto; }
        .page-card { border: 0; border-radius: 1rem; box-shadow: 0 12px 30px rgba(0, 0, 0, .06); }
        .brand-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.25rem;
            border-radius: 1rem;
            background: linear-gradient(135deg, #16213e 0%, #8f6a2a 100%);
            color: #fff;
            box-shadow: 0 14px 34px rgba(22, 33, 62, .18);
        }
        .brand-mark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 3rem;
            height: 3rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, .14);
            padding: .4rem;
        }
        .brand-mark img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }
        .brand-kicker {
            text-transform: uppercase;
            letter-spacing: .18em;
            font-size: .72rem;
            opacity: .82;
        }
        .autofill-panel {
            border: 1px solid rgba(22, 33, 62, .08);
            border-radius: 1rem;
            padding: 1rem;
            background: #fbfcff;
        }
        .interview-stage {
            background: #101828;
            border-radius: 1rem;
            overflow: hidden;
            position: relative;
            min-height: 320px;
        }
        .interview-stage video,
        .interview-stage canvas {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .interview-overlay {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.1rem;
            border-radius: 1rem;
            background: #d9e6fb;
            border: 1px solid #bdd0f3;
        }
        .interview-timer {
            background: #1b2b52;
            color: #fff;
            border-radius: 999px;
            padding: .35rem .8rem;
            font-weight: 700;
            letter-spacing: .04em;
            white-space: nowrap;
        }
        .interview-question {
            color: #0f172a;
            font-size: 1rem;
            line-height: 1.5;
            flex: 1 1 auto;
        }
        .interview-stage-placeholder {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, .7);
            text-align: center;
            padding: 1.5rem;
        }
        .interview-preview-modal .modal-content {
            border: 0;
            border-radius: 1rem;
            overflow: hidden;
        }
        .interview-preview-modal .modal-header {
            border-bottom: 1px solid rgba(22, 33, 62, .08);
        }
        .interview-preview-modal video {
            width: 100%;
            display: block;
            background: #000;
            border-radius: .75rem;
        }
        .branch-search-picker {
            position: relative;
        }
        .branch-search-results {
            position: absolute;
            top: calc(100% + 0.35rem);
            left: 0;
            right: 0;
            z-index: 1065;
            max-height: 240px;
            overflow-y: auto;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 0.75rem;
            background: #fff;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
            display: none;
        }
        .branch-search-result {
            width: 100%;
            border: 0;
            background: transparent;
            text-align: left;
            padding: 0.75rem 0.9rem;
            line-height: 1.35;
        }
        .branch-search-result:hover,
        .branch-search-result:focus {
            background: rgba(13, 110, 253, 0.08);
            outline: none;
        }
        .branch-search-result-id {
            font-weight: 600;
        }
        .branch-search-result-name {
            display: block;
            color: #6c757d;
            font-size: 0.85rem;
        }
        .language-switcher {
            min-width: 220px;
        }
        .language-switcher .form-label {
            color: rgba(255, 255, 255, 0.82);
            font-size: 0.72rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .language-switcher .form-select {
            background-color: rgba(255, 255, 255, 0.96);
            border: 0;
            min-height: 42px;
        }
    </style>
</head>
<body>
    <div class="container page-shell">
        <div class="brand-header mb-4">
            <div class="d-flex align-items-center gap-3">
                <div class="brand-mark">
                    <img src="{{ \App\Support\ProjectAsset::url('admin/assets/images/logo-icon.png') }}" alt="Attica Gold logo">
                </div>
                <div>
                    <div class="brand-kicker" data-i18n="brand.company">Attica Gold Company</div>
                    <h1 class="h4 mb-0" data-i18n="{{ $requiresVideoInterview ? 'title.hiring' : 'title.walkin' }}">{{ $requiresVideoInterview ? 'Attica Hiring Form' : 'Attica Walk-In Form' }}</h1>
                </div>
            </div>
            <div class="d-flex flex-column align-items-lg-end gap-2">
                <div class="text-end small opacity-75">
                    <div data-i18n="brand.portal">Candidate Application Portal</div>
                    <div>{{ optional($formLink->hiring_date)->toDateString() ?: '--' }}</div>
                </div>
                <div class="language-switcher">
                    <label for="hiringFormLanguage" class="form-label mb-1" data-i18n="language.label">Language</label>
                    <select id="hiringFormLanguage" class="form-select form-select-sm">
                        <option value="en">English</option>
                        <option value="kn">ಕನ್ನಡ</option>
                        <option value="hi">हिन्दी</option>
                        <option value="te">తెలుగు</option>
                        <option value="ta">தமிழ்</option>
                    </select>
                </div>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success">
                <div>{{ session('status') }}</div>
                @if ($submissionCode)
                    <div class="mt-1 fw-semibold"><span data-i18n="alert.submission_id">Submission ID</span>: {{ $submissionCode }}</div>
                @endif
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($resubmissionOf)
            <div class="alert alert-info" data-i18n="alert.resubmission">
                You are updating a previous submission. A new submission ID will be created when you submit this form.
            </div>
        @endif

        <form method="post" action="{{ route('recruitment-hiring-form-submit', $formLink->public_token) }}" enctype="multipart/form-data" class="d-flex flex-column gap-4" id="hiringForm">
            @csrf
            <input type="hidden" name="resubmission_submission_code" value="{{ $resubmissionOf?->submission_code }}">

            <div class="card page-card mb-4">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
                        <div>
                            <h2 class="mb-1" data-i18n="{{ $requiresVideoInterview ? 'title.hiring' : 'title.walkin' }}">{{ $requiresVideoInterview ? 'Attica Hiring Form' : 'Attica Walk-In Form' }}</h2>
                            <p class="mb-0 text-muted" data-i18n="{{ $requiresVideoInterview ? 'intro.hiring_desc' : 'intro.walkin_desc' }}">
                                @if ($requiresVideoInterview)
                                    Please fill the hiring details, upload your resume, complete the video interview, and submit your form to the hiring team.
                                @else
                                    Please fill the hiring details, upload your resume, and submit your walk-in form to the hiring team.
                                @endif
                            </p>
                        </div>
                        <div class="text-lg-end">
                            <div class="fw-semibold" data-i18n="label.hiring_date">Hiring Date</div>
                            <div class="text-muted">{{ optional($formLink->hiring_date)->toDateString() ?: '--' }}</div>
                        </div>
                    </div>

                    <div class="autofill-panel mb-4">
                        <div class="row g-3 align-items-end">
                            <div class="col-lg-4">
                                <label class="form-label" data-i18n="autofill.resume_cv">Resume / CV</label>
                                <input type="file" name="resume_file" id="resumeFileInput" class="form-control" accept=".pdf,.doc,.docx">
                                <div class="form-text" data-i18n="autofill.accepted_types">Accepted: PDF, DOC, DOCX. Max 10 MB.</div>
                            </div>
                            <div class="col-lg-4">
                                <label class="form-label d-block" data-i18n="autofill.question">Autofill form fields from CV?</label>
                                <div class="d-flex flex-wrap gap-3 pt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="resume_autofill_choice" id="resumeAutofillYes" value="yes">
                                        <label class="form-check-label" for="resumeAutofillYes" data-i18n="common.yes">Yes</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="resume_autofill_choice" id="resumeAutofillNo" value="no" checked>
                                        <label class="form-check-label" for="resumeAutofillNo" data-i18n="common.no">No</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <button type="button" class="btn btn-outline-primary w-100" id="resumeAutofillButton" disabled data-i18n="autofill.button">Search CV And Autofill</button>
                            </div>
                            <div class="col-12">
                                <div class="alert alert-secondary mb-0" id="resumeAutofillStatus" data-status-key="autofill.initial">Upload your resume first. If you choose Yes, the form will search the CV and fill relevant fields automatically.</div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-lg-8">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" data-i18n="field.position">Position Applied For</label>
                                    @if ($showPositionDropdown)
                                        <select name="position_applied_for" id="positionAppliedFor" class="form-select" required>
                                            <option value="">Select</option>
                                            @foreach ($positionOptions as $option)
                                                <option value="{{ $option }}" @selected($selectedPosition === $option)>{{ $option }}</option>
                                            @endforeach
                                            <option value="other" @selected($selectedPosition === 'other')>Other</option>
                                        </select>
                                        <div class="mt-2" id="customPositionWrap" style="{{ $selectedPosition === 'other' ? '' : 'display:none;' }}">
                                            <input type="text" name="custom_position_applied_for" id="customPositionInput" class="form-control" value="{{ $customPosition }}" placeholder="Enter job role" data-i18n-placeholder="field.enter_job_role">
                                        </div>
                                    @else
                                        <input type="text" name="position_applied_for" id="positionAppliedFor" class="form-control" value="{{ $selectedPosition }}" required>
                                    @endif
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" data-i18n="field.candidate_name">Candidate Name</label>
                                    <input type="text" name="candidate_name" class="form-control" value="{{ old('candidate_name', data_get($payload, 'candidate_name')) }}" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" data-i18n="field.date_of_birth">Date of Birth</label>
                                    <input type="date" name="date_of_birth" class="form-control" value="{{ old('date_of_birth', data_get($payload, 'date_of_birth')) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" data-i18n="field.gender">Gender</label>
                                    <select name="gender" class="form-select">
                                        <option value="">Select</option>
                                        @foreach ($genderOptions as $option)
                                            <option value="{{ $option }}" @selected(old('gender', data_get($payload, 'gender')) === $option)>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" data-i18n="field.marital_status">Marital Status</label>
                                    <select name="marital_status" class="form-select">
                                        <option value="">Select</option>
                                        @foreach ($maritalStatusOptions as $option)
                                            <option value="{{ $option }}" @selected(old('marital_status', data_get($payload, 'marital_status')) === $option)>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" data-i18n="field.current_address">Current Address</label>
                                    <textarea name="current_address" class="form-control" rows="3">{{ old('current_address', data_get($payload, 'current_address')) }}</textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" data-i18n="field.permanent_address">Permanent Address</label>
                                    <textarea name="permanent_address" class="form-control" rows="3">{{ old('permanent_address', data_get($payload, 'permanent_address')) }}</textarea>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" data-i18n="field.phone_number">Phone Number</label>
                                    <input type="tel" inputmode="numeric" name="contact_number" id="contactNumber" class="form-control" value="{{ old('contact_number', data_get($payload, 'contact_number')) }}" maxlength="10" pattern="[0-9]{10}" data-digit-only="10" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" data-i18n="field.email">Email</label>
                                    <input type="email" name="email" class="form-control" value="{{ old('email', data_get($payload, 'email')) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" data-i18n="field.aadhaar_number">Aadhaar Card Number</label>
                                    <input type="tel" inputmode="numeric" name="aadhaar_number" class="form-control" value="{{ old('aadhaar_number', data_get($payload, 'aadhaar_number')) }}" maxlength="12" pattern="[0-9]{12}" data-digit-only="12">
                                </div>
                                <div class="col-12">
                                    <label class="form-label d-block" data-i18n="field.whatsapp_confirmation">WhatsApp Confirmation</label>
                                    <input type="hidden" name="is_whatsapp_same_as_contact" id="isWhatsappSameAsContactValue" value="{{ (string) $isWhatsappSame !== '0' ? '1' : '0' }}">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="1" id="whatsappSameAsContact" @checked((string) $isWhatsappSame !== '0')>
                                        <label class="form-check-label" for="whatsappSameAsContact" data-i18n="field.whatsapp_same">
                                            The entered phone number is my WhatsApp number.
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4" id="whatsappNumberWrap" style="{{ (string) $isWhatsappSame !== '0' ? 'display:none;' : '' }}">
                                    <label class="form-label" data-i18n="field.whatsapp_number">WhatsApp Number</label>
                                    <input type="tel" inputmode="numeric" name="whatsapp_number" id="whatsappNumber" class="form-control" value="{{ old('whatsapp_number', data_get($payload, 'whatsapp_number')) }}" maxlength="10" pattern="[0-9]{10}" data-digit-only="10">
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            @include('admin.recruitment.partials.camera_field', [
                                'fieldId' => 'candidate_photo_data',
                                'inputName' => 'candidate_photo_data',
                                'label' => 'Candidate Photo',
                                'labelKey' => 'field.candidate_photo',
                                'buttonLabel' => 'Open Camera',
                                'buttonLabelKey' => 'camera.open_button',
                                'previewSrc' => '',
                                'placeholderKey' => 'camera.no_image',
                                'titleKey' => 'field.candidate_photo',
                                'facingMode' => 'user',
                            ])
                        </div>
                    </div>
                </div>
            </div>

            @if ($requiresVideoInterview)
                <div class="card page-card mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                            <div>
                                <h4 class="mb-1" data-i18n="interview.heading">Interactive Video Interview</h4>
                                <p class="mb-0 text-muted" data-i18n="interview.description">Click start to open your front camera or webcam. The first question will always be about your recent work, and the remaining 4 questions will be selected randomly based on the chosen position. One video will be recorded with the question subtitles and countdown timer embedded in the saved video.</p>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-outline-primary" id="previewInterviewButton" data-i18n="interview.preview_button">Preview Interview</button>
                                <button type="button" class="btn btn-primary" id="startInterviewButton" data-i18n="interview.start_button">Start Interview</button>
                                <button type="button" class="btn btn-outline-secondary d-none" id="retakeInterviewButton" data-i18n="interview.record_again">Record Again</button>
                            </div>
                        </div>

                        <div class="interview-stage ratio ratio-16x9 mb-3">
                            <video id="interviewSourceVideo" autoplay playsinline muted style="display:none;"></video>
                            <canvas id="interviewPreviewCanvas"></canvas>
                            <div class="interview-stage-placeholder" id="interviewPlaceholder" data-i18n="interview.placeholder">
                                Start the interview to record your answers. Keep your face visible and speak clearly.
                            </div>
                        </div>

                        <div class="interview-overlay mb-3" id="interviewOverlay" style="display:none;">
                            <div class="interview-question" id="interviewQuestion" data-i18n="interview.question_placeholder">Question will appear here.</div>
                            <div class="interview-timer" id="interviewTimer">00:30</div>
                        </div>

                        <div class="alert alert-secondary mb-3" id="interviewStatus" data-status-key="interview.status_required">
                            The interview recording is required before you submit the form.
                        </div>

                        <input type="file" name="interview_video_file" id="interviewVideoFile" class="d-none" accept="video/webm,video/mp4,video/quicktime,video/x-matroska">
                        <input type="hidden" name="interview_payload" id="interviewPayload">
                    </div>
                </div>
            @endif

            <div class="card page-card mb-4">
                <div class="card-body p-4">
                    <h4 class="mb-3" data-i18n="section.additional_information">Additional Information</h4>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label d-block" data-i18n="field.physical_fitness">Physical Fitness</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="physical_fitness" value="1" @checked(old('physical_fitness', data_get($payload, 'physical_fitness')) === true || old('physical_fitness') === '1')>
                                    <label class="form-check-label" data-i18n="common.yes">Yes</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="physical_fitness" value="0" @checked(old('physical_fitness', data_get($payload, 'physical_fitness')) === false || old('physical_fitness') === '0')>
                                    <label class="form-check-label" data-i18n="common.no">No</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" data-i18n="field.if_no_reason">If No, Reason</label>
                            <input type="text" name="physical_fitness_reason" class="form-control" value="{{ old('physical_fitness_reason', data_get($payload, 'physical_fitness_reason')) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label d-block" data-i18n="field.own_two_wheeler">Own Two Wheeler</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="own_two_wheeler" value="1" @checked(old('own_two_wheeler', data_get($payload, 'own_two_wheeler')) === true || old('own_two_wheeler') === '1')>
                                    <label class="form-check-label" data-i18n="common.yes">Yes</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="own_two_wheeler" value="0" @checked(old('own_two_wheeler', data_get($payload, 'own_two_wheeler')) === false || old('own_two_wheeler') === '0')>
                                    <label class="form-check-label" data-i18n="common.no">No</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" data-i18n="field.know_attica">How do you know Attica</label>
                            <input type="text" name="know_attica" class="form-control" value="{{ old('know_attica', data_get($payload, 'know_attica')) }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card page-card mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="mb-0" data-i18n="section.professional_qualification">Professional Qualification</h4>
                        <button type="button" class="btn btn-outline-secondary btn-sm js-add-row" data-target="qualifications-body" data-template="qualification-row-template" data-i18n="common.add_row">Add Row</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th data-i18n="table.examination">Examination</th>
                                    <th data-i18n="table.university">University</th>
                                    <th data-i18n="table.main_subject">Main Subject</th>
                                    <th data-i18n="table.year_of_passing">Year of Passing</th>
                                    <th data-i18n="table.percentage_obtained">Percentage Obtained</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="qualifications-body">
                                @foreach ($qualifications as $index => $qualification)
                                    <tr>
                                        <td><input type="text" class="form-control" name="qualifications[{{ $index }}][examination]" value="{{ $qualification['examination'] ?? '' }}"></td>
                                        <td><input type="text" class="form-control" name="qualifications[{{ $index }}][university]" value="{{ $qualification['university'] ?? '' }}"></td>
                                        <td><input type="text" class="form-control" name="qualifications[{{ $index }}][main_subject]" value="{{ $qualification['main_subject'] ?? '' }}"></td>
                                        <td><input type="number" min="1000" max="9999" step="1" class="form-control" name="qualifications[{{ $index }}][year_of_passing]" value="{{ $qualification['year_of_passing'] ?? '' }}"></td>
                                        <td><input type="number" min="0" max="100" step="0.01" class="form-control" name="qualifications[{{ $index }}][percentage_obtained]" value="{{ $qualification['percentage_obtained'] ?? '' }}"></td>
                                        <td><button type="button" class="btn btn-outline-danger btn-sm js-remove-row" data-i18n="common.remove">Remove</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card page-card mb-4">
                <div class="card-body p-4">
                    <h4 class="mb-3" data-i18n="section.skills_experience">Skills & Experience</h4>
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <label class="form-label" data-i18n="field.computer_knowledge">Computer Knowledge</label>
                            <textarea name="computer_knowledge" class="form-control" rows="3">{{ old('computer_knowledge', data_get($payload, 'computer_knowledge')) }}</textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0" data-i18n="section.work_experience">Work Experience</h5>
                        <button type="button" class="btn btn-outline-secondary btn-sm js-add-row" data-target="work-experience-body" data-template="work-experience-row-template" data-i18n="common.add_row">Add Row</button>
                    </div>
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th data-i18n="table.company_name">Company Name</th>
                                    <th data-i18n="table.designation">Designation</th>
                                    <th data-i18n="table.experience">Experience</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="work-experience-body">
                                @foreach ($workExperiences as $index => $experience)
                                    <tr>
                                        <td><input type="text" class="form-control" name="work_experiences[{{ $index }}][company_name]" value="{{ $experience['company_name'] ?? '' }}"></td>
                                        <td><input type="text" class="form-control" name="work_experiences[{{ $index }}][designation]" value="{{ $experience['designation'] ?? '' }}"></td>
                                        <td><input type="number" min="0" step="0.1" class="form-control" name="work_experiences[{{ $index }}][experience]" value="{{ $experience['experience'] ?? '' }}"></td>
                                        <td><button type="button" class="btn btn-outline-danger btn-sm js-remove-row" data-i18n="common.remove">Remove</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" data-i18n="field.languages_speak">Languages Known - Speak</label>
                            <textarea name="languages_speak" class="form-control" rows="3">{{ old('languages_speak', data_get($payload, 'languages_speak')) }}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" data-i18n="field.languages_read">Languages Known - Read</label>
                            <textarea name="languages_read" class="form-control" rows="3">{{ old('languages_read', data_get($payload, 'languages_read')) }}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" data-i18n="field.languages_write">Languages Known - Write</label>
                            <textarea name="languages_write" class="form-control" rows="3">{{ old('languages_write', data_get($payload, 'languages_write')) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card page-card mb-4">
                <div class="card-body p-4">
                    <h4 class="mb-3" data-i18n="section.compensation">Compensation & Availability</h4>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" data-i18n="field.present_remuneration">Present Remuneration</label>
                            <input type="number" step="0.01" min="0" name="present_remuneration" class="form-control" value="{{ old('present_remuneration', data_get($payload, 'present_remuneration')) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" data-i18n="field.salary_expectation">Salary Expectation</label>
                            <input type="number" step="0.01" min="0" name="salary_expectation" class="form-control" value="{{ old('salary_expectation', data_get($payload, 'salary_expectation')) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" data-i18n="field.notice_period">Notice Period for Joining</label>
                            <select name="notice_period" class="form-select">
                                <option value="">Select</option>
                                @foreach ($noticePeriodOptions as $option)
                                    <option value="{{ $option }}" @selected(old('notice_period', data_get($payload, 'notice_period')) === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card page-card mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="mb-0" data-i18n="section.references">References</h4>
                        <button type="button" class="btn btn-outline-secondary btn-sm js-add-row" data-target="references-body" data-template="reference-row-template" data-i18n="common.add_row">Add Row</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th data-i18n="table.name">Name</th>
                                    <th data-i18n="table.contact_number">Contact Number</th>
                                    <th data-i18n="table.designation">Designation</th>
                                    <th data-i18n="table.relationship">Relationship</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="references-body">
                                @foreach ($references as $index => $reference)
                                    <tr>
                                        <td><input type="text" class="form-control" name="references[{{ $index }}][name]" value="{{ $reference['name'] ?? '' }}"></td>
                                        <td><input type="tel" inputmode="numeric" class="form-control" name="references[{{ $index }}][contact_number]" value="{{ $reference['contact_number'] ?? '' }}" maxlength="10" pattern="[0-9]{10}" data-digit-only="10"></td>
                                        <td><input type="text" class="form-control" name="references[{{ $index }}][designation]" value="{{ $reference['designation'] ?? '' }}"></td>
                                        <td>
                                            <select class="form-select" name="references[{{ $index }}][relationship]">
                                                <option value="">Select</option>
                                                @foreach ($relationshipOptions as $option)
                                                    <option value="{{ $option }}" @selected(($reference['relationship'] ?? '') === $option)>{{ $option }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td><button type="button" class="btn btn-outline-danger btn-sm js-remove-row" data-i18n="common.remove">Remove</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card page-card mb-4">
                <div class="card-body p-4">
                    <h4 class="mb-3" data-i18n="section.final">Final Section</h4>
                    <div class="row g-3">
                        <div class="col-lg-3">
                            <label class="form-label" data-i18n="field.preferred_state">Preferred Work State</label>
                            <select name="preferred_work_location_state" class="form-select" id="preferredWorkLocationState" required>
                                <option value="">Select State</option>
                                @foreach ($stateOptions as $stateOption)
                                    <option value="{{ $stateOption }}" @selected($preferredWorkLocationState === $stateOption)>{{ $stateOption }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label" data-i18n="field.preferred_city">Preferred Work City</label>
                            <select name="preferred_work_location_city" class="form-select" id="preferredWorkLocationCity" required data-selected-city="{{ $preferredWorkLocationCity }}">
                                <option value="">Select City</option>
                            </select>
                            <div class="form-text" data-i18n="field.choose_city_help">Choose a city from the selected state.</div>
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label" data-i18n="field.place">Place</label>
                            <input type="text" name="place" class="form-control" value="{{ old('place', data_get($payload, 'place')) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" data-i18n="field.date">Date</label>
                            <input type="date" name="form_date" class="form-control" value="{{ old('form_date', data_get($payload, 'form_date', now()->toDateString())) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" data-i18n="field.signature">Signature</label>
                            <input type="text" name="signature" class="form-control" value="{{ old('signature', data_get($payload, 'signature')) }}" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end pb-4">
                <button type="submit" class="btn btn-primary px-5" data-i18n="action.submit_form">Submit Hiring Form</button>
            </div>
        </form>
    </div>

    <script type="text/template" id="qualification-row-template">
        <tr>
            <td><input type="text" class="form-control" name="qualifications[__INDEX__][examination]"></td>
            <td><input type="text" class="form-control" name="qualifications[__INDEX__][university]"></td>
            <td><input type="text" class="form-control" name="qualifications[__INDEX__][main_subject]"></td>
            <td><input type="number" min="1000" max="9999" step="1" class="form-control" name="qualifications[__INDEX__][year_of_passing]"></td>
            <td><input type="number" min="0" max="100" step="0.01" class="form-control" name="qualifications[__INDEX__][percentage_obtained]"></td>
            <td><button type="button" class="btn btn-outline-danger btn-sm js-remove-row" data-i18n="common.remove">Remove</button></td>
        </tr>
    </script>

    <script type="text/template" id="work-experience-row-template">
        <tr>
            <td><input type="text" class="form-control" name="work_experiences[__INDEX__][company_name]"></td>
            <td><input type="text" class="form-control" name="work_experiences[__INDEX__][designation]"></td>
            <td><input type="number" min="0" step="0.1" class="form-control" name="work_experiences[__INDEX__][experience]"></td>
            <td><button type="button" class="btn btn-outline-danger btn-sm js-remove-row" data-i18n="common.remove">Remove</button></td>
        </tr>
    </script>

    <script type="text/template" id="reference-row-template">
        <tr>
            <td><input type="text" class="form-control" name="references[__INDEX__][name]"></td>
            <td><input type="tel" inputmode="numeric" class="form-control" name="references[__INDEX__][contact_number]" maxlength="10" pattern="[0-9]{10}" data-digit-only="10"></td>
            <td><input type="text" class="form-control" name="references[__INDEX__][designation]"></td>
            <td>
                <select class="form-select" name="references[__INDEX__][relationship]">
                    <option value="">Select</option>
                    @foreach ($relationshipOptions as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </td>
            <td><button type="button" class="btn btn-outline-danger btn-sm js-remove-row" data-i18n="common.remove">Remove</button></td>
        </tr>
    </script>

    <div class="modal fade interview-preview-modal" id="previewInterviewModal" tabindex="-1" aria-labelledby="previewInterviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-1" id="previewInterviewModalLabel" data-i18n="interview.preview_title">Interview Tutorial</h5>
                        <p class="mb-0 text-muted small" data-i18n="interview.preview_subtitle">Watch this guide before starting your interview.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3 p-md-4">
                    <video id="previewInterviewVideo" controls preload="metadata">
                        <source src="{{ \App\Support\ProjectAsset::url('storage/interview.mp4') }}" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                </div>
            </div>
        </div>
    </div>

    @include('admin.recruitment.partials.camera_modal')

    <script src="{{ \App\Support\ProjectAsset::url('admin/assets/js/bootstrap.bundle.min.js') }}"></script>
    <script>
        const hiringFormTranslationCatalog = {
            en: {
                'language.label': 'Language',
                'brand.company': 'Attica Gold Company',
                'brand.portal': 'Candidate Application Portal',
                'title.hiring': 'Attica Hiring Form',
                'title.walkin': 'Attica Walk-In Form',
                'alert.submission_id': 'Submission ID',
                'alert.resubmission': 'You are updating a previous submission. A new submission ID will be created when you submit this form.',
                'label.hiring_date': 'Hiring Date',
                'intro.hiring_desc': 'Please fill the hiring details, upload your resume, complete the video interview, and submit your form to the hiring team.',
                'intro.walkin_desc': 'Please fill the hiring details, upload your resume, and submit your walk-in form to the hiring team.',
                'autofill.resume_cv': 'Resume / CV',
                'autofill.accepted_types': 'Accepted: PDF, DOC, DOCX. Max 10 MB.',
                'autofill.question': 'Autofill form fields from CV?',
                'autofill.button': 'Search CV And Autofill',
                'autofill.initial': 'Upload your resume first. If you choose Yes, the form will search the CV and fill relevant fields automatically.',
                'autofill.search_prompt': 'Click "Search CV And Autofill" to fill the form using the uploaded resume.',
                'autofill.upload_then_search': 'Upload your resume, then click "Search CV And Autofill".',
                'autofill.off': 'Resume will be uploaded only for the hiring team. Autofill is currently off.',
                'autofill.searching': 'Searching the CV and extracting relevant details...',
                'autofill.success': 'Relevant details found in the CV have been filled into the form. Please review all fields before submitting.',
                'autofill.parse_error': 'Unable to parse the uploaded CV.',
                'common.yes': 'Yes',
                'common.no': 'No',
                'common.add_row': 'Add Row',
                'common.remove': 'Remove',
                'field.position': 'Position Applied For',
                'field.enter_job_role': 'Enter job role',
                'field.candidate_name': 'Candidate Name',
                'field.date_of_birth': 'Date of Birth',
                'field.gender': 'Gender',
                'field.marital_status': 'Marital Status',
                'field.current_address': 'Current Address',
                'field.permanent_address': 'Permanent Address',
                'field.phone_number': 'Phone Number',
                'field.email': 'Email',
                'field.aadhaar_number': 'Aadhaar Card Number',
                'field.whatsapp_confirmation': 'WhatsApp Confirmation',
                'field.whatsapp_same': 'The entered phone number is my WhatsApp number.',
                'field.whatsapp_number': 'WhatsApp Number',
                'field.candidate_photo': 'Candidate Photo',
                'field.physical_fitness': 'Physical Fitness',
                'field.if_no_reason': 'If No, Reason',
                'field.own_two_wheeler': 'Own Two Wheeler',
                'field.know_attica': 'How do you know Attica',
                'field.computer_knowledge': 'Computer Knowledge',
                'field.languages_speak': 'Languages Known - Speak',
                'field.languages_read': 'Languages Known - Read',
                'field.languages_write': 'Languages Known - Write',
                'field.present_remuneration': 'Present Remuneration',
                'field.salary_expectation': 'Salary Expectation',
                'field.notice_period': 'Notice Period for Joining',
                'field.preferred_state': 'Preferred Work State',
                'field.preferred_city': 'Preferred Work City',
                'field.choose_city_help': 'Choose a city from the selected state.',
                'field.place': 'Place',
                'field.date': 'Date',
                'field.signature': 'Signature',
                'section.additional_information': 'Additional Information',
                'section.professional_qualification': 'Professional Qualification',
                'section.skills_experience': 'Skills & Experience',
                'section.work_experience': 'Work Experience',
                'section.compensation': 'Compensation & Availability',
                'section.references': 'References',
                'section.final': 'Final Section',
                'table.examination': 'Examination',
                'table.university': 'University',
                'table.main_subject': 'Main Subject',
                'table.year_of_passing': 'Year of Passing',
                'table.percentage_obtained': 'Percentage Obtained',
                'table.company_name': 'Company Name',
                'table.designation': 'Designation',
                'table.experience': 'Experience',
                'table.name': 'Name',
                'table.contact_number': 'Contact Number',
                'table.relationship': 'Relationship',
                'action.submit_form': 'Submit Hiring Form',
                'interview.heading': 'Interactive Video Interview',
                'interview.description': 'Click start to open your front camera or webcam. The first question will always be about your recent work, and the remaining 4 questions will be selected randomly based on the chosen position. One video will be recorded with the question subtitles and countdown timer embedded in the saved video.',
                'interview.preview_button': 'Preview Interview',
                'interview.start_button': 'Start Interview',
                'interview.record_again': 'Record Again',
                'interview.placeholder': 'Start the interview to record your answers. Keep your face visible and speak clearly.',
                'interview.question_placeholder': 'Question will appear here.',
                'interview.status_required': 'The interview recording is required before you submit the form.',
                'interview.preview_title': 'Interview Tutorial',
                'interview.preview_subtitle': 'Watch this guide before starting your interview.',
                'interview.video_not_supported': 'Your browser does not support the video tag.',
                'interview.fixed_opening_question': 'Tell us about yourself and the work you handled most recently.',
                'interview.status_success': 'Interview recorded successfully.',
                'interview.status_finishing': 'Finishing interview recording...',
                'interview.status_position_changed': 'Position changed. Please record the interview again for the selected position.',
                'interview.status_camera_access': 'Unable to access camera or microphone. Please allow permission and try again.',
                'interview.status_camera_restart': 'Unable to start a new recording. Please allow permission and try again.',
                'interview.status_complete_before_submit': 'Please complete the video interview before submitting the form.',
                'interview.recording_status': 'Recording question :current of :total.',
                'interview.alert_select_position': 'Please select or enter the position applied for before starting the interview.',
                'interview.alert_browser_unsupported': 'Video interview recording is not supported in this browser. Please use the latest Chrome or Edge browser.',
                'interview.alert_questions_unavailable': 'Interview questions are not available for the selected position yet. Please contact the hiring team.',
                'camera.open_button': 'Open Camera',
                'camera.no_image': 'No camera image captured yet.',
                'camera.capture_title': 'Capture Image',
                'camera.loading': 'Opening camera...',
                'camera.close_button': 'Close',
                'camera.capture_button': 'Capture',
                'camera.unsupported': 'Camera access is not supported in this browser.',
                'camera.permission_error': 'Unable to access the camera. Please allow camera access and try again.',
                'validation.valid_city': 'Please select a valid city for the chosen state.',
            },
            kn: {
                'language.label': 'ಭಾಷೆ',
                'brand.company': 'ಅಟ್ಟಿಕಾ ಗೋಲ್ಡ್ ಕಂಪನಿ',
                'brand.portal': 'ಅಭ್ಯರ್ಥಿ ಅರ್ಜಿ ಪೋರ್ಟಲ್',
                'title.hiring': 'ಅಟ್ಟಿಕಾ ನೇಮಕಾತಿ ಅರ್ಜಿ',
                'title.walkin': 'ಅಟ್ಟಿಕಾ ವಾಕ್-ಇನ್ ಅರ್ಜಿ',
                'alert.submission_id': 'ಸಲ್ಲಿಕೆ ಐಡಿ',
                'alert.resubmission': 'ನೀವು ಹಿಂದಿನ ಸಲ್ಲಿಕೆಯನ್ನು ನವೀಕರಿಸುತ್ತಿದ್ದೀರಿ. ಈ ಫಾರ್ಮ್ ಸಲ್ಲಿಸಿದಾಗ ಹೊಸ ಸಲ್ಲಿಕೆ ಐಡಿ ರಚಿಸಲಾಗುತ್ತದೆ.',
                'label.hiring_date': 'ನೇಮಕಾತಿ ದಿನಾಂಕ',
                'intro.hiring_desc': 'ದಯವಿಟ್ಟು ನೇಮಕಾತಿ ವಿವರಗಳನ್ನು ಭರ್ತಿ ಮಾಡಿ, ನಿಮ್ಮ ರೆಸ್ಯೂಮ್ ಅನ್ನು ಅಪ್‌ಲೋಡ್ ಮಾಡಿ, ವೀಡಿಯೊ ಸಂದರ್ಶನವನ್ನು ಪೂರ್ಣಗೊಳಿಸಿ ಮತ್ತು ಫಾರ್ಮ್ ಅನ್ನು ನೇಮಕಾತಿ ತಂಡಕ್ಕೆ ಸಲ್ಲಿಸಿ.',
                'intro.walkin_desc': 'ದಯವಿಟ್ಟು ನೇಮಕಾತಿ ವಿವರಗಳನ್ನು ಭರ್ತಿ ಮಾಡಿ, ನಿಮ್ಮ ರೆಸ್ಯೂಮ್ ಅನ್ನು ಅಪ್‌ಲೋಡ್ ಮಾಡಿ ಮತ್ತು ನಿಮ್ಮ ವಾಕ್-ಇನ್ ಫಾರ್ಮ್ ಅನ್ನು ನೇಮಕಾತಿ ತಂಡಕ್ಕೆ ಸಲ್ಲಿಸಿ.',
                'autofill.resume_cv': 'ರೆಸ್ಯೂಮ್ / ಸಿವಿ',
                'autofill.accepted_types': 'ಅಂಗೀಕೃತ: PDF, DOC, DOCX. ಗರಿಷ್ಠ 10 MB.',
                'autofill.question': 'ಸಿವಿಯಿಂದ ಫಾರ್ಮ್ ವಿವರಗಳನ್ನು ಸ್ವಯಂ ಭರ್ತಿ ಮಾಡಬೇಕೆ?',
                'autofill.button': 'ಸಿವಿ ಹುಡುಕಿ ಮತ್ತು ಸ್ವಯಂ ಭರ್ತಿ ಮಾಡಿ',
                'autofill.initial': 'ಮೊದಲು ನಿಮ್ಮ ರೆಸ್ಯೂಮ್ ಅಪ್‌ಲೋಡ್ ಮಾಡಿ. ನೀವು ಹೌದು ಆಯ್ಕೆ ಮಾಡಿದರೆ, ಫಾರ್ಮ್ ಸಿವಿಯಿಂದ ಸಂಬಂಧಿತ ವಿವರಗಳನ್ನು ಸ್ವಯಂ ಭರ್ತಿ ಮಾಡುತ್ತದೆ.',
                'autofill.search_prompt': 'ಅಪ್‌ಲೋಡ್ ಮಾಡಿದ ರೆಸ್ಯೂಮ್ ಬಳಸಿ ಫಾರ್ಮ್ ಭರ್ತಿ ಮಾಡಲು "ಸಿವಿ ಹುಡುಕಿ ಮತ್ತು ಸ್ವಯಂ ಭರ್ತಿ ಮಾಡಿ" ಕ್ಲಿಕ್ ಮಾಡಿ.',
                'autofill.upload_then_search': 'ನಿಮ್ಮ ರೆಸ್ಯೂಮ್ ಅಪ್‌ಲೋಡ್ ಮಾಡಿ, ನಂತರ "ಸಿವಿ ಹುಡುಕಿ ಮತ್ತು ಸ್ವಯಂ ಭರ್ತಿ ಮಾಡಿ" ಕ್ಲಿಕ್ ಮಾಡಿ.',
                'autofill.off': 'ರೆಸ್ಯೂಮ್ ಅನ್ನು ನೇಮಕಾತಿ ತಂಡಕ್ಕೆ ಮಾತ್ರ ಅಪ್‌ಲೋಡ್ ಮಾಡಲಾಗುತ್ತದೆ. ಸ್ವಯಂ ಭರ್ತಿ ಈಗ ಆಫ್ ಆಗಿದೆ.',
                'autofill.searching': 'ಸಿವಿಯನ್ನು ಹುಡುಕಿ ಸಂಬಂಧಿತ ವಿವರಗಳನ್ನು ಹೊರತೆಗೆಯಲಾಗುತ್ತಿದೆ...',
                'autofill.success': 'ಸಿವಿಯಲ್ಲಿ ಕಂಡುಬಂದ ಸಂಬಂಧಿತ ವಿವರಗಳನ್ನು ಫಾರ್ಮ್‌ನಲ್ಲಿ ಭರ್ತಿ ಮಾಡಲಾಗಿದೆ. ಸಲ್ಲಿಸುವ ಮೊದಲು ಎಲ್ಲಾ ಕ್ಷೇತ್ರಗಳನ್ನು ಪರಿಶೀಲಿಸಿ.',
                'autofill.parse_error': 'ಅಪ್‌ಲೋಡ್ ಮಾಡಿದ ಸಿವಿಯನ್ನು ಓದಲು ಸಾಧ್ಯವಾಗಲಿಲ್ಲ.',
                'common.yes': 'ಹೌದು',
                'common.no': 'ಇಲ್ಲ',
                'common.add_row': 'ಸಾಲು ಸೇರಿಸಿ',
                'common.remove': 'ತೆಗೆದುಹಾಕಿ',
                'field.position': 'ಅರ್ಜಿಸಿದ ಹುದ್ದೆ',
                'field.enter_job_role': 'ಉದ್ಯೋಗ ಹುದ್ದೆ ನಮೂದಿಸಿ',
                'field.candidate_name': 'ಅಭ್ಯರ್ಥಿಯ ಹೆಸರು',
                'field.date_of_birth': 'ಜನ್ಮ ದಿನಾಂಕ',
                'field.gender': 'ಲಿಂಗ',
                'field.marital_status': 'ವೈವಾಹಿಕ ಸ್ಥಿತಿ',
                'field.current_address': 'ಪ್ರಸ್ತುತ ವಿಳಾಸ',
                'field.permanent_address': 'ಶಾಶ್ವತ ವಿಳಾಸ',
                'field.phone_number': 'ಫೋನ್ ಸಂಖ್ಯೆ',
                'field.email': 'ಇಮೇಲ್',
                'field.aadhaar_number': 'ಆಧಾರ್ ಕಾರ್ಡ್ ಸಂಖ್ಯೆ',
                'field.whatsapp_confirmation': 'ವಾಟ್ಸಾಪ್ ದೃಢೀಕರಣ',
                'field.whatsapp_same': 'ನಮೂದಿಸಿದ ಫೋನ್ ಸಂಖ್ಯೆ ನನ್ನ ವಾಟ್ಸಾಪ್ ಸಂಖ್ಯೆಯೇ ಆಗಿದೆ.',
                'field.whatsapp_number': 'ವಾಟ್ಸಾಪ್ ಸಂಖ್ಯೆ',
                'field.candidate_photo': 'ಅಭ್ಯರ್ಥಿಯ ಫೋಟೋ',
                'field.physical_fitness': 'ದೈಹಿಕ ಸಾಮರ್ಥ್ಯ',
                'field.if_no_reason': 'ಇಲ್ಲದಿದ್ದರೆ ಕಾರಣ',
                'field.own_two_wheeler': 'ಸ್ವಂತ ಎರಡು ಚಕ್ರ ವಾಹನ',
                'field.know_attica': 'ಅಟ್ಟಿಕಾ ಬಗ್ಗೆ ನಿಮಗೆ ಹೇಗೆ ತಿಳಿದುಬಂದಿತು',
                'field.computer_knowledge': 'ಕಂಪ್ಯೂಟರ್ ಜ್ಞಾನ',
                'field.languages_speak': 'ತಿಳಿದಿರುವ ಭಾಷೆಗಳು - ಮಾತನಾಡುವುದು',
                'field.languages_read': 'ತಿಳಿದಿರುವ ಭಾಷೆಗಳು - ಓದುವುದು',
                'field.languages_write': 'ತಿಳಿದಿರುವ ಭಾಷೆಗಳು - ಬರೆಯುವುದು',
                'field.present_remuneration': 'ಪ್ರಸ್ತುತ ವೇತನ',
                'field.salary_expectation': 'ನಿರೀಕ್ಷಿತ ವೇತನ',
                'field.notice_period': 'ಸೇರುವ ನೋಟಿಸ್ ಅವಧಿ',
                'field.preferred_state': 'ಇಷ್ಟವಾದ ಕೆಲಸದ ರಾಜ್ಯ',
                'field.preferred_city': 'ಇಷ್ಟವಾದ ಕೆಲಸದ ನಗರ',
                'field.choose_city_help': 'ಆಯ್ಕೆ ಮಾಡಿದ ರಾಜ್ಯದಿಂದ ಒಂದು ನಗರವನ್ನು ಆಯ್ಕೆಮಾಡಿ.',
                'field.place': 'ಸ್ಥಳ',
                'field.date': 'ದಿನಾಂಕ',
                'field.signature': 'ಸಹಿ',
                'section.additional_information': 'ಹೆಚ್ಚುವರಿ ಮಾಹಿತಿ',
                'section.professional_qualification': 'ವೃತ್ತಿಪರ ಅರ್ಹತೆ',
                'section.skills_experience': 'ಕೌಶಲ್ಯಗಳು ಮತ್ತು ಅನುಭವ',
                'section.work_experience': 'ಕೆಲಸದ ಅನುಭವ',
                'section.compensation': 'ವೇತನ ಮತ್ತು ಲಭ್ಯತೆ',
                'section.references': 'ಪರಿಚಯದವರು',
                'section.final': 'ಅಂತಿಮ ವಿಭಾಗ',
                'table.examination': 'ಪರೀಕ್ಷೆ',
                'table.university': 'ವಿಶ್ವವಿದ್ಯಾಲಯ',
                'table.main_subject': 'ಮುಖ್ಯ ವಿಷಯ',
                'table.year_of_passing': 'ಪಾಸಾದ ವರ್ಷ',
                'table.percentage_obtained': 'ಪಡೆದ ಶೇಕಡಾವಾರು',
                'table.company_name': 'ಕಂಪನಿ ಹೆಸರು',
                'table.designation': 'ಹುದ್ದೆ',
                'table.experience': 'ಅನುಭವ',
                'table.name': 'ಹೆಸರು',
                'table.contact_number': 'ಸಂಪರ್ಕ ಸಂಖ್ಯೆ',
                'table.relationship': 'ಸಂಬಂಧ',
                'action.submit_form': 'ನೇಮಕಾತಿ ಅರ್ಜಿ ಸಲ್ಲಿಸಿ',
                'interview.heading': 'ಪರಸ್ಪರ ಕ್ರಿಯಾತ್ಮಕ ವೀಡಿಯೊ ಸಂದರ್ಶನ',
                'interview.description': 'ನಿಮ್ಮ ಮುಂಭಾಗದ ಕ್ಯಾಮೆರಾ ಅಥವಾ ವೆಬ್‌ಕ್ಯಾಮ್ ತೆರೆಯಲು ಪ್ರಾರಂಭಿಸಿ ಕ್ಲಿಕ್ ಮಾಡಿ. ಮೊದಲ ಪ್ರಶ್ನೆ ಯಾವಾಗಲೂ ನಿಮ್ಮ ಇತ್ತೀಚಿನ ಕೆಲಸದ ಬಗ್ಗೆ ಇರುತ್ತದೆ ಮತ್ತು ಉಳಿದ 4 ಪ್ರಶ್ನೆಗಳು ಆಯ್ಕೆ ಮಾಡಿದ ಹುದ್ದೆಯನ್ನು ಆಧರಿಸಿ ಆಯ್ಕೆ ಮಾಡಲಾಗುತ್ತದೆ. ಪ್ರಶ್ನೆಗಳ ಉಪಶೀರ್ಷಿಕೆಗಳು ಮತ್ತು ಕೌಂಟ್‌ಡೌನ್ ಟೈಮರ್ ಹೊಂದಿರುವ ಒಂದು ವೀಡಿಯೊ ಮಾತ್ರ ಸಂರಕ್ಷಿಸಲಾಗುತ್ತದೆ.',
                'interview.preview_button': 'ಸಂದರ್ಶನ ಪೂರ್ವವೀಕ್ಷಣೆ',
                'interview.start_button': 'ಸಂದರ್ಶನ ಆರಂಭಿಸಿ',
                'interview.record_again': 'ಮತ್ತೆ ದಾಖಲಿಸಿ',
                'interview.placeholder': 'ನಿಮ್ಮ ಉತ್ತರಗಳನ್ನು ದಾಖಲಿಸಲು ಸಂದರ್ಶನವನ್ನು ಆರಂಭಿಸಿ. ನಿಮ್ಮ ಮುಖ ಸ್ಪಷ್ಟವಾಗಿ ಕಾಣುವಂತೆ ಮಾಡಿ ಮತ್ತು ಸ್ಪಷ್ಟವಾಗಿ ಮಾತನಾಡಿ.',
                'interview.question_placeholder': 'ಪ್ರಶ್ನೆ ಇಲ್ಲಿ ಕಾಣಿಸುತ್ತದೆ.',
                'interview.status_required': 'ಫಾರ್ಮ್ ಸಲ್ಲಿಸುವ ಮೊದಲು ಸಂದರ್ಶನದ ದಾಖಲೆ ಅಗತ್ಯವಿದೆ.',
                'interview.preview_title': 'ಸಂದರ್ಶನ ಮಾರ್ಗದರ್ಶಿ',
                'interview.preview_subtitle': 'ಸಂದರ್ಶನ ಆರಂಭಿಸುವ ಮೊದಲು ಈ ಮಾರ್ಗದರ್ಶಿಯನ್ನು ನೋಡಿ.',
                'interview.video_not_supported': 'ನಿಮ್ಮ ಬ್ರೌಸರ್ ವೀಡಿಯೊ ಟ್ಯಾಗ್ ಅನ್ನು ಬೆಂಬಲಿಸುವುದಿಲ್ಲ.',
                'interview.fixed_opening_question': 'ನಿಮ್ಮ ಬಗ್ಗೆ ಮತ್ತು ನೀವು ಇತ್ತೀಚೆಗೆ ಮಾಡಿದ ಕೆಲಸದ ಬಗ್ಗೆ ನಮಗೆ ತಿಳಿಸಿ.',
                'interview.status_success': 'ಸಂದರ್ಶನ ಯಶಸ್ವಿಯಾಗಿ ದಾಖಲಾಗಿದೆ.',
                'interview.status_finishing': 'ಸಂದರ್ಶನದ ದಾಖಲೆಯನ್ನು ಮುಗಿಸಲಾಗುತ್ತಿದೆ...',
                'interview.status_position_changed': 'ಹುದ್ದೆ ಬದಲಾಗಿದೆ. ಆಯ್ಕೆ ಮಾಡಿದ ಹುದ್ದೆಗೆ ದಯವಿಟ್ಟು ಮತ್ತೆ ಸಂದರ್ಶನ ದಾಖಲಿಸಿ.',
                'interview.status_camera_access': 'ಕ್ಯಾಮೆರಾ ಅಥವಾ ಮೈಕ್ರೋಫೋನ್ ಪ್ರವೇಶಿಸಲು ಸಾಧ್ಯವಾಗಲಿಲ್ಲ. ಅನುಮತಿ ನೀಡಿ ಮತ್ತೆ ಪ್ರಯತ್ನಿಸಿ.',
                'interview.status_camera_restart': 'ಹೊಸ ದಾಖಲೆ ಪ್ರಾರಂಭಿಸಲು ಸಾಧ್ಯವಾಗಲಿಲ್ಲ. ಅನುಮತಿ ನೀಡಿ ಮತ್ತೆ ಪ್ರಯತ್ನಿಸಿ.',
                'interview.status_complete_before_submit': 'ದಯವಿಟ್ಟು ಫಾರ್ಮ್ ಸಲ್ಲಿಸುವ ಮೊದಲು ವೀಡಿಯೊ ಸಂದರ್ಶನವನ್ನು ಪೂರ್ಣಗೊಳಿಸಿ.',
                'interview.recording_status': 'ಪ್ರಶ್ನೆ :current / :total ದಾಖಲಿಸಲಾಗುತ್ತಿದೆ.',
                'interview.alert_select_position': 'ಸಂದರ್ಶನ ಆರಂಭಿಸುವ ಮೊದಲು ಅರ್ಜಿಸಿದ ಹುದ್ದೆಯನ್ನು ಆಯ್ಕೆಮಾಡಿ ಅಥವಾ ನಮೂದಿಸಿ.',
                'interview.alert_browser_unsupported': 'ಈ ಬ್ರೌಸರ್‌ನಲ್ಲಿ ವೀಡಿಯೊ ಸಂದರ್ಶನದ ದಾಖಲೆ ಬೆಂಬಲಿಸಲಾಗುವುದಿಲ್ಲ. ದಯವಿಟ್ಟು ಇತ್ತೀಚಿನ Chrome ಅಥವಾ Edge ಬಳಸಿ.',
                'interview.alert_questions_unavailable': 'ಆಯ್ಕೆ ಮಾಡಿದ ಹುದ್ದೆಗೆ ಸಂದರ್ಶನ ಪ್ರಶ್ನೆಗಳು ಇನ್ನೂ ಲಭ್ಯವಿಲ್ಲ. ದಯವಿಟ್ಟು ನೇಮಕಾತಿ ತಂಡವನ್ನು ಸಂಪರ್ಕಿಸಿ.',
                'camera.open_button': 'ಕ್ಯಾಮೆರಾ ತೆರೆಯಿರಿ',
                'camera.no_image': 'ಇನ್ನೂ ಕ್ಯಾಮೆರಾ ಚಿತ್ರ ಸೆರೆ ಹಿಡಿಯಲಾಗಿಲ್ಲ.',
                'camera.capture_title': 'ಚಿತ್ರ ಸೆರೆಹಿಡಿ',
                'camera.loading': 'ಕ್ಯಾಮೆರಾ ತೆರೆಯಲಾಗುತ್ತಿದೆ...',
                'camera.close_button': 'ಮುಚ್ಚಿ',
                'camera.capture_button': 'ಸೆರೆಹಿಡಿ',
                'camera.unsupported': 'ಈ ಬ್ರೌಸರ್‌ನಲ್ಲಿ ಕ್ಯಾಮೆರಾ ಪ್ರವೇಶ ಬೆಂಬಲಿಸಲಾಗುವುದಿಲ್ಲ.',
                'camera.permission_error': 'ಕ್ಯಾಮೆರಾವನ್ನು ಪ್ರವೇಶಿಸಲು ಸಾಧ್ಯವಾಗಲಿಲ್ಲ. ಅನುಮತಿ ನೀಡಿ ಮತ್ತೆ ಪ್ರಯತ್ನಿಸಿ.',
                'validation.valid_city': 'ಆಯ್ಕೆ ಮಾಡಿದ ರಾಜ್ಯಕ್ಕೆ ಮಾನ್ಯವಾದ ನಗರವನ್ನು ಆಯ್ಕೆಮಾಡಿ.',
            },
            hi: {
                'language.label': 'भाषा',
                'brand.company': 'अटिका गोल्ड कंपनी',
                'brand.portal': 'उम्मीदवार आवेदन पोर्टल',
                'title.hiring': 'अटिका हायरिंग फॉर्म',
                'title.walkin': 'अटिका वॉक-इन फॉर्म',
                'alert.submission_id': 'सबमिशन आईडी',
                'alert.resubmission': 'आप अपनी पिछली प्रविष्टि अपडेट कर रहे हैं। यह फॉर्म जमा करने पर नई सबमिशन आईडी बनाई जाएगी।',
                'label.hiring_date': 'हायरिंग तिथि',
                'intro.hiring_desc': 'कृपया भर्ती विवरण भरें, अपना रिज्यूमे अपलोड करें, वीडियो इंटरव्यू पूरा करें और फॉर्म को भर्ती टीम को जमा करें।',
                'intro.walkin_desc': 'कृपया भर्ती विवरण भरें, अपना रिज्यूमे अपलोड करें और अपना वॉक-इन फॉर्म भर्ती टीम को जमा करें।',
                'autofill.resume_cv': 'रिज्यूमे / सीवी',
                'autofill.accepted_types': 'स्वीकार्य: PDF, DOC, DOCX. अधिकतम 10 MB.',
                'autofill.question': 'क्या CV से फॉर्म की जानकारी स्वतः भरनी है?',
                'autofill.button': 'CV खोजें और स्वतः भरें',
                'autofill.initial': 'पहले अपना रिज्यूमे अपलोड करें। यदि आप हाँ चुनते हैं, तो फॉर्म CV से संबंधित जानकारी स्वतः भर देगा।',
                'autofill.search_prompt': 'अपलोड किए गए रिज्यूमे से फॉर्म भरने के लिए "CV खोजें और स्वतः भरें" पर क्लिक करें।',
                'autofill.upload_then_search': 'अपना रिज्यूमे अपलोड करें, फिर "CV खोजें और स्वतः भरें" पर क्लिक करें।',
                'autofill.off': 'रिज्यूमे केवल भर्ती टीम के लिए अपलोड किया जाएगा। ऑटोफिल अभी बंद है।',
                'autofill.searching': 'CV खोजा जा रहा है और संबंधित जानकारी निकाली जा रही है...',
                'autofill.success': 'CV में मिली संबंधित जानकारी फॉर्म में भर दी गई है। जमा करने से पहले सभी फ़ील्ड की समीक्षा करें।',
                'autofill.parse_error': 'अपलोड किए गए CV को पढ़ा नहीं जा सका।',
                'common.yes': 'हाँ',
                'common.no': 'नहीं',
                'common.add_row': 'पंक्ति जोड़ें',
                'common.remove': 'हटाएँ',
                'field.position': 'आवेदन किया गया पद',
                'field.enter_job_role': 'पद का नाम दर्ज करें',
                'field.candidate_name': 'उम्मीदवार का नाम',
                'field.date_of_birth': 'जन्म तिथि',
                'field.gender': 'लिंग',
                'field.marital_status': 'वैवाहिक स्थिति',
                'field.current_address': 'वर्तमान पता',
                'field.permanent_address': 'स्थायी पता',
                'field.phone_number': 'फोन नंबर',
                'field.email': 'ईमेल',
                'field.aadhaar_number': 'आधार कार्ड नंबर',
                'field.whatsapp_confirmation': 'व्हाट्सऐप पुष्टि',
                'field.whatsapp_same': 'दर्ज किया गया फोन नंबर मेरा व्हाट्सऐप नंबर है।',
                'field.whatsapp_number': 'व्हाट्सऐप नंबर',
                'field.candidate_photo': 'उम्मीदवार का फोटो',
                'field.physical_fitness': 'शारीरिक फिटनेस',
                'field.if_no_reason': 'यदि नहीं, कारण',
                'field.own_two_wheeler': 'क्या आपके पास दोपहिया है',
                'field.know_attica': 'आपको अटिका के बारे में कैसे पता चला',
                'field.computer_knowledge': 'कंप्यूटर ज्ञान',
                'field.languages_speak': 'ज्ञात भाषाएँ - बोलना',
                'field.languages_read': 'ज्ञात भाषाएँ - पढ़ना',
                'field.languages_write': 'ज्ञात भाषाएँ - लिखना',
                'field.present_remuneration': 'वर्तमान वेतन',
                'field.salary_expectation': 'अपेक्षित वेतन',
                'field.notice_period': 'जॉइनिंग नोटिस पीरियड',
                'field.preferred_state': 'पसंदीदा कार्य राज्य',
                'field.preferred_city': 'पसंदीदा कार्य शहर',
                'field.choose_city_help': 'चुने गए राज्य में से एक शहर चुनें।',
                'field.place': 'स्थान',
                'field.date': 'तिथि',
                'field.signature': 'हस्ताक्षर',
                'section.additional_information': 'अतिरिक्त जानकारी',
                'section.professional_qualification': 'व्यावसायिक योग्यता',
                'section.skills_experience': 'कौशल और अनुभव',
                'section.work_experience': 'कार्य अनुभव',
                'section.compensation': 'वेतन और उपलब्धता',
                'section.references': 'संदर्भ',
                'section.final': 'अंतिम भाग',
                'table.examination': 'परीक्षा',
                'table.university': 'विश्वविद्यालय',
                'table.main_subject': 'मुख्य विषय',
                'table.year_of_passing': 'उत्तीर्ण वर्ष',
                'table.percentage_obtained': 'प्राप्त प्रतिशत',
                'table.company_name': 'कंपनी का नाम',
                'table.designation': 'पदनाम',
                'table.experience': 'अनुभव',
                'table.name': 'नाम',
                'table.contact_number': 'संपर्क नंबर',
                'table.relationship': 'संबंध',
                'action.submit_form': 'हायरिंग फॉर्म जमा करें',
                'interview.heading': 'इंटरैक्टिव वीडियो इंटरव्यू',
                'interview.description': 'अपना फ्रंट कैमरा या वेबकैम खोलने के लिए स्टार्ट पर क्लिक करें। पहला प्रश्न हमेशा आपके हाल के कार्य के बारे में होगा और बाकी 4 प्रश्न चुने गए पद के आधार पर यादृच्छिक रूप से चुने जाएंगे। एक ही वीडियो प्रश्न उपशीर्षक और काउंटडाउन टाइमर के साथ सुरक्षित किया जाएगा।',
                'interview.preview_button': 'इंटरव्यू प्रीव्यू',
                'interview.start_button': 'इंटरव्यू शुरू करें',
                'interview.record_again': 'फिर से रिकॉर्ड करें',
                'interview.placeholder': 'अपने उत्तर रिकॉर्ड करने के लिए इंटरव्यू शुरू करें। अपना चेहरा स्पष्ट रखें और साफ बोलें।',
                'interview.question_placeholder': 'प्रश्न यहाँ दिखाई देगा।',
                'interview.status_required': 'फॉर्म जमा करने से पहले इंटरव्यू रिकॉर्डिंग आवश्यक है।',
                'interview.preview_title': 'इंटरव्यू ट्यूटोरियल',
                'interview.preview_subtitle': 'इंटरव्यू शुरू करने से पहले यह गाइड देखें।',
                'interview.video_not_supported': 'आपका ब्राउज़र वीडियो टैग को सपोर्ट नहीं करता।',
                'interview.fixed_opening_question': 'अपने बारे में और हाल ही में किए गए काम के बारे में बताइए।',
                'interview.status_success': 'इंटरव्यू सफलतापूर्वक रिकॉर्ड हो गया।',
                'interview.status_finishing': 'इंटरव्यू रिकॉर्डिंग पूरी की जा रही है...',
                'interview.status_position_changed': 'पद बदल गया है। चुने गए पद के लिए कृपया इंटरव्यू फिर से रिकॉर्ड करें।',
                'interview.status_camera_access': 'कैमरा या माइक्रोफोन एक्सेस नहीं हो सका। कृपया अनुमति दें और फिर से प्रयास करें।',
                'interview.status_camera_restart': 'नई रिकॉर्डिंग शुरू नहीं हो सकी। कृपया अनुमति दें और फिर से प्रयास करें।',
                'interview.status_complete_before_submit': 'कृपया फॉर्म जमा करने से पहले वीडियो इंटरव्यू पूरा करें।',
                'interview.recording_status': 'प्रश्न :current / :total रिकॉर्ड किया जा रहा है।',
                'interview.alert_select_position': 'इंटरव्यू शुरू करने से पहले आवेदन किया गया पद चुनें या दर्ज करें।',
                'interview.alert_browser_unsupported': 'इस ब्राउज़र में वीडियो इंटरव्यू रिकॉर्डिंग समर्थित नहीं है। कृपया नवीनतम Chrome या Edge ब्राउज़र का उपयोग करें।',
                'interview.alert_questions_unavailable': 'चुने गए पद के लिए इंटरव्यू प्रश्न अभी उपलब्ध नहीं हैं। कृपया भर्ती टीम से संपर्क करें।',
                'camera.open_button': 'कैमरा खोलें',
                'camera.no_image': 'अभी तक कोई कैमरा छवि कैप्चर नहीं की गई है।',
                'camera.capture_title': 'छवि कैप्चर करें',
                'camera.loading': 'कैमरा खोला जा रहा है...',
                'camera.close_button': 'बंद करें',
                'camera.capture_button': 'कैप्चर करें',
                'camera.unsupported': 'इस ब्राउज़र में कैमरा एक्सेस समर्थित नहीं है।',
                'camera.permission_error': 'कैमरा एक्सेस नहीं हो सका। कृपया अनुमति दें और फिर से प्रयास करें।',
                'validation.valid_city': 'कृपया चुने गए राज्य के लिए मान्य शहर चुनें।',
            },
            te: {
                'language.label': 'భాష',
                'brand.company': 'అట్టికా గోల్డ్ కంపెనీ',
                'brand.portal': 'అభ్యర్థి దరఖాస్తు పోర్టల్',
                'title.hiring': 'అట్టికా హైరింగ్ ఫారం',
                'title.walkin': 'అట్టికా వాక్-ఇన్ ఫారం',
                'alert.submission_id': 'సబ్మిషన్ ఐడి',
                'alert.resubmission': 'మీరు మునుపటి సమర్పణను నవీకరిస్తున్నారు. ఈ ఫారం సమర్పించినప్పుడు కొత్త సబ్మిషన్ ఐడి సృష్టించబడుతుంది.',
                'label.hiring_date': 'నియామక తేదీ',
                'intro.hiring_desc': 'దయచేసి నియామక వివరాలు పూరించండి, మీ రెజ్యూమ్‌ను అప్లోడ్ చేయండి, వీడియో ఇంటర్వ్యూను పూర్తి చేసి ఫారాన్ని నియామక బృందానికి సమర్పించండి.',
                'intro.walkin_desc': 'దయచేసి నియామక వివరాలు పూరించండి, మీ రెజ్యూమ్‌ను అప్లోడ్ చేసి మీ వాక్-ఇన్ ఫారాన్ని నియామక బృందానికి సమర్పించండి.',
                'autofill.resume_cv': 'రెజ్యూమ్ / సీవీ',
                'autofill.accepted_types': 'అంగీకరించబడేవి: PDF, DOC, DOCX. గరిష్టం 10 MB.',
                'autofill.question': 'CV నుంచి ఫారం వివరాలు ఆటోఫిల్ చేయాలా?',
                'autofill.button': 'CV వెతికి ఆటోఫిల్ చేయండి',
                'autofill.initial': 'ముందుగా మీ రెజ్యూమ్‌ను అప్లోడ్ చేయండి. మీరు అవును ఎంచుకుంటే, ఫారం CV నుండి సంబంధిత వివరాలను ఆటోఫిల్ చేస్తుంది.',
                'autofill.search_prompt': 'అప్లోడ్ చేసిన రెజ్యూమ్‌తో ఫారం నింపడానికి "CV వెతికి ఆటోఫిల్ చేయండి" క్లిక్ చేయండి.',
                'autofill.upload_then_search': 'మీ రెజ్యూమ్‌ను అప్లోడ్ చేసి, తరువాత "CV వెతికి ఆటోఫిల్ చేయండి" క్లిక్ చేయండి.',
                'autofill.off': 'రెజ్యూమ్‌ను నియామక బృందానికి మాత్రమే అప్లోడ్ చేస్తారు. ఆటోఫిల్ ప్రస్తుతం ఆఫ్‌లో ఉంది.',
                'autofill.searching': 'CV ను వెతికి సంబంధిత వివరాలను తీసుకుంటోంది...',
                'autofill.success': 'CV లో కనిపించిన సంబంధిత వివరాలు ఫారంలో నింపబడ్డాయి. సమర్పించే ముందు అన్ని ఫీల్డ్‌లను సమీక్షించండి.',
                'autofill.parse_error': 'అప్లోడ్ చేసిన CV ను చదవలేకపోయాము.',
                'common.yes': 'అవును',
                'common.no': 'కాదు',
                'common.add_row': 'వరుస జోడించండి',
                'common.remove': 'తొలగించండి',
                'field.position': 'దరఖాస్తు చేసిన పదవి',
                'field.enter_job_role': 'ఉద్యోగ పాత్రను నమోదు చేయండి',
                'field.candidate_name': 'అభ్యర్థి పేరు',
                'field.date_of_birth': 'పుట్టిన తేదీ',
                'field.gender': 'లింగం',
                'field.marital_status': 'వైవాహిక స్థితి',
                'field.current_address': 'ప్రస్తుత చిరునామా',
                'field.permanent_address': 'శాశ్వత చిరునామా',
                'field.phone_number': 'ఫోన్ నంబర్',
                'field.email': 'ఇమెయిల్',
                'field.aadhaar_number': 'ఆధార్ కార్డ్ నంబర్',
                'field.whatsapp_confirmation': 'వాట్సాప్ నిర్ధారణ',
                'field.whatsapp_same': 'నమోదు చేసిన ఫోన్ నంబర్ నా వాట్సాప్ నంబర్.',
                'field.whatsapp_number': 'వాట్సాప్ నంబర్',
                'field.candidate_photo': 'అభ్యర్థి ఫోటో',
                'field.physical_fitness': 'శారీరక దృఢత్వం',
                'field.if_no_reason': 'కాదు అయితే కారణం',
                'field.own_two_wheeler': 'సొంత టూ వీలర్ ఉందా',
                'field.know_attica': 'అట్టికా గురించి మీకు ఎలా తెలిసింది',
                'field.computer_knowledge': 'కంప్యూటర్ పరిజ్ఞానం',
                'field.languages_speak': 'తెలిసిన భాషలు - మాట్లాడటం',
                'field.languages_read': 'తెలిసిన భాషలు - చదవడం',
                'field.languages_write': 'తెలిసిన భాషలు - రాయడం',
                'field.present_remuneration': 'ప్రస్తుత వేతనం',
                'field.salary_expectation': 'అంచనా వేతనం',
                'field.notice_period': 'చేరడానికి నోటీస్ పీరియడ్',
                'field.preferred_state': 'ఇష్టమైన పని రాష్ట్రం',
                'field.preferred_city': 'ఇష్టమైన పని నగరం',
                'field.choose_city_help': 'ఎంచుకున్న రాష్ట్రం నుండి ఒక నగరాన్ని ఎంచుకోండి.',
                'field.place': 'స్థలం',
                'field.date': 'తేదీ',
                'field.signature': 'సంతకం',
                'section.additional_information': 'అదనపు సమాచారం',
                'section.professional_qualification': 'వృత్తి అర్హత',
                'section.skills_experience': 'నైపుణ్యాలు మరియు అనుభవం',
                'section.work_experience': 'పని అనుభవం',
                'section.compensation': 'వేతనం మరియు లభ్యత',
                'section.references': 'రిఫరెన్సులు',
                'section.final': 'చివరి విభాగం',
                'table.examination': 'పరీక్ష',
                'table.university': 'విశ్వవిద్యాలయం',
                'table.main_subject': 'ప్రధాన విషయం',
                'table.year_of_passing': 'పాస్ అయిన సంవత్సరం',
                'table.percentage_obtained': 'సంపాదించిన శాతం',
                'table.company_name': 'కంపెనీ పేరు',
                'table.designation': 'హోదా',
                'table.experience': 'అనుభవం',
                'table.name': 'పేరు',
                'table.contact_number': 'సంప్రదింపు నంబర్',
                'table.relationship': 'సంబంధం',
                'action.submit_form': 'హైరింగ్ ఫారం సమర్పించండి',
                'interview.heading': 'ఇంటరాక్టివ్ వీడియో ఇంటర్వ్యూ',
                'interview.description': 'మీ ఫ్రంట్ కెమెరా లేదా వెబ్‌క్యామ్ తెరవడానికి స్టార్ట్ క్లిక్ చేయండి. మొదటి ప్రశ్న ఎప్పుడూ మీ ఇటీవలి పనిపై ఉంటుంది, మిగిలిన 4 ప్రశ్నలు ఎంచుకున్న పదవిని ఆధారంగా యాదృచ్ఛికంగా ఎంచుకోబడతాయి. ప్రశ్న సబ్‌టైటిల్స్ మరియు కౌంట్‌డౌన్ టైమర్‌తో ఒకే వీడియో సేవ్ చేయబడుతుంది.',
                'interview.preview_button': 'ఇంటర్వ్యూ ప్రివ్యూ',
                'interview.start_button': 'ఇంటర్వ్యూ ప్రారంభించండి',
                'interview.record_again': 'మళ్లీ రికార్డ్ చేయండి',
                'interview.placeholder': 'మీ జవాబులను రికార్డ్ చేయడానికి ఇంటర్వ్యూను ప్రారంభించండి. మీ ముఖం స్పష్టంగా కనిపించేలా చూసి స్పష్టంగా మాట్లాడండి.',
                'interview.question_placeholder': 'ప్రశ్న ఇక్కడ కనిపిస్తుంది.',
                'interview.status_required': 'ఫారం సమర్పించే ముందు ఇంటర్వ్యూ రికార్డింగ్ అవసరం.',
                'interview.preview_title': 'ఇంటర్వ్యూ ట్యుటోరియల్',
                'interview.preview_subtitle': 'ఇంటర్వ్యూ ప్రారంభించే ముందు ఈ గైడ్‌ను చూడండి.',
                'interview.video_not_supported': 'మీ బ్రౌజర్ వీడియో ట్యాగ్‌ను మద్దతు ఇవ్వదు.',
                'interview.fixed_opening_question': 'మీ గురించి మరియు మీరు ఇటీవల చేసిన పనిపై చెప్పండి.',
                'interview.status_success': 'ఇంటర్వ్యూ విజయవంతంగా రికార్డ్ అయింది.',
                'interview.status_finishing': 'ఇంటర్వ్యూ రికార్డింగ్ పూర్తవుతోంది...',
                'interview.status_position_changed': 'పదవి మారింది. ఎంచుకున్న పదవికి దయచేసి మళ్లీ ఇంటర్వ్యూను రికార్డ్ చేయండి.',
                'interview.status_camera_access': 'కెమెరా లేదా మైక్రోఫోన్‌ను యాక్సెస్ చేయలేకపోయాం. అనుమతి ఇచ్చి మళ్లీ ప్రయత్నించండి.',
                'interview.status_camera_restart': 'కొత్త రికార్డింగ్‌ను ప్రారంభించలేకపోయాం. అనుమతి ఇచ్చి మళ్లీ ప్రయత్నించండి.',
                'interview.status_complete_before_submit': 'దయచేసి ఫారం సమర్పించే ముందు వీడియో ఇంటర్వ్యూను పూర్తి చేయండి.',
                'interview.recording_status': ':total లో :current ప్రశ్న రికార్డ్ అవుతోంది.',
                'interview.alert_select_position': 'ఇంటర్వ్యూ ప్రారంభించే ముందు దరఖాస్తు చేసిన పదవిని ఎంచుకోండి లేదా నమోదు చేయండి.',
                'interview.alert_browser_unsupported': 'ఈ బ్రౌజర్‌లో వీడియో ఇంటర్వ్యూ రికార్డింగ్‌కు మద్దతు లేదు. దయచేసి తాజా Chrome లేదా Edge ఉపయోగించండి.',
                'interview.alert_questions_unavailable': 'ఎంచుకున్న పదవికి ఇంటర్వ్యూ ప్రశ్నలు ఇంకా అందుబాటులో లేవు. దయచేసి నియామక బృందాన్ని సంప్రదించండి.',
                'camera.open_button': 'కెమెరా తెరవండి',
                'camera.no_image': 'ఇంకా కెమెరా చిత్రం తీసుకోలేదు.',
                'camera.capture_title': 'చిత్రం తీసుకోండి',
                'camera.loading': 'కెమెరా తెరుచుకుంటోంది...',
                'camera.close_button': 'మూసివేయండి',
                'camera.capture_button': 'తీసుకోండి',
                'camera.unsupported': 'ఈ బ్రౌజర్‌లో కెమెరా యాక్సెస్‌కు మద్దతు లేదు.',
                'camera.permission_error': 'కెమెరాను యాక్సెస్ చేయలేకపోయాం. అనుమతి ఇచ్చి మళ్లీ ప్రయత్నించండి.',
                'validation.valid_city': 'ఎంచుకున్న రాష్ట్రానికి సరైన నగరాన్ని ఎంచుకోండి.',
            },
            ta: {
                'language.label': 'மொழி',
                'brand.company': 'அட்டிகா கோல்ட் நிறுவனம்',
                'brand.portal': 'வேட்பாளர் விண்ணப்ப தளம்',
                'title.hiring': 'அட்டிகா பணியாளர் விண்ணப்பப் படிவம்',
                'title.walkin': 'அட்டிகா வாக்-இன் படிவம்',
                'alert.submission_id': 'சமர்ப்பிப்பு ஐடி',
                'alert.resubmission': 'நீங்கள் முன்பைய சமர்ப்பிப்பை புதுப்பித்து வருகிறீர்கள். இந்த படிவத்தை சமர்ப்பித்ததும் புதிய சமர்ப்பிப்பு ஐடி உருவாகும்.',
                'label.hiring_date': 'நியமன தேதி',
                'intro.hiring_desc': 'தயவுசெய்து ஆட்சேர்ப்பு விவரங்களை நிரப்பி, உங்கள் ரெஸ்யூமேவை பதிவேற்றி, வீடியோ நேர்காணலை முடித்து, படிவத்தை ஆட்சேர்ப்பு குழுவிற்கு சமர்ப்பிக்கவும்.',
                'intro.walkin_desc': 'தயவுசெய்து ஆட்சேர்ப்பு விவரங்களை நிரப்பி, உங்கள் ரெஸ்யூமேவை பதிவேற்றி, உங்கள் வாக்-இன் படிவத்தை ஆட்சேர்ப்பு குழுவிற்கு சமர்ப்பிக்கவும்.',
                'autofill.resume_cv': 'ரெஸ்யூமே / சி.வி',
                'autofill.accepted_types': 'ஏற்றுக்கொள்ளப்படும்: PDF, DOC, DOCX. அதிகபட்சம் 10 MB.',
                'autofill.question': 'CV-இலிருந்து படிவ விவரங்களை தானாக நிரப்பவா?',
                'autofill.button': 'CV தேடி தானாக நிரப்பவும்',
                'autofill.initial': 'முதலில் உங்கள் ரெஸ்யூமேவை பதிவேற்றவும். நீங்கள் ஆம் என்பதைத் தேர்ந்தெடுத்தால், CV-இலிருந்து தொடர்புடைய விவரங்கள் தானாக நிரப்பப்படும்.',
                'autofill.search_prompt': 'பதிவேற்றிய ரெஸ்யூமேவை பயன்படுத்தி படிவத்தை நிரப்ப "CV தேடி தானாக நிரப்பவும்" என்பதை கிளிக் செய்யவும்.',
                'autofill.upload_then_search': 'உங்கள் ரெஸ்யூமேவை பதிவேற்றி, பின்னர் "CV தேடி தானாக நிரப்பவும்" என்பதை கிளிக் செய்யவும்.',
                'autofill.off': 'ரெஸ்யூமே ஆட்சேர்ப்பு குழுவிற்கே பதிவேற்றப்படும். ஆட்டோபில் தற்போது அணைக்கப்பட்டுள்ளது.',
                'autofill.searching': 'CV தேடி, தொடர்புடைய தகவல்கள் எடுக்கப்படுகிறது...',
                'autofill.success': 'CV-இல் கிடைத்த தொடர்புடைய விவரங்கள் படிவத்தில் நிரப்பப்பட்டுள்ளன. சமர்ப்பிக்கும் முன் அனைத்து புலங்களையும் சரிபார்க்கவும்.',
                'autofill.parse_error': 'பதிவேற்றிய CV-ஐ படிக்க முடியவில்லை.',
                'common.yes': 'ஆம்',
                'common.no': 'இல்லை',
                'common.add_row': 'வரிசை சேர்க்கவும்',
                'common.remove': 'நீக்கவும்',
                'field.position': 'விண்ணப்பித்த பதவி',
                'field.enter_job_role': 'வேலைப் பொறுப்பை உள்ளிடவும்',
                'field.candidate_name': 'வேட்பாளர் பெயர்',
                'field.date_of_birth': 'பிறந்த தேதி',
                'field.gender': 'பாலினம்',
                'field.marital_status': 'திருமண நிலை',
                'field.current_address': 'தற்போதைய முகவரி',
                'field.permanent_address': 'நிரந்தர முகவரி',
                'field.phone_number': 'தொலைபேசி எண்',
                'field.email': 'மின்னஞ்சல்',
                'field.aadhaar_number': 'ஆதார் அட்டை எண்',
                'field.whatsapp_confirmation': 'வாட்ஸ்அப் உறுதி',
                'field.whatsapp_same': 'உள்ளிடப்பட்ட தொலைபேசி எண் எனது வாட்ஸ்அப் எண் ஆகும்.',
                'field.whatsapp_number': 'வாட்ஸ்அப் எண்',
                'field.candidate_photo': 'வேட்பாளர் புகைப்படம்',
                'field.physical_fitness': 'உடல் தகுதி',
                'field.if_no_reason': 'இல்லையெனில் காரணம்',
                'field.own_two_wheeler': 'சொந்த இருசக்கர வாகனம் உள்ளதா',
                'field.know_attica': 'அட்டிகா பற்றி உங்களுக்கு எப்படி தெரிந்தது',
                'field.computer_knowledge': 'கணினி அறிவு',
                'field.languages_speak': 'அறிந்த மொழிகள் - பேசுதல்',
                'field.languages_read': 'அறிந்த மொழிகள் - வாசித்தல்',
                'field.languages_write': 'அறிந்த மொழிகள் - எழுதுதல்',
                'field.present_remuneration': 'தற்போதைய சம்பளம்',
                'field.salary_expectation': 'எதிர்பார்க்கும் சம்பளம்',
                'field.notice_period': 'சேரும் முன் அறிவிப்பு காலம்',
                'field.preferred_state': 'விருப்பமான வேலை மாநிலம்',
                'field.preferred_city': 'விருப்பமான வேலை நகரம்',
                'field.choose_city_help': 'தேர்ந்தெடுக்கப்பட்ட மாநிலத்திலிருந்து ஒரு நகரத்தைத் தேர்ந்தெடுக்கவும்.',
                'field.place': 'இடம்',
                'field.date': 'தேதி',
                'field.signature': 'கையொப்பம்',
                'section.additional_information': 'கூடுதல் தகவல்',
                'section.professional_qualification': 'தொழில்முறை தகுதி',
                'section.skills_experience': 'திறன்கள் மற்றும் அனுபவம்',
                'section.work_experience': 'பணி அனுபவம்',
                'section.compensation': 'சம்பளம் மற்றும் தயார்நிலை',
                'section.references': 'பரிந்துரைகள்',
                'section.final': 'இறுதி பகுதி',
                'table.examination': 'தேர்வு',
                'table.university': 'பல்கலைக்கழகம்',
                'table.main_subject': 'முக்கிய பாடம்',
                'table.year_of_passing': 'தேர்ச்சி பெற்ற ஆண்டு',
                'table.percentage_obtained': 'பெற்ற சதவீதம்',
                'table.company_name': 'நிறுவனத்தின் பெயர்',
                'table.designation': 'பதவி',
                'table.experience': 'அனுபவம்',
                'table.name': 'பெயர்',
                'table.contact_number': 'தொடர்பு எண்',
                'table.relationship': 'உறவு',
                'action.submit_form': 'பணியாளர் விண்ணப்பப் படிவத்தை சமர்ப்பிக்கவும்',
                'interview.heading': 'இணையாற்றும் வீடியோ நேர்காணல்',
                'interview.description': 'உங்கள் முன்பக்க கேமரா அல்லது வெப்கேமை திறக்க Start ஐ கிளிக் செய்யவும். முதல் கேள்வி எப்போதும் நீங்கள் சமீபத்தில் செய்த பணியைப் பற்றியது இருக்கும். மீதமுள்ள 4 கேள்விகள் தேர்ந்தெடுத்த பதவியின் அடிப்படையில் சீரற்ற முறையில் தேர்வு செய்யப்படும். கேள்வி சப்-டைட்டில் மற்றும் கவுண்ட்டவுன் டைமர் உடன் ஒரு வீடியோ சேமிக்கப்படும்.',
                'interview.preview_button': 'நேர்காணல் முன்னோட்டம்',
                'interview.start_button': 'நேர்காணலை தொடங்கு',
                'interview.record_again': 'மீண்டும் பதிவு செய்',
                'interview.placeholder': 'உங்கள் பதில்களை பதிவு செய்ய நேர்காணலை தொடங்குங்கள். உங்கள் முகம் தெளிவாகத் தெரியட்டும் மற்றும் தெளிவாகப் பேசுங்கள்.',
                'interview.question_placeholder': 'கேள்வி இங்கே தோன்றும்.',
                'interview.status_required': 'படிவத்தை சமர்ப்பிக்கும் முன் நேர்காணல் பதிவு அவசியம்.',
                'interview.preview_title': 'நேர்காணல் வழிகாட்டி',
                'interview.preview_subtitle': 'நேர்காணலை தொடங்குவதற்கு முன் இந்த வழிகாட்டியைப் பாருங்கள்.',
                'interview.video_not_supported': 'உங்கள் உலாவி வீடியோ டேக்கை ஆதரிக்கவில்லை.',
                'interview.fixed_opening_question': 'உங்களைப் பற்றியும் நீங்கள் சமீபத்தில் செய்த பணியைப் பற்றியும் சொல்லுங்கள்.',
                'interview.status_success': 'நேர்காணல் வெற்றிகரமாக பதிவு செய்யப்பட்டது.',
                'interview.status_finishing': 'நேர்காணல் பதிவு முடிக்கப்படுகிறது...',
                'interview.status_position_changed': 'பதவி மாற்றப்பட்டுள்ளது. தேர்ந்தெடுத்த பதவிக்காக நேர்காணலை மீண்டும் பதிவு செய்யவும்.',
                'interview.status_camera_access': 'கேமரா அல்லது மைக்ரோஃபோனை அணுக முடியவில்லை. அனுமதி வழங்கி மீண்டும் முயற்சிக்கவும்.',
                'interview.status_camera_restart': 'புதிய பதிவை தொடங்க முடியவில்லை. அனுமதி வழங்கி மீண்டும் முயற்சிக்கவும்.',
                'interview.status_complete_before_submit': 'படிவத்தை சமர்ப்பிக்கும் முன் வீடியோ நேர்காணலை முடிக்கவும்.',
                'interview.recording_status': ':total இல் :current கேள்வி பதிவு செய்யப்படுகிறது.',
                'interview.alert_select_position': 'நேர்காணலை தொடங்குவதற்கு முன் விண்ணப்பித்த பதவியைத் தேர்ந்தெடுக்கவும் அல்லது உள்ளிடவும்.',
                'interview.alert_browser_unsupported': 'இந்த உலாவியில் வீடியோ நேர்காணல் பதிவு ஆதரிக்கப்படவில்லை. சமீபத்திய Chrome அல்லது Edge பயன்படுத்தவும்.',
                'interview.alert_questions_unavailable': 'தேர்ந்தெடுத்த பதவிக்கு நேர்காணல் கேள்விகள் இன்னும் இல்லை. ஆட்சேர்ப்பு குழுவை தொடர்பு கொள்ளவும்.',
                'camera.open_button': 'கேமராவைத் திறக்கவும்',
                'camera.no_image': 'இன்னும் கேமரா படம் பிடிக்கப்படவில்லை.',
                'camera.capture_title': 'படம் பிடிக்கவும்',
                'camera.loading': 'கேமரா திறக்கப்படுகிறது...',
                'camera.close_button': 'மூடு',
                'camera.capture_button': 'பிடிக்கவும்',
                'camera.unsupported': 'இந்த உலாவியில் கேமரா அணுகல் ஆதரிக்கப்படவில்லை.',
                'camera.permission_error': 'கேமராவை அணுக முடியவில்லை. அனுமதி வழங்கி மீண்டும் முயற்சிக்கவும்.',
                'validation.valid_city': 'தேர்ந்தெடுக்கப்பட்ட மாநிலத்திற்கு செல்லுபடியான நகரத்தைத் தேர்ந்தெடுக்கவும்.',
            },
        };

        const hiringFormOptionTranslations = {
            en: {},
            kn: {
                'Select': 'ಆಯ್ಕೆಮಾಡಿ',
                'Select State': 'ರಾಜ್ಯ ಆಯ್ಕೆಮಾಡಿ',
                'Select City': 'ನಗರ ಆಯ್ಕೆಮಾಡಿ',
                'Other': 'ಇತರೆ',
                'Male': 'ಪುರುಷ',
                'Female': 'ಮಹಿಳೆ',
                'Single': 'ಅವಿವಾಹಿತ',
                'Married': 'ವಿವಾಹಿತ',
                'Divorced': 'ವಿಚ್ಛೇದಿತ',
                'Widowed': 'ವಿಧವೆ / ವಿಧುರ',
                'Separated': 'ಪ್ರತ್ಯೇಕ',
                'Immediate': 'ತಕ್ಷಣ',
                '15 Days': '15 ದಿನಗಳು',
                '30 Days': '30 ದಿನಗಳು',
                '45 Days': '45 ದಿನಗಳು',
                '60 Days': '60 ದಿನಗಳು',
                '90 Days': '90 ದಿನಗಳು',
                'Friend': 'ಸ್ನೇಹಿತ',
                'Relative': 'ಬಂಧು',
                'Colleague': 'ಸಹೋದ್ಯೋಗಿ',
                'Neighbour': 'ನೆರೆಹೊರೆಯವರು',
            },
            hi: {
                'Select': 'चुनें',
                'Select State': 'राज्य चुनें',
                'Select City': 'शहर चुनें',
                'Other': 'अन्य',
                'Male': 'पुरुष',
                'Female': 'महिला',
                'Single': 'अविवाहित',
                'Married': 'विवाहित',
                'Divorced': 'तलाकशुदा',
                'Widowed': 'विधवा / विधुर',
                'Separated': 'अलग',
                'Immediate': 'तुरंत',
                '15 Days': '15 दिन',
                '30 Days': '30 दिन',
                '45 Days': '45 दिन',
                '60 Days': '60 दिन',
                '90 Days': '90 दिन',
                'Friend': 'मित्र',
                'Relative': 'रिश्तेदार',
                'Colleague': 'सहकर्मी',
                'Neighbour': 'पड़ोसी',
            },
            te: {
                'Select': 'ఎంచుకోండి',
                'Select State': 'రాష్ట్రాన్ని ఎంచుకోండి',
                'Select City': 'నగరాన్ని ఎంచుకోండి',
                'Other': 'ఇతర',
                'Male': 'పురుషుడు',
                'Female': 'మహిళ',
                'Single': 'అవివాహితుడు / అవివాహిత',
                'Married': 'వివాహితుడు / వివాహిత',
                'Divorced': 'విడాకులు పొందిన',
                'Widowed': 'విధవ / విధవరుడు',
                'Separated': 'వేరు',
                'Immediate': 'తక్షణం',
                '15 Days': '15 రోజులు',
                '30 Days': '30 రోజులు',
                '45 Days': '45 రోజులు',
                '60 Days': '60 రోజులు',
                '90 Days': '90 రోజులు',
                'Friend': 'స్నేహితుడు',
                'Relative': 'బంధువు',
                'Colleague': 'সহోద్యోగి',
                'Neighbour': 'పొరుగు వారు',
            },
            ta: {
                'Select': 'தேர்ந்தெடுக்கவும்',
                'Select State': 'மாநிலத்தைத் தேர்ந்தெடுக்கவும்',
                'Select City': 'நகரத்தைத் தேர்ந்தெடுக்கவும்',
                'Other': 'மற்றவை',
                'Male': 'ஆண்',
                'Female': 'பெண்',
                'Single': 'திருமணம் ஆகாதவர்',
                'Married': 'திருமணமானவர்',
                'Divorced': 'விவாகரத்து பெற்றவர்',
                'Widowed': 'கணவர் / மனைவி இழந்தவர்',
                'Separated': 'பிரிந்து இருப்பவர்',
                'Immediate': 'உடனே',
                '15 Days': '15 நாட்கள்',
                '30 Days': '30 நாட்கள்',
                '45 Days': '45 நாட்கள்',
                '60 Days': '60 நாட்கள்',
                '90 Days': '90 நாட்கள்',
                'Friend': 'நண்பர்',
                'Relative': 'உறவினர்',
                'Colleague': 'சக பணியாளர்',
                'Neighbour': 'அயல்வாசி',
            },
        };

        window.hiringFormI18n = window.hiringFormI18n || {};
        window.hiringFormI18n.currentLanguage = 'en';
        window.hiringFormI18n.translate = function (key, params = {}) {
            const language = window.hiringFormI18n.currentLanguage || 'en';
            let value = hiringFormTranslationCatalog[language]?.[key] ?? hiringFormTranslationCatalog.en[key] ?? key;

            Object.entries(params).forEach(function ([paramKey, paramValue]) {
                value = value.replaceAll(`:${paramKey}`, String(paramValue));
            });

            return value;
        };
        window.hiringFormI18n.t = window.hiringFormI18n.translate;

        document.addEventListener('DOMContentLoaded', function () {
            const languageStorageKey = 'attica_hiring_form_language';
            const hiringLocationOptions = @json($hiringLocationOptions);
            const requiresVideoInterview = @json($requiresVideoInterview);
            const questionBank = @json($questionBank);
            const fixedOpeningQuestion = 'Tell us about yourself and the work you handled most recently.';
            const interviewConfig = {
                questionsPerCandidate: 5,
                secondsPerQuestion: 20,
            };

            const languageSelect = document.getElementById('hiringFormLanguage');
            const positionInput = document.getElementById('positionAppliedFor');
            const customPositionWrap = document.getElementById('customPositionWrap');
            const customPositionInput = document.getElementById('customPositionInput');
            const contactNumberInput = document.getElementById('contactNumber');
            const whatsappSameCheckbox = document.getElementById('whatsappSameAsContact');
            const whatsappSameValueInput = document.getElementById('isWhatsappSameAsContactValue');
            const whatsappNumberWrap = document.getElementById('whatsappNumberWrap');
            const whatsappNumberInput = document.getElementById('whatsappNumber');
            const resumeFileInput = document.getElementById('resumeFileInput');
            const resumeAutofillYes = document.getElementById('resumeAutofillYes');
            const resumeAutofillButton = document.getElementById('resumeAutofillButton');
            const resumeAutofillStatus = document.getElementById('resumeAutofillStatus');
            const form = document.getElementById('hiringForm');
            const preferredWorkLocationState = document.getElementById('preferredWorkLocationState');
            const preferredWorkLocationCity = document.getElementById('preferredWorkLocationCity');

            const startInterviewButton = document.getElementById('startInterviewButton');
            const previewInterviewButton = document.getElementById('previewInterviewButton');
            const retakeInterviewButton = document.getElementById('retakeInterviewButton');
            const sourceVideo = document.getElementById('interviewSourceVideo');
            const previewCanvas = document.getElementById('interviewPreviewCanvas');
            const previewContext = previewCanvas ? previewCanvas.getContext('2d') : null;
            const overlay = document.getElementById('interviewOverlay');
            const interviewTimer = document.getElementById('interviewTimer');
            const interviewQuestion = document.getElementById('interviewQuestion');
            const interviewPlaceholder = document.getElementById('interviewPlaceholder');
            const interviewStatus = document.getElementById('interviewStatus');
            const interviewVideoFile = document.getElementById('interviewVideoFile');
            const interviewPayloadInput = document.getElementById('interviewPayload');
            const previewInterviewModalElement = document.getElementById('previewInterviewModal');
            const previewInterviewVideo = document.getElementById('previewInterviewVideo');
            const previewInterviewModal = previewInterviewModalElement ? new bootstrap.Modal(previewInterviewModalElement) : null;

            let liveStream = null;
            let recorderStream = null;
            let mediaRecorder = null;
            let recordedChunks = [];
            let activeQuestions = [];
            let currentQuestionIndex = 0;
            let secondsRemaining = interviewConfig.secondsPerQuestion;
            let questionInterval = null;
            let renderFrame = null;
            let recordedPreviewUrl = null;
            let recordingStartedAt = null;
            let currentRecordedPosition = '';

            function t(key, params = {}) {
                return window.hiringFormI18n.translate(key, params);
            }

            function setTranslatedStatus(element, key, className, params = {}) {
                if (!element) {
                    return;
                }

                if (className) {
                    element.className = className;
                }

                element.dataset.statusKey = key;
                element.dataset.statusParams = JSON.stringify(params);
                element.textContent = t(key, params);
            }

            function refreshTranslatedStatus(element) {
                if (!element?.dataset.statusKey) {
                    return;
                }

                const params = element.dataset.statusParams ? JSON.parse(element.dataset.statusParams) : {};
                element.textContent = t(element.dataset.statusKey, params);
            }

            function translateOptions(root = document) {
                root.querySelectorAll('option').forEach(function (option) {
                    const baseText = option.dataset.baseText || option.textContent.trim();
                    option.dataset.baseText = baseText;
                    option.textContent = hiringFormOptionTranslations[window.hiringFormI18n.currentLanguage]?.[baseText] || baseText;
                });
            }

            function applyTranslations(root = document) {
                root.querySelectorAll('[data-i18n]').forEach(function (element) {
                    const key = element.dataset.i18n;

                    if (!key) {
                        return;
                    }

                    element.textContent = t(key);
                });

                root.querySelectorAll('[data-i18n-placeholder]').forEach(function (element) {
                    const key = element.dataset.i18nPlaceholder;

                    if (!key) {
                        return;
                    }

                    element.placeholder = t(key);
                });

                root.querySelectorAll('[data-camera-title-key]').forEach(function (element) {
                    const key = element.dataset.cameraTitleKey;

                    if (!key) {
                        return;
                    }

                    element.dataset.cameraTitle = t(key);
                });

                translateOptions(root);
                refreshTranslatedStatus(resumeAutofillStatus);
                refreshTranslatedStatus(interviewStatus);
                document.documentElement.lang = window.hiringFormI18n.currentLanguage;
                document.title = t(requiresVideoInterview ? 'title.hiring' : 'title.walkin');
            }

            window.hiringFormI18n.applyTranslations = applyTranslations;

            function setLanguage(language) {
                const resolvedLanguage = hiringFormTranslationCatalog[language] ? language : 'en';
                window.hiringFormI18n.currentLanguage = resolvedLanguage;

                if (languageSelect) {
                    languageSelect.value = resolvedLanguage;
                }

                try {
                    window.localStorage.setItem(languageStorageKey, resolvedLanguage);
                } catch (error) {
                }

                applyTranslations();
            }

            function fixedOpeningQuestionText() {
                return t('interview.fixed_opening_question');
            }

            function syncPreferredWorkCities() {
                if (!preferredWorkLocationState || !preferredWorkLocationCity) {
                    return;
                }

                const selectedState = preferredWorkLocationState.value || '';
                const savedCity = preferredWorkLocationCity.dataset.selectedCity || '';
                const previousCity = preferredWorkLocationCity.value || '';
                const availableCities = Array.isArray(hiringLocationOptions[selectedState]) ? hiringLocationOptions[selectedState] : [];
                const targetCity = availableCities.includes(previousCity)
                    ? previousCity
                    : (availableCities.includes(savedCity) ? savedCity : '');

                preferredWorkLocationCity.innerHTML = `<option value="">${hiringFormOptionTranslations[window.hiringFormI18n.currentLanguage]?.['Select City'] || 'Select City'}</option>`;

                availableCities.forEach((city) => {
                    const option = document.createElement('option');
                    option.value = city;
                    option.textContent = city;
                    option.selected = city === targetCity;
                    preferredWorkLocationCity.appendChild(option);
                });

                preferredWorkLocationCity.dataset.selectedCity = targetCity;
            }

            function syncWhatsappVisibility() {
                const same = whatsappSameCheckbox.checked;
                whatsappSameValueInput.value = same ? '1' : '0';
                whatsappNumberWrap.style.display = same ? 'none' : '';
                whatsappNumberInput.required = !same;

                if (same) {
                    whatsappNumberInput.value = contactNumberInput.value;
                }
            }

            function syncCustomPositionVisibility() {
                if (!customPositionWrap || !customPositionInput || !positionInput || positionInput.tagName !== 'SELECT') {
                    return;
                }

                const isOther = positionInput.value === 'other';
                customPositionWrap.style.display = isOther ? '' : 'none';
                customPositionInput.required = isOther;

                if (!isOther) {
                    customPositionInput.value = '';
                }
            }

            function formatTimer(totalSeconds) {
                const safeSeconds = Math.max(0, Number(totalSeconds) || 0);
                const minutes = Math.floor(safeSeconds / 60);
                const seconds = safeSeconds % 60;
                return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            }

            function syncResumeAutofillButton() {
                const shouldAutofill = resumeAutofillYes.checked;
                const hasFile = Boolean(resumeFileInput.files && resumeFileInput.files.length);
                resumeAutofillButton.disabled = !shouldAutofill || !hasFile;
            }

            function setFieldValue(name, value) {
                if (!value) {
                    return;
                }

                const field = form.querySelector(`[name="${name}"]`);

                if (!field) {
                    return;
                }

                field.value = value;
                field.dispatchEvent(new Event('input', { bubbles: true }));
                field.dispatchEvent(new Event('change', { bubbles: true }));
            }

            function fillTableRows(targetId, prefix, rows, keys) {
                if (!Array.isArray(rows) || !rows.length) {
                    return;
                }

                const target = document.getElementById(targetId);

                if (!target) {
                    return;
                }

                target.innerHTML = '';

                rows.forEach(function (row, index) {
                    const tr = document.createElement('tr');
                    tr.innerHTML = keys.map(function (key) {
                        const inputType = ['year_of_passing', 'percentage_obtained', 'experience'].includes(key) ? 'number' : 'text';
                        const extra = key === 'percentage_obtained' ? ' step="0.01" min="0" max="100"' : key === 'experience' ? ' step="0.1" min="0"' : key === 'year_of_passing' ? ' min="1000" max="9999" step="1"' : '';
                        const safeValue = String(row[key] || '').replace(/"/g, '&quot;');
                        return `<td><input type="${inputType}" class="form-control" name="${prefix}[${index}][${key}]" value="${safeValue}"${extra}></td>`;
                    }).join('') + `<td><button type="button" class="btn btn-outline-danger btn-sm js-remove-row" data-i18n="common.remove">${t('common.remove')}</button></td>`;
                    target.appendChild(tr);
                });

                applyTranslations(target);
            }

            async function autofillFromResume() {
                if (!resumeFileInput.files || !resumeFileInput.files.length) {
                    return;
                }

                resumeAutofillButton.disabled = true;
                setTranslatedStatus(resumeAutofillStatus, 'autofill.searching', 'alert alert-primary mb-0');

                try {
                    const formData = new FormData();
                    formData.append('_token', form.querySelector('input[name="_token"]').value);
                    formData.append('resume_file', resumeFileInput.files[0]);

                    const response = await fetch(@json(route('recruitment-hiring-form-autofill', $formLink->public_token)), {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        body: formData,
                        credentials: 'same-origin',
                    });

                    const result = await response.json();

                    if (!response.ok || !result.ok) {
                        throw new Error(result.message || t('autofill.parse_error'));
                    }

                    const payload = result.payload || {};
                    setFieldValue('candidate_name', payload.candidate_name || '');
                    setFieldValue('email', payload.email || '');
                    setFieldValue('contact_number', payload.contact_number || '');
                    setFieldValue('current_address', payload.current_address || '');
                    setFieldValue('computer_knowledge', payload.computer_knowledge || '');
                    setFieldValue('languages_speak', payload.languages_speak || '');
                    setFieldValue('languages_read', payload.languages_read || '');
                    setFieldValue('languages_write', payload.languages_write || '');

                    if (whatsappSameCheckbox.checked && payload.contact_number) {
                        whatsappNumberInput.value = payload.contact_number;
                    }

                    fillTableRows('qualifications-body', 'qualifications', payload.qualifications || [], [
                        'examination',
                        'university',
                        'main_subject',
                        'year_of_passing',
                        'percentage_obtained',
                    ]);
                    fillTableRows('work-experience-body', 'work_experiences', payload.work_experiences || [], [
                        'company_name',
                        'designation',
                        'experience',
                    ]);

                    setTranslatedStatus(resumeAutofillStatus, 'autofill.success', 'alert alert-success mb-0');
                } catch (error) {
                    resumeAutofillStatus.className = 'alert alert-danger mb-0';
                    resumeAutofillStatus.dataset.statusKey = '';
                    resumeAutofillStatus.dataset.statusParams = '';
                    resumeAutofillStatus.textContent = error.message || t('autofill.parse_error');
                } finally {
                    syncResumeAutofillButton();
                }
            }

            function shuffle(array) {
                const items = [...array];

                for (let index = items.length - 1; index > 0; index -= 1) {
                    const swapIndex = Math.floor(Math.random() * (index + 1));
                    [items[index], items[swapIndex]] = [items[swapIndex], items[index]];
                }

                return items;
            }

            function normalizedQuestion(value) {
                return String(value || '').trim().toLowerCase();
            }

            function selectedPosition() {
                const baseValue = (positionInput?.value || '').trim();

                if (baseValue === 'other' && customPositionInput) {
                    return customPositionInput.value.trim();
                }

                return baseValue;
            }

            function questionsForPosition(position) {
                if (position && Array.isArray(questionBank[position]) && questionBank[position].length >= interviewConfig.questionsPerCandidate) {
                    return questionBank[position];
                }

                return Array.isArray(questionBank.__default__) ? questionBank.__default__ : [];
            }

            function resetQuestionTimer() {
                secondsRemaining = interviewConfig.secondsPerQuestion;
                interviewTimer.textContent = formatTimer(secondsRemaining);
            }

            function drawInterviewFrame() {
                if (!sourceVideo.videoWidth || !sourceVideo.videoHeight) {
                    renderFrame = requestAnimationFrame(drawInterviewFrame);
                    return;
                }

                if (previewCanvas.width !== sourceVideo.videoWidth || previewCanvas.height !== sourceVideo.videoHeight) {
                    previewCanvas.width = sourceVideo.videoWidth;
                    previewCanvas.height = sourceVideo.videoHeight;
                }

                previewContext.drawImage(sourceVideo, 0, 0, previewCanvas.width, previewCanvas.height);
                previewContext.fillStyle = 'rgba(15, 23, 42, 0.75)';
                previewContext.fillRect(previewCanvas.width - 170, 20, 150, 48);
                previewContext.fillStyle = '#ffffff';
                previewContext.font = 'bold 26px Arial';
                previewContext.textAlign = 'center';
                previewContext.fillText(interviewTimer.textContent, previewCanvas.width - 95, 52);

                const questionText = interviewQuestion.textContent || '';
                const maxWidth = previewCanvas.width - 80;
                const boxX = 30;
                const lineHeight = 30;
                const lines = [];
                const words = questionText.split(/\s+/).filter(Boolean);
                let currentLine = '';

                previewContext.font = '24px Arial';
                previewContext.textAlign = 'left';

                words.forEach(word => {
                    const testLine = currentLine ? `${currentLine} ${word}` : word;
                    if (previewContext.measureText(testLine).width > maxWidth && currentLine) {
                        lines.push(currentLine);
                        currentLine = word;
                    } else {
                        currentLine = testLine;
                    }
                });

                if (currentLine) {
                    lines.push(currentLine);
                }

                const boxHeight = Math.max(72, 36 + (lines.length * lineHeight));
                const boxY = previewCanvas.height - boxHeight - 30;
                previewContext.fillStyle = 'rgba(15, 23, 42, 0.80)';
                previewContext.fillRect(boxX, boxY, maxWidth + 20, boxHeight);
                previewContext.fillStyle = '#ffffff';

                lines.forEach((line, index) => {
                    previewContext.fillText(line, boxX + 10, boxY + 32 + (index * lineHeight));
                });

                renderFrame = requestAnimationFrame(drawInterviewFrame);
            }

            function stopTracks(stream) {
                if (!stream) {
                    return;
                }

                stream.getTracks().forEach(track => track.stop());
            }

            function clearRecordedInterview() {
                if (interviewVideoFile) {
                    interviewVideoFile.value = '';
                }
                if (interviewPayloadInput) {
                    interviewPayloadInput.value = '';
                }
                currentRecordedPosition = '';

                if (recordedPreviewUrl) {
                    URL.revokeObjectURL(recordedPreviewUrl);
                    recordedPreviewUrl = null;
                }
            }

            function cleanupInterviewSession() {
                stopTracks(liveStream);
                stopTracks(recorderStream);
                liveStream = null;
                recorderStream = null;
                if (sourceVideo) {
                    sourceVideo.srcObject = null;
                    sourceVideo.style.display = 'none';
                }
                if (overlay) {
                    overlay.style.display = 'none';
                }
                if (interviewPlaceholder) {
                    interviewPlaceholder.style.display = 'flex';
                }

                if (questionInterval) {
                    clearInterval(questionInterval);
                    questionInterval = null;
                }

                if (renderFrame) {
                    cancelAnimationFrame(renderFrame);
                    renderFrame = null;
                }

                if (startInterviewButton) {
                    startInterviewButton.disabled = false;
                }
            }

            function finalizeInterview(blob, mimeType) {
                const extension = mimeType.includes('mp4') ? 'mp4' : 'webm';
                const position = selectedPosition();
                const file = new File([blob], `attica_interview_${Date.now()}.${extension}`, {
                    type: mimeType || 'video/webm',
                });
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                interviewVideoFile.files = dataTransfer.files;

                if (recordedPreviewUrl) {
                    URL.revokeObjectURL(recordedPreviewUrl);
                }

                retakeInterviewButton.classList.remove('d-none');
                setTranslatedStatus(interviewStatus, 'interview.status_success', 'alert alert-success mb-3');
                interviewPayloadInput.value = JSON.stringify({
                    position: position,
                    questions: activeQuestions,
                    duration_seconds: interviewConfig.secondsPerQuestion,
                    recorded_at: new Date().toISOString(),
                    started_at: recordingStartedAt,
                });
                currentRecordedPosition = position;
            }

            function stopInterviewRecording() {
                if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                    mediaRecorder.stop();
                } else {
                    cleanupInterviewSession();
                }
            }

            function advanceQuestion() {
                currentQuestionIndex += 1;

                if (currentQuestionIndex >= activeQuestions.length) {
                    setTranslatedStatus(interviewStatus, 'interview.status_finishing', 'alert alert-secondary mb-3');
                    stopInterviewRecording();
                    return;
                }

                interviewQuestion.textContent = activeQuestions[currentQuestionIndex];
                resetQuestionTimer();
            }

            async function startInterviewRecording() {
                const position = selectedPosition();
                const questionPool = questionsForPosition(position);
                const randomQuestionPool = questionPool.filter(question =>
                    normalizedQuestion(question) !== normalizedQuestion(fixedOpeningQuestion) &&
                    normalizedQuestion(question) !== normalizedQuestion(fixedOpeningQuestionText())
                );

                if (!position) {
                    window.alert(t('interview.alert_select_position'));
                    positionInput?.focus();
                    return;
                }

                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || typeof MediaRecorder === 'undefined') {
                    window.alert(t('interview.alert_browser_unsupported'));
                    return;
                }

                if (randomQuestionPool.length < interviewConfig.questionsPerCandidate - 1) {
                    window.alert(t('interview.alert_questions_unavailable'));
                    return;
                }

                cleanupInterviewSession();
                clearRecordedInterview();
                activeQuestions = [
                    fixedOpeningQuestionText(),
                    ...shuffle(randomQuestionPool).slice(0, interviewConfig.questionsPerCandidate - 1),
                ];
                currentQuestionIndex = 0;
                recordingStartedAt = new Date().toISOString();

                try {
                    liveStream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: {
                                ideal: 'user',
                            },
                        },
                        audio: true,
                    });
                } catch (primaryError) {
                    liveStream = await navigator.mediaDevices.getUserMedia({
                        video: true,
                        audio: true,
                    });
                }

                sourceVideo.srcObject = liveStream;
                sourceVideo.style.display = 'block';
                await sourceVideo.play();

                const canvasStream = previewCanvas.captureStream(30);
                recorderStream = new MediaStream([
                    ...canvasStream.getVideoTracks(),
                    ...liveStream.getAudioTracks(),
                ]);

                const supportedMimeType = [
                    'video/webm;codecs=vp9,opus',
                    'video/webm;codecs=vp8,opus',
                    'video/webm',
                    'video/mp4',
                ].find(type => MediaRecorder.isTypeSupported(type)) || '';

                mediaRecorder = supportedMimeType
                    ? new MediaRecorder(recorderStream, { mimeType: supportedMimeType })
                    : new MediaRecorder(recorderStream);

                recordedChunks = [];
                mediaRecorder.ondataavailable = function (event) {
                    if (event.data && event.data.size > 0) {
                        recordedChunks.push(event.data);
                    }
                };
                mediaRecorder.onstop = function () {
                    const blob = new Blob(recordedChunks, { type: mediaRecorder.mimeType || supportedMimeType || 'video/webm' });
                    cleanupInterviewSession();
                    finalizeInterview(blob, mediaRecorder.mimeType || supportedMimeType || 'video/webm');
                };

                interviewPlaceholder.style.display = 'none';
                overlay.style.display = 'flex';
                interviewQuestion.textContent = activeQuestions[currentQuestionIndex];
                resetQuestionTimer();
                drawInterviewFrame();
                mediaRecorder.start(1000);
                setTranslatedStatus(interviewStatus, 'interview.recording_status', 'alert alert-primary mb-3', {
                    current: 1,
                    total: activeQuestions.length,
                });
                startInterviewButton.disabled = true;

                questionInterval = setInterval(function () {
                    secondsRemaining -= 1;
                    interviewTimer.textContent = formatTimer(secondsRemaining);

                    if (secondsRemaining <= 0) {
                        advanceQuestion();
                        setTranslatedStatus(interviewStatus, 'interview.recording_status', 'alert alert-primary mb-3', {
                            current: Math.min(currentQuestionIndex + 1, activeQuestions.length),
                            total: activeQuestions.length,
                        });
                    }
                }, 1000);
            }

            whatsappSameCheckbox?.addEventListener('change', syncWhatsappVisibility);
            contactNumberInput?.addEventListener('input', function () {
                if (whatsappSameCheckbox.checked) {
                    whatsappNumberInput.value = this.value;
                }
            });
            resumeFileInput?.addEventListener('change', function () {
                syncResumeAutofillButton();

                if (resumeAutofillYes.checked) {
                    setTranslatedStatus(
                        resumeAutofillStatus,
                        resumeFileInput.files?.length ? 'autofill.search_prompt' : 'autofill.upload_then_search',
                        'alert alert-secondary mb-0'
                    );
                }
            });
            form.querySelectorAll('input[name="resume_autofill_choice"]').forEach(function (input) {
                input.addEventListener('change', function () {
                    syncResumeAutofillButton();

                    if (resumeAutofillYes.checked) {
                        setTranslatedStatus(
                            resumeAutofillStatus,
                            resumeFileInput.files?.length ? 'autofill.search_prompt' : 'autofill.upload_then_search',
                            'alert alert-secondary mb-0'
                        );
                    } else {
                        setTranslatedStatus(resumeAutofillStatus, 'autofill.off', 'alert alert-secondary mb-0');
                    }
                });
            });
            resumeAutofillButton?.addEventListener('click', autofillFromResume);

            languageSelect?.addEventListener('change', function () {
                setLanguage(this.value);
            });

            positionInput?.addEventListener('change', function () {
                syncCustomPositionVisibility();

                if (currentRecordedPosition && currentRecordedPosition !== selectedPosition()) {
                    clearRecordedInterview();
                    setTranslatedStatus(interviewStatus, 'interview.status_position_changed', 'alert alert-warning mb-3');
                }
            });
            customPositionInput?.addEventListener('input', function () {
                if (currentRecordedPosition && currentRecordedPosition !== selectedPosition()) {
                    clearRecordedInterview();
                    setTranslatedStatus(interviewStatus, 'interview.status_position_changed', 'alert alert-warning mb-3');
                }
            });

            previewInterviewButton?.addEventListener('click', function () {
                if (previewInterviewVideo) {
                    previewInterviewVideo.currentTime = 0;
                }
                previewInterviewModal?.show();
            });

            previewInterviewModalElement?.addEventListener('shown.bs.modal', function () {
                if (previewInterviewVideo) {
                    const playPromise = previewInterviewVideo.play();

                    if (playPromise && typeof playPromise.catch === 'function') {
                        playPromise.catch(function () {});
                    }
                }
            });

            previewInterviewModalElement?.addEventListener('hidden.bs.modal', function () {
                if (previewInterviewVideo) {
                    previewInterviewVideo.pause();
                    previewInterviewVideo.currentTime = 0;
                }
            });

            startInterviewButton?.addEventListener('click', async function () {
                try {
                    await startInterviewRecording();
                } catch (error) {
                    cleanupInterviewSession();
                    startInterviewButton.disabled = false;
                    setTranslatedStatus(interviewStatus, 'interview.status_camera_access', 'alert alert-danger mb-3');
                }
            });

            retakeInterviewButton?.addEventListener('click', async function () {
                try {
                    await startInterviewRecording();
                } catch (error) {
                    cleanupInterviewSession();
                    startInterviewButton.disabled = false;
                    setTranslatedStatus(interviewStatus, 'interview.status_camera_restart', 'alert alert-danger mb-3');
                }
            });

            document.addEventListener('input', function (event) {
                const input = event.target.closest('[data-digit-only]');

                if (!input) {
                    return;
                }

                const maxLength = Number.parseInt(input.dataset.digitOnly || '0', 10);
                const digits = input.value.replace(/\D+/g, '');
                input.value = maxLength > 0 ? digits.slice(0, maxLength) : digits;

                if (input === contactNumberInput && whatsappSameCheckbox.checked) {
                    whatsappNumberInput.value = input.value;
                }
            });

            document.querySelectorAll('.js-add-row').forEach(button => {
                button.addEventListener('click', function () {
                    const target = document.getElementById(this.dataset.target);
                    const template = document.getElementById(this.dataset.template);

                    if (!target || !template) {
                        return;
                    }

                    const index = target.querySelectorAll('tr').length;
                    target.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', index));
                    applyTranslations(target);
                });
            });

            document.addEventListener('click', function (event) {
                const removeButton = event.target.closest('.js-remove-row');

                if (!removeButton) {
                    return;
                }

                removeButton.closest('tr')?.remove();
            });

            form.addEventListener('submit', function (event) {
                if (preferredWorkLocationState && preferredWorkLocationCity) {
                    const selectedState = preferredWorkLocationState.value || '';
                    const selectedCity = preferredWorkLocationCity.value || '';
                    const allowedCities = Array.isArray(hiringLocationOptions[selectedState]) ? hiringLocationOptions[selectedState] : [];

                    if (!selectedState) {
                        event.preventDefault();
                        preferredWorkLocationState.reportValidity();
                        preferredWorkLocationState.focus();
                        return;
                    }

                    if (!selectedCity || !allowedCities.includes(selectedCity)) {
                        event.preventDefault();
                        preferredWorkLocationCity.setCustomValidity(t('validation.valid_city'));
                        preferredWorkLocationCity.reportValidity();
                        preferredWorkLocationCity.focus();
                        return;
                    }

                    preferredWorkLocationCity.setCustomValidity('');
                }

                if (requiresVideoInterview && (!interviewVideoFile || !interviewVideoFile.files || !interviewVideoFile.files.length)) {
                    event.preventDefault();
                    setTranslatedStatus(interviewStatus, 'interview.status_complete_before_submit', 'alert alert-danger mb-3');
                    window.scrollTo({
                        top: interviewStatus.getBoundingClientRect().top + window.scrollY - 40,
                        behavior: 'smooth',
                    });
                }
            });

            window.addEventListener('beforeunload', cleanupInterviewSession);
            preferredWorkLocationState?.addEventListener('change', function () {
                preferredWorkLocationCity.dataset.selectedCity = '';
                syncPreferredWorkCities();
            });
            preferredWorkLocationCity?.addEventListener('change', function () {
                this.dataset.selectedCity = this.value || '';
                this.setCustomValidity('');
            });
            const savedLanguage = (() => {
                try {
                    return window.localStorage.getItem(languageStorageKey) || 'en';
                } catch (error) {
                    return 'en';
                }
            })();
            syncPreferredWorkCities();
            syncWhatsappVisibility();
            syncCustomPositionVisibility();
            syncResumeAutofillButton();
            setLanguage(savedLanguage);
        });
    </script>
</body>
</html>
