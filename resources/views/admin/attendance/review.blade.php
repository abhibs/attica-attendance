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
                        <li class="breadcrumb-item active" aria-current="page">Half Day / Single Punch</li>
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
                        <p class="mb-1">Half Day Entries</p>
                        <h4 class="mb-0">{{ $summary['half_days'] }}</h4>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card rounded-4 mb-0 attendance-stat-card">
                    <div class="card-body">
                        <p class="mb-1">Single Punch Entries</p>
                        <h4 class="mb-0">{{ $summary['single_punch_days'] }}</h4>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card rounded-4 mb-0 attendance-stat-card">
                    <div class="card-body">
                        <p class="mb-1">Review Queue</p>
                        <h4 class="mb-0">{{ $summary['total_rows'] }}</h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="card rounded-4 mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
                    <div>
                        <h4 class="mb-1 attendance-title">Half Day / Single Punch Review</h4>
                        <p class="mb-0 attendance-muted">
                            Filter by employee, region, or branch and bulk override the selected entries.
                        </p>
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
                        <input type="text" name="emp_id" value="{{ $filters['emp_id'] }}" class="form-control"
                            placeholder="Optional">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Issue</label>
                        <select name="issue" class="form-select">
                            <option value="all" @selected($filters['issue'] === 'all')>All Issues</option>
                            <option value="half_day" @selected($filters['issue'] === 'half_day')>Half Day</option>
                            <option value="single_punch" @selected($filters['issue'] === 'single_punch')>Single Punch</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">State</label>
                        <input type="search" name="state" class="form-control" list="attendanceReviewStateOptions"
                            value="{{ $filters['state'] }}" placeholder="All States">
                        <datalist id="attendanceReviewStateOptions">
                            @foreach ($filters['states'] as $state)
                                <option value="{{ $state }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">City</label>
                        <input type="search" name="city" class="form-control" list="attendanceReviewCityOptions"
                            value="{{ $filters['city'] }}" placeholder="All Cities">
                        <datalist id="attendanceReviewCityOptions">
                            @foreach ($filters['cities'] as $city)
                                <option value="{{ $city }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Branch</label>
                        <input type="hidden" name="branch_id" id="attendanceReviewBranchId"
                            value="{{ $filters['branch_id'] }}">
                        <input type="search" id="attendanceReviewBranchSearch" class="form-control"
                            value="{{ $filters['selected_branch_search'] ?? '' }}" list="attendanceReviewBranchOptions"
                            placeholder="All active branches" autocomplete="off">
                        <datalist id="attendanceReviewBranchOptions">
                            @foreach (($filters['branch_options'] ?? []) as $branchOption)
                                <option value="{{ $branchOption['label'] }}">
                                    {{ $branchOption['meta'] }}
                                </option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100">Apply</button>
                        <a href="{{ route('admin-attendance-review') }}" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card rounded-4">
            <div class="card-body">
                <form method="post" action="{{ route('admin-attendance-review-update') }}" id="attendanceReviewBulkForm">
                    @csrf
                    <div id="attendanceReviewSelectedIds"></div>
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                        <div>
                            <h5 class="mb-1 attendance-title">Review Entries</h5>
                            <p class="mb-0 attendance-muted">Each attendance entry can be updated as full day, half day, or absent.</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <div class="datatable-toolbar"></div>
                            <select name="override_status" class="form-select" style="min-width: 180px;" required>
                                <option value="">Bulk Update Status</option>
                                <option value="full_day">Mark Full Day</option>
                                <option value="half_day">Mark Half Day</option>
                                <option value="absent">Mark Absent</option>
                            </select>
                            <button type="submit" class="btn btn-primary">Update Selected</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle js-admin-datatable"
                            data-admin-datatable="true" data-admin-scroll-x="false">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="reviewSelectAll">
                                    </th>
                                    <th>Dates</th>
                                    <th>Emp ID</th>
                                    <th>Name</th>
                                    <th>Designation</th>
                                    <th>Branch</th>
                                    <th>Region</th>
                                    <th>Status</th>
                                    <th>Check In</th>
                                    <th>Check Out</th>
                                    <th>Worked Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="review-entry-checkbox"
                                                data-attendance-ids="{{ implode(',', $row['attendance_ids']) }}">
                                        </td>
                                        <td>
                                            <div>{{ $row['date'] }}</div>
                                        </td>
                                        <td>{{ $row['emp_id'] }}</td>
                                        <td>{{ $row['employee_name'] }}</td>
                                        <td>{{ $row['designation'] ?: '--' }}</td>
                                        <td>
                                            <div>{{ $row['branch_id'] ?: '--' }}</div>
                                            <small>{{ $row['branch_name'] ?: 'Branch unavailable' }}</small>
                                        </td>
                                        <td>{{ ($row['city'] ?: '--') . ', ' . ($row['state'] ?: '--') }}</td>
                                        <td>
                                            <span class="attendance-status-pill {{ $row['status'] }}">
                                                {{ $row['status_label'] }}
                                            </span>
                                        </td>
                                        <td>{{ $row['check_in'] }}</td>
                                        <td>{{ $row['check_out'] }}</td>
                                        <td>{{ $row['worked_time'] }}</td>
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
            const branchIdInput = document.getElementById('attendanceReviewBranchId');
            const branchSearchInput = document.getElementById('attendanceReviewBranchSearch');
            const branchOptions = @json(($filters['branch_options'] ?? collect())->values());
            const master = document.getElementById('reviewSelectAll');
            const monthInput = document.querySelector('input[name="month"]');
            const fromInput = document.querySelector('input[name="from_date"]');
            const toInput = document.querySelector('input[name="to_date"]');
            const bulkForm = document.getElementById('attendanceReviewBulkForm');
            const selectedIdsContainer = document.getElementById('attendanceReviewSelectedIds');

            function syncBranchId() {
                if (!branchIdInput || !branchSearchInput) {
                    return;
                }

                const searchValue = branchSearchInput.value.trim();
                if (searchValue === '') {
                    branchIdInput.value = '';
                    return;
                }

                const exactMatch = branchOptions.find((option) => option.label === searchValue);
                branchIdInput.value = exactMatch ? exactMatch.id : '';
            }

            branchSearchInput?.addEventListener('change', syncBranchId);
            branchSearchInput?.form?.addEventListener('submit', syncBranchId);

            monthInput?.addEventListener('change', function() {
                const value = monthInput.value;
                if (!value || !fromInput || !toInput) {
                    return;
                }

                const [year, month] = value.split('-').map(Number);
                const lastDay = new Date(year, month, 0).getDate();
                const today = new Date();
                const isCurrentMonth = today.getFullYear() === year && (today.getMonth() + 1) === month;
                const paddedMonth = String(month).padStart(2, '0');
                fromInput.value = `${year}-${paddedMonth}-01`;
                toInput.value = `${year}-${paddedMonth}-${String(isCurrentMonth ? today.getDate() : lastDay).padStart(2, '0')}`;
            });

            bulkForm?.addEventListener('submit', function() {
                if (!selectedIdsContainer) {
                    return;
                }

                selectedIdsContainer.innerHTML = '';

                document.querySelectorAll('.review-entry-checkbox:checked').forEach((checkbox) => {
                    const ids = (checkbox.dataset.attendanceIds || '')
                        .split(',')
                        .map((value) => value.trim())
                        .filter(Boolean);

                    ids.forEach((id) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'attendance_ids[]';
                        input.value = id;
                        selectedIdsContainer.appendChild(input);
                    });
                });
            });

            if (!master) {
                return;
            }

            master.addEventListener('change', function() {
                document.querySelectorAll('.review-entry-checkbox').forEach((checkbox) => {
                    checkbox.checked = master.checked;
                });
            });
        });
    </script>
@endsection
