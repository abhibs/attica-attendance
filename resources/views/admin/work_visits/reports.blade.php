@extends('admin.layout.app')

@section('content')
    @include('admin.attendance.partials.styles')

    <div class="main-content attendance-page">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Work Visit</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin-dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Work Visit Reports</li>
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
                        <label class="form-label">Visit Date From</label>
                        <input type="date" name="from_date" value="{{ $filters['from_date'] }}" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Visit Date To</label>
                        <input type="date" name="to_date" value="{{ $filters['to_date'] }}" class="form-control">
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100">Apply</button>
                        <a href="{{ route('admin-work-visits-reports') }}" class="btn btn-outline-secondary w-100">Reset</a>
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
                                <th>Visit Date</th>
                                <th>Site</th>
                                <th>Assigned By</th>
                                <th>GPS</th>
                                <th>Photo</th>
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
                                    <td>{{ $row['visit_date'] }}</td>
                                    <td>{{ $row['site_location'] }}</td>
                                    <td>{{ $row['approved_by'] }}</td>
                                    <td>
                                        <div>{{ $row['coordinates'] }}</div>
                                        <a href="{{ $row['map_url'] }}" target="_blank" class="small">Open Map</a>
                                    </td>
                                    <td>
                                        @if ($row['photo_url'] !== '')
                                            <a href="{{ $row['photo_url'] }}" target="_blank" class="btn btn-outline-primary btn-sm">View Photo</a>
                                        @else
                                            --
                                        @endif
                                    </td>
                                    <td style="min-width: 240px;">{{ $row['reason'] }}</td>
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
                                    <td colspan="12" class="text-center text-muted py-4">No work visit requests found.</td>
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
