@extends('admin.layout.app')

@section('content')
    <style>
        .branch-login-stat {
            border: 1px solid var(--admin-border-color);
            border-radius: 1rem;
            background: var(--admin-surface-color);
            padding: 1rem 1.25rem;
            height: 100%;
        }

        .branch-login-stat span {
            display: block;
            margin-bottom: 0.35rem;
            color: var(--admin-muted-text-color);
            font-size: 0.85rem;
        }

        .branch-login-stat strong {
            font-size: 1.55rem;
            line-height: 1;
        }

        .branch-login-summary {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 0.85rem;
        }

        .branch-login-summary-card {
            border: 1px solid rgba(var(--admin-primary-color-rgb), 0.12);
            border-radius: 0.9rem;
            background: rgba(var(--admin-primary-color-rgb), 0.05);
            padding: 0.9rem 1rem;
        }

        .branch-login-summary-card strong,
        .branch-login-summary-card span,
        .branch-login-summary-card small {
            display: block;
        }

        .branch-login-summary-card span {
            color: var(--admin-muted-text-color);
            font-size: 0.82rem;
        }

        .branch-login-summary-card small {
            margin-top: 0.35rem;
            color: var(--admin-muted-text-color);
        }
    </style>

    <div class="main-content">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Branch</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin-dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Branch Logins</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="row row-cols-1 row-cols-md-3 g-3 mb-4">
            <div class="col">
                <div class="branch-login-stat">
                    <span>Employees Logged In</span>
                    <strong>{{ $totalEmployees }}</strong>
                </div>
            </div>
            <div class="col">
                <div class="branch-login-stat">
                    <span>Branches Used</span>
                    <strong>{{ $totalBranches }}</strong>
                </div>
            </div>
            <div class="col">
                <div class="branch-login-stat">
                    <span>Date Filter</span>
                    <strong>{{ $filters['date'] !== '' ? \Illuminate\Support\Carbon::parse($filters['date'])->format('d M Y') : 'All' }}</strong>
                </div>
            </div>
        </div>

        <div class="card rounded-4 mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
                    <div>
                        <h4 class="mb-1">Branch Logins</h4>
                        <p class="mb-0 text-muted">Shows each employee's latest app login branch and login time.</p>
                    </div>
                </div>

                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label">Login Date</label>
                        <input type="date" name="date" value="{{ $filters['date'] }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Branch</label>
                        <input type="hidden" name="branch_id" id="branchLoginBranchId" value="{{ $filters['branch_id'] }}">
                        <input type="search" id="branchLoginBranchSearch" class="form-control" value="{{ $filters['selected_branch_search'] }}" list="branchLoginBranchOptions" placeholder="All branches" autocomplete="off">
                        <datalist id="branchLoginBranchOptions">
                            @foreach ($filters['branch_options'] as $branchOption)
                                <option value="{{ $branchOption['label'] }}">{{ $branchOption['meta'] }}</option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Employee ID</label>
                        <input type="text" name="emp_id" value="{{ $filters['emp_id'] }}" class="form-control" placeholder="Optional">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Employee Name</label>
                        <input type="text" name="employee_name" value="{{ $filters['employee_name'] }}" class="form-control" placeholder="Optional">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">State</label>
                        <input type="search" name="state" value="{{ $filters['state'] }}" class="form-control" list="branchLoginStateOptions" placeholder="All States">
                        <datalist id="branchLoginStateOptions">
                            @foreach ($filters['states'] as $state)
                                <option value="{{ $state }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">City</label>
                        <input type="search" name="city" value="{{ $filters['city'] }}" class="form-control" list="branchLoginCityOptions" placeholder="All Cities">
                        <datalist id="branchLoginCityOptions">
                            @foreach ($filters['cities'] as $city)
                                <option value="{{ $city }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100">Apply</button>
                        <a href="{{ route('admin-branch-logins') }}" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        @if ($summaryByBranch->isNotEmpty())
            <div class="card rounded-4 mb-4">
                <div class="card-body">
                    <h5 class="mb-3">Branch Summary</h5>
                    <div class="branch-login-summary">
                        @foreach ($summaryByBranch as $branchSummary)
                            <div class="branch-login-summary-card">
                                <strong>{{ $branchSummary['branch_id'] }} - {{ $branchSummary['branch_name'] }}</strong>
                                <span>{{ ($branchSummary['city'] ?: '--') . ', ' . ($branchSummary['state'] ?: '--') }}</span>
                                <small>{{ $branchSummary['login_count'] }} employee(s), latest {{ $branchSummary['latest_login'] }}</small>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="card rounded-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                    <div>
                        <h5 class="mb-1">Employee Login List</h5>
                        <p class="mb-0 text-muted">Latest app login branch captured during employee login.</p>
                    </div>
                    <div class="datatable-toolbar"></div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle js-admin-datatable" data-admin-datatable="true" data-admin-scroll-x="false">
                        <thead>
                            <tr>
                                <th>Emp ID</th>
                                <th>Name</th>
                                <th>Designation</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Branch ID</th>
                                <th>Branch Name</th>
                                <th>City</th>
                                <th>State</th>
                                <th>Last Login</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td>{{ $row['emp_id'] }}</td>
                                    <td>{{ $row['employee_name'] }}</td>
                                    <td>{{ $row['designation'] }}</td>
                                    <td>{{ $row['contact'] }}</td>
                                    <td>{{ $row['status'] }}</td>
                                    <td>{{ $row['branch_id'] ?: '--' }}</td>
                                    <td>{{ $row['branch_name'] }}</td>
                                    <td>{{ $row['city'] ?: '--' }}</td>
                                    <td>{{ $row['state'] ?: '--' }}</td>
                                    <td>{{ $row['last_login_at'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const branchIdInput = document.getElementById('branchLoginBranchId');
            const branchSearchInput = document.getElementById('branchLoginBranchSearch');
            const branchOptions = @json($filters['branch_options']);

            function syncBranchIdFromSearch() {
                const selected = branchOptions.find((item) => item.label === branchSearchInput.value);
                branchIdInput.value = selected ? selected.id : '';
            }

            if (branchSearchInput) {
                branchSearchInput.addEventListener('change', syncBranchIdFromSearch);
                branchSearchInput.addEventListener('blur', syncBranchIdFromSearch);
            }
        });
    </script>
@endsection
