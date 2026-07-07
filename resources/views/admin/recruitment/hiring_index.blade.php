@extends('admin.layout.app')

@section('content')
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
                        <li class="breadcrumb-item active" aria-current="page">Hiring</li>
                    </ol>
                </nav>
            </div>
            <div class="ms-auto">
                <a href="{{ route('admin-hiring-create') }}" class="btn btn-primary">Create Static Hiring Form</a>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success border-0">{{ session('status') }}</div>
        @endif

        <div class="card rounded-4 mb-4">
            <div class="card-body">
                <div class="mb-3">
                    <h4 class="mb-1">Static Hiring Links</h4>
                    <p class="mb-0 text-muted">Each link is reusable. Share the same link with hundreds of candidates. Every submission will create a separate candidate row with its own unique submission ID.</p>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Form</th>
                                <th>Hiring Date</th>
                                <th>Positions</th>
                                <th>Created By</th>
                                <th>Submissions</th>
                                <th>Link</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($formLinks as $formLink)
                                @php
                                    $hiringUrl = route('recruitment-hiring-form-show', $formLink->public_token);
                                    $formTypeLabel = $formLink->isWalkInForm() ? 'Walk-In' : 'Standard';
                                    $shareMessage = $formLink->isWalkInForm()
                                        ? 'Please complete your Attica walk-in form.'
                                        : 'Please complete your Attica hiring form.';
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $formLink->title }}</div>
                                        <div class="small text-muted">{{ $formTypeLabel }}</div>
                                    </td>
                                    <td>{{ optional($formLink->hiring_date)->toDateString() ?: '--' }}</td>
                                    <td>
                                        @if ($formLink->positionOptions() !== [])
                                            {{ implode(', ', $formLink->positionOptions()) }}
                                        @else
                                            <span class="text-muted">Any position</span>
                                        @endif
                                    </td>
                                    <td>{{ $formLink->createdByAdmin?->name ?: '--' }}</td>
                                    <td>{{ $formLink->submissions_count }}</td>
                                    <td><a href="{{ $hiringUrl }}" target="_blank">{{ $hiringUrl }}</a></td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            <button type="button" class="btn btn-outline-primary btn-sm js-copy-link" data-link="{{ $hiringUrl }}">Copy Link</button>
                                            <button type="button" class="btn btn-outline-success btn-sm js-share-whatsapp" data-link="{{ $hiringUrl }}" data-message="{{ $shareMessage }}">Share on WhatsApp</button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No static hiring links created yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card rounded-4">
            <div class="card-body">
                <div class="mb-3">
                    <h4 class="mb-1">Hiring Submissions</h4>
                    <p class="mb-0 text-muted">Preferred work location is taken from the active branch selected in the hiring form. Empty values are shown as Undisclosed under the Other tab.</p>
                    <p class="mb-0 text-muted">Only candidate submissions appear here. Resend opens WhatsApp for the candidate’s stored WhatsApp number and sends the same hiring link again.</p>
                </div>

                <form method="get" action="{{ route('admin-hiring-index') }}" class="row g-3 align-items-end mb-4">
                    <input type="hidden" name="place_tab" value="{{ $filters['place_tab'] ?? '' }}">
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
                        <a href="{{ route('admin-hiring-index') }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>

                <ul class="nav nav-tabs mb-3">
                    @foreach ($placeTabs as $tabKey => $tabLabel)
                        @php
                            $tabFilters = array_filter([
                                'date_from' => $filters['date_from'] ?? '',
                                'date_to' => $filters['date_to'] ?? '',
                                'status' => $filters['status'] ?? '',
                                'place_tab' => $tabKey,
                            ], fn ($value) => $value !== null && $value !== '');
                        @endphp
                        <li class="nav-item">
                            <a href="{{ route('admin-hiring-index', $tabFilters) }}" class="nav-link @if (($filters['place_tab'] ?? '') === $tabKey) active @endif">
                                {{ $tabLabel }} ({{ $placeTabCounts[$tabKey] ?? 0 }})
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle" data-admin-datatable="true" data-admin-scroll-x="false">
                        <thead>
                            <tr>
                                <th>Submission ID</th>
                                <th>Submitted Date</th>
                                <th>Preferred Work Location</th>
                                <th>Position</th>
                                <th>Candidate</th>
                                <th>Aadhaar</th>
                                <th>Contact</th>
                                <th>WhatsApp</th>
                                <th>Resume</th>
                                <th>Status</th>
                                <th>Duplicate</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($candidates as $candidate)
                                @php
                                    $status = $candidate->status;
                                    $formLink = $candidate->formLink;
                                    $hiringUrl = $formLink ? route('recruitment-hiring-form-show', ['token' => $formLink->public_token, 'resubmission' => $candidate->submission_code]) : null;
                                    $hiringUpdateUrl = $candidate->hiring_update_token ? route('recruitment-hiring-update-link', $candidate->hiring_update_token) : $hiringUrl;
                                    $whatsappTarget = $candidate->whatsappTarget();
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $candidate->submission_code ?: '--' }}</div>
                                        @if ($candidate->resubmissionOf?->submission_code)
                                            <div class="small text-muted">Resubmission of {{ $candidate->resubmissionOf->submission_code }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $candidate->display_hiring_date }}</td>
                                    <td>{{ $candidate->hiring_place }}</td>
                                    <td>{{ $candidate->position_applied_for ?: '--' }}</td>
                                    <td>{{ $candidate->candidate_name ?: '--' }}</td>
                                    <td>{{ $candidate->aadhaar_number ?: '--' }}</td>
                                    <td>{{ $candidate->contact_number ?: '--' }}</td>
                                    <td>{{ $candidate->whatsapp_number ?: '--' }}</td>
                                    <td>
                                        @if ($candidate->resume_file_path)
                                            <a href="{{ \App\Support\ProjectAsset::url($candidate->resume_file_path) }}" target="_blank">View Resume</a>
                                        @else
                                            <span class="text-muted">--</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($status === \App\Models\RecruitmentCandidate::STATUS_HIRING_SUBMITTED)
                                            <span class="badge bg-primary">Submitted</span>
                                        @elseif ($status === \App\Models\RecruitmentCandidate::STATUS_HIRING_SELECTED)
                                            <span class="badge bg-success">Selected</span>
                                        @elseif ($status === \App\Models\RecruitmentCandidate::STATUS_HIRING_HOLD)
                                            <span class="badge bg-info text-dark">Hold</span>
                                        @elseif ($status === \App\Models\RecruitmentCandidate::STATUS_HIRING_REJECTED)
                                            <span class="badge bg-danger">Rejected</span>
                                        @elseif ($status === \App\Models\RecruitmentCandidate::STATUS_HIRING_UPDATE_REQUESTED)
                                            <span class="badge bg-secondary">Update Requested</span>
                                        @elseif (in_array($status, [
                                            \App\Models\RecruitmentCandidate::STATUS_JOINING_FORM_SHARED,
                                            \App\Models\RecruitmentCandidate::STATUS_JOINING_SUBMITTED,
                                            \App\Models\RecruitmentCandidate::STATUS_JOINING_HOLD,
                                            \App\Models\RecruitmentCandidate::STATUS_JOINING_REJECTED,
                                            \App\Models\RecruitmentCandidate::STATUS_JOINING_UPDATE_REQUESTED,
                                            \App\Models\RecruitmentCandidate::STATUS_ONBOARDED,
                                            \App\Models\RecruitmentCandidate::STATUS_JOINED,
                                        ], true))
                                            <span class="badge bg-dark text-white">Moved To Joining</span>
                                        @else
                                            <span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($candidate->has_duplicate_aadhaar)
                                            <a href="{{ route('admin-hiring-show', $candidate->id) }}#duplicate-submissions" class="btn btn-outline-danger btn-sm px-2 py-0">
                                                {{ $candidate->duplicate_count }}
                                            </a>
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="recruitment-action-grid">
                                            <a href="{{ route('admin-hiring-show', $candidate->id) }}" class="btn btn-outline-secondary btn-sm recruitment-action-btn">
                                                <i class="bx bx-show-alt"></i>
                                                <span>Details</span>
                                            </a>
                                            @if ($candidate->resubmissionOf)
                                                <a href="{{ route('admin-hiring-show', $candidate->resubmissionOf->id) }}" class="btn btn-outline-info btn-sm recruitment-action-btn">
                                                    <i class="bx bx-history"></i>
                                                    <span>Previous</span>
                                                </a>
                                            @endif

                                            @if (in_array($status, [
                                                \App\Models\RecruitmentCandidate::STATUS_HIRING_SUBMITTED,
                                                \App\Models\RecruitmentCandidate::STATUS_HIRING_HOLD,
                                                \App\Models\RecruitmentCandidate::STATUS_HIRING_UPDATE_REQUESTED,
                                            ], true))
                                                <form method="post" action="{{ route('admin-hiring-decision', $candidate->id) }}">
                                                    @csrf
                                                    <input type="hidden" name="action" value="select">
                                                    <button type="submit" class="btn btn-success btn-sm recruitment-action-btn">
                                                        <i class="bx bx-check"></i>
                                                        <span>Select</span>
                                                    </button>
                                                </form>
                                                <form method="post" action="{{ route('admin-hiring-decision', $candidate->id) }}">
                                                    @csrf
                                                    <input type="hidden" name="action" value="hold">
                                                    <button type="submit" class="btn btn-warning btn-sm recruitment-action-btn">
                                                        <i class="bx bx-pause"></i>
                                                        <span>Hold</span>
                                                    </button>
                                                </form>
                                                <form method="post" action="{{ route('admin-hiring-decision', $candidate->id) }}">
                                                    @csrf
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="btn btn-danger btn-sm recruitment-action-btn">
                                                        <i class="bx bx-x"></i>
                                                        <span>Reject</span>
                                                    </button>
                                                </form>
                                            @endif

                                            @if ($hiringUrl && $whatsappTarget && in_array($status, [
                                                \App\Models\RecruitmentCandidate::STATUS_HIRING_SUBMITTED,
                                                \App\Models\RecruitmentCandidate::STATUS_HIRING_HOLD,
                                                \App\Models\RecruitmentCandidate::STATUS_HIRING_REJECTED,
                                                \App\Models\RecruitmentCandidate::STATUS_HIRING_SELECTED,
                                            ], true))
                                                <form method="post" action="{{ route('admin-hiring-decision', $candidate->id) }}" class="js-whatsapp-action-form" data-phone="{{ $whatsappTarget }}" data-link="{{ $hiringUpdateUrl }}" data-message="Please update your Attica hiring form." data-button-text="Resend Form">
                                                    @csrf
                                                    <input type="hidden" name="action" value="resend">
                                                    <button type="submit" class="btn btn-outline-dark btn-sm recruitment-action-btn">
                                                        <i class="bx bx-refresh"></i>
                                                        <span>Resend</span>
                                                    </button>
                                                </form>
                                            @endif

                                            @if ($status === \App\Models\RecruitmentCandidate::STATUS_HIRING_UPDATE_REQUESTED && $candidate->hiring_update_token)
                                                <button type="button" class="btn btn-outline-primary btn-sm recruitment-action-btn js-copy-link" data-link="{{ $hiringUpdateUrl }}">
                                                    <i class="bx bx-copy"></i>
                                                    <span>Copy Link</span>
                                                </button>
                                            @endif

                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            @if ($candidates->isEmpty())
                                <tr>
                                    <td colspan="12" class="text-center text-muted">No hiring submissions found for this location tab.</td>
                                </tr>
                            @endif
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

            document.querySelectorAll('.js-share-whatsapp').forEach(button => {
                button.addEventListener('click', function () {
                    const link = this.dataset.link || '';
                    const message = encodeURIComponent(`${this.dataset.message || 'Please complete the form.'}\n${link}`);
                    window.open(`https://wa.me/?text=${message}`, '_blank');
                });
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
