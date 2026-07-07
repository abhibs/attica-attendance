@extends('admin.layout.app')

@section('content')
    @include('admin.attendance.partials.styles')
    @php
        $reportPageTitle = match ($reportView) {
            'salary' => 'Salary Reports',
            'advance' => 'Advance Reports',
            default => 'Attendance Reports',
        };
        $reportPageDescription = match ($reportView) {
            'salary' => 'Review bank-ready salary output calculated from attendance, then export the sheet to Excel.',
            'advance' => 'Review employee advance balances and transaction totals in the selected date range.',
            default => 'Review employee attendance with date, employee, scope, and location filters.',
        };
        $reportBaseQuery = request()->except(['table_search', 'page', 'per_page']);
        $reportFirstItem = max((int) ($reportPaginator?->firstItem() ?? 1), 1);
        $reportsAdminUser = Auth::guard('admin')->user();
        $hideCalendarForHrReportUser = strtolower(trim((string) ($reportsAdminUser?->name ?? ''))) === 'hr'
            && strtolower(trim((string) ($reportsAdminUser?->email ?? ''))) === 'hr.attica@gmail.com';
    @endphp
    <style>
        .attendance-late-trigger {
            min-width: 140px;
        }

        .attendance-late-summary-card {
            border: 1px solid var(--admin-border-color);
            border-radius: 1rem;
            background: var(--admin-surface-color);
            padding: 0.95rem 1rem;
            height: 100%;
        }

        .attendance-late-summary-card span {
            display: block;
            font-size: 0.82rem;
            color: var(--admin-muted-text-color);
            margin-bottom: 0.35rem;
        }

        .attendance-late-summary-card strong {
            font-size: 1.35rem;
            color: var(--admin-text-color);
        }

        body.admin-layout .attendance-page .attendance-report-responsive {
            overflow-x: hidden !important;
            overflow-y: hidden;
            width: 100%;
        }

        .attendance-report-table {
            width: 100% !important;
            min-width: 0;
            table-layout: fixed;
        }

        .attendance-report-table th,
        .attendance-report-table td {
            white-space: nowrap;
            background-clip: padding-box;
            vertical-align: middle;
            box-sizing: border-box;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .attendance-count-report-table {
            font-size: 11px;
            line-height: 1.25;
        }

        .attendance-count-report-table th,
        .attendance-count-report-table td {
            padding: 0.35rem 0.28rem;
        }

        .attendance-count-report-table thead th {
            white-space: normal;
            line-height: 1.15;
            text-align: center;
        }

        .attendance-count-report-table td:nth-child(3),
        .attendance-count-report-table td:nth-child(4),
        .attendance-count-report-table td:nth-child(5) {
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .attendance-report-table thead th {
            background: var(--admin-background-color, #f7f9fc);
        }

        .attendance-count-report-table th:nth-child(1),
        .attendance-count-report-table td:nth-child(1) {
            width: 3.5%;
            text-align: center;
        }

        .attendance-count-report-table th:nth-child(2),
        .attendance-count-report-table td:nth-child(2) {
            width: 6%;
            text-align: center;
        }

        .attendance-count-report-table th:nth-child(3),
        .attendance-count-report-table td:nth-child(3) {
            width: 9%;
        }

        .attendance-count-report-table th:nth-child(4),
        .attendance-count-report-table td:nth-child(4) {
            width: 10%;
        }

        .attendance-count-report-table th:nth-child(5),
        .attendance-count-report-table td:nth-child(5) {
            width: 8%;
        }

        .attendance-count-report-table th:nth-child(6),
        .attendance-count-report-table td:nth-child(6) {
            width: 5.5%;
            text-align: center;
        }

        .attendance-count-report-table th:nth-child(n+7):nth-child(-n+14),
        .attendance-count-report-table td:nth-child(n+7):nth-child(-n+14) {
            width: 4.7%;
            text-align: center;
        }

        .attendance-count-report-table th:nth-child(15),
        .attendance-count-report-table td:nth-child(15) {
            width: 7.5%;
            text-align: center;
        }

        .attendance-count-report-table th:nth-child(16),
        .attendance-count-report-table td:nth-child(16) {
            width: 6%;
            text-align: center;
        }

        .attendance-count-report-table th:nth-child(17),
        .attendance-count-report-table td:nth-child(17) {
            width: 7%;
            text-align: center;
        }

        .attendance-count-report-table .attendance-late-trigger,
        .attendance-count-report-table .btn-sm {
            min-width: 0;
            padding: 0.25rem 0.4rem;
            font-size: 11px;
            line-height: 1.2;
            white-space: normal;
        }

        .attendance-count-report-table .attendance-status-pill {
            padding: 4px 6px;
            font-size: 10px;
        }

        .salary-count-card {
            border: 1px solid var(--admin-border-color);
            border-radius: 1rem;
            background: var(--admin-surface-color);
            padding: 0.95rem 1rem;
            height: 100%;
        }

        .salary-count-card span {
            display: block;
            font-size: 0.8rem;
            color: var(--admin-muted-text-color);
            margin-bottom: 0.35rem;
        }

        .salary-count-card strong {
            font-size: 1.2rem;
            color: var(--admin-text-color);
        }

        .salary-report-actions {
            min-width: 220px;
        }

        .salary-report-actions .btn {
            width: 100%;
            text-align: left;
            white-space: normal;
        }

        .salary-hold-form {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            min-width: 0;
        }

        .salary-hold-form .form-control,
        .salary-hold-form .btn {
            width: 100%;
            min-width: 0 !important;
        }

        .salary-hold-form .btn {
            white-space: normal;
        }

        .salary-report-table .salary-attendance-actions {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .salary-report-table .salary-attendance-actions .btn {
            width: 100%;
            min-width: 0;
            white-space: normal;
        }

        .salary-report-table {
            font-size: 12px;
            line-height: 1.25;
        }

        .salary-report-table th,
        .salary-report-table td {
            padding: 0.45rem 0.5rem;
        }

        .salary-report-table thead th {
            white-space: normal;
            overflow-wrap: anywhere;
            line-height: 1.15;
            vertical-align: middle;
        }

        .salary-report-table td {
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .salary-net-breakdown {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
            margin-top: 0.25rem;
            line-height: 1.25;
        }

        .salary-net-breakdown span {
            display: block;
        }
    </style>

    <div class="main-content attendance-page">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">{{ $reportPageTitle }}</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin-dashboard') }}"><i class="bx bx-home-alt"></i></a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">{{ $reportPageTitle }}</li>
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
                        <h4 class="mb-1 attendance-title">{{ $reportPageTitle }}</h4>
                        <p class="mb-0 attendance-muted">{{ $reportPageDescription }}</p>
                    </div>
                    <div class="d-flex {{ $reportView === 'salary' ? 'flex-column salary-report-actions' : 'flex-wrap' }} gap-2">
                        @if ($reportView === 'salary')
                            <a href="{{ route('admin-salary-reports-export', request()->query()) }}"
                                class="btn btn-primary">
                                Download Excel
                            </a>
                            <a href="{{ route('admin-salary-reports-export', array_merge(request()->query(), ['format' => 'statewise'])) }}"
                                class="btn btn-outline-primary">
                                Download Statewise Excel
                            </a>
                            <a href="{{ route('admin-salary-reports-export', array_merge(request()->query(), ['format' => 'pf-employees'])) }}"
                                class="btn btn-outline-primary">
                                PF Employee Salary Excel
                            </a>
                            <a href="{{ route('admin-salary-reports-export', array_merge(request()->query(), ['format' => 'salary-hold'])) }}"
                                class="btn btn-outline-danger">
                                Salary Hold Excel
                            </a>
                        @endif
                        <a href="{{ route('admin-salary-advance') }}" class="btn btn-outline-primary">Open Advance
                            Details</a>
                    </div>
                </div>

                <form method="get" class="row g-3 align-items-end">
                    @if ($filters['menu'] !== '')
                        <input type="hidden" name="menu" value="{{ $filters['menu'] }}">
                    @endif
                    <div class="col-md-2">
                        <label class="form-label">Month</label>
                        <input type="month" name="month" value="{{ $filters['month'] }}" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">From</label>
                        <input type="date" name="from_date" value="{{ $filters['start_date'] }}" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To</label>
                        <input type="date" name="to_date" value="{{ $filters['end_date'] }}" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Scope</label>
                        <select name="scope" class="form-select">
                            <option value="all" @selected($filters['scope'] === 'all')>All</option>
                            <option value="ho" @selected($filters['scope'] === 'ho')>HO</option>
                            <option value="branch" @selected($filters['scope'] === 'branch')>Branch</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">State, City or Branch</label>
                        <input type="text" name="location_search" value="{{ $filters['location_search'] }}"
                            class="form-control" list="reportLocationOptions"
                            placeholder="Type state, city, branch ID or branch name" autocomplete="off">
                        <datalist id="reportLocationOptions">
                            @foreach ($filters['location_options'] as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </datalist>
                        <div class="form-text">Suggestions use active branches only.</div>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100">Apply</button>
                        <a href="{{ route($reportRoute, array_filter(['menu' => $filters['menu']])) }}"
                            class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card rounded-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                    <div>
                        <h5 class="mb-1 attendance-title">
                            @if ($reportView === 'attendance')
                                Attendance Report
                            @elseif ($reportView === 'salary')
                                Salary Report
                            @else
                                Advance Report
                            @endif
                        </h5>
                        <p class="mb-0 attendance-muted">Copy, CSV and print options are attached to this report table.</p>
                    </div>
                    <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-2">
                        <form method="get" class="d-flex gap-2">
                            @foreach ($reportBaseQuery as $key => $value)
                                @if (!is_array($value))
                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                @endif
                            @endforeach
                            <input type="search" name="table_search" value="{{ $filters['table_search'] }}"
                                class="form-control" placeholder="Search report" autocomplete="off">
                            <select name="per_page" class="form-select" style="width: 92px;" onchange="this.form.submit()">
                                @foreach ([25, 50, 100, 200] as $perPageOption)
                                    <option value="{{ $perPageOption }}" @selected((int) $filters['per_page'] === $perPageOption)>{{ $perPageOption }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-primary">Search</button>
                            @if ($filters['table_search'] !== '')
                                <a href="{{ route($reportRoute, $reportBaseQuery) }}"
                                    class="btn btn-outline-secondary">Clear</a>
                            @endif
                        </form>
                        <div class="datatable-toolbar"></div>
                    </div>
                </div>

                <div class="table-responsive attendance-report-responsive">
                    @if ($reportView === 'advance')
                        <table class="table table-bordered table-hover align-middle js-admin-datatable"
                            data-admin-datatable="true" data-admin-searching="false" data-admin-paging="false"
                            data-admin-info="false" data-admin-serial-offset="{{ $reportFirstItem - 1 }}">
                            <thead>
                                <tr>
                                    <th>S.No</th>
                                    <th>Emp ID</th>
                                    <th>Name</th>
                                    <th>Designation</th>
                                    <th>Branch</th>
                                    <th>Last Advance Date</th>
                                    <th>Transactions</th>
                                    <th>Advance In Window</th>
                                    <th>PF</th>
                                    <th>View Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reportRows as $row)
                                    <tr>
                                        <td>{{ $reportFirstItem + $loop->index }}</td>
                                        <td>{{ $row['emp_id'] }}</td>
                                        <td>{{ $row['employee_name'] }}</td>
                                        <td>{{ $row['designation'] ?: '--' }}</td>
                                        <td>
                                            <div>{{ $row['branch_id'] ?: '--' }}</div>
                                            <small
                                                class="text-muted">{{ $row['branch_name'] ?: 'Branch unavailable' }}</small>
                                        </td>
                                        <td>{{ $row['last_advance_date'] ?: '--' }}</td>
                                        <td>{{ $row['transaction_count'] }}</td>
                                        <td>{{ 'Rs ' . number_format($row['total_advance'], 0) }}</td>
                                        <td>{{ 'Rs ' . number_format($row['pf'], 0) }}</td>
                                        <td>
                                            @php
                                                $advanceHistoryEmpId = trim((string) $row['emp_id']);
                                            @endphp
                                            @if ($advanceHistoryEmpId !== '')
                                                <a href="{{ route('admin-salary-advance-history', ['empId' => $advanceHistoryEmpId, 'from_date' => $filters['advance_window_start'], 'to_date' => $filters['advance_window_end']]) }}"
                                                    class="btn btn-primary btn-sm">
                                                    View Details
                                                </a>
                                            @else
                                                <span class="badge bg-danger">Employee ID missing</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @elseif ($reportView === 'salary')
                        <table
                            class="table table-bordered table-hover align-middle js-admin-datatable attendance-report-table salary-report-table"
                            data-admin-datatable="true" data-admin-searching="false" data-admin-paging="false"
                            data-admin-info="false" data-admin-scroll-x="false"
                            data-admin-serial-offset="{{ $reportFirstItem - 1 }}">
                            <thead>
                                <tr>
                                    <th>S.No</th>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Bank Name</th>
                                    <th>Name as per A/C</th>
                                    <th>A/C Number</th>
                                    <th>IFSC Code</th>
                                    <th>UAN Number</th>
                                    <th>Base Salary</th>
                                    <th>Security Deposit</th>
                                    <th>Salary Hold</th>
                                    <th>Net Salary</th>
                                    <th>Payable Days</th>
                                    <th>Attendance Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reportRows as $row)
                                    <tr>
                                        <td>{{ $reportFirstItem + $loop->index }}</td>
                                        <td>{{ $row['employee_name'] }}</td>
                                        <td>{{ $row['contact'] ?: '--' }}</td>
                                        <td>{{ $row['bank_name'] ?: '--' }}</td>
                                        <td>{{ $row['account_name'] ?: '--' }}</td>
                                        <td>{{ $row['bank_account_number'] ?: '--' }}</td>
                                        <td>{{ $row['ifsc_code'] ?: '--' }}</td>
                                        <td>{{ $row['uan_number'] ?: '--' }}</td>
                                        <td>{{ $row['salary'] !== null ? 'Rs ' . number_format((float) $row['salary'], 0) : '--' }}
                                        </td>
                                        <td>{{ 'Rs ' . number_format($row['security_deposit'] ?? 0, 0) }}</td>
                                        <td>
                                            @if ($row['salary_on_hold'] ?? false)
                                                <span class="badge bg-danger mb-2">On Hold</span>
                                                @if (($row['salary_hold_reason'] ?? '') !== '')
                                                    <small class="d-block text-muted mb-2">{{ $row['salary_hold_reason'] }}</small>
                                                @endif
                                                <form method="post" action="{{ route('admin-salary-reports-hold') }}"
                                                    class="salary-hold-form">
                                                    @csrf
                                                    <input type="hidden" name="employee_id" value="{{ $row['id'] }}">
                                                    <input type="hidden" name="payroll_month" value="{{ $filters['calendar_month'] }}">
                                                    <input type="hidden" name="action" value="release">
                                                    <button type="submit" class="btn btn-sm btn-outline-success">Release</button>
                                                </form>
                                            @else
                                                <form method="post" action="{{ route('admin-salary-reports-hold') }}"
                                                    class="salary-hold-form">
                                                    @csrf
                                                    <input type="hidden" name="employee_id" value="{{ $row['id'] }}">
                                                    <input type="hidden" name="payroll_month" value="{{ $filters['calendar_month'] }}">
                                                    <input type="hidden" name="action" value="hold">
                                                    <input type="text" name="reason" class="form-control form-control-sm"
                                                        placeholder="Reason (optional)">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Hold</button>
                                                </form>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($row['net_payable_salary'] !== null)
                                                <div>{{ 'Rs ' . number_format($row['net_payable_salary'], 0) }}</div>
                                                <small class="text-muted salary-net-breakdown">
                                                    <span>Gross {{ $row['gross_payable_salary'] !== null ? 'Rs ' . number_format($row['gross_payable_salary'], 0) : '--' }}</span>
                                                    <span>Advance {{ 'Rs ' . number_format($row['advance'], 0) }}</span>
                                                    <span>PF {{ 'Rs ' . number_format($row['pf'], 0) }}</span>
                                                    <span>Security Deposit {{ 'Rs ' . number_format($row['security_deposit'] ?? 0, 0) }}</span>
                                                </small>
                                            @else
                                                --
                                            @endif
                                        </td>
                                        <td>{{ $row['payable_days_label'] ?? rtrim(rtrim(number_format((float) ($row['payable_days'] ?? 0), 1, '.', ''), '0'), '.') }}</td>
                                        <td>
                                            <div class="salary-attendance-actions">
                                                <button type="button" class="btn btn-outline-primary btn-sm"
                                                    data-salary-attendance='{{ json_encode(
                                                        [
                                                            'empId' => $row['emp_id'],
                                                            'employeeName' => $row['employee_name'],
                                                            'designation' => $row['designation'] ?? '',
                                                            'state' => $row['state'] ?? '',
                                                            'presentDays' => number_format((float) $row['present_days'], 1, '.', ''),
                                                            'fullDays' => $row['full_days'],
                                                            'halfDays' => $row['half_days'],
                                                            'singlePunches' => $row['single_punches'],
                                                            'weekOffDays' => $row['week_off_days'],
                                                            'paidSundayDays' => $row['paid_sunday_days'] ?? 0,
                                                            'absentDays' => $row['absent_days'],
                                                            'regularizedDays' => $row['regularized_days'],
                                                            'creditedDays' => number_format((float) $row['credited_days'], 1, '.', ''),
                                                            'payableDays' => $row['payable_days_label'] ?? rtrim(rtrim(number_format((float) ($row['payable_days'] ?? 0), 1, '.', ''), '0'), '.'),
                                                            'baseSalary' => $row['salary'],
                                                            'salaryPerDay' => $row['salary_per_day'],
                                                            'salaryDaysInMonth' => $row['salary_days_in_month'] ?? 30,
                                                            'grossSalary' => $row['gross_payable_salary'],
                                                            'advance' => $row['advance'],
                                                            'pf' => $row['pf'],
                                                            'securityDeposit' => $row['security_deposit'] ?? 0,
                                                            'netSalary' => $row['net_payable_salary'],
                                                        ],
                                                        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP,
                                                    ) }}'
                                                    onclick="openSalaryAttendanceModal(this.dataset.salaryAttendance)">
                                                    Show Count
                                                </button>
                                                @unless ($hideCalendarForHrReportUser)
                                                <button type="button" class="btn btn-primary btn-sm"
                                                    onclick="openAttendanceCalendar('{{ $row['emp_id'] }}', '{{ $filters['month'] }}', '{{ $row['branch_id'] }}', '{{ $filters['start_date'] }}', '{{ $filters['end_date'] }}')">
                                                    Calendar
                                                </button>
                                                @endunless
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <table
                            class="table table-bordered table-hover align-middle js-admin-datatable attendance-report-table attendance-count-report-table"
                            data-admin-datatable="true" data-admin-searching="false" data-admin-paging="false"
                            data-admin-info="false" data-admin-scroll-x="false"
                            data-admin-serial-offset="{{ $reportFirstItem - 1 }}">
                            <thead>
                                <tr>
                                    <th>S.No</th>
                                    <th>Emp ID</th>
                                    <th>Name</th>
                                    <th>Designation</th>
                                    <th>Branch</th>
                                    <th>Status</th>
                                    <th>Days Present</th>
                                    <th>Half Days</th>
                                    <th>Payable Days</th>
                                    <th>Week Off Days</th>
                                    <th>Days Absent</th>
                                    <th>Single Punches</th>
                                    <th>Regularized Days</th>
                                    <th>Completed Punches</th>
                                    <th>Late Logins</th>
                                    <th>Last Attendance</th>
                                    @unless ($hideCalendarForHrReportUser)
                                    <th>Calendar</th>
                                    @endunless
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reportRows as $row)
                                    <tr>
                                        <td>{{ $reportFirstItem + $loop->index }}</td>
                                        <td>{{ $row['emp_id'] }}</td>
                                        <td>{{ $row['employee_name'] }}</td>
                                        <td>{{ $row['designation'] ?: '--' }}</td>
                                        <td>
                                            <div>{{ $row['branch_id'] ?: '--' }}</div>
                                            <small
                                                class="text-muted">{{ $row['branch_name'] ?: 'Branch unavailable' }}</small>
                                        </td>
                                        <td>
                                            <span
                                                class="attendance-status-pill {{ strtolower($row['status']) === 'blocked' ? 'blocked' : 'active' }}">
                                                {{ $row['status'] }}
                                            </span>
                                        </td>
                                        <td>{{ $row['present_days'] }}</td>
                                        <td>{{ $row['half_days'] }}</td>
                                        <td>{{ $row['payable_days_label'] ?? rtrim(rtrim(number_format((float) ($row['payable_days'] ?? 0), 1, '.', ''), '0'), '.') }}</td>
                                        <td>{{ $row['week_off_days'] }}</td>
                                        <td>{{ $row['absent_days'] }}</td>
                                        <td>{{ $row['single_punches'] }}</td>
                                        <td>{{ $row['regularized_days'] }}</td>
                                        <td>{{ $row['completed_punches'] }}</td>
                                        <td>
                                            <button type="button"
                                                class="btn btn-outline-primary btn-sm attendance-late-trigger"
                                                data-late-payload='{{ json_encode(
                                                    [
                                                        'empId' => $row['emp_id'],
                                                        'employeeName' => $row['employee_name'],
                                                        'schedule' => $row['punctuality_schedule'],
                                                        'lateDays' => $row['late_logins'],
                                                        'earlyLogoutDays' => $row['early_logouts'],
                                                        'irregularDays' => $row['irregular_days'],
                                                        'details' => $row['late_details'],
                                                    ],
                                                    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP,
                                                ) }}'
                                                onclick="openLateAttendanceModal(this.dataset.latePayload)">
                                                {{ $row['late_logins'] }} late
                                            </button>
                                            <div class="small text-muted mt-1">{{ $row['early_logouts'] }} early logout
                                            </div>
                                        </td>
                                        <td>{{ $row['last_present_date'] ?: '--' }}</td>
                                        @unless ($hideCalendarForHrReportUser)
                                        <td>
                                            <button type="button" class="btn btn-primary btn-sm"
                                                onclick="openAttendanceCalendar('{{ $row['emp_id'] }}', '{{ $filters['month'] }}', '{{ $row['branch_id'] }}', '{{ $filters['start_date'] }}', '{{ $filters['end_date'] }}')">
                                                View Calendar
                                            </button>
                                        </td>
                                        @endunless
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mt-3">
                    <div class="attendance-muted">
                        Showing {{ $reportPaginator->firstItem() ?? 0 }} to {{ $reportPaginator->lastItem() ?? 0 }}
                        of {{ $reportPaginator->total() }} rows
                    </div>
                    {{ $reportPaginator->onEachSide(1)->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="salaryAttendanceModal" tabindex="-1" aria-labelledby="salaryAttendanceModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <h5 class="modal-title" id="salaryAttendanceModalLabel">Attendance Count</h5>
                        <p class="mb-0 attendance-muted" id="salaryAttendanceModalSubtitle">Selected period attendance
                            summary</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="row g-3 mb-3" id="salaryAttendanceSummary"></div>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <tbody>
                                <tr>
                                    <th style="width: 260px;">Salary Per Day</th>
                                    <td id="salaryAttendanceSalaryPerDay">--</td>
                                </tr>
                                <tr>
                                    <th>Gross Salary From Attendance</th>
                                    <td id="salaryAttendanceGrossSalary">--</td>
                                </tr>
                                <tr>
                                    <th>Advance Deduction</th>
                                    <td id="salaryAttendanceAdvance">--</td>
                                </tr>
                                <tr>
                                    <th>PF Deduction</th>
                                    <td id="salaryAttendancePf">--</td>
                                </tr>
                                <tr>
                                    <th>Security Deposit</th>
                                    <td id="salaryAttendanceSecurityDeposit">--</td>
                                </tr>
                                <tr>
                                    <th>Net Salary</th>
                                    <td id="salaryAttendanceNetSalary">--</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-responsive mt-3">
                        <table class="table table-bordered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>SL NO</th>
                                    <th>Designatoin</th>
                                    <th>Gross Salary</th>
                                    <th>BASIC</th>
                                    <th>DA</th>
                                    <th>BASIC + DA SALARY</th>
                                    <th>PF RATE OF WAGES</th>
                                    <th>OTHER ALLOWANCES</th>
                                    <th>GROSS SALARY</th>
                                    <th>EE-PF -12% ON BASIC</th>
                                    <th>ESI - 0.75% ON GROSS</th>
                                    <th>PT</th>
                                    <th>Advance</th>
                                    <th>Security Deposit</th>
                                    <th>TOTAL DEDUCTIONS</th>
                                    <th>TAKE HOME SALARY</th>
                                    <th>ER-PF -12%</th>
                                    <th>ER-ESI-3.25%</th>
                                    <th>CTC</th>
                                </tr>
                            </thead>
                            <tbody id="salaryAttendancePfBreakupBody">
                                <tr>
                                    <td colspan="19" class="text-center text-muted">Breakup will appear here.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="attendanceLateModal" tabindex="-1" aria-labelledby="attendanceLateModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <h5 class="modal-title" id="attendanceLateModalLabel">Late Login Details</h5>
                        <p class="mb-0 attendance-muted" id="attendanceLateModalSubtitle">Selected period details</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="row g-3 mb-3" id="attendanceLateSummary"></div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Source</th>
                                    <th>Check In</th>
                                    <th>Shift Start</th>
                                    <th>Late By</th>
                                    <th>Check Out</th>
                                    <th>Shift End</th>
                                    <th>Left Early By</th>
                                </tr>
                            </thead>
                            <tbody id="attendanceLateModalBody">
                                <tr>
                                    <td colspan="8" class="text-center text-muted">Select a value from the Late Logins
                                        column.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @unless ($hideCalendarForHrReportUser)
        @include('admin.attendance.partials.calendar_modal')
    @endunless

    <script>
        (() => {
            const reportView = @json($reportView);
            const monthInput = document.querySelector('input[name="month"]');
            const fromInput = document.querySelector('input[name="from_date"]');
            const toInput = document.querySelector('input[name="to_date"]');

            if (!monthInput || !fromInput || !toInput) {
                return;
            }

            monthInput.addEventListener('change', () => {
                const value = monthInput.value;

                if (!/^\d{4}-\d{2}$/.test(value)) {
                    return;
                }

                const [year, month] = value.split('-').map(Number);
                const lastDay = new Date(year, month, 0).getDate();
                const paddedMonth = String(month).padStart(2, '0');

                if (reportView === 'advance') {
                    const advanceEndDate = new Date(year, month, 11);
                    const advanceEndMonth = String(advanceEndDate.getMonth() + 1).padStart(2, '0');
                    const advanceEndDay = String(advanceEndDate.getDate()).padStart(2, '0');

                    fromInput.value = `${year}-${paddedMonth}-13`;
                    toInput.value = `${advanceEndDate.getFullYear()}-${advanceEndMonth}-${advanceEndDay}`;
                    return;
                }

                fromInput.value = `${year}-${paddedMonth}-01`;
                toInput.value = `${year}-${paddedMonth}-${String(lastDay).padStart(2, '0')}`;
            });
        })();

        function escapeLateHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function parseAttendanceLatePayload(payload) {
            if (!payload) {
                return {};
            }

            if (typeof payload === 'string') {
                try {
                    return JSON.parse(payload);
                } catch (error) {
                    console.error('Unable to parse attendance late payload.', error);
                    return {};
                }
            }

            return payload;
        }

        function getAttendanceLateModal() {
            const modalElement = document.getElementById('attendanceLateModal');

            if (!modalElement || !window.bootstrap || !bootstrap.Modal) {
                return {
                    element: modalElement,
                    instance: null
                };
            }

            return {
                element: modalElement,
                instance: bootstrap.Modal.getOrCreateInstance(modalElement)
            };
        }

        window.openLateAttendanceModal = function(payload) {
            const parsedPayload = parseAttendanceLatePayload(payload);
            const {
                element: attendanceLateModalElement,
                instance: attendanceLateModalInstance
            } = getAttendanceLateModal();

            if (!attendanceLateModalElement || !attendanceLateModalInstance) {
                return;
            }

            const subtitle = document.getElementById('attendanceLateModalSubtitle');
            const summary = document.getElementById('attendanceLateSummary');
            const body = document.getElementById('attendanceLateModalBody');
            const details = Array.isArray(parsedPayload?.details) ? parsedPayload.details : [];

            if (subtitle) {
                subtitle.textContent =
                    `${parsedPayload?.employeeName || 'Employee'} (${parsedPayload?.empId || '--'}) | Shift ${parsedPayload?.schedule || '--'}`;
            }

            if (summary) {
                summary.innerHTML = `
                    <div class="col-md-4">
                        <div class="attendance-late-summary-card">
                            <span>Late Days</span>
                            <strong>${escapeLateHtml(parsedPayload?.lateDays ?? 0)}</strong>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="attendance-late-summary-card">
                            <span>Early Logout Days</span>
                            <strong>${escapeLateHtml(parsedPayload?.earlyLogoutDays ?? 0)}</strong>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="attendance-late-summary-card">
                            <span>Irregular Days In Selected Period</span>
                            <strong>${escapeLateHtml(parsedPayload?.irregularDays ?? 0)}</strong>
                        </div>
                    </div>
                `;
            }

            if (body) {
                body.innerHTML = details.length ?
                    details.map((detail) => `
                        <tr>
                            <td>${escapeLateHtml(detail.date_label || detail.date || '--')}</td>
                            <td>${escapeLateHtml(detail.source || '--')}</td>
                            <td>${escapeLateHtml(detail.check_in || '--')}</td>
                            <td>${escapeLateHtml(detail.shift_start || '--')}</td>
                            <td>${escapeLateHtml(detail.late_label || '--')}</td>
                            <td>${escapeLateHtml(detail.check_out || '--')}</td>
                            <td>${escapeLateHtml(detail.shift_end || '--')}</td>
                            <td>${escapeLateHtml(detail.early_logout_label || '--')}</td>
                        </tr>
                    `).join('') :
                    '<tr><td colspan="8" class="text-center text-muted">No late or early logout days in the selected period.</td></tr>';
            }

            attendanceLateModalInstance.show();
        };

        function parseSalaryAttendancePayload(payload) {
            if (!payload) {
                return {};
            }

            if (typeof payload === 'string') {
                try {
                    return JSON.parse(payload);
                } catch (error) {
                    console.error('Unable to parse salary attendance payload.', error);
                    return {};
                }
            }

            return payload;
        }

        function formatSalaryCurrency(value) {
            const numericValue = Number(value);

            if (!Number.isFinite(numericValue)) {
                return '--';
            }

            return `Rs ${Math.round(numericValue)}`;
        }

        function formatAttendanceCount(value, decimals = 0) {
            const numericValue = Number(value);
            if (!Number.isFinite(numericValue)) {
                return decimals > 0 ? (0).toFixed(decimals) : '0';
            }

            return decimals > 0 ? numericValue.toFixed(decimals) : String(Math.trunc(numericValue));
        }

        // State + designation-tier matrix for monthly BASIC and DA values.
        const PF_BASIC_DA_BY_EMPLOYEE = {
            '1000652': { basic: 21000, da: 7000 },
            '1001604': { basic: 21000, da: 7000 },
            '1002187': { basic: 12750, da: 5000 },
            '1003276': { basic: 12750, da: 5000 },
            '1004912': { basic: 12750, da: 5000 },
            '1004948': { basic: 15000, da: 5000 },
            '1005065': { basic: 12750, da: 5000 },
            '1004940': { basic: 24000, da: 7000 },
            '1004265': { basic: 15000, da: 4550 },
            '1000042': { basic: 18000, da: 7000 },
            '1005113': { basic: 21000, da: 7000 },
            '1004823': { basic: 16000, da: 4550 },
            '1003040': { basic: 12750, da: 5000 },
            '1002651': { basic: 20000, da: 7000 },
            '1002867': { basic: 12750, da: 5000 },
            '1000423': { basic: 16000, da: 4550 },
            '1000735': { basic: 17000, da: 6000 },
            '1001627': { basic: 17000, da: 6000 },
            '1000336': { basic: 17000, da: 6000 },
            '1004975': { basic: 14000, da: 6000 },
        };

        const PF_BASIC_DA_BY_STATE_AND_TIER = {
            'KA': {
                'senior': {
                    basic: 14200,
                    da: 4550
                },
                'middle': {
                    basic: 12750,
                    da: 4550
                },
                'junior': {
                    basic: 11600,
                    da: 4550
                },
            },
            'AP-TS': {
                'te': {
                    basic: 4102,
                    da: 9408
                },
                'abm': {
                    basic: 4722,
                    da: 9408
                },
                'bm': {
                    basic: 5557,
                    da: 9408
                },
            },
            'TN': {
                'te-junior': {
                    basic: 6691,
                    da: 7353
                },
                'gunman': {
                    basic: 8000,
                    da: 7353
                },
                'abm': {
                    basic: 6880,
                    da: 7353
                },
                'bm': {
                    basic: 7390,
                    da: 7353
                },
            },
        };

        function normalizePfState(value) {
            const normalized = String(value || '').trim().toUpperCase();
            if (!normalized) {
                return '';
            }

            if (normalized === 'KA' || normalized.includes('KARNATAKA')) {
                return 'KA';
            }
            if (
                normalized === 'TN' ||
                normalized.includes('TAMIL')
            ) {
                return 'TN';
            }
            if (
                normalized === 'AP-TS' ||
                normalized === 'AP/TS' ||
                normalized.includes('ANDHRA') ||
                normalized.includes('TELANGANA') ||
                normalized.includes('AP') ||
                normalized.includes('TS')
            ) {
                return 'AP-TS';
            }

            return normalized;
        }

        function isBmDesignation(value) {
            return value === 'bm' ||
                value.includes('branch-manager') ||
                value.startsWith('bm-') ||
                value.endsWith('-bm');
        }

        function resolvePfDesignationTier(stateKey, value) {
            const normalized = String(value || '')
                .trim()
                .toLowerCase()
                .replace(/[_\s]+/g, '-');

            if (stateKey === 'KA') {
                if (
                    normalized.includes('senior') ||
                    normalized.includes('cashier') ||
                    normalized.includes('zonal') ||
                    isBmDesignation(normalized)
                ) {
                    return 'senior';
                }
                if (
                    normalized.includes('driver') ||
                    normalized.includes('bouncer') ||
                    normalized.includes('housekeeping') ||
                    normalized.includes('house-keeping') ||
                    normalized.includes('house-keeper') ||
                    normalized.includes('gunman') ||
                    normalized.includes('junior')
                ) {
                    return 'junior';
                }

                return 'middle';
            }

            if (stateKey === 'AP-TS') {
                if (normalized.includes('abm') || normalized.includes('assistant-branch-manager')) {
                    return 'abm';
                }

                return isBmDesignation(normalized) ? 'bm' : 'te';
            }

            if (stateKey === 'TN') {
                if (normalized.includes('gunman') || normalized.includes('gun-man')) {
                    return 'gunman';
                }
                if (normalized.includes('abm') || normalized.includes('assistant-branch-manager')) {
                    return 'abm';
                }

                return isBmDesignation(normalized) ? 'bm' : 'te-junior';
            }

            return '';
        }

        function resolveBasicDaMonthly(parsedPayload) {
            const employeeOverride = PF_BASIC_DA_BY_EMPLOYEE[String(parsedPayload?.empId || '').trim()];

            if (employeeOverride) {
                return {
                    stateKey: normalizePfState(parsedPayload?.state),
                    tierKey: 'employee-override',
                    basicMonthly: employeeOverride.basic,
                    daMonthly: employeeOverride.da,
                };
            }

            const stateKey = normalizePfState(parsedPayload?.state);
            const tierKey = resolvePfDesignationTier(stateKey, parsedPayload?.designation);
            const mapped = PF_BASIC_DA_BY_STATE_AND_TIER?.[stateKey]?.[tierKey];

            if (mapped && Number.isFinite(Number(mapped.basic)) && Number.isFinite(Number(mapped.da))) {
                return {
                    stateKey,
                    tierKey,
                    basicMonthly: Number(mapped.basic),
                    daMonthly: Number(mapped.da),
                };
            }

            const defaultByState = {
                'AP-TS': PF_BASIC_DA_BY_STATE_AND_TIER['AP-TS'].te,
                'TN': PF_BASIC_DA_BY_STATE_AND_TIER.TN['te-junior'],
            };
            const fallback = defaultByState[stateKey] || PF_BASIC_DA_BY_STATE_AND_TIER.KA.middle;

            return {
                stateKey,
                tierKey,
                basicMonthly: fallback.basic,
                daMonthly: fallback.da,
            };
        }

        function ceilTo(value, significance) {
            const numericValue = Number(value);
            const numericSignificance = Number(significance);

            if (!Number.isFinite(numericValue) || !Number.isFinite(numericSignificance) || numericSignificance <= 0) {
                return 0;
            }

            return Math.ceil(numericValue / numericSignificance) * numericSignificance;
        }

        function computePfSheetBreakup(parsedPayload) {
            const salaryDaysInMonth = Number(parsedPayload?.salaryDaysInMonth) > 0 ? Number(parsedPayload
                .salaryDaysInMonth) : 30;
            const creditedDays = Number(parsedPayload?.creditedDays);
            const grossSalaryInput = Number.isFinite(Number(parsedPayload?.grossSalary)) ?
                Number(parsedPayload.grossSalary) :
                (Number(parsedPayload?.salaryPerDay) || 0) * (Number.isFinite(creditedDays) ? creditedDays : 0);
            const basicDaMonthly = resolveBasicDaMonthly(parsedPayload);
            let proRatedBasic = salaryDaysInMonth > 0 && Number.isFinite(creditedDays) ?
                (basicDaMonthly.basicMonthly / salaryDaysInMonth) * creditedDays :
                0;
            let proRatedDa = salaryDaysInMonth > 0 && Number.isFinite(creditedDays) ?
                (basicDaMonthly.daMonthly / salaryDaysInMonth) * creditedDays :
                0;
            let basicPlusDa = proRatedBasic + proRatedDa;

            if (basicPlusDa > grossSalaryInput && basicPlusDa > 0) {
                proRatedBasic = Math.round((grossSalaryInput * (proRatedBasic / basicPlusDa)) * 100) / 100;
                proRatedDa = grossSalaryInput - proRatedBasic;
                basicPlusDa = grossSalaryInput;
            }

            const pfRateOfWages = Math.min(15000, basicPlusDa);
            const otherAllowances = Math.max(0, grossSalaryInput - basicPlusDa);
            const grossSalary = basicPlusDa + otherAllowances;
            const eePf = Math.round((pfRateOfWages * 0.12) * 100) / 100;
            const esi = 0;
            const pt = 0;
            const advance = Number(parsedPayload?.advance) || 0;
            const securityDeposit = Number(parsedPayload?.securityDeposit) || 0;
            const totalDeductions = eePf + esi + pt + advance + securityDeposit;
            const takeHomeSalary = grossSalary - totalDeductions;
            const erPf = Math.round((pfRateOfWages * 0.12) * 100) / 100;
            const erEsi = 0;
            const ctc = grossSalary + erPf + erEsi;

            return {
                slNo: 1,
                designation: parsedPayload?.designation || '--',
                state: parsedPayload?.state || '--',
                tier: basicDaMonthly.tierKey || '--',
                grossSalaryInput,
                basic: proRatedBasic,
                da: proRatedDa,
                basicPlusDa,
                pfRateOfWages,
                otherAllowances,
                grossSalary,
                eePf,
                esi,
                pt,
                advance,
                securityDeposit,
                totalDeductions,
                takeHomeSalary,
                erPf,
                erEsi,
                ctc
            };
        }

        window.openSalaryAttendanceModal = function(payload) {
            const parsedPayload = parseSalaryAttendancePayload(payload);
            const modalElement = document.getElementById('salaryAttendanceModal');

            if (!modalElement || !window.bootstrap || !bootstrap.Modal) {
                return;
            }

            const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
            const subtitle = document.getElementById('salaryAttendanceModalSubtitle');
            const summary = document.getElementById('salaryAttendanceSummary');

            if (subtitle) {
                const stateLabel = normalizePfState(parsedPayload?.state) || '--';
                const tierLabel = resolvePfDesignationTier(stateLabel, parsedPayload?.designation) || '--';
                subtitle.textContent =
                    `${parsedPayload.employeeName || 'Employee'} (${parsedPayload.empId || '--'}) | ${stateLabel} | ${tierLabel}`;
            }

            if (summary) {
                summary.innerHTML = `
                    <div class="col-md-3">
                        <div class="salary-count-card">
                            <span>Credited Days</span>
                            <strong>${escapeLateHtml(formatAttendanceCount(parsedPayload.creditedDays, 1))}</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="salary-count-card">
                            <span>Payable Days</span>
                            <strong>${escapeLateHtml(parsedPayload.payableDays ?? 0)}</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="salary-count-card">
                            <span>Full Days</span>
                            <strong>${escapeLateHtml(formatAttendanceCount(parsedPayload.fullDays))}</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="salary-count-card">
                            <span>Half Days</span>
                            <strong>${escapeLateHtml(formatAttendanceCount(parsedPayload.halfDays))}</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="salary-count-card">
                            <span>Present Days</span>
                            <strong>${escapeLateHtml(formatAttendanceCount(parsedPayload.presentDays, 1))}</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="salary-count-card">
                            <span>Single Punches</span>
                            <strong>${escapeLateHtml(formatAttendanceCount(parsedPayload.singlePunches))}</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="salary-count-card">
                            <span>Week Off Days</span>
                            <strong>${escapeLateHtml(formatAttendanceCount(parsedPayload.weekOffDays))}</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="salary-count-card">
                            <span>Absent Days</span>
                            <strong>${escapeLateHtml(formatAttendanceCount(parsedPayload.absentDays))}</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="salary-count-card">
                            <span>Regularized Days</span>
                            <strong>${escapeLateHtml(formatAttendanceCount(parsedPayload.regularizedDays))}</strong>
                        </div>
                    </div>
                `;
            }

            const salaryPerDay = document.getElementById('salaryAttendanceSalaryPerDay');
            const grossSalary = document.getElementById('salaryAttendanceGrossSalary');
            const advance = document.getElementById('salaryAttendanceAdvance');
            const pf = document.getElementById('salaryAttendancePf');
            const securityDeposit = document.getElementById('salaryAttendanceSecurityDeposit');
            const netSalary = document.getElementById('salaryAttendanceNetSalary');
            const pfBreakupBody = document.getElementById('salaryAttendancePfBreakupBody');

            if (salaryPerDay) {
                salaryPerDay.textContent = formatSalaryCurrency(parsedPayload.salaryPerDay);
            }

            if (grossSalary) {
                grossSalary.textContent = formatSalaryCurrency(parsedPayload.grossSalary);
            }

            if (advance) {
                advance.textContent = formatSalaryCurrency(parsedPayload.advance);
            }

            if (pf) {
                pf.textContent = formatSalaryCurrency(parsedPayload.pf);
            }

            if (securityDeposit) {
                securityDeposit.textContent = formatSalaryCurrency(parsedPayload.securityDeposit);
            }

            if (netSalary) {
                netSalary.textContent = formatSalaryCurrency(parsedPayload.netSalary);
            }

            if (pfBreakupBody) {
                const breakup = computePfSheetBreakup(parsedPayload);
                pfBreakupBody.innerHTML = `
                    <tr>
                        <td>${escapeLateHtml(breakup.slNo)}</td>
                        <td>${escapeLateHtml(breakup.designation)}</td>
                        <td>${escapeLateHtml(formatSalaryCurrency(breakup.grossSalaryInput))}</td>
                        <td>${escapeLateHtml(formatSalaryCurrency(breakup.basic))}</td>
                        <td>${escapeLateHtml(formatSalaryCurrency(breakup.da))}</td>
                        <td>${escapeLateHtml(formatSalaryCurrency(breakup.basicPlusDa))}</td>
                        <td>${escapeLateHtml(formatSalaryCurrency(breakup.pfRateOfWages))}</td>
                        <td>${escapeLateHtml(formatSalaryCurrency(breakup.otherAllowances))}</td>
                        <td>${escapeLateHtml(formatSalaryCurrency(breakup.grossSalary))}</td>
                        <td>${escapeLateHtml(formatSalaryCurrency(breakup.eePf))}</td>
                        <td>${escapeLateHtml(formatSalaryCurrency(breakup.esi))}</td>
                        <td>${escapeLateHtml(formatSalaryCurrency(breakup.pt))}</td>
                        <td>${escapeLateHtml(formatSalaryCurrency(breakup.advance))}</td>
                        <td>${escapeLateHtml(formatSalaryCurrency(breakup.securityDeposit))}</td>
                        <td>${escapeLateHtml(formatSalaryCurrency(breakup.totalDeductions))}</td>
                        <td>${escapeLateHtml(formatSalaryCurrency(breakup.takeHomeSalary))}</td>
                        <td>${escapeLateHtml(formatSalaryCurrency(breakup.erPf))}</td>
                        <td>${escapeLateHtml(formatSalaryCurrency(breakup.erEsi))}</td>
                        <td>${escapeLateHtml(formatSalaryCurrency(breakup.ctc))}</td>
                    </tr>
                `;
            }

            modal.show();
        };
    </script>
@endsection
