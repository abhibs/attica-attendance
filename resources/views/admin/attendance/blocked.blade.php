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
                        <li class="breadcrumb-item active" aria-current="page">Blocked Employees</li>
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
                        <h4 class="mb-1 attendance-title">Blocked Employees</h4>
                        <p class="mb-0 attendance-muted">
                            Review blocked users, filter by employee or branch, and unblock multiple employees at once.
                        </p>
                    </div>
                </div>

                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Employee ID</label>
                        <input type="text" name="emp_id" value="{{ $filters['emp_id'] }}" class="form-control"
                            placeholder="Optional">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">State</label>
                        <select name="state" class="form-select">
                            <option value="">All States</option>
                            @foreach ($filters['states'] as $state)
                                <option value="{{ $state }}" @selected($filters['state'] === $state)>{{ $state }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">City</label>
                        <select name="city" class="form-select">
                            <option value="">All Cities</option>
                            @foreach ($filters['cities'] as $city)
                                <option value="{{ $city }}" @selected($filters['city'] === $city)>{{ $city }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Branch</label>
                        <select name="branch_id" class="form-select">
                            <option value="">All Branches</option>
                            @foreach ($filters['branches'] as $branch)
                                <option value="{{ $branch->branchId }}" @selected($filters['branch_id'] === $branch->branchId)>
                                    {{ $branch->branchId }} - {{ $branch->branchName }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100">Apply</button>
                        <a href="{{ route('admin-attendance-blocked') }}" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card rounded-4">
            <div class="card-body">
                <form method="post" action="{{ route('admin-attendance-blocked-unblock') }}">
                    @csrf
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                        <div>
                            <h5 class="mb-1 attendance-title">Blocked Employee List</h5>
                            <p class="mb-0 attendance-muted">Select employees and unblock them in one action.</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <div class="datatable-toolbar"></div>
                            <button type="submit" class="btn btn-primary">Unblock Selected</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle js-admin-datatable"
                            data-admin-datatable="true">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="blockedSelectAll">
                                    </th>
                                    <th>Emp ID</th>
                                    <th>Name</th>
                                    <th>Designation</th>
                                    <th>Branch</th>
                                    <th>Region</th>
                                    <th>Blocked On</th>
                                    <th>Last Attendance</th>
                                    <th>Consecutive Absent</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="employee_ids[]" value="{{ $row['id'] }}"
                                                class="blocked-entry-checkbox">
                                        </td>
                                        <td>{{ $row['emp_id'] }}</td>
                                        <td>{{ $row['employee_name'] }}</td>
                                        <td>{{ $row['designation'] ?: '--' }}</td>
                                        <td>
                                            <div>{{ $row['branch_id'] ?: '--' }}</div>
                                            <small>{{ $row['branch_name'] ?: 'Branch unavailable' }}</small>
                                        </td>
                                        <td>{{ ($row['city'] ?: '--') . ', ' . ($row['state'] ?: '--') }}</td>
                                        <td>{{ $row['blocked_on'] ?: '--' }}</td>
                                        <td>{{ $row['last_attendance'] ?: '--' }}</td>
                                        <td>{{ $row['consecutive_absences'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const master = document.getElementById('blockedSelectAll');

            if (!master) {
                return;
            }

            master.addEventListener('change', function() {
                document.querySelectorAll('.blocked-entry-checkbox').forEach((checkbox) => {
                    checkbox.checked = master.checked;
                });
            });
        });
    </script>
@endsection
