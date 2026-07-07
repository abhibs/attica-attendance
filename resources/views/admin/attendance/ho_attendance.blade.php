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
                        <li class="breadcrumb-item active" aria-current="page">HO Attendance</li>
                    </ol>
                </nav>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success border-0">{{ session('status') }}</div>
        @endif

        <div class="card rounded-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                    <div>
                        <h4 class="mb-1 attendance-title">HO Attendance</h4>
                        <p class="mb-0 attendance-section-subtitle">
                            Imported Excel attendance dashboard with employee wise monthly calendar popup.
                        </p>
                    </div>
                    <a href="{{ route('admin-attendance-reports') }}" class="btn btn-grd-primary">Open Attendance Reports</a>
                </div>
            </div>
        </div>

        <div class="card rounded-4 mb-4">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Month</label>
                        <input type="month" name="month" value="{{ $filters['month'] }}" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Employee ID</label>
                        <input type="text" name="emp_id" value="{{ $filters['emp_id'] }}" class="form-control"
                            placeholder="Search employee id">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Employee Name</label>
                        <input type="text" name="employee_name" value="{{ $filters['employee_name'] }}" class="form-control"
                            placeholder="Search employee name">
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100">Apply</button>
                        <a href="{{ route('admin-attendance-ho') }}" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card rounded-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                    <div>
                        <h5 class="mb-1 attendance-title">Head Office Employee Attendance</h5>
                        <p class="mb-0 attendance-muted">Copy, CSV and print options are available for this table.</p>
                    </div>
                    <div class="datatable-toolbar"></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle js-admin-datatable"
                        data-admin-datatable="true">
                        <thead>
                            <tr>
                                    <th>Emp ID</th>
                                    <th>Name</th>
                                    <th>Present Days</th>
                                    <th>Half Days</th>
                                    <th>Week Off Days</th>
                                    <th>Absent Days</th>
                                    <th>Single Punches</th>
                                    <th>Total Logged Time</th>
                                    <th>Calendar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    <td>{{ $row['emp_id'] }}</td>
                                    <td>{{ $row['employee_name'] }}</td>
                                    <td>{{ $row['present_days'] }}</td>
                                    <td>{{ $row['half_days'] }}</td>
                                    <td>{{ $row['week_off_days'] }}</td>
                                    <td>{{ $row['absent_days'] }}</td>
                                    <td>{{ $row['single_punch_days'] }}</td>
                                    <td>{{ $row['logged_time_label'] }}</td>
                                    <td>
                                        <button type="button" class="btn btn-primary btn-sm"
                                            onclick="openAttendanceCalendar('{{ $row['emp_id'] }}', '{{ $filters['month'] }}', '')">
                                            View Calendar
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center fw-semibold">No imported HO attendance found for the selected month.</td>
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
@endsection
