@extends('admin.layout.app')

@section('content')
    @include('admin.attendance.partials.styles')

    <div class="main-content attendance-page">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Attendance</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin-dashboard') }}"><i class="bx bx-home-alt"></i></a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">HO Attendance Import</li>
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
                        <h4 class="mb-1 attendance-title">HO Attendance Import</h4>
                        <p class="mb-0 attendance-muted">
                            Import Excel attendance workbooks and review imported employee attendance in the same page.
                        </p>
                    </div>
                    <a href="{{ route('admin-attendance-reports') }}" class="btn btn-outline-primary">Open Attendance Reports</a>
                </div>

                <form method="post" action="{{ route('admin-attendance-import-store') }}" enctype="multipart/form-data"
                    class="row g-3 align-items-end">
                    @csrf
                    <div class="col-md-5">
                        <label class="form-label">Excel File</label>
                        <input type="file" class="form-control" name="attendance_file" accept=".xlsx,.xls,.xlsm" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Import Excel</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card rounded-4 mb-4">
            <div class="card-body">
                <h5 class="mb-3 attendance-title">Imported Attendance Filters</h5>
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label">From Date</label>
                        <input type="date" name="from_date" value="{{ $filters['from_date'] }}" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To Date</label>
                        <input type="date" name="to_date" value="{{ $filters['to_date'] }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Filter</label>
                        <input type="text" name="search" value="{{ $filters['search'] }}" class="form-control"
                            placeholder="Employee ID or name">
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100">Apply</button>
                        <a href="{{ route('admin-attendance-import') }}" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card rounded-4 mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                    <div>
                        <h5 class="mb-1 attendance-title">Imported Employee Attendance Report</h5>
                        <p class="mb-0 attendance-muted">
                            Present, absent, half day, and single punch totals are calculated inside the selected date range.
                        </p>
                    </div>
                    <div class="datatable-toolbar"></div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle js-admin-datatable"
                        data-admin-datatable="true">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Employee ID</th>
                                <th>Employee Name</th>
                                <th>Present Days</th>
                                <th>Week Off Days</th>
                                <th>Absent Days</th>
                                <th>Half Days</th>
                                <th>Single Punch</th>
                                <th>Total Logged Time</th>
                                <th>Calendar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($importReportRows as $index => $row)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $row['emp_id'] }}</td>
                                    <td>{{ $row['employee_name'] }}</td>
                                    <td>{{ $row['present_days'] }}</td>
                                    <td>{{ $row['week_off_days'] }}</td>
                                    <td>{{ $row['absent_days'] }}</td>
                                    <td>{{ $row['half_days'] }}</td>
                                    <td>{{ $row['single_punch_days'] }}</td>
                                    <td>{{ $row['logged_time_label'] }}</td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary"
                                            onclick="openAttendanceCalendar('{{ $row['emp_id'] }}', '{{ $calendarMonth }}', '')">
                                            View Calendar
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center fw-semibold">No imported employees found for the selected date range.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card rounded-4 mb-4" data-review-card="single-punch"
            data-initial-state="{{ $filters['show_single_punch'] ? 'expanded' : 'collapsed' }}">
            <div class="card-body">
                <form method="post" action="{{ route('admin-attendance-import-review-update') }}" id="importSinglePunchForm">
                    @csrf
                    <input type="hidden" name="from_date" value="{{ $filters['from_date'] }}">
                    <input type="hidden" name="to_date" value="{{ $filters['to_date'] }}">
                    <input type="hidden" name="search" value="{{ $filters['search'] }}">

                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                        <div>
                            <h5 class="mb-1 attendance-title">Imported Single Punch Review</h5>
                        </div>
                        <button type="button" class="btn btn-outline-secondary" data-review-toggle>Hide</button>
                    </div>

                    <div class="mt-3" data-review-card-content>
                        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                            <div>
                                <p class="mb-0 attendance-muted">Select imported single punch entries and update them to full day if they should be credited.</p>
                            </div>
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <div class="datatable-toolbar"></div>
                                <select name="override_status" class="form-select" style="min-width: 200px;" required>
                                    <option value="full_day" selected>Mark Full Day</option>
                                    <option value="half_day">Mark Half Day</option>
                                    <option value="absent">Mark Absent</option>
                                </select>
                                <button type="submit" class="btn btn-primary">Update Selected</button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle js-admin-datatable"
                                data-admin-datatable="true">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;"><input type="checkbox" data-import-select-all="single-punch"></th>
                                        <th>Date</th>
                                        <th>Emp ID</th>
                                        <th>Name</th>
                                        <th>Status</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th>Worked Time</th>
                                        <th>Calendar</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($singlePunchRows as $row)
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="selected_records[]" value="{{ $row['record_key'] }}"
                                                    data-record-key="{{ $row['record_key'] }}"
                                                    class="import-entry-checkbox import-entry-single-punch">
                                            </td>
                                            <td>{{ $row['attendance_date'] }}</td>
                                            <td>{{ $row['emp_id'] }}</td>
                                            <td>{{ $row['employee_name'] ?: '--' }}</td>
                                            <td>
                                                <span class="attendance-status-pill {{ $row['status'] }}">
                                                    {{ $row['status_label'] }}
                                                </span>
                                            </td>
                                            <td>{{ $row['first_login_label'] ?: '--' }}</td>
                                            <td>{{ $row['last_logout_label'] ?: '--' }}</td>
                                            <td>{{ $row['worked_time_label'] }}</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary"
                                                    onclick="openAttendanceCalendar('{{ $row['emp_id'] }}', '{{ \Illuminate\Support\Carbon::parse($row['attendance_date'])->format('Y-m') }}', '')">
                                                    Calendar
                                                </button>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <button type="button" class="btn btn-sm btn-success"
                                                        onclick="submitImportedRowAction('importSinglePunchForm', '{{ $row['record_key'] }}', 'full_day')">
                                                        Full Day
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-warning"
                                                        onclick="submitImportedRowAction('importSinglePunchForm', '{{ $row['record_key'] }}', 'half_day')">
                                                        Half Day
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger"
                                                        onclick="submitImportedRowAction('importSinglePunchForm', '{{ $row['record_key'] }}', 'absent')">
                                                        Absent
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="10" class="text-center fw-semibold">No imported single punch entries found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card rounded-4 mb-4" data-review-card="half-day"
            data-initial-state="{{ $filters['show_half_day'] ? 'expanded' : 'collapsed' }}">
            <div class="card-body">
                <form method="post" action="{{ route('admin-attendance-import-review-update') }}" id="importHalfDayForm">
                    @csrf
                    <input type="hidden" name="from_date" value="{{ $filters['from_date'] }}">
                    <input type="hidden" name="to_date" value="{{ $filters['to_date'] }}">
                    <input type="hidden" name="search" value="{{ $filters['search'] }}">

                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                        <div>
                            <h5 class="mb-1 attendance-title">Imported Half Day Review</h5>
                        </div>
                        <button type="button" class="btn btn-outline-secondary" data-review-toggle>Hide</button>
                    </div>

                    <div class="mt-3" data-review-card-content>
                        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                            <div>
                                <p class="mb-0 attendance-muted">Select imported half day entries and update them to full day when the attendance should be regularized.</p>
                            </div>
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <div class="datatable-toolbar"></div>
                                <select name="override_status" class="form-select" style="min-width: 200px;" required>
                                    <option value="full_day" selected>Mark Full Day</option>
                                    <option value="half_day">Mark Half Day</option>
                                    <option value="absent">Mark Absent</option>
                                </select>
                                <button type="submit" class="btn btn-primary">Update Selected</button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle js-admin-datatable"
                                data-admin-datatable="true">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;"><input type="checkbox" data-import-select-all="half-day"></th>
                                        <th>Date</th>
                                        <th>Emp ID</th>
                                        <th>Name</th>
                                        <th>Status</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th>Worked Time</th>
                                        <th>Calendar</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($halfDayRows as $row)
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="selected_records[]" value="{{ $row['record_key'] }}"
                                                    data-record-key="{{ $row['record_key'] }}"
                                                    class="import-entry-checkbox import-entry-half-day">
                                            </td>
                                            <td>{{ $row['attendance_date'] }}</td>
                                            <td>{{ $row['emp_id'] }}</td>
                                            <td>{{ $row['employee_name'] ?: '--' }}</td>
                                            <td>
                                                <span class="attendance-status-pill {{ $row['status'] }}">
                                                    {{ $row['status_label'] }}
                                                </span>
                                            </td>
                                            <td>{{ $row['first_login_label'] ?: '--' }}</td>
                                            <td>{{ $row['last_logout_label'] ?: '--' }}</td>
                                            <td>{{ $row['worked_time_label'] }}</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary"
                                                    onclick="openAttendanceCalendar('{{ $row['emp_id'] }}', '{{ \Illuminate\Support\Carbon::parse($row['attendance_date'])->format('Y-m') }}', '')">
                                                    Calendar
                                                </button>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <button type="button" class="btn btn-sm btn-success"
                                                        onclick="submitImportedRowAction('importHalfDayForm', '{{ $row['record_key'] }}', 'full_day')">
                                                        Full Day
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-warning"
                                                        onclick="submitImportedRowAction('importHalfDayForm', '{{ $row['record_key'] }}', 'half_day')">
                                                        Half Day
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger"
                                                        onclick="submitImportedRowAction('importHalfDayForm', '{{ $row['record_key'] }}', 'absent')">
                                                        Absent
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="10" class="text-center fw-semibold">No imported half day entries found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card rounded-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                    <div>
                        <h5 class="mb-1 attendance-title">Imported Attendance Overview</h5>
                        <p class="mb-0 attendance-muted">
                            Showing imported Excel attendance rows from {{ $filters['from_date'] }} to {{ $filters['to_date'] }}.
                        </p>
                    </div>
                    <div class="datatable-toolbar"></div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle js-admin-datatable"
                        data-admin-datatable="true">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Employee ID</th>
                                <th>Employee Name</th>
                                <th>Present Days</th>
                                <th>Login Before 9:30</th>
                                <th>Login 9:30 - 9:40</th>
                                <th>Login 9:40 - 10:00</th>
                                <th>Login 10:00 - 10:30</th>
                                <th>After 10:30</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($overviewRows as $index => $row)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $row->emp_id }}</td>
                                    <td>{{ $row->employee_name ?: '--' }}</td>
                                    <td>{{ $row->attendance_days }}</td>
                                    <td>{{ $row->login_930 }}</td>
                                    <td>{{ $row->login_940 }}</td>
                                    <td>{{ $row->login_1000 }}</td>
                                    <td>{{ $row->login_1030 }}</td>
                                    <td>{{ $row->login_1031 }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center fw-semibold">No imported attendance rows found for the selected date range.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @php($calendarRouteTemplate = route('admin-attendance-import-calendar', ['empId' => '__EMP__']))
    @include('admin.attendance.partials.calendar_modal')

    <script>
        function submitImportedRowAction(formId, recordKey, overrideStatus) {
            const form = document.getElementById(formId);

            if (!form) {
                return;
            }

            form.querySelectorAll('input[type="checkbox"][name="selected_records[]"]').forEach((checkbox) => {
                checkbox.checked = checkbox.dataset.recordKey === recordKey;
            });

            const statusSelect = form.querySelector('select[name="override_status"]');

            if (statusSelect) {
                statusSelect.value = overrideStatus;
            }

            form.submit();
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[data-import-select-all]').forEach((master) => {
                master.addEventListener('change', function() {
                    const group = master.getAttribute('data-import-select-all');

                    document.querySelectorAll('.import-entry-' + group).forEach((checkbox) => {
                        checkbox.checked = master.checked;
                    });
                });
            });

            document.querySelectorAll('[data-review-card]').forEach((card) => {
                const cardKey = card.getAttribute('data-review-card');
                const content = card.querySelector('[data-review-card-content]');
                const toggle = card.querySelector('[data-review-toggle]');

                if (!content || !toggle) {
                    return;
                }

                const storageKey = 'attendance-import-card-' + cardKey;
                const initialState = localStorage.getItem(storageKey) || card.getAttribute('data-initial-state') || 'expanded';

                const applyState = (state) => {
                    const collapsed = state === 'collapsed';
                    content.classList.toggle('d-none', collapsed);
                    toggle.textContent = collapsed ? 'Show' : 'Hide';
                    toggle.classList.toggle('btn-outline-primary', collapsed);
                    toggle.classList.toggle('btn-outline-secondary', !collapsed);
                };

                applyState(initialState);

                toggle.addEventListener('click', function() {
                    const nextState = content.classList.contains('d-none') ? 'expanded' : 'collapsed';
                    localStorage.setItem(storageKey, nextState);
                    applyState(nextState);
                });
            });
        });
    </script>
@endsection
