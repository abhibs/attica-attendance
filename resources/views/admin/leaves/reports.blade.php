@extends('admin.layout.app')

@section('content')
    @include('admin.attendance.partials.styles')

    <div class="main-content attendance-page">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">{{ $breadcrumbTitle ?? 'Leaves' }}</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin-dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ $pageTitle ?? 'Leave Reports' }}</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card rounded-4 mb-0 attendance-stat-card">
                    <div class="card-body">
                        <p class="mb-1">Total Requests</p>
                        <h4 class="mb-0">{{ $summary['total'] }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card rounded-4 mb-0 attendance-stat-card">
                    <div class="card-body">
                        <p class="mb-1">Pending</p>
                        <h4 class="mb-0">{{ $summary['pending'] }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card rounded-4 mb-0 attendance-stat-card">
                    <div class="card-body">
                        <p class="mb-1">Approved</p>
                        <h4 class="mb-0">{{ $summary['approved'] }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card rounded-4 mb-0 attendance-stat-card">
                    <div class="card-body">
                        <p class="mb-1">Rejected</p>
                        <h4 class="mb-0">{{ $summary['rejected'] }}</h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="card rounded-4 mb-4">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Employee ID</label>
                        <input type="text" name="emp_id" value="{{ $filters['emp_id'] }}" class="form-control" placeholder="Optional">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="all" @selected($filters['status'] === 'all')>All</option>
                            <option value="pending" @selected($filters['status'] === 'pending')>Pending</option>
                            <option value="approved" @selected($filters['status'] === 'approved')>Approved</option>
                            <option value="rejected" @selected($filters['status'] === 'rejected')>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Leave Date From</label>
                        <input type="date" name="from_date" value="{{ $filters['from_date'] }}" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Leave Date To</label>
                        <input type="date" name="to_date" value="{{ $filters['to_date'] }}" class="form-control">
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100">Apply</button>
                        <a href="{{ route($resetRoute ?? 'admin-leaves-reports') }}" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card rounded-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle js-admin-datatable" data-admin-datatable="true" data-admin-paging="false" data-admin-info="false">
                        <thead>
                            <tr>
                                <th>Applied On</th>
                                <th>Emp ID</th>
                                <th>Name</th>
                                <th>Leave Date</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Reviewed By</th>
                                <th>Reviewed At</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    <td>{{ $row['applied_at'] }}</td>
                                    <td>{{ $row['emp_id'] }}</td>
                                    <td>{{ $row['employee_name'] }}</td>
                                    <td>{{ $row['leave_date'] }}</td>
                                    <td style="min-width: 280px;">{{ $row['reason'] }}</td>
                                    <td>
                                        <span class="attendance-status-pill {{ strtolower($row['status']) }}">
                                            {{ ucfirst($row['status']) }}
                                        </span>
                                    </td>
                                    <td>{{ $row['reviewed_by'] }}</td>
                                    <td>{{ $row['reviewed_at'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No leave requests found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if (isset($paginator) && $paginator->hasPages())
                    <div class="mt-3 d-flex justify-content-end">
                        {{ $paginator->links('pagination::bootstrap-5') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
