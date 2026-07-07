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
                        <li class="breadcrumb-item active" aria-current="page">Review Work Visit</li>
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
                        <p class="mb-1">Pending Requests</p>
                        <h4 class="mb-0">{{ $summary['pending'] }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card rounded-4 mb-0 attendance-stat-card">
                    <div class="card-body">
                        <p class="mb-1">Visits For Today</p>
                        <h4 class="mb-0">{{ $summary['today'] }}</h4>
                    </div>
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
                        <input type="text" name="emp_id" value="{{ $filters['emp_id'] }}" class="form-control" placeholder="Optional">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Visit Date From</label>
                        <input type="date" name="from_date" value="{{ $filters['from_date'] }}" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Visit Date To</label>
                        <input type="date" name="to_date" value="{{ $filters['to_date'] }}" class="form-control">
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100">Apply</button>
                        <a href="{{ route('admin-work-visits-review') }}" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card rounded-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Applied On</th>
                                <th>Emp ID</th>
                                <th>Name</th>
                                <th>Visit Dates</th>
                                <th>Site</th>
                                <th>Assigned By</th>
                                <th>GPS</th>
                                <th>Photo</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Action</th>
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
                                    <td style="min-width: 280px;">{{ $row['reason'] }}</td>
                                    <td>
                                        <span class="attendance-status-pill {{ strtolower($row['status']) }}">
                                            {{ ucfirst($row['status']) }}
                                        </span>
                                    </td>
                                    <td style="min-width: 280px;">
                                        <div class="d-flex flex-column gap-2">
                                            <form method="post" action="{{ route('admin-work-visits-review-update') }}">
                                                @csrf
                                                <input type="hidden" name="month" value="{{ $filters['month'] }}">
                                                <input type="hidden" name="emp_id" value="{{ $filters['emp_id'] }}">
                                                <input type="hidden" name="from_date" value="{{ $filters['from_date'] }}">
                                                <input type="hidden" name="to_date" value="{{ $filters['to_date'] }}">
                                                @foreach ($row['site_visit_request_ids'] as $siteVisitRequestId)
                                                    <input type="hidden" name="site_visit_request_ids[]" value="{{ $siteVisitRequestId }}">
                                                @endforeach
                                                <input type="hidden" name="status" value="approved">
                                                <textarea name="review_note" class="form-control mb-2" rows="2" placeholder="Optional HR note"></textarea>
                                                <button type="submit" class="btn btn-success btn-sm w-100">Approve</button>
                                            </form>
                                            <form method="post" action="{{ route('admin-work-visits-review-update') }}">
                                                @csrf
                                                <input type="hidden" name="month" value="{{ $filters['month'] }}">
                                                <input type="hidden" name="emp_id" value="{{ $filters['emp_id'] }}">
                                                <input type="hidden" name="from_date" value="{{ $filters['from_date'] }}">
                                                <input type="hidden" name="to_date" value="{{ $filters['to_date'] }}">
                                                @foreach ($row['site_visit_request_ids'] as $siteVisitRequestId)
                                                    <input type="hidden" name="site_visit_request_ids[]" value="{{ $siteVisitRequestId }}">
                                                @endforeach
                                                <input type="hidden" name="status" value="rejected">
                                                <textarea name="review_note" class="form-control mb-2" rows="2" placeholder="Optional rejection note"></textarea>
                                                <button type="submit" class="btn btn-danger btn-sm w-100">Reject</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center text-muted py-4">No pending work visit requests found.</td>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const monthInput = document.querySelector('input[name="month"]');
            const fromInput = document.querySelector('input[name="from_date"]');
            const toInput = document.querySelector('input[name="to_date"]');

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
        });
    </script>
@endsection
