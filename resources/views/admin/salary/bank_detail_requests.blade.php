@extends('admin.layout.app')

@section('content')
    @include('admin.attendance.partials.styles')

    @php
        $activeTab = $filters['tab'] ?? 'pending_approval';
        $tabLinks = [
            'verification' => [
                'label' => 'Verification',
                'count' => $summary['pending_verification'],
            ],
            'pending_approval' => [
                'label' => 'Pending Approval',
                'count' => $summary['pending_approval'],
            ],
            'approved' => [
                'label' => 'Approved',
                'count' => $summary['approved_waiting'],
            ],
        ];
        $bulkButtons = match ($activeTab) {
            'verification' => [
                ['action' => 'verify', 'scope' => 'selected', 'label' => 'Verify Selected', 'class' => 'btn-primary'],
                ['action' => 'reject', 'scope' => 'selected', 'label' => 'Reject Selected', 'class' => 'btn-outline-danger'],
                ['action' => 'verify', 'scope' => 'all', 'label' => 'Verify All', 'class' => 'btn-outline-primary'],
                ['action' => 'reject', 'scope' => 'all', 'label' => 'Reject All', 'class' => 'btn-danger'],
            ],
            'approved' => [
                ['action' => 'reject', 'scope' => 'selected', 'label' => 'Reject Selected', 'class' => 'btn-outline-danger'],
                ['action' => 'reject', 'scope' => 'all', 'label' => 'Reject All', 'class' => 'btn-danger'],
            ],
            default => [
                ['action' => 'approve', 'scope' => 'selected', 'label' => 'Approve Selected', 'class' => 'btn-success'],
                ['action' => 'reject', 'scope' => 'selected', 'label' => 'Reject Selected', 'class' => 'btn-outline-danger'],
                ['action' => 'approve', 'scope' => 'all', 'label' => 'Approve All', 'class' => 'btn-outline-success'],
                ['action' => 'reject', 'scope' => 'all', 'label' => 'Reject All', 'class' => 'btn-danger'],
            ],
        };
    @endphp

    <div class="main-content attendance-page">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Salary</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin-dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Bank Detail Requests</li>
                    </ol>
                </nav>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success border-0">{{ session('status') }}</div>
        @endif

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card rounded-4 mb-0 attendance-stat-card">
                    <div class="card-body">
                        <p class="mb-1">Pending Verification</p>
                        <h4 class="mb-0">{{ $summary['pending_verification'] }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card rounded-4 mb-0 attendance-stat-card">
                    <div class="card-body">
                        <p class="mb-1">Pending Approval</p>
                        <h4 class="mb-0">{{ $summary['pending_approval'] }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card rounded-4 mb-0 attendance-stat-card">
                    <div class="card-body">
                        <p class="mb-1">Approved, Waiting For Employee</p>
                        <h4 class="mb-0">{{ $summary['approved_waiting'] }}</h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="card rounded-4 mb-4">
            <div class="card-body">
                <ul class="nav nav-tabs mb-3">
                    @foreach ($tabLinks as $tabKey => $tab)
                        @php
                            $tabQuery = ['tab' => $tabKey];
                            if (($filters['emp_id'] ?? '') !== '') {
                                $tabQuery['emp_id'] = $filters['emp_id'];
                            }
                        @endphp
                        <li class="nav-item">
                            <a class="nav-link {{ $activeTab === $tabKey ? 'active' : '' }}" href="{{ route('admin-bank-detail-requests', $tabQuery) }}">
                                {{ $tab['label'] }}
                                <span class="badge bg-secondary ms-1">{{ $tab['count'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>

                <form method="get" class="row g-3 align-items-end">
                    <input type="hidden" name="tab" value="{{ $activeTab }}">
                    <div class="col-md-4">
                        <label class="form-label">Employee ID</label>
                        <input type="text" name="emp_id" value="{{ $filters['emp_id'] }}" class="form-control" placeholder="Optional">
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100">Apply</button>
                        <a href="{{ route('admin-bank-detail-requests', ['tab' => $activeTab]) }}" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card rounded-4">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h5 class="mb-1 attendance-title">Employee Bank Detail Requests</h5>
                        <p class="mb-0 attendance-muted">Approve edit access first, then verify submitted bank changes.</p>
                    </div>
                    <form method="post" action="{{ route('admin-bank-detail-requests-bulk') }}" id="bank-detail-bulk-form" class="d-flex flex-wrap align-items-start gap-2">
                        @csrf
                        <input type="hidden" name="tab" value="{{ $activeTab }}">
                        <input type="hidden" name="emp_id" value="{{ $filters['emp_id'] }}">
                        <input type="hidden" name="scope" id="bank-detail-bulk-scope" value="selected">
                        <input type="hidden" name="bulk_action" id="bank-detail-bulk-action" value="">
                        <div id="bank-detail-selected-inputs"></div>
                        <textarea name="admin_note" class="form-control" rows="1" placeholder="Optional bulk note" style="min-width: 220px;"></textarea>
                        @foreach ($bulkButtons as $button)
                            <button
                                type="button"
                                class="btn {{ $button['class'] }} btn-sm bank-detail-bulk-button"
                                data-action="{{ $button['action'] }}"
                                data-scope="{{ $button['scope'] }}"
                                data-label="{{ $button['label'] }}"
                            >
                                {{ $button['label'] }}
                            </button>
                        @endforeach
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle" data-admin-datatable="true">
                        <thead>
                            <tr>
                                <th style="width: 42px;">
                                    <input type="checkbox" class="form-check-input" id="bank-detail-select-all" aria-label="Select all visible bank detail requests">
                                </th>
                                <th>Created</th>
                                <th>Emp ID</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Current Details</th>
                                <th>Requested Details</th>
                                <th>Notes</th>
                                <th>Timeline</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input bank-detail-row-checkbox" value="{{ $row['id'] }}" aria-label="Select request {{ $row['id'] }}">
                                    </td>
                                    <td>{{ $row['created_at'] }}</td>
                                    <td>{{ $row['emp_id'] }}</td>
                                    <td>
                                        <div>{{ $row['employee_name'] }}</div>
                                        <small class="text-muted">{{ $row['designation'] }}</small>
                                    </td>
                                    <td>
                                        <span class="attendance-status-pill {{ strtolower($row['status']) }}">
                                            {{ ucfirst($row['status']) }}
                                        </span>
                                    </td>
                                    <td style="min-width: 260px;">
                                        <div><strong>Name:</strong> {{ $row['current_account_name'] ?: '--' }}</div>
                                        <div><strong>Bank:</strong> {{ $row['current_bank_name'] ?: '--' }}</div>
                                        <div><strong>A/C:</strong> {{ $row['current_bank_ac_no'] ?: '--' }}</div>
                                        <div><strong>IFSC:</strong> {{ $row['current_ifsc_code'] ?: '--' }}</div>
                                        <div><strong>UAN:</strong> {{ $row['current_uan_number'] ?: '--' }}</div>
                                    </td>
                                    <td style="min-width: 260px;">
                                        <div><strong>Name:</strong> {{ $row['requested_account_name'] ?: '--' }}</div>
                                        <div><strong>Bank:</strong> {{ $row['requested_bank_name'] ?: '--' }}</div>
                                        <div><strong>A/C:</strong> {{ $row['requested_bank_ac_no'] ?: '--' }}</div>
                                        <div><strong>IFSC:</strong> {{ $row['requested_ifsc_code'] ?: '--' }}</div>
                                        <div><strong>UAN:</strong> {{ $row['requested_uan_number'] ?: '--' }}</div>
                                    </td>
                                    <td style="min-width: 240px;">
                                        <div><strong>Employee:</strong> {{ $row['request_note'] ?: '--' }}</div>
                                        <div class="mt-2"><strong>Admin:</strong> {{ $row['admin_note'] ?: '--' }}</div>
                                    </td>
                                    <td style="min-width: 220px;">
                                        <div><strong>Approved:</strong> {{ $row['approved_at'] }}</div>
                                        <div><strong>Submitted:</strong> {{ $row['submitted_at'] }}</div>
                                        <div><strong>Verified:</strong> {{ $row['verified_at'] }}</div>
                                    </td>
                                    <td style="min-width: 260px;">
                                        @if ($row['status'] === 'pending')
                                            <form method="post" action="{{ route('admin-bank-detail-requests-approve', $row['id']) }}" class="mb-2">
                                                @csrf
                                                <textarea name="admin_note" class="form-control mb-2" rows="2" placeholder="Optional approval note"></textarea>
                                                <button type="submit" class="btn btn-success btn-sm w-100">Approve Edit Access</button>
                                            </form>
                                            <form method="post" action="{{ route('admin-bank-detail-requests-reject', $row['id']) }}">
                                                @csrf
                                                <textarea name="admin_note" class="form-control mb-2" rows="2" placeholder="Reason for rejection"></textarea>
                                                <button type="submit" class="btn btn-danger btn-sm w-100">Reject</button>
                                            </form>
                                        @elseif ($row['status'] === 'submitted')
                                            <form method="post" action="{{ route('admin-bank-detail-requests-verify', $row['id']) }}" class="mb-2">
                                                @csrf
                                                <textarea name="admin_note" class="form-control mb-2" rows="2" placeholder="Optional verification note"></textarea>
                                                <button type="submit" class="btn btn-primary btn-sm w-100">Verify Bank Details</button>
                                            </form>
                                            <form method="post" action="{{ route('admin-bank-detail-requests-reject', $row['id']) }}">
                                                @csrf
                                                <textarea name="admin_note" class="form-control mb-2" rows="2" placeholder="Reason for rejection"></textarea>
                                                <button type="submit" class="btn btn-danger btn-sm w-100">Reject</button>
                                            </form>
                                        @elseif ($row['status'] === 'approved')
                                            <div class="text-muted mb-2">Waiting for the employee to submit updated bank details in the app.</div>
                                            <form method="post" action="{{ route('admin-bank-detail-requests-reject', $row['id']) }}">
                                                @csrf
                                                <textarea name="admin_note" class="form-control mb-2" rows="2" placeholder="Reason for rejection"></textarea>
                                                <button type="submit" class="btn btn-danger btn-sm w-100">Reject</button>
                                            </form>
                                        @else
                                            <span class="text-muted">No action available.</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">No bank detail requests found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const selectAll = document.getElementById('bank-detail-select-all');
            const bulkForm = document.getElementById('bank-detail-bulk-form');
            const actionInput = document.getElementById('bank-detail-bulk-action');
            const scopeInput = document.getElementById('bank-detail-bulk-scope');
            const selectedInputs = document.getElementById('bank-detail-selected-inputs');

            function selectedCheckboxes() {
                return Array.from(document.querySelectorAll('.bank-detail-row-checkbox:checked'));
            }

            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    document.querySelectorAll('.bank-detail-row-checkbox').forEach(function (checkbox) {
                        checkbox.checked = selectAll.checked;
                    });
                });
            }

            document.querySelectorAll('.bank-detail-bulk-button').forEach(function (button) {
                button.addEventListener('click', function () {
                    const action = button.dataset.action;
                    const scope = button.dataset.scope;
                    const label = button.dataset.label || 'Submit';

                    selectedInputs.innerHTML = '';

                    if (scope === 'selected') {
                        const checked = selectedCheckboxes();

                        if (checked.length === 0) {
                            alert('Select at least one bank detail request.');
                            return;
                        }

                        checked.forEach(function (checkbox) {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'request_ids[]';
                            input.value = checkbox.value;
                            selectedInputs.appendChild(input);
                        });
                    }

                    if (! confirm(label + '?')) {
                        return;
                    }

                    actionInput.value = action;
                    scopeInput.value = scope;
                    bulkForm.submit();
                });
            });
        });
    </script>
@endsection
