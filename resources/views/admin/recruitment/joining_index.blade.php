@extends('admin.layout.app')

@section('content')
    @php
        $reopenOnboardedCandidateId = old('_candidate_id');
    @endphp
    <style>
        .recruitment-action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(88px, 1fr));
            gap: 0.3rem;
            min-width: 190px;
        }

        .recruitment-action-grid > form,
        .recruitment-action-grid > a,
        .recruitment-action-grid > button {
            margin: 0;
        }

        .recruitment-action-btn {
            width: 100%;
            min-height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
            padding: 0.2rem 0.45rem;
            font-size: 0.68rem;
            font-weight: 600;
            line-height: 1.05;
            white-space: nowrap;
        }

        .recruitment-action-btn i {
            font-size: 0.9rem;
            line-height: 1;
        }
    </style>
    <div class="main-content">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Recruitment</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin-dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Joining</li>
                    </ol>
                </nav>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success border-0">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger border-0">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card rounded-4">
            <div class="card-body">
                <div class="mb-3">
                    <h4 class="mb-1">Joining Queue</h4>
                    <p class="mb-0 text-muted">Candidates selected by hiring appear here. The onboarding message is sent directly to the candidate’s stored WhatsApp number.</p>
                </div>

                <form method="get" action="{{ route('admin-joining-index') }}" class="row g-3 align-items-end mb-4">
                    <div class="col-md-3">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            @foreach ($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="{{ route('admin-joining-index') }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle" data-admin-datatable="true" data-admin-scroll-x="false">
                        <thead>
                            <tr>
                                <th>Submission ID</th>
                                <th>Employee ID</th>
                                <th>Joining Date</th>
                                <th>Candidate</th>
                                <th>Position</th>
                                <th>State</th>
                                <th>Contact</th>
                                <th>WhatsApp</th>
                                <th>Resume</th>
                                <th>Fixed Salary</th>
                                <th>Hiring User</th>
                                <th>Joining User</th>
                                <th>HR Manager</th>
                                <th>Status</th>
                                <th>Onboarding Link</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($candidates as $candidate)
                                @php
                                    $onboardingPayload = $candidate->onboarding_payload ?? [];
                                    $preferredWorkState = trim((string) (
                                        data_get($candidate->hiring_payload, 'preferred_work_location_state')
                                        ?: data_get($candidate->hiring_payload, 'preferred_work_location_branch_state')
                                    ));
                                    $joiningUrl = route('recruitment-onboarding-form-show', $candidate->public_token);
                                    $joiningUpdateUrl = $candidate->joining_update_token ? route('recruitment-onboarding-update-link', $candidate->joining_update_token) : $joiningUrl;
                                    $status = $candidate->status;
                                    $canMarkOnboarded = in_array($status, [
                                        \App\Models\RecruitmentCandidate::STATUS_HIRING_SELECTED,
                                        \App\Models\RecruitmentCandidate::STATUS_JOINING_FORM_SHARED,
                                        \App\Models\RecruitmentCandidate::STATUS_JOINING_SUBMITTED,
                                        \App\Models\RecruitmentCandidate::STATUS_JOINING_HOLD,
                                        \App\Models\RecruitmentCandidate::STATUS_JOINING_REJECTED,
                                        \App\Models\RecruitmentCandidate::STATUS_JOINING_UPDATE_REQUESTED,
                                    ], true);
                                    $hasJoiningLink = in_array($status, [
                                        \App\Models\RecruitmentCandidate::STATUS_JOINING_FORM_SHARED,
                                        \App\Models\RecruitmentCandidate::STATUS_JOINING_SUBMITTED,
                                        \App\Models\RecruitmentCandidate::STATUS_JOINING_HOLD,
                                        \App\Models\RecruitmentCandidate::STATUS_JOINING_REJECTED,
                                        \App\Models\RecruitmentCandidate::STATUS_JOINING_UPDATE_REQUESTED,
                                        \App\Models\RecruitmentCandidate::STATUS_ONBOARDED,
                                        \App\Models\RecruitmentCandidate::STATUS_JOINED,
                                    ], true);
                                    $whatsappTarget = $candidate->whatsappTarget();
                                @endphp
                                <tr>
                                    <td>{{ $candidate->submission_code ?: '--' }}</td>
                                    <td>{{ $candidate->generated_emp_id ?: '--' }}</td>
                                    <td>{{ $candidate->display_joining_date }}</td>
                                    <td>{{ $candidate->candidate_name ?: '--' }}</td>
                                    <td>{{ $candidate->position_applied_for ?: '--' }}</td>
                                    <td>{{ $preferredWorkState !== '' ? $preferredWorkState : '--' }}</td>
                                    <td>{{ $candidate->contact_number ?: '--' }}</td>
                                    <td>{{ $candidate->whatsapp_number ?: '--' }}</td>
                                    <td>
                                        @if ($candidate->resume_file_path)
                                            <a href="{{ \App\Support\ProjectAsset::url($candidate->resume_file_path) }}" target="_blank">View Resume</a>
                                        @else
                                            <span class="text-muted">--</span>
                                        @endif
                                    </td>
                                    <td>{{ $candidate->fixed_salary ?: '--' }}</td>
                                    <td>{{ $candidate->display_hiring_admin_name }}</td>
                                    <td>{{ $candidate->display_joining_admin_name }}</td>
                                    <td>{{ $candidate->display_hr_admin_name }}</td>
                                    <td>
                                        @if ($status === \App\Models\RecruitmentCandidate::STATUS_HIRING_SELECTED)
                                            <span class="badge bg-primary text-light">Selected</span>
                                        @elseif ($status === \App\Models\RecruitmentCandidate::STATUS_JOINING_FORM_SHARED)
                                            <span class="badge bg-info text-light ">Form Shared</span>
                                        @elseif ($status === \App\Models\RecruitmentCandidate::STATUS_JOINING_SUBMITTED)
                                            <span class="badge bg-primary text-light">Submitted</span>
                                        @elseif ($status === \App\Models\RecruitmentCandidate::STATUS_JOINING_HOLD)
                                            <span class="badge bg-warning text-light">Hold</span>
                                        @elseif ($status === \App\Models\RecruitmentCandidate::STATUS_JOINING_REJECTED)
                                            <span class="badge bg-danger text-light">Rejected</span>
                                        @elseif ($status === \App\Models\RecruitmentCandidate::STATUS_JOINING_UPDATE_REQUESTED)
                                            <span class="badge bg-secondary text-light">Update Requested</span>
                                        @elseif ($status === \App\Models\RecruitmentCandidate::STATUS_ONBOARDED)
                                            <span class="badge bg-success text-light">Onboarded ({{ $candidate->generated_emp_id }})</span>
                                        @elseif ($status === \App\Models\RecruitmentCandidate::STATUS_JOINED)
                                            <span class="badge bg-light">Joined</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($hasJoiningLink)
                                            <a href="{{ $joiningUrl }}" target="_blank">{{ $joiningUrl }}</a>
                                        @else
                                            <span class="text-muted">Send onboarding form first</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="recruitment-action-grid">
                                            @if (in_array($status, [
                                                \App\Models\RecruitmentCandidate::STATUS_HIRING_SELECTED,
                                                \App\Models\RecruitmentCandidate::STATUS_JOINING_FORM_SHARED,
                                            ], true) && $whatsappTarget)
                                                <form method="post" action="{{ route('admin-joining-start', $candidate->id) }}" class="js-whatsapp-action-form" data-phone="{{ $whatsappTarget }}" data-link="{{ $joiningUrl }}" data-message="Congratulations. You have been selected. Please complete the onboarding form so we can proceed with your onboarding.">
                                                    @csrf
                                                    <button type="submit" class="btn btn-primary btn-sm recruitment-action-btn">
                                                        <i class="bx bx-send"></i>
                                                        <span>Send Link</span>
                                                    </button>
                                                </form>
                                            @endif

                                            <a href="{{ route('admin-joining-form', $candidate->id) }}" class="btn btn-outline-secondary btn-sm recruitment-action-btn">
                                                <i class="bx bx-show-alt"></i>
                                                <span>Details</span>
                                            </a>

                                            @if ($canMarkOnboarded)
                                                <button type="button" class="btn btn-success btn-sm recruitment-action-btn" data-bs-toggle="modal"
                                                    data-bs-target="#markOnboardedModal{{ $candidate->id }}">
                                                    <i class="bx bx-badge-check"></i>
                                                    <span>Onboard</span>
                                                </button>
                                            @endif

                                            @if (in_array($status, [
                                                \App\Models\RecruitmentCandidate::STATUS_JOINING_SUBMITTED,
                                                \App\Models\RecruitmentCandidate::STATUS_JOINING_HOLD,
                                                \App\Models\RecruitmentCandidate::STATUS_JOINING_UPDATE_REQUESTED,
                                            ], true))
                                                <form method="post" action="{{ route('admin-joining-decision', $candidate->id) }}">
                                                    @csrf
                                                    <input type="hidden" name="action" value="hold">
                                                    <button type="submit" class="btn btn-warning btn-sm recruitment-action-btn">
                                                        <i class="bx bx-pause"></i>
                                                        <span>Hold</span>
                                                    </button>
                                                </form>
                                                <form method="post" action="{{ route('admin-joining-decision', $candidate->id) }}">
                                                    @csrf
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="btn btn-danger btn-sm recruitment-action-btn">
                                                        <i class="bx bx-x"></i>
                                                        <span>Reject</span>
                                                    </button>
                                                </form>
                                            @endif

                                            @if ($whatsappTarget && in_array($status, [
                                                \App\Models\RecruitmentCandidate::STATUS_JOINING_SUBMITTED,
                                                \App\Models\RecruitmentCandidate::STATUS_JOINING_HOLD,
                                                \App\Models\RecruitmentCandidate::STATUS_JOINING_REJECTED,
                                            ], true))
                                                <form method="post" action="{{ route('admin-joining-decision', $candidate->id) }}" class="js-whatsapp-action-form" data-phone="{{ $whatsappTarget }}" data-link="{{ $joiningUpdateUrl }}" data-message="Congratulations. You have been selected. Please review and complete the onboarding form so we can proceed with your onboarding.">
                                                    @csrf
                                                    <input type="hidden" name="action" value="resend">
                                                    <button type="submit" class="btn btn-outline-dark btn-sm recruitment-action-btn">
                                                        <i class="bx bx-refresh"></i>
                                                        <span>Resend</span>
                                                    </button>
                                                </form>
                                            @endif

                                            @if ($status === \App\Models\RecruitmentCandidate::STATUS_JOINING_UPDATE_REQUESTED && $candidate->joining_update_token)
                                                <button type="button" class="btn btn-outline-primary btn-sm recruitment-action-btn js-copy-link" data-link="{{ $joiningUpdateUrl }}">
                                                    <i class="bx bx-copy"></i>
                                                    <span>Copy Link</span>
                                                </button>
                                            @endif

                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @foreach ($candidates as $candidate)
            @php
                $onboardingPayload = $candidate->onboarding_payload ?? [];
                $selectedBranchId = old('_candidate_id') == $candidate->id
                    ? old('deployed_branch_id', data_get($onboardingPayload, 'deployed_branch_id'))
                    : data_get($onboardingPayload, 'deployed_branch_id');
            @endphp
            <div class="modal fade" id="markOnboardedModal{{ $candidate->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form method="post" action="{{ route('admin-joining-decision', $candidate->id) }}">
                            @csrf
                            <input type="hidden" name="_candidate_id" value="{{ $candidate->id }}">
                            <input type="hidden" name="action" value="onboarded">
                            <div class="modal-header">
                                <h5 class="modal-title">Mark {{ $candidate->candidate_name }} Onboarded</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Date of Joining</label>
                                    <input type="date" name="date_of_joining" class="form-control"
                                        value="{{ old('_candidate_id') == $candidate->id ? old('date_of_joining', data_get($onboardingPayload, 'date_of_joining')) : data_get($onboardingPayload, 'date_of_joining') }}"
                                        required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Appointed Designation</label>
                                    <input type="text" name="appointed_designation" class="form-control"
                                        value="{{ old('_candidate_id') == $candidate->id ? old('appointed_designation', data_get($onboardingPayload, 'appointed_designation') ?: $candidate->position_applied_for) : (data_get($onboardingPayload, 'appointed_designation') ?: $candidate->position_applied_for) }}"
                                        required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Deployed Branch Name</label>
                                    <select name="deployed_branch_id" class="form-select" required>
                                        <option value="">Select Branch</option>
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->branchId }}" @selected($selectedBranchId === $branch->branchId)>
                                                {{ $branch->branchId }} - {{ $branch->branchName }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Shift Timing</label>
                                    <input type="text" name="shift_timing" class="form-control"
                                        value="{{ old('_candidate_id') == $candidate->id ? old('shift_timing', data_get($onboardingPayload, 'shift_timing')) : data_get($onboardingPayload, 'shift_timing') }}"
                                        placeholder="10:00 AM - 7:00 PM" required>
                                </div>
                                <div class="mb-0">
                                    <label class="form-label">Fixed Salary</label>
                                    <input type="number" step="0.01" min="0" name="fixed_salary" class="form-control"
                                        value="{{ old('_candidate_id') == $candidate->id ? old('fixed_salary', $candidate->fixed_salary ?: data_get($onboardingPayload, 'fixed_salary')) : ($candidate->fixed_salary ?: data_get($onboardingPayload, 'fixed_salary')) }}"
                                        placeholder="Enter fixed salary" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-primary">Confirm Onboarded</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
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
                                'X-CSRF-TOKEN': this.querySelector('input[name="_token"]')?.value || '',
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

            const reopenCandidateId = @json($reopenOnboardedCandidateId);

            if (reopenCandidateId && window.bootstrap) {
                const modalElement = document.getElementById(`markOnboardedModal${reopenCandidateId}`);

                if (modalElement) {
                    window.setTimeout(() => {
                        window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
                    }, 100);
                }
            }
        });
    </script>
@endsection
