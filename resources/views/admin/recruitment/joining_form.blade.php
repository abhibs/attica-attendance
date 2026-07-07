@extends('admin.layout.app')

@section('content')
    @php
        $payload = old() ?: $defaults;
        $workExperiences = old('work_experiences', data_get($defaults, 'work_experiences', [['company_name' => '', 'designation' => '', 'experience' => '']]));
        $references = old('references', data_get($defaults, 'references', [['name' => '', 'contact_number' => '', 'designation' => '', 'relationship' => '']]));
        $documentChecklist = old('document_checklist', data_get($defaults, 'document_checklist', []));
        $documentPhotoPaths = $candidate->document_photo_paths ?? [];
        $selectedBranchId = old('deployed_branch_id', data_get($payload, 'deployed_branch_id'));
        $selectedBranch = $branches->first(fn ($branch) => trim((string) $branch->branchId) === trim((string) $selectedBranchId));
        $selectedBranchLabel = $selectedBranch
            ? trim((string) $selectedBranch->branchId.' - '.$selectedBranch->branchName, ' -')
            : '';
        $branchOptions = $branches->map(function ($branch) {
            $branchId = trim((string) $branch->branchId);
            $branchName = trim((string) $branch->branchName);

            return [
                'id' => $branchId,
                'name' => $branchName,
                'label' => trim($branchId.' - '.$branchName, ' -'),
                'normalized_id' => strtolower(preg_replace('/[^a-z0-9]+/i', '', $branchId)),
                'normalized_name' => strtolower(preg_replace('/[^a-z0-9]+/i', '', $branchName)),
            ];
        })->values();
        $joiningUrl = route('recruitment-onboarding-form-show', $candidate->public_token);
        $joiningUpdateUrl = $candidate->joining_update_token ? route('recruitment-onboarding-update-link', $candidate->joining_update_token) : $joiningUrl;
        $whatsappTarget = $candidate->whatsappTarget();
        $activityLogs = $candidate->activityLogs ?? collect();
        $hiringUserName = $candidate->hiring_admin_name ?: ($candidate->hiringAdmin?->name ?: $candidate->hiringAdmin?->email ?: '--');
        $joiningUserName = $candidate->joining_admin_name ?: ($candidate->joiningAdmin?->name ?: $candidate->joiningAdmin?->email ?: '--');
        $hrAdminName = $candidate->hr_admin_name ?: '--';
        $adminRole = strtolower(trim((string) (Auth::guard('admin')->user()?->role ?? '')));
        $isHrAdmin = $adminRole === ''
            || in_array($adminRole, [\App\Models\Admin::ROLE_HR_ADMIN, \App\Models\Admin::ROLE_SUBHR], true);
        $canShareJoiningForm = in_array($candidate->status, [
            \App\Models\RecruitmentCandidate::STATUS_JOINING_FORM_SHARED,
            \App\Models\RecruitmentCandidate::STATUS_JOINING_UPDATE_REQUESTED,
        ], true);
        $canMarkOnboarded = in_array($candidate->status, [
            \App\Models\RecruitmentCandidate::STATUS_HIRING_SELECTED,
            \App\Models\RecruitmentCandidate::STATUS_JOINING_FORM_SHARED,
            \App\Models\RecruitmentCandidate::STATUS_JOINING_SUBMITTED,
            \App\Models\RecruitmentCandidate::STATUS_JOINING_HOLD,
            \App\Models\RecruitmentCandidate::STATUS_JOINING_REJECTED,
            \App\Models\RecruitmentCandidate::STATUS_JOINING_UPDATE_REQUESTED,
        ], true);
        $canResendJoiningForm = in_array($candidate->status, [
            \App\Models\RecruitmentCandidate::STATUS_JOINING_SUBMITTED,
            \App\Models\RecruitmentCandidate::STATUS_JOINING_HOLD,
            \App\Models\RecruitmentCandidate::STATUS_JOINING_UPDATE_REQUESTED,
        ], true);
    @endphp

    <style>
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
    </style>

    <div class="main-content">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Recruitment</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin-dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin-joining-index') }}">Joining</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Onboarding Form</li>
                    </ol>
                </nav>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger border-0">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="{{ route('admin-joining-store', $candidate->id) }}" class="d-flex flex-column gap-4">
            @csrf

            <div class="card rounded-4">
                <div class="card-body">
                    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
                        <div>
                            <h4 class="mb-1">Onboarding Form</h4>
                            <p class="mb-0 text-muted">{{ $candidate->candidate_name }} | Review the candidate-submitted onboarding data and take the joining decision.</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-self-start">
                            @if ($canShareJoiningForm && $whatsappTarget)
                                <button type="submit" form="joining-start-form" class="btn btn-outline-success btn-sm">Send on WhatsApp</button>
                            @endif
                            @if ($canShareJoiningForm)
                                <button type="button" class="btn btn-outline-primary btn-sm js-copy-link" data-link="{{ $joiningUrl }}">Copy Link</button>
                                <a href="{{ $joiningUrl }}" target="_blank" class="btn btn-outline-secondary btn-sm">Open Candidate Form</a>
                            @endif
                            @if ($candidate->status === \App\Models\RecruitmentCandidate::STATUS_JOINING_UPDATE_REQUESTED && $candidate->joining_update_token)
                                <button type="button" class="btn btn-outline-primary btn-sm js-copy-link" data-link="{{ $joiningUpdateUrl }}">Copy Update Link</button>
                            @endif
                            @if ($canMarkOnboarded)
                                <button type="submit" form="joining-onboarded-form" class="btn btn-success btn-sm">Mark Onboarded</button>
                                @if ($whatsappTarget && $canResendJoiningForm)
                                    <button type="submit" form="joining-resend-form" class="btn btn-outline-dark btn-sm">Resend Form</button>
                                @endif
                            @endif
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-3"><strong>Submission ID:</strong> {{ $candidate->submission_code ?: '--' }}</div>
                        <div class="col-md-3"><strong>Employee ID:</strong> {{ $candidate->generated_emp_id ?: '--' }}</div>
                        <div class="col-md-3"><strong>Contact:</strong> {{ $candidate->contact_number ?: '--' }}</div>
                        <div class="col-md-3"><strong>WhatsApp:</strong> {{ $candidate->whatsapp_number ?: '--' }}</div>
                        <div class="col-md-3"><strong>Fixed Salary:</strong> {{ $candidate->fixed_salary ?: data_get($payload, 'fixed_salary') ?: '--' }}</div>
                        <div class="col-md-3"><strong>Hiring User:</strong> {{ $hiringUserName }}</div>
                        <div class="col-md-3"><strong>Joining User:</strong> {{ $joiningUserName }}</div>
                        <div class="col-md-3"><strong>HR Manager:</strong> {{ $hrAdminName }}</div>
                        <div class="col-md-3">
                            <strong>Resume:</strong>
                            @if ($candidate->resume_file_path)
                                <a href="{{ \App\Support\ProjectAsset::url($candidate->resume_file_path) }}" target="_blank">View / Download</a>
                            @else
                                --
                            @endif
                        </div>
                        @if ($candidate->interview_video_path)
                            <div class="col-md-6">
                                <strong>Interview Video:</strong>
                                <a href="{{ \App\Support\ProjectAsset::url($candidate->interview_video_path) }}" target="_blank">Open Candidate Interview</a>
                            </div>
                        @endif
                    </div>

                    <div class="border rounded-4 p-3 p-lg-4 mb-4 bg-light-subtle">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-2 mb-3">
                            <div>
                                <h5 class="mb-1">Pre-Onboarding Assignment</h5>
                                <p class="mb-0 text-muted">These are the joining-team inputs. They prefill the HR Admin mark-on-duty step and can still be updated later.</p>
                            </div>
                            @if ($candidate->generated_emp_id)
                                <div class="text-lg-end">
                                    <div class="small text-muted">Assigned Employee ID</div>
                                    <div class="fw-semibold">{{ $candidate->generated_emp_id }}</div>
                                </div>
                            @endif
                        </div>

                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Date of Joining</label>
                                <input type="date" name="date_of_joining" class="form-control"
                                    value="{{ old('date_of_joining', data_get($payload, 'date_of_joining')) }}"
                                    data-onboarding-assignment="date_of_joining" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Appointed Designation</label>
                                <input type="text" name="appointed_designation" class="form-control"
                                    value="{{ old('appointed_designation', data_get($payload, 'appointed_designation') ?: $candidate->position_applied_for) }}"
                                    data-onboarding-assignment="appointed_designation" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Deployed Branch</label>
                                <div class="branch-search-picker" data-branch-picker>
                                    <input type="text"
                                        class="form-control"
                                        value="{{ $selectedBranchLabel }}"
                                        placeholder="Type branch name or ID"
                                        autocomplete="off"
                                        data-branch-search>
                                    <input type="hidden"
                                        name="deployed_branch_id"
                                        value="{{ $selectedBranchId }}"
                                        data-branch-value
                                        data-onboarding-assignment="deployed_branch_id">
                                    <div class="branch-search-results list-group" data-branch-results></div>
                                </div>
                                <div class="form-text">Search by branch name, full ID, or part of the ID like `123` for `AGPL123`.</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Shift Timing</label>
                                <input type="text" name="shift_timing" class="form-control"
                                    value="{{ old('shift_timing', data_get($payload, 'shift_timing')) }}"
                                    placeholder="10:00 AM - 7:00 PM"
                                    data-onboarding-assignment="shift_timing" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Fixed Salary</label>
                                <input type="number" step="0.01" min="0" name="fixed_salary" class="form-control"
                                    value="{{ old('fixed_salary', data_get($payload, 'fixed_salary')) }}"
                                    placeholder="Enter fixed salary"
                                    data-onboarding-assignment="fixed_salary" required>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-lg-8">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name (as per Aadhar)</label>
                                    <input type="text" name="full_name_as_per_aadhar" class="form-control" value="{{ old('full_name_as_per_aadhar', data_get($payload, 'full_name_as_per_aadhar')) }}" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" name="date_of_birth" class="form-control" value="{{ old('date_of_birth', data_get($payload, 'date_of_birth')) }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Gender</label>
                                    <select name="gender" class="form-select">
                                        <option value="">Select</option>
                                        @foreach ($genderOptions as $option)
                                            <option value="{{ $option }}" @selected(old('gender', data_get($payload, 'gender')) === $option)>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Father Name</label>
                                    <input type="text" name="father_name" class="form-control" value="{{ old('father_name', data_get($payload, 'father_name')) }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Mother Name</label>
                                    <input type="text" name="mother_name" class="form-control" value="{{ old('mother_name', data_get($payload, 'mother_name')) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Marital Status</label>
                                    <select name="marital_status" class="form-select">
                                        <option value="">Select</option>
                                        @foreach ($maritalStatusOptions as $option)
                                            <option value="{{ $option }}" @selected(old('marital_status', data_get($payload, 'marital_status')) === $option)>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Spouse Name</label>
                                    <input type="text" name="spouse_name" class="form-control" value="{{ old('spouse_name', data_get($payload, 'spouse_name')) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Number of Children</label>
                                    <input type="number" min="0" step="1" name="number_of_children" class="form-control" value="{{ old('number_of_children', data_get($payload, 'number_of_children')) }}">
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            @include('admin.recruitment.partials.camera_field', [
                                'fieldId' => 'employee_photo_data',
                                'inputName' => 'employee_photo_data',
                                'label' => 'Employee Photo',
                                'buttonLabel' => 'Open Camera',
                                'previewSrc' => $candidate->employee_photo_path ?: $candidate->candidate_photo_path,
                                'facingMode' => 'user',
                            ])
                        </div>
                    </div>
                </div>
            </div>

            <div class="card rounded-4">
                <div class="card-body">
                    <h5 class="mb-3">Contact & Address</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Personal Email ID</label>
                            <input type="email" name="personal_email_id" class="form-control" value="{{ old('personal_email_id', data_get($payload, 'personal_email_id')) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" inputmode="numeric" name="contact_number" class="form-control" value="{{ old('contact_number', data_get($payload, 'contact_number')) }}" maxlength="10" pattern="[0-9]{10}" data-digit-only="10" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Blood Group</label>
                            <select name="blood_group" class="form-select">
                                <option value="">Select</option>
                                @foreach ($bloodGroupOptions as $option)
                                    <option value="{{ $option }}" @selected(old('blood_group', data_get($payload, 'blood_group')) === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Present Address</label>
                            <textarea name="present_address" class="form-control" rows="3">{{ old('present_address', data_get($payload, 'present_address')) }}</textarea>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">City</label>
                            <input type="text" name="present_city" class="form-control" value="{{ old('present_city', data_get($payload, 'present_city')) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">PIN Code</label>
                            <input type="number" min="0" step="1" name="present_pin_code" class="form-control" value="{{ old('present_pin_code', data_get($payload, 'present_pin_code')) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">If Rented - Owner Name</label>
                            <input type="text" name="rented_owner_name" class="form-control" value="{{ old('rented_owner_name', data_get($payload, 'rented_owner_name')) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">If Rented - Owner Contact Number</label>
                            <input type="tel" inputmode="numeric" name="rented_owner_contact_number" class="form-control" value="{{ old('rented_owner_contact_number', data_get($payload, 'rented_owner_contact_number')) }}" maxlength="10" pattern="[0-9]{10}" data-digit-only="10">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Permanent Address</label>
                            <textarea name="permanent_address" class="form-control" rows="3">{{ old('permanent_address', data_get($payload, 'permanent_address')) }}</textarea>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">City</label>
                            <input type="text" name="permanent_city" class="form-control" value="{{ old('permanent_city', data_get($payload, 'permanent_city')) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">PIN Code</label>
                            <input type="number" min="0" step="1" name="permanent_pin_code" class="form-control" value="{{ old('permanent_pin_code', data_get($payload, 'permanent_pin_code')) }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card rounded-4">
                <div class="card-body">
                    <h5 class="mb-3">Employment Details in Attica</h5>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Referred By</label>
                            <input type="text" name="referred_by" class="form-control" value="{{ old('referred_by', data_get($payload, 'referred_by')) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Religion</label>
                            <input type="text" name="religion" class="form-control" value="{{ old('religion', data_get($payload, 'religion')) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Caste</label>
                            <input type="text" name="caste" class="form-control" value="{{ old('caste', data_get($payload, 'caste')) }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card rounded-4">
                <div class="card-body">
                    <h5 class="mb-3">Previous Company Details</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="company_name" class="form-control" value="{{ old('company_name', data_get($payload, 'company_name')) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Designation</label>
                            <input type="text" name="previous_designation" class="form-control" value="{{ old('previous_designation', data_get($payload, 'previous_designation')) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Relieving Date</label>
                            <input type="date" name="relieving_date" class="form-control" value="{{ old('relieving_date', data_get($payload, 'relieving_date')) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Number of Years of Experience</label>
                            <input type="number" min="0" step="0.1" name="years_of_experience" class="form-control" value="{{ old('years_of_experience', data_get($payload, 'years_of_experience')) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Reporting Officer Name</label>
                            <input type="text" name="reporting_officer_name" class="form-control" value="{{ old('reporting_officer_name', data_get($payload, 'reporting_officer_name')) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Reporting Officer Contact Number</label>
                            <input type="tel" inputmode="numeric" name="reporting_officer_contact_number" class="form-control" value="{{ old('reporting_officer_contact_number', data_get($payload, 'reporting_officer_contact_number')) }}" maxlength="10" pattern="[0-9]{10}" data-digit-only="10">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card rounded-4">
                <div class="card-body">
                    <h5 class="mb-3">Additional Employment Information</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <label class="form-label">Computer Knowledge</label>
                            <textarea name="computer_knowledge" class="form-control" rows="3">{{ old('computer_knowledge', data_get($payload, 'computer_knowledge')) }}</textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Work Experience</h6>
                        <button type="button" class="btn btn-outline-secondary btn-sm js-add-row" data-target="onboarding-work-experience-body" data-template="onboarding-work-experience-row-template">Add Row</button>
                    </div>
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>Company Name</th>
                                    <th>Designation</th>
                                    <th>Experience</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="onboarding-work-experience-body">
                                @foreach ($workExperiences as $index => $experience)
                                    <tr>
                                        <td><input type="text" class="form-control" name="work_experiences[{{ $index }}][company_name]" value="{{ $experience['company_name'] ?? '' }}"></td>
                                        <td><input type="text" class="form-control" name="work_experiences[{{ $index }}][designation]" value="{{ $experience['designation'] ?? '' }}"></td>
                                        <td><input type="number" min="0" step="0.1" class="form-control" name="work_experiences[{{ $index }}][experience]" value="{{ $experience['experience'] ?? '' }}"></td>
                                        <td><button type="button" class="btn btn-outline-danger btn-sm js-remove-row">Remove</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Languages Known - Speak</label>
                            <textarea name="languages_speak" class="form-control" rows="3">{{ old('languages_speak', data_get($payload, 'languages_speak')) }}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Languages Known - Read</label>
                            <textarea name="languages_read" class="form-control" rows="3">{{ old('languages_read', data_get($payload, 'languages_read')) }}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Languages Known - Write</label>
                            <textarea name="languages_write" class="form-control" rows="3">{{ old('languages_write', data_get($payload, 'languages_write')) }}</textarea>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Present Remuneration</label>
                            <input type="number" step="0.01" min="0" name="present_remuneration" class="form-control" value="{{ old('present_remuneration', data_get($payload, 'present_remuneration')) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Salary Expectation</label>
                            <input type="number" step="0.01" min="0" name="salary_expectation" class="form-control" value="{{ old('salary_expectation', data_get($payload, 'salary_expectation')) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Notice Period</label>
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

            <div class="card rounded-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">References & Emergency Contacts</h5>
                        <button type="button" class="btn btn-outline-secondary btn-sm js-add-row" data-target="onboarding-references-body" data-template="onboarding-reference-row-template">Add Row</button>
                    </div>

                    <div class="table-responsive mb-4">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Contact Number</th>
                                    <th>Designation</th>
                                    <th>Relationship</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="onboarding-references-body">
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
                                        <td><button type="button" class="btn btn-outline-danger btn-sm js-remove-row">Remove</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Emergency Contact Number</label>
                            <input type="tel" inputmode="numeric" name="emergency_contact_number" class="form-control" value="{{ old('emergency_contact_number', data_get($payload, 'emergency_contact_number')) }}" maxlength="10" pattern="[0-9]{10}" data-digit-only="10">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Emergency Contact Name</label>
                            <input type="text" name="emergency_contact_name" class="form-control" value="{{ old('emergency_contact_name', data_get($payload, 'emergency_contact_name')) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Relationship</label>
                            <select name="emergency_relationship" class="form-select">
                                <option value="">Select</option>
                                @foreach ($relationshipOptions as $option)
                                    <option value="{{ $option }}" @selected(old('emergency_relationship', data_get($payload, 'emergency_relationship')) === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Reference Name</label>
                            <input type="text" name="emergency_reference_name" class="form-control" value="{{ old('emergency_reference_name', data_get($payload, 'emergency_reference_name')) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Reference Contact Number</label>
                            <input type="tel" inputmode="numeric" name="emergency_reference_contact_number" class="form-control" value="{{ old('emergency_reference_contact_number', data_get($payload, 'emergency_reference_contact_number')) }}" maxlength="10" pattern="[0-9]{10}" data-digit-only="10">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Reference Designation</label>
                            <input type="text" name="emergency_reference_designation" class="form-control" value="{{ old('emergency_reference_designation', data_get($payload, 'emergency_reference_designation')) }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card rounded-4">
                <div class="card-body">
                    <h5 class="mb-3">Document Submission Checklist</h5>
                    <div class="row g-3">
                        @foreach ($documentKeys as $key => $label)
                            <div class="col-xl-4 col-md-6">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="document_checklist[{{ $key }}]" value="1" id="doc_check_{{ $key }}" @checked(data_get($documentChecklist, $key))>
                                        <label class="form-check-label fw-semibold" for="doc_check_{{ $key }}">{{ $label }}</label>
                                    </div>

                                    @include('admin.recruitment.partials.camera_field', [
                                        'fieldId' => 'document_photo_'.$key,
                                        'inputName' => 'document_photo_data['.$key.']',
                                        'label' => $label.' Photo',
                                        'buttonLabel' => 'Open Camera',
                                        'previewSrc' => $documentPhotoPaths[$key] ?? '',
                                    ])
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card rounded-4">
                <div class="card-body">
                    <h5 class="mb-3">Final Declaration</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Place</label>
                            <input type="text" name="place" class="form-control" value="{{ old('place', data_get($payload, 'place')) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Date</label>
                            <input type="date" name="form_date" class="form-control" value="{{ old('form_date', data_get($payload, 'form_date', now()->toDateString())) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Signature</label>
                            <input type="text" name="signature" class="form-control" value="{{ old('signature', data_get($payload, 'signature')) }}" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary px-5">Save Onboarding Details</button>
            </div>
        </form>

        <div class="card rounded-4 mt-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Activity History</h5>
                    <span class="text-muted small">Latest updates for this candidate</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>User</th>
                                <th>Role</th>
                                <th>Action</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($activityLogs as $log)
                                <tr>
                                    <td>{{ optional($log->created_at)->format('Y-m-d H:i') ?: '--' }}</td>
                                    <td>{{ $log->actor_name ?: '--' }}</td>
                                    <td>{{ $log->actor_role ? ucfirst(str_replace('_', ' ', $log->actor_role)) : '--' }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $log->action ?: '--')) }}</td>
                                    <td>{{ $log->remarks ?: '--' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No activity has been logged for this candidate yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <form method="post" action="{{ route('admin-joining-start', $candidate->id) }}" id="joining-start-form" class="d-none js-whatsapp-action-form" data-phone="{{ $whatsappTarget ?: '' }}" data-link="{{ $joiningUrl }}" data-message="Congratulations. You have been selected. Please complete the onboarding form so we can proceed with your onboarding.">
            @csrf
        </form>
        <form method="post" action="{{ route('admin-joining-decision', $candidate->id) }}" id="joining-onboarded-form" class="d-none">
            @csrf
            <input type="hidden" name="action" value="onboarded">
            <input type="hidden" name="date_of_joining" value="">
            <input type="hidden" name="appointed_designation" value="">
            <input type="hidden" name="deployed_branch_id" value="">
            <input type="hidden" name="shift_timing" value="">
            <input type="hidden" name="fixed_salary" value="">
        </form>
        <form method="post" action="{{ route('admin-joining-decision', $candidate->id) }}" id="joining-resend-form" class="d-none js-whatsapp-action-form" data-phone="{{ $whatsappTarget ?: '' }}" data-link="{{ $joiningUpdateUrl }}" data-message="Congratulations. You have been selected. Please review and complete the onboarding form so we can proceed with your onboarding.">
            @csrf
            <input type="hidden" name="action" value="resend">
        </form>
    </div>

    <script type="text/template" id="onboarding-work-experience-row-template">
        <tr>
            <td><input type="text" class="form-control" name="work_experiences[__INDEX__][company_name]"></td>
            <td><input type="text" class="form-control" name="work_experiences[__INDEX__][designation]"></td>
            <td><input type="number" min="0" step="0.1" class="form-control" name="work_experiences[__INDEX__][experience]"></td>
            <td><button type="button" class="btn btn-outline-danger btn-sm js-remove-row">Remove</button></td>
        </tr>
    </script>

    <script type="text/template" id="onboarding-reference-row-template">
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
            <td><button type="button" class="btn btn-outline-danger btn-sm js-remove-row">Remove</button></td>
        </tr>
    </script>

    @include('admin.recruitment.partials.camera_modal')

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const branchOptions = @json($branchOptions);

            document.addEventListener('input', function (event) {
                const input = event.target.closest('[data-digit-only]');

                if (!input) {
                    return;
                }

                const maxLength = Number.parseInt(input.dataset.digitOnly || '0', 10);
                const digits = input.value.replace(/\D+/g, '');
                input.value = maxLength > 0 ? digits.slice(0, maxLength) : digits;
            });

            function normalizeBranchSearch(value) {
                return String(value || '')
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '');
            }

            function branchMatches(option, term) {
                if (!term) {
                    return true;
                }

                return option.label.toLowerCase().includes(term)
                    || option.id.toLowerCase().includes(term)
                    || option.name.toLowerCase().includes(term)
                    || option.normalized_id.includes(normalizeBranchSearch(term))
                    || option.normalized_name.includes(normalizeBranchSearch(term));
            }

            function findExactBranch(searchValue) {
                const raw = String(searchValue || '').trim().toLowerCase();
                const normalized = normalizeBranchSearch(searchValue);

                if (!raw && !normalized) {
                    return null;
                }

                return branchOptions.find((option) => {
                    return option.id.toLowerCase() === raw
                        || option.label.toLowerCase() === raw
                        || option.name.toLowerCase() === raw
                        || option.normalized_id === normalized
                        || option.normalized_name === normalized;
                }) || null;
            }

            function branchSuggestions(searchValue) {
                const raw = String(searchValue || '').trim().toLowerCase();

                return branchOptions
                    .filter((option) => branchMatches(option, raw))
                    .slice(0, 8);
            }

            function renderBranchSuggestions(results, suggestions) {
                results.innerHTML = '';

                if (!suggestions.length) {
                    results.style.display = 'none';
                    return;
                }

                suggestions.forEach((option) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'branch-search-result list-group-item list-group-item-action';
                    button.dataset.branchId = option.id;
                    button.innerHTML = `<span class="branch-search-result-id">${option.id}</span><span class="branch-search-result-name">${option.name || 'Branch'}</span>`;
                    results.appendChild(button);
                });

                results.style.display = 'block';
            }

            function selectBranch(picker, option) {
                const searchInput = picker.querySelector('[data-branch-search]');
                const hiddenInput = picker.querySelector('[data-branch-value]');
                const results = picker.querySelector('[data-branch-results]');

                searchInput.value = option ? option.label : '';
                hiddenInput.value = option ? option.id : '';
                searchInput.setCustomValidity('');
                results.style.display = 'none';
                results.innerHTML = '';
            }

            document.querySelectorAll('[data-branch-picker]').forEach((picker) => {
                const searchInput = picker.querySelector('[data-branch-search]');
                const hiddenInput = picker.querySelector('[data-branch-value]');
                const results = picker.querySelector('[data-branch-results]');
                const form = picker.closest('form');

                searchInput.addEventListener('focus', function () {
                    renderBranchSuggestions(results, branchSuggestions(this.value));
                });

                searchInput.addEventListener('input', function () {
                    hiddenInput.value = '';
                    this.setCustomValidity('');
                    renderBranchSuggestions(results, branchSuggestions(this.value));
                });

                searchInput.addEventListener('blur', function () {
                    window.setTimeout(() => {
                        const exact = findExactBranch(searchInput.value);

                        if (exact) {
                            selectBranch(picker, exact);
                            return;
                        }

                        if (!searchInput.value.trim()) {
                            selectBranch(picker, null);
                            return;
                        }

                        results.style.display = 'none';
                    }, 150);
                });

                results.addEventListener('click', function (event) {
                    const button = event.target.closest('[data-branch-id]');

                    if (!button) {
                        return;
                    }

                    const option = branchOptions.find((item) => item.id === button.dataset.branchId) || null;
                    selectBranch(picker, option);
                });

                form?.addEventListener('submit', function (event) {
                    const exact = findExactBranch(searchInput.value);

                    if (exact && !hiddenInput.value) {
                        selectBranch(picker, exact);
                    }

                    if (searchInput.value.trim() && !hiddenInput.value) {
                        event.preventDefault();
                        searchInput.setCustomValidity('Please select a valid branch from the suggestions.');
                        searchInput.reportValidity();
                    }
                });
            });

            document.addEventListener('click', function (event) {
                if (event.target.closest('[data-branch-picker]')) {
                    return;
                }

                document.querySelectorAll('[data-branch-results]').forEach((results) => {
                    results.style.display = 'none';
                });
            });

            async function copyText(value) {
                if (!value) {
                    return false;
                }

                if (navigator.clipboard) {
                    try {
                        await navigator.clipboard.writeText(value);
                        return true;
                    } catch (error) {
                    }
                }

                const textArea = document.createElement('textarea');
                textArea.value = value;
                textArea.setAttribute('readonly', '');
                textArea.style.position = 'fixed';
                textArea.style.top = '0';
                textArea.style.left = '-9999px';
                textArea.style.opacity = '0';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                textArea.setSelectionRange(0, textArea.value.length);

                let copied = false;

                try {
                    copied = document.execCommand('copy');
                } catch (error) {
                    copied = false;
                }

                document.body.removeChild(textArea);

                return copied;
            }

            document.addEventListener('click', async function (event) {
                const button = event.target.closest('.js-copy-link');

                if (!button) {
                    return;
                }

                const link = button.dataset.link || '';
                const originalText = button.dataset.originalText || button.textContent;
                button.dataset.originalText = originalText;

                if (!link) {
                    return;
                }

                try {
                    const copied = await copyText(link);

                    if (!copied) {
                        throw new Error('Copy failed');
                    }

                    button.textContent = 'Copied';
                    setTimeout(() => {
                        button.textContent = originalText;
                    }, 1500);
                } catch (error) {
                    window.prompt('Copy this link', link);
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
                });
            });

            document.addEventListener('click', function (event) {
                const removeButton = event.target.closest('.js-remove-row');

                if (!removeButton) {
                    return;
                }

                removeButton.closest('tr')?.remove();
            });

            document.querySelectorAll('.js-whatsapp-action-form').forEach(form => {
                form.addEventListener('submit', async function (event) {
                    event.preventDefault();

                    const phone = this.dataset.phone || '';
                    const link = this.dataset.link || '';
                    const messagePrefix = this.dataset.message || 'Please complete the form.';
                    const externalButton = document.querySelector(`[form="${this.id}"]`);
                    const button = this.querySelector('button[type="submit"]') || externalButton;
                    const originalText = button ? button.textContent : '';
                    const csrfToken = this.querySelector('input[name="_token"]')?.value || '';
                    const requestUrl = this.getAttribute('action') || '';

                    if (!phone || !link) {
                        window.alert('WhatsApp number or onboarding link is missing for this candidate.');
                        return;
                    }

                    if (button) {
                        button.disabled = true;
                        button.textContent = 'Sending...';
                    }

                    try {
                        const response = await fetch(requestUrl, {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                            },
                            body: new FormData(this),
                            credentials: 'same-origin',
                        });

                        let payload = null;

                        try {
                            payload = await response.clone().json();
                        } catch (jsonError) {
                            payload = null;
                        }

                        if (!response.ok) {
                            throw new Error(payload?.message || 'Unable to update candidate status.');
                        }

                        const shareUrl = payload?.share_url || link;
                        const message = encodeURIComponent(`${messagePrefix}\n${shareUrl}`);
                        window.open(`https://wa.me/${phone}?text=${message}`, '_blank');
                        window.location.reload();
                    } catch (error) {
                        window.alert(error.message || 'Unable to send WhatsApp message.');
                    } finally {
                        if (button) {
                            button.disabled = false;
                            button.textContent = originalText;
                        }
                    }
                });
            });

            const joiningOnboardedForm = document.getElementById('joining-onboarded-form');

            joiningOnboardedForm?.addEventListener('submit', function (event) {
                const dateOfJoiningInput = document.querySelector('[data-onboarding-assignment="date_of_joining"]');
                const appointedDesignationInput = document.querySelector('[data-onboarding-assignment="appointed_designation"]');
                const branchSearchInput = document.querySelector('[data-branch-search]');
                const branchValueInput = document.querySelector('[data-onboarding-assignment="deployed_branch_id"]');
                const shiftTimingInput = document.querySelector('[data-onboarding-assignment="shift_timing"]');
                const fixedSalaryInput = document.querySelector('[data-onboarding-assignment="fixed_salary"]');
                const exactBranch = findExactBranch(branchSearchInput?.value || '');

                if (exactBranch && branchValueInput && !branchValueInput.value) {
                    branchValueInput.value = exactBranch.id;
                    branchSearchInput.value = exactBranch.label;
                }

                const requiredInputs = [
                    dateOfJoiningInput,
                    appointedDesignationInput,
                    shiftTimingInput,
                    fixedSalaryInput,
                ];

                const firstInvalidInput = requiredInputs.find((input) => {
                    return input && !String(input.value || '').trim();
                });

                if (firstInvalidInput) {
                    event.preventDefault();
                    firstInvalidInput.setCustomValidity('This field is required before marking candidate as onboarded.');
                    firstInvalidInput.reportValidity();
                    firstInvalidInput.addEventListener('input', function clearValidity() {
                        firstInvalidInput.setCustomValidity('');
                        firstInvalidInput.removeEventListener('input', clearValidity);
                    });
                    return;
                }

                if ((branchSearchInput?.value || '').trim() !== '' && !(branchValueInput?.value || '').trim()) {
                    event.preventDefault();
                    branchSearchInput?.setCustomValidity('Please select a valid branch from the suggestions.');
                    branchSearchInput?.reportValidity();
                    return;
                }

                if (branchSearchInput && !(branchValueInput?.value || '').trim()) {
                    event.preventDefault();
                    branchSearchInput.setCustomValidity('Please select a valid branch from the suggestions.');
                    branchSearchInput.reportValidity();
                    return;
                }

                this.querySelector('input[name="date_of_joining"]').value = dateOfJoiningInput?.value || '';
                this.querySelector('input[name="appointed_designation"]').value = appointedDesignationInput?.value || '';
                this.querySelector('input[name="deployed_branch_id"]').value = branchValueInput?.value || '';
                this.querySelector('input[name="shift_timing"]').value = shiftTimingInput?.value || '';
                this.querySelector('input[name="fixed_salary"]').value = fixedSalaryInput?.value || '';
            });
        });
    </script>
@endsection
