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
                        <li class="breadcrumb-item active" aria-current="page">Fraud Reports</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="row row-cols-1 row-cols-md-3 g-3 mb-4">
            <div class="col">
                <div class="card rounded-4 mb-0 attendance-stat-card">
                    <div class="card-body">
                        <p class="mb-1">Reports</p>
                        <h4 class="mb-0">{{ $summary['total'] }}</h4>
                    </div>
                </div>
            </div>
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
                        <p class="mb-1">Branches</p>
                        <h4 class="mb-0">{{ $summary['branches'] }}</h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="card rounded-4 mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
                    <div>
                        <h4 class="mb-1 attendance-title">Fraud Reports</h4>
                        <p class="mb-0 attendance-muted">Mobile-screen attendance attempts reported by the employee app.</p>
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
                        <input type="search" name="state" class="form-control" list="fraudStateOptions" value="{{ $filters['state'] }}" placeholder="All States">
                        <datalist id="fraudStateOptions">
                            @foreach ($filters['states'] as $state)
                                <option value="{{ $state }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">City</label>
                        <input type="search" name="city" class="form-control" list="fraudCityOptions" value="{{ $filters['city'] }}" placeholder="All Cities">
                        <datalist id="fraudCityOptions">
                            @foreach ($filters['cities'] as $city)
                                <option value="{{ $city }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Branch</label>
                        <input type="hidden" name="branch_id" id="fraudBranchId" value="{{ $filters['branch_id'] }}">
                        <input type="search" id="fraudBranchSearch" class="form-control" value="{{ $filters['selected_branch_search'] ?? '' }}" list="fraudBranchOptions" placeholder="All active branches" autocomplete="off">
                        <datalist id="fraudBranchOptions">
                            @foreach (($filters['branch_options'] ?? []) as $branchOption)
                                <option value="{{ $branchOption['label'] }}">{{ $branchOption['meta'] }}</option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100">Apply</button>
                        <a href="{{ route('admin-attendance-fraud-reports') }}" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card rounded-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                    <div>
                        <h5 class="mb-1 attendance-title">Reported Attempts</h5>
                        <p class="mb-0 attendance-muted">Click the proof image to open the captured photo in a new tab.</p>
                    </div>
                    <div class="datatable-toolbar"></div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle js-admin-datatable" data-admin-datatable="true" data-admin-scroll-x="false">
                        <thead>
                            <tr>
                                <th>Reported At</th>
                                <th>Proof</th>
                                <th>Emp ID</th>
                                <th>Name</th>
                                <th>Designation</th>
                                <th>Branch</th>
                                <th>Region</th>
                                <th>Source</th>
                                <th>Confidence</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    <td>{{ $row['reported_at'] }}</td>
                                    <td>
                                        @if ($row['proof_url'] !== '')
                                            <a href="{{ $row['proof_url'] }}" target="_blank" rel="noopener">
                                                <img src="{{ $row['proof_url'] }}" alt="Fraud proof" style="width: 72px; height: 72px; object-fit: cover; border-radius: 8px;">
                                            </a>
                                        @else
                                            --
                                        @endif
                                    </td>
                                    <td>{{ $row['emp_id'] }}</td>
                                    <td>{{ $row['employee_name'] }}</td>
                                    <td>{{ $row['designation'] }}</td>
                                    <td>
                                        <div>{{ $row['branch_name'] }}</div>
                                        <small class="text-muted">{{ $row['branch_id'] }}</small>
                                    </td>
                                    <td>{{ trim(($row['city'] ?: '--').', '.($row['state'] ?: '--'), ', ') }}</td>
                                    <td>{{ $row['source'] }}</td>
                                    <td>{{ $row['confidence'] }}</td>
                                    <td style="min-width: 240px;">{{ $row['reason'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">No fraud reports found for the selected filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const branchOptions = @json($filters['branch_options'] ?? []);
            const branchSearch = document.getElementById('fraudBranchSearch');
            const branchId = document.getElementById('fraudBranchId');

            if (!branchSearch || !branchId) {
                return;
            }

            const syncBranchId = () => {
                const value = branchSearch.value.trim().toLowerCase();
                const match = branchOptions.find((option) => option.label.toLowerCase() === value);
                branchId.value = match ? match.id : '';
            };

            branchSearch.addEventListener('change', syncBranchId);
            branchSearch.addEventListener('blur', syncBranchId);
            branchSearch.form?.addEventListener('submit', syncBranchId);
        });
    </script>
@endsection
