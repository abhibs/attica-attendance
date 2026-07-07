@extends('admin.layout.app')

@section('content')
    @include('admin.attendance.partials.styles')

    <div class="main-content attendance-page">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Attendance</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin-dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Out of Office</li>
                    </ol>
                </nav>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success border-0">{{ session('status') }}</div>
        @endif

        <div class="row row-cols-1 row-cols-md-3 g-3 mb-4">
            <div class="col">
                <div class="card rounded-4 mb-0 attendance-stat-card">
                    <div class="card-body">
                        <p class="mb-1">Employees</p>
                        <h4 class="mb-0">{{ $summary['employees'] }}</h4>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card rounded-4 mb-0 attendance-stat-card">
                    <div class="card-body">
                        <p class="mb-1">Walked Out Days</p>
                        <h4 class="mb-0">{{ $summary['days'] }}</h4>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card rounded-4 mb-0 attendance-stat-card">
                    <div class="card-body">
                        <p class="mb-1">Flagged GPS Pings</p>
                        <h4 class="mb-0">{{ $summary['pings'] }}</h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="card rounded-4 mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
                    <div>
                        <h4 class="mb-1 attendance-title">Out of Office</h4>
                        <p class="mb-0 attendance-muted">Employees are listed here when GPS tracking places them more than 1 km from their branch during an active attendance session.</p>
                    </div>
                </div>

                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label">Month</label>
                        <input type="month" name="month" value="{{ $filters['month'] }}" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">From</label>
                        <input type="date" name="from_date" value="{{ $filters['from_date'] }}" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To</label>
                        <input type="date" name="to_date" value="{{ $filters['to_date'] }}" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Employee ID</label>
                        <input type="text" name="emp_id" value="{{ $filters['emp_id'] }}" class="form-control" placeholder="Optional">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">State</label>
                        <input type="search" name="state" class="form-control" list="outOfOfficeStateOptions" value="{{ $filters['state'] }}" placeholder="All States">
                        <datalist id="outOfOfficeStateOptions">
                            @foreach ($filters['states'] as $state)
                                <option value="{{ $state }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">City</label>
                        <input type="search" name="city" class="form-control" list="outOfOfficeCityOptions" value="{{ $filters['city'] }}" placeholder="All Cities">
                        <datalist id="outOfOfficeCityOptions">
                            @foreach ($filters['cities'] as $city)
                                <option value="{{ $city }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Branch</label>
                        <input type="hidden" name="branch_id" id="outOfOfficeBranchId" value="{{ $filters['branch_id'] }}">
                        <input type="search" id="outOfOfficeBranchSearch" class="form-control" value="{{ $filters['selected_branch_search'] ?? '' }}" list="outOfOfficeBranchOptions" placeholder="All active branches" autocomplete="off">
                        <datalist id="outOfOfficeBranchOptions">
                            @foreach (($filters['branch_options'] ?? []) as $branchOption)
                                <option value="{{ $branchOption['label'] }}">{{ $branchOption['meta'] }}</option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100">Apply</button>
                        <a href="{{ route('admin-attendance-out-of-office') }}" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card rounded-4">
            <div class="card-body">
                <form method="post" action="{{ route('admin-attendance-out-of-office-update') }}" id="outOfOfficeBulkForm">
                    @csrf
                    <div id="outOfOfficeSelectedIds"></div>
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                        <div>
                            <h5 class="mb-1 attendance-title">Walked Out Entries</h5>
                            <p class="mb-0 attendance-muted">Select one or more days and mark attendance as half day or full day.</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <div class="datatable-toolbar"></div>
                            <select name="override_status" class="form-select" style="min-width: 180px;" required>
                                <option value="">Update Status</option>
                                <option value="half_day">Mark Half Day</option>
                                <option value="full_day">Mark Full Day</option>
                            </select>
                            <button type="submit" class="btn btn-primary">Update Selected</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle js-admin-datatable" data-admin-datatable="true" data-admin-scroll-x="false">
                            <thead>
                                <tr>
                                    <th style="width: 40px;"><input type="checkbox" id="outOfOfficeSelectAll"></th>
                                    <th>Date</th>
                                    <th>Emp ID</th>
                                    <th>Name</th>
                                    <th>Designation</th>
                                    <th>Branch</th>
                                    <th>Region</th>
                                    <th>First Out</th>
                                    <th>Last Out</th>
                                    <th>Pings</th>
                                    <th>Max Distance</th>
                                    <th>Override</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr>
                                        <td>
                                            @if (! empty($row['attendance_ids']))
                                                <input type="checkbox" class="out-office-entry-checkbox" data-attendance-ids="{{ implode(',', $row['attendance_ids']) }}">
                                            @endif
                                        </td>
                                        <td>{{ $row['date'] }}</td>
                                        <td>{{ $row['emp_id'] }}</td>
                                        <td>{{ $row['employee_name'] }}</td>
                                        <td>{{ $row['designation'] }}</td>
                                        <td>
                                            <div>{{ $row['branch_id'] ?: '--' }}</div>
                                            <small>{{ $row['branch_name'] ?: 'Branch unavailable' }}</small>
                                        </td>
                                        <td>{{ ($row['city'] ?: '--') . ', ' . ($row['state'] ?: '--') }}</td>
                                        <td>{{ $row['first_out_at'] }}</td>
                                        <td>{{ $row['last_out_at'] }}</td>
                                        <td>{{ $row['ping_count'] }}</td>
                                        <td>{{ $row['max_distance'] }}</td>
                                        <td>{{ $row['override_label'] }}</td>
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
            const branchIdInput = document.getElementById('outOfOfficeBranchId');
            const branchSearchInput = document.getElementById('outOfOfficeBranchSearch');
            const branchOptions = @json(($filters['branch_options'] ?? collect())->values());
            const master = document.getElementById('outOfOfficeSelectAll');
            const bulkForm = document.getElementById('outOfOfficeBulkForm');
            const selectedIdsContainer = document.getElementById('outOfOfficeSelectedIds');

            function syncBranchIdFromSearch() {
                const selected = branchOptions.find((item) => item.label === branchSearchInput.value);
                branchIdInput.value = selected ? selected.id : '';
            }

            function selectedCheckboxes() {
                return Array.from(document.querySelectorAll('.out-office-entry-checkbox:checked'));
            }

            function syncSelectedIds() {
                selectedIdsContainer.innerHTML = '';
                selectedCheckboxes().forEach(function(checkbox) {
                    (checkbox.dataset.attendanceIds || '').split(',').filter(Boolean).forEach(function(id) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'attendance_ids[]';
                        input.value = id;
                        selectedIdsContainer.appendChild(input);
                    });
                });
            }

            if (branchSearchInput) {
                branchSearchInput.addEventListener('change', syncBranchIdFromSearch);
                branchSearchInput.addEventListener('blur', syncBranchIdFromSearch);
            }

            if (master) {
                master.addEventListener('change', function() {
                    document.querySelectorAll('.out-office-entry-checkbox').forEach(function(checkbox) {
                        checkbox.checked = master.checked;
                    });
                    syncSelectedIds();
                });
            }

            document.querySelectorAll('.out-office-entry-checkbox').forEach(function(checkbox) {
                checkbox.addEventListener('change', syncSelectedIds);
            });

            if (bulkForm) {
                bulkForm.addEventListener('submit', function(event) {
                    syncSelectedIds();
                    if (!selectedIdsContainer.querySelector('input[name="attendance_ids[]"]')) {
                        event.preventDefault();
                        alert('Select at least one out of office entry.');
                    }
                });
            }
        });
    </script>
@endsection
