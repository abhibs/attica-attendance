@extends('admin.layout.app')

@section('content')
    @include('admin.attendance.partials.styles')

    @php
        $oldEmployeeIds = array_map('strval', old('employee_ids', []));
    @endphp

    <style>
        .notification-employee-picker {
            max-height: 420px;
            overflow: auto;
            border: 1px solid var(--admin-border-color);
            border-radius: 18px;
        }

        .notification-employee-picker table {
            margin-bottom: 0;
        }

        .notification-employee-picker thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: var(--admin-surface-color);
        }

        .notification-employee-picker tr.is-hidden {
            display: none;
        }

        .notification-replay-row {
            cursor: pointer;
        }

        .notification-replay-row:hover {
            background: rgba(var(--admin-primary-color-rgb), 0.05);
        }

        .notification-replay-hint {
            font-size: 0.78rem;
            color: var(--admin-muted-text-color);
        }
    </style>

    <div class="main-content attendance-page">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Notifications</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin-dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Notifications</li>
                    </ol>
                </nav>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success border-0">{{ session('status') }}</div>
        @endif

        <div class="card rounded-4 mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
                    <div>
                        <h4 class="mb-1 attendance-title">Push Notification</h4>
                        <p class="mb-0 attendance-muted">Send a notification to employees, a branch, city, state, or all active users.</p>
                    </div>
                </div>

                <form method="post" action="{{ route('admin-notifications-store') }}" id="adminNotificationForm" class="row g-3">
                    @csrf
                    <input type="hidden" name="audience_value" id="notificationAudienceValue" value="{{ old('audience_value') }}">

                    <div class="col-md-3">
                        <label class="form-label">Send To</label>
                        <select name="audience_type" id="notificationAudienceType" class="form-select" required>
                            <option value="all" @selected(old('audience_type', 'all') === 'all')>All Users</option>
                            <option value="employee" @selected(old('audience_type') === 'employee')>Employee</option>
                            <option value="branch" @selected(old('audience_type') === 'branch')>Branch</option>
                            <option value="city" @selected(old('audience_type') === 'city')>City</option>
                            <option value="state" @selected(old('audience_type') === 'state')>State</option>
                        </select>
                    </div>

                    <div class="col-12 notification-target" data-target-type="employee">
                        <div class="row g-3">
                            <div class="col-lg-8">
                                <label class="form-label">Search Employees</label>
                                <input type="search" id="notificationEmployeeSearch" class="form-control"
                                    placeholder="Type employee ID or name to search">
                            </div>
                            <div class="col-lg-4 d-flex align-items-end gap-2">
                                <button type="button" class="btn btn-outline-secondary w-100" id="notificationSelectVisibleEmployees">
                                    Select Visible
                                </button>
                                <button type="button" class="btn btn-outline-secondary w-100" id="notificationClearVisibleEmployees">
                                    Clear Visible
                                </button>
                            </div>
                            <div class="col-12">
                                <div class="notification-employee-picker">
                                    <table class="table table-bordered table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th style="width: 56px;">
                                                    <input type="checkbox" id="notificationSelectAllEmployees">
                                                </th>
                                                <th>Emp ID</th>
                                                <th>Name</th>
                                                <th>Designation</th>
                                                <th>Contact</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($employees as $employee)
                                                @php
                                                    $employeeId = (string) $employee->id;
                                                    $employeeSearchText = strtolower(trim(implode(' ', [
                                                        $employee->empId,
                                                        $employee->name,
                                                    ])));
                                                @endphp
                                                <tr data-notification-employee-row data-search-text="{{ $employeeSearchText }}">
                                                    <td>
                                                        <input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}"
                                                            class="notification-employee-checkbox"
                                                            @checked(old('audience_type') === 'employee' && in_array($employeeId, $oldEmployeeIds, true))>
                                                    </td>
                                                    <td>{{ trim($employee->empId) }}</td>
                                                    <td>{{ $employee->name }}</td>
                                                    <td>{{ $employee->designation ?: '--' }}</td>
                                                    <td>{{ $employee->contact ?: '--' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div class="form-text">Search by employee ID or name, then select one or more employees.</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-5 notification-target" data-target-type="branch">
                        <label class="form-label">Branch</label>
                        <input
                            type="search"
                            class="form-control notification-target-input"
                            data-target-type="branch"
                            list="notificationBranchOptions"
                            placeholder="Type branch ID or branch name"
                            value="{{ old('audience_type') === 'branch' ? old('audience_value') : '' }}">
                        <datalist id="notificationBranchOptions">
                            @foreach ($branches as $branch)
                                <option value="{{ trim($branch->branchId . ' - ' . $branch->branchName, ' -') }}"></option>
                            @endforeach
                        </datalist>
                    </div>

                    <div class="col-md-5 notification-target" data-target-type="city">
                        <label class="form-label">City</label>
                        <input
                            type="search"
                            class="form-control notification-target-input"
                            data-target-type="city"
                            list="notificationCityOptions"
                            placeholder="Type city name"
                            value="{{ old('audience_type') === 'city' ? old('audience_value') : '' }}">
                        <datalist id="notificationCityOptions">
                            @foreach ($cities as $city)
                                <option value="{{ $city }}"></option>
                            @endforeach
                        </datalist>
                    </div>

                    <div class="col-md-5 notification-target" data-target-type="state">
                        <label class="form-label">State</label>
                        <input
                            type="search"
                            class="form-control notification-target-input"
                            data-target-type="state"
                            list="notificationStateOptions"
                            placeholder="Type state name"
                            value="{{ old('audience_type') === 'state' ? old('audience_value') : '' }}">
                        <datalist id="notificationStateOptions">
                            @foreach ($states as $state)
                                <option value="{{ $state }}"></option>
                            @endforeach
                        </datalist>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" value="{{ old('title', 'Attica Pagar') }}" class="form-control" maxlength="120">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Content</label>
                        <textarea name="body" class="form-control" rows="4" maxlength="1000" required placeholder="Enter notification content">{{ old('body') }}</textarea>
                    </div>

                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">Send Notification</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card rounded-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                    <div>
                        <h5 class="mb-1 attendance-title">Recent Notifications</h5>
                        <p class="mb-0 attendance-muted">Click a recent notification to load its audience and message back into the form.</p>
                    </div>
                    <div class="datatable-toolbar"></div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle js-admin-datatable" data-admin-datatable="true">
                        <thead>
                            <tr>
                                <th>Sent At</th>
                                <th>Target</th>
                                <th>Title</th>
                                <th>Content</th>
                                <th>Recipients</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentNotifications as $notification)
                                @php
                                    $recipientEmployees = $notification->deliveries
                                        ->map(fn ($delivery) => $delivery->employee)
                                        ->filter()
                                        ->unique('id')
                                        ->values();
                                    $recipientIds = $recipientEmployees
                                        ->pluck('id')
                                        ->map(fn ($id) => (int) $id)
                                        ->values()
                                        ->all();
                                    $recipientPreview = $recipientEmployees
                                        ->take(3)
                                        ->map(fn ($employee) => trim((string) $employee->empId . ' - ' . (string) $employee->name, ' -'))
                                        ->values()
                                        ->all();
                                    $replayPayload = [
                                        'audienceType' => $notification->audience_type,
                                        'audienceValue' => $notification->audience_value,
                                        'title' => $notification->title,
                                        'body' => $notification->body,
                                        'employeeIds' => $recipientIds,
                                    ];
                                @endphp
                                <tr class="notification-replay-row"
                                    data-replay-notification='@json($replayPayload)'>
                                    <td>{{ optional($notification->sent_at)->format('d M Y h:i A') ?: '--' }}</td>
                                    <td>{{ ucfirst($notification->audience_type) }}{{ $notification->audience_value ? ': ' . $notification->audience_value : '' }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $notification->title }}</div>
                                        <div class="notification-replay-hint">Click to reuse</div>
                                    </td>
                                    <td>{{ $notification->body }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $notification->deliveries_count }}</div>
                                        @if (!empty($recipientPreview))
                                            <div class="small text-muted">{{ implode(', ', $recipientPreview) }}{{ count($recipientIds) > count($recipientPreview) ? ' ...' : '' }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        const audienceTypeInput = document.getElementById('notificationAudienceType');
        const audienceValueInput = document.getElementById('notificationAudienceValue');
        const targetGroups = document.querySelectorAll('.notification-target');
        const targetInputs = document.querySelectorAll('.notification-target-input');
        const employeeRows = Array.from(document.querySelectorAll('[data-notification-employee-row]'));
        const employeeSearchInput = document.getElementById('notificationEmployeeSearch');
        const employeeSelectAllInput = document.getElementById('notificationSelectAllEmployees');
        const employeeSelectVisibleButton = document.getElementById('notificationSelectVisibleEmployees');
        const employeeClearVisibleButton = document.getElementById('notificationClearVisibleEmployees');
        const notificationForm = document.getElementById('adminNotificationForm');
        const titleInput = notificationForm?.querySelector('input[name="title"]');
        const bodyInput = notificationForm?.querySelector('textarea[name="body"]');
        const replayRows = Array.from(document.querySelectorAll('[data-replay-notification]'));

        function employeeCheckbox(row) {
            return row.querySelector('.notification-employee-checkbox');
        }

        function visibleEmployeeRows() {
            return employeeRows.filter((row) => !row.classList.contains('is-hidden'));
        }

        function syncEmployeeSelectAllState() {
            if (!employeeSelectAllInput) {
                return;
            }

            const rows = visibleEmployeeRows();
            const checkedRows = rows.filter((row) => employeeCheckbox(row)?.checked);

            employeeSelectAllInput.checked = rows.length > 0 && checkedRows.length === rows.length;
            employeeSelectAllInput.indeterminate = checkedRows.length > 0 && checkedRows.length < rows.length;
        }

        function applyEmployeeSearch() {
            const query = (employeeSearchInput?.value || '').trim().toLowerCase();

            employeeRows.forEach((row) => {
                const searchable = row.dataset.searchText || '';
                row.classList.toggle('is-hidden', query !== '' && !searchable.includes(query));
            });

            syncEmployeeSelectAllState();
        }

        function syncNotificationTargets() {
            const audienceType = audienceTypeInput?.value || 'all';
            let selectedValue = '';

            targetGroups.forEach((group) => {
                const isVisible = group.dataset.targetType === audienceType;
                group.classList.toggle('d-none', !isVisible);
            });

            targetInputs.forEach((input) => {
                input.disabled = input.dataset.targetType !== audienceType;
                if (!input.disabled) {
                    selectedValue = input.value || '';
                }
            });

            employeeRows.forEach((row) => {
                const checkbox = employeeCheckbox(row);
                if (checkbox) {
                    checkbox.disabled = audienceType !== 'employee';
                }
            });

            audienceValueInput.value = audienceType === 'all' ? '' : selectedValue;
            syncEmployeeSelectAllState();
        }

        function applyReplayPayload(payload) {
            if (!payload || !notificationForm) {
                return;
            }

            const audienceType = payload.audienceType || 'all';
            const audienceValue = payload.audienceValue || '';
            const employeeIds = Array.isArray(payload.employeeIds)
                ? payload.employeeIds.map((id) => String(id))
                : [];

            if (audienceTypeInput) {
                audienceTypeInput.value = audienceType;
            }

            targetInputs.forEach((input) => {
                input.value = input.dataset.targetType === audienceType ? audienceValue : '';
            });

            employeeRows.forEach((row) => {
                const checkbox = employeeCheckbox(row);
                if (!checkbox) {
                    return;
                }

                checkbox.checked = audienceType === 'employee' && employeeIds.includes(String(checkbox.value));
            });

            if (employeeSearchInput) {
                employeeSearchInput.value = '';
            }

            if (titleInput) {
                titleInput.value = payload.title || 'Attica Pagar';
            }

            if (bodyInput) {
                bodyInput.value = payload.body || '';
            }

            applyEmployeeSearch();
            syncNotificationTargets();
            notificationForm.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        }

        audienceTypeInput?.addEventListener('change', syncNotificationTargets);
        targetInputs.forEach((input) => input.addEventListener('change', syncNotificationTargets));
        employeeSearchInput?.addEventListener('input', applyEmployeeSearch);
        employeeSelectAllInput?.addEventListener('change', function() {
            visibleEmployeeRows().forEach((row) => {
                const checkbox = employeeCheckbox(row);
                if (checkbox && !checkbox.disabled) {
                    checkbox.checked = employeeSelectAllInput.checked;
                }
            });
            syncEmployeeSelectAllState();
        });
        employeeSelectVisibleButton?.addEventListener('click', function() {
            visibleEmployeeRows().forEach((row) => {
                const checkbox = employeeCheckbox(row);
                if (checkbox && !checkbox.disabled) {
                    checkbox.checked = true;
                }
            });
            syncEmployeeSelectAllState();
        });
        employeeClearVisibleButton?.addEventListener('click', function() {
            visibleEmployeeRows().forEach((row) => {
                const checkbox = employeeCheckbox(row);
                if (checkbox && !checkbox.disabled) {
                    checkbox.checked = false;
                }
            });
            syncEmployeeSelectAllState();
        });
        employeeRows.forEach((row) => employeeCheckbox(row)?.addEventListener('change', syncEmployeeSelectAllState));
        replayRows.forEach((row) => row.addEventListener('click', function() {
            try {
                applyReplayPayload(JSON.parse(row.dataset.replayNotification || '{}'));
            } catch (_) {
                // Ignore malformed payloads and keep the form usable.
            }
        }));
        document.getElementById('adminNotificationForm')?.addEventListener('submit', syncNotificationTargets);
        applyEmployeeSearch();
        syncNotificationTargets();
    </script>
@endsection
