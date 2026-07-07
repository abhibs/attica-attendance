@extends('admin.layout.app')

@section('content')
    @php
        $payload = $candidate->hiring_payload ?? [];
        $qualifications = data_get($payload, 'qualifications', []);
        $workExperiences = data_get($payload, 'work_experiences', []);
        $references = data_get($payload, 'references', []);
        $interviewPayload = $candidate->interview_payload ?? [];
        $interviewQuestions = data_get($interviewPayload, 'questions', []);
        $resendUrl = $candidate->formLink ? route('recruitment-hiring-form-show', ['token' => $candidate->formLink->public_token, 'resubmission' => $candidate->submission_code]) : null;
        $resendUpdateUrl = $candidate->hiring_update_token ? route('recruitment-hiring-update-link', $candidate->hiring_update_token) : $resendUrl;
        $whatsappTarget = $candidate->whatsappTarget();
        $requiresVideoInterview = $candidate->formLink?->requiresVideoInterview() ?? true;
    @endphp

    <div class="main-content">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Recruitment</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin-dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin-hiring-index') }}">Hiring</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Complete Details</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="card rounded-4 mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                    <div>
                        <h4 class="mb-1">{{ $candidate->candidate_name ?: 'Candidate Details' }}</h4>
                        <p class="mb-1 text-muted">{{ $candidate->position_applied_for ?: '--' }}</p>
                        <div class="small text-muted">Submission ID: {{ $candidate->submission_code ?: '--' }}</div>
                        @if ($candidate->resubmissionOf?->submission_code)
                            <div class="small text-muted">Resubmission of: {{ $candidate->resubmissionOf->submission_code }}</div>
                        @endif
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @if ($candidate->status === \App\Models\RecruitmentCandidate::STATUS_HIRING_SUBMITTED)
                            <form method="post" action="{{ route('admin-hiring-decision', $candidate->id) }}">
                                @csrf
                                <input type="hidden" name="action" value="select">
                                <button type="submit" class="btn btn-success btn-sm">Select</button>
                            </form>
                        @endif
                        @if ($candidate->status === \App\Models\RecruitmentCandidate::STATUS_HIRING_UPDATE_REQUESTED && $candidate->hiring_update_token)
                            <button type="button" class="btn btn-outline-primary btn-sm js-copy-link" data-link="{{ $resendUpdateUrl }}">Copy Update Link</button>
                        @endif
                        @if ($resendUrl && $whatsappTarget && in_array($candidate->status, [
                            \App\Models\RecruitmentCandidate::STATUS_HIRING_SUBMITTED,
                            \App\Models\RecruitmentCandidate::STATUS_HIRING_HOLD,
                            \App\Models\RecruitmentCandidate::STATUS_HIRING_REJECTED,
                            \App\Models\RecruitmentCandidate::STATUS_HIRING_SELECTED,
                        ], true))
                            <form method="post" action="{{ route('admin-hiring-decision', $candidate->id) }}" class="js-whatsapp-action-form" data-phone="{{ $whatsappTarget }}" data-link="{{ $resendUpdateUrl }}" data-message="Please update your Attica hiring form.">
                                @csrf
                                <input type="hidden" name="action" value="resend">
                                <button type="submit" class="btn btn-outline-dark btn-sm">Resend Form</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card rounded-4 mb-4">
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="row g-3">
                            <div class="col-md-6"><strong>Hiring Date:</strong> {{ data_get($payload, 'hiring_date') ?: '--' }}</div>
                            <div class="col-md-6"><strong>Candidate Name:</strong> {{ $candidate->candidate_name ?: '--' }}</div>
                            <div class="col-md-6"><strong>Position:</strong> {{ $candidate->position_applied_for ?: '--' }}</div>
                            <div class="col-md-6"><strong>Date of Birth:</strong> {{ data_get($payload, 'date_of_birth') ?: '--' }}</div>
                            <div class="col-md-6"><strong>Gender:</strong> {{ data_get($payload, 'gender') ?: '--' }}</div>
                            <div class="col-md-6"><strong>Marital Status:</strong> {{ data_get($payload, 'marital_status') ?: '--' }}</div>
                            <div class="col-md-6"><strong>Contact Number:</strong> {{ $candidate->contact_number ?: '--' }}</div>
                            <div class="col-md-6"><strong>WhatsApp Number:</strong> {{ $candidate->whatsapp_number ?: '--' }}</div>
                            <div class="col-md-6"><strong>Aadhaar Number:</strong> {{ $candidate->aadhaar_number ?: data_get($payload, 'aadhaar_number') ?: '--' }}</div>
                            <div class="col-md-6"><strong>Email:</strong> {{ $candidate->email ?: '--' }}</div>
                            <div class="col-md-6"><strong>Resume:</strong>
                                @if ($candidate->resume_file_path)
                                    <a href="{{ asset('public/'.$candidate->resume_file_path) }}" target="_blank">View / Download</a>
                                @else
                                    --
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        @if ($candidate->candidate_photo_path)
                            <img src="{{ asset('public/'.$candidate->candidate_photo_path) }}" alt="{{ $candidate->candidate_name }}" class="img-fluid rounded-4 border">
                        @else
                            <div class="border rounded-4 h-100 d-flex align-items-center justify-content-center text-muted bg-light p-4">No photo captured</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card rounded-4 mb-4">
            <div class="card-body">
                <h5 class="mb-3">Addresses & Additional Information</h5>
                <div class="row g-4">
                    <div class="col-md-6">
                        <p class="mb-2"><strong>Current Address:</strong><br>{{ data_get($payload, 'current_address') ?: '--' }}</p>
                        <p class="mb-0"><strong>Permanent Address:</strong><br>{{ data_get($payload, 'permanent_address') ?: '--' }}</p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-2"><strong>Physical Fitness:</strong> {{ data_get($payload, 'physical_fitness') === null ? '--' : (data_get($payload, 'physical_fitness') ? 'Yes' : 'No') }}</p>
                        <p class="mb-2"><strong>If No, Reason:</strong> {{ data_get($payload, 'physical_fitness_reason') ?: '--' }}</p>
                        <p class="mb-2"><strong>Own Two Wheeler:</strong> {{ data_get($payload, 'own_two_wheeler') === null ? '--' : (data_get($payload, 'own_two_wheeler') ? 'Yes' : 'No') }}</p>
                        <p class="mb-0"><strong>How do you know Attica:</strong> {{ data_get($payload, 'know_attica') ?: '--' }}</p>
                    </div>
                </div>
            </div>
        </div>

        @if ($duplicates->isNotEmpty())
            <div class="card rounded-4 mb-4" id="duplicate-submissions">
                <div class="card-body">
                    <h5 class="mb-3">Duplicate Aadhaar Submissions</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>Submission ID</th>
                                    <th>Candidate</th>
                                    <th>Position</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($duplicates as $duplicate)
                                    <tr>
                                        <td>{{ $duplicate->submission_code ?: '--' }}</td>
                                        <td>{{ $duplicate->candidate_name ?: '--' }}</td>
                                        <td>{{ $duplicate->position_applied_for ?: '--' }}</td>
                                        <td>{{ ucfirst(str_replace('_', ' ', $duplicate->status ?: '--')) }}</td>
                                        <td>{{ optional($duplicate->created_at)->format('Y-m-d H:i') ?: '--' }}</td>
                                        <td>
                                            <a href="{{ route('admin-hiring-show', $duplicate->id) }}" class="btn btn-outline-secondary btn-sm">View Form</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        @if ($requiresVideoInterview)
            <div class="card rounded-4 mb-4">
                <div class="card-body">
                    <h5 class="mb-3">Video Interview</h5>
                    <div class="row g-4">
                        <div class="col-lg-7">
                            @if ($candidate->interview_video_path)
                                <video controls preload="metadata" class="w-100 rounded-4 border" src="{{ asset('public/'.$candidate->interview_video_path) }}"></video>
                            @else
                                <div class="border rounded-4 bg-light text-muted p-4">No interview video uploaded.</div>
                            @endif
                        </div>
                        <div class="col-lg-5">
                            <p class="mb-2"><strong>Recorded Position:</strong> {{ data_get($interviewPayload, 'position') ?: $candidate->position_applied_for ?: '--' }}</p>
                            <p class="mb-2"><strong>Questions Asked:</strong> {{ count($interviewQuestions) ?: 0 }}</p>
                            <p class="mb-3"><strong>Per Question Timer:</strong> {{ data_get($interviewPayload, 'duration_seconds') ?: '--' }} seconds</p>
                            <div class="border rounded-3 p-3 bg-light">
                                <h6 class="mb-2">Question List</h6>
                                <ol class="mb-0 ps-3">
                                    @forelse ($interviewQuestions as $question)
                                        <li>{{ $question }}</li>
                                    @empty
                                        <li class="text-muted">No question metadata available.</li>
                                    @endforelse
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="card rounded-4 mb-4">
                <div class="card-body">
                    <h5 class="mb-2">Walk-In Form</h5>
                    <p class="mb-0 text-muted">This submission was collected through a walk-in form, so no video interview section was required.</p>
                </div>
            </div>
        @endif

        <div class="card rounded-4 mb-4">
            <div class="card-body">
                <h5 class="mb-3">Professional Qualification</h5>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>Examination</th>
                                <th>University</th>
                                <th>Main Subject</th>
                                <th>Year of Passing</th>
                                <th>Percentage Obtained</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($qualifications as $row)
                                <tr>
                                    <td>{{ $row['examination'] ?: '--' }}</td>
                                    <td>{{ $row['university'] ?: '--' }}</td>
                                    <td>{{ $row['main_subject'] ?: '--' }}</td>
                                    <td>{{ $row['year_of_passing'] ?: '--' }}</td>
                                    <td>{{ $row['percentage_obtained'] ?: '--' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">No qualification rows added.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card rounded-4">
            <div class="card-body">
                <h5 class="mb-3">Skills, Compensation & References</h5>
                <div class="row g-4 mb-4">
                    <div class="col-lg-6">
                        <p><strong>Computer Knowledge:</strong><br>{{ data_get($payload, 'computer_knowledge') ?: '--' }}</p>
                        <p><strong>Languages - Speak:</strong><br>{{ data_get($payload, 'languages_speak') ?: '--' }}</p>
                        <p><strong>Languages - Read:</strong><br>{{ data_get($payload, 'languages_read') ?: '--' }}</p>
                        <p class="mb-0"><strong>Languages - Write:</strong><br>{{ data_get($payload, 'languages_write') ?: '--' }}</p>
                    </div>
                    <div class="col-lg-6">
                        <p><strong>Present Remuneration:</strong> {{ data_get($payload, 'present_remuneration') ?: '--' }}</p>
                        <p><strong>Salary Expectation:</strong> {{ data_get($payload, 'salary_expectation') ?: '--' }}</p>
                        <p><strong>Notice Period:</strong> {{ data_get($payload, 'notice_period') ?: '--' }}</p>
                        <p><strong>Preferred Work Location:</strong> {{ data_get($payload, 'preferred_work_location_label') ?: '--' }}</p>
                        <p><strong>Place:</strong> {{ data_get($payload, 'place') ?: '--' }}</p>
                        <p class="mb-0"><strong>Signature:</strong> {{ data_get($payload, 'signature') ?: '--' }}</p>
                    </div>
                </div>

                <h6>Work Experience</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>Company Name</th>
                                <th>Designation</th>
                                <th>Experience</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($workExperiences as $row)
                                <tr>
                                    <td>{{ $row['company_name'] ?: '--' }}</td>
                                    <td>{{ $row['designation'] ?: '--' }}</td>
                                    <td>{{ $row['experience'] ?: '--' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted">No work experience rows added.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <h6>References</h6>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Contact Number</th>
                                <th>Designation</th>
                                <th>Relationship</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($references as $row)
                                <tr>
                                    <td>{{ $row['name'] ?: '--' }}</td>
                                    <td>{{ $row['contact_number'] ?: '--' }}</td>
                                    <td>{{ $row['designation'] ?: '--' }}</td>
                                    <td>{{ $row['relationship'] ?: '--' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">No reference rows added.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
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

            document.querySelectorAll('.js-whatsapp-action-form').forEach(form => {
                form.addEventListener('submit', async function (event) {
                    event.preventDefault();

                    const phone = this.dataset.phone || '';
                    const link = this.dataset.link || '';
                    const messagePrefix = this.dataset.message || 'Please complete the form.';
                    const button = this.querySelector('button[type="submit"]');
                    const originalText = button ? button.textContent : '';
                    const csrfToken = this.querySelector('input[name="_token"]')?.value || '';
                    const requestUrl = this.getAttribute('action') || '';

                    if (!phone || !link) {
                        window.alert('WhatsApp number or form link is missing for this candidate.');
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
        });
    </script>
@endsection
