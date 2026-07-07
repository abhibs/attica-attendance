@php
    $payload = old() ?: $defaults;
    $workExperiences = old('work_experiences', data_get($defaults, 'work_experiences', [['company_name' => '', 'designation' => '', 'experience' => '']]));
    $references = old('references', data_get($defaults, 'references', [['name' => '', 'contact_number' => '', 'designation' => '', 'relationship' => '']]));
    $documentChecklist = old('document_checklist', data_get($defaults, 'document_checklist', []));
    $documentPhotoPaths = $candidate->document_photo_paths ?? [];
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attica Joining Form</title>
    <link href="{{ asset('public/admin/assets/css/bootstrap.min.css') }}" rel="stylesheet">
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
    </style>
</head>
<body>
    <div class="container page-shell">
        <div class="brand-header mb-4">
            <div class="d-flex align-items-center gap-3">
                <div class="brand-mark">
                    <img src="{{ asset('public/admin/assets/images/logo-icon.png') }}" alt="Attica Gold logo">
                </div>
                <div>
                    <div class="brand-kicker">Attica Gold Company</div>
                    <h1 class="h4 mb-0">Attica Joining Form</h1>
                </div>
            </div>
            <div class="text-end small opacity-75">
                <div>Candidate Joining Portal</div>
                <div>{{ $candidate->position_applied_for ?: '--' }}</div>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
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

        @if ($isReadOnly)
            <div class="alert alert-info">This onboarding form is currently locked for editing. Please contact the joining team if you need an update request.</div>
        @endif

        <form method="post" action="{{ route('recruitment-onboarding-form-submit', $candidate->public_token) }}" class="d-flex flex-column gap-4">
            @csrf
            <fieldset {{ $isReadOnly ? 'disabled' : '' }}>
                <div class="card page-card">
                    <div class="card-body p-4">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
                            <div>
                                <h2 class="mb-1">Attica Joining Form</h2>
                                <p class="mb-0 text-muted">Please complete the onboarding details and submit them to the joining team.</p>
                            </div>
                            <div class="text-lg-end">
                                <div class="fw-semibold">{{ $candidate->candidate_name }}</div>
                                <div class="text-muted">{{ $candidate->position_applied_for ?: '--' }}</div>
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
                                    'showPreview' => true,
                                ])
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card page-card">
                    <div class="card-body p-4">
                        <h4 class="mb-3">Contact & Address</h4>
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

                <div class="card page-card">
                    <div class="card-body p-4">
                        <h4 class="mb-3">Employment Details in Attica</h4>
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

                <div class="card page-card">
                    <div class="card-body p-4">
                        <h4 class="mb-3">Previous Company Details</h4>
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

                <div class="card page-card">
                    <div class="card-body p-4">
                        <h4 class="mb-3">Additional Employment Information</h4>
                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <label class="form-label">Computer Knowledge</label>
                                <textarea name="computer_knowledge" class="form-control" rows="3">{{ old('computer_knowledge', data_get($payload, 'computer_knowledge')) }}</textarea>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Work Experience</h5>
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

                <div class="card page-card">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="mb-0">References & Emergency Contacts</h4>
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

                <div class="card page-card">
                    <div class="card-body p-4">
                        <h4 class="mb-3">Document Submission Checklist</h4>
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
                                            'showPreview' => true,
                                        ])
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="card page-card">
                    <div class="card-body p-4">
                        <h4 class="mb-3">Final Declaration</h4>
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
            </fieldset>

            @unless ($isReadOnly)
                <div class="d-flex justify-content-end pb-4">
                    <button type="submit" class="btn btn-primary px-5">Save Onboarding Form</button>
                </div>
            @endunless
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

    <script src="{{ asset('public/admin/assets/js/bootstrap.bundle.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.addEventListener('input', function (event) {
                const input = event.target.closest('[data-digit-only]');

                if (!input) {
                    return;
                }

                const maxLength = Number.parseInt(input.dataset.digitOnly || '0', 10);
                const digits = input.value.replace(/\D+/g, '');
                input.value = maxLength > 0 ? digits.slice(0, maxLength) : digits;
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
        });
    </script>
</body>
</html>