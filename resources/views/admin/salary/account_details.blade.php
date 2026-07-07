@extends('admin.layout.app')

@section('content')
    <div class="main-content">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Salary</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin-dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Account Details</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="card rounded-4 mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                    <div>
                        <h4 class="mb-1">Employee Account Details</h4>
                        <p class="mb-0 text-muted">Filter employee bank details by branch, name, and state.</p>
                    </div>
                    <a href="{{ route('admin-salary-account-details-export', request()->query()) }}" class="btn btn-primary">
                        Download CSV
                    </a>
                </div>

                <form method="get" class="row g-3 align-items-end mb-2">
                    <div class="col-md-4">
                        <label class="form-label">Branch</label>
                        <select name="branch_id" class="form-select">
                            <option value="">All Branches</option>
                            @foreach ($branchOptions as $branch)
                                @php
                                    $branchCode = trim((string) ($branch['code'] ?? ''));
                                    $branchName = trim((string) ($branch['name'] ?? ''));
                                @endphp
                                <option value="{{ $branchCode }}" @selected($filters['branch_id'] === $branchCode)>
                                    {{ $branchCode }}{{ $branchName !== '' ? ' - '.$branchName : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">State</label>
                        <select name="state" class="form-select">
                            <option value="">All States</option>
                            @foreach ($stateOptions as $state)
                                <option value="{{ $state }}" @selected($filters['state'] === $state)>{{ $state }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Employee Name</label>
                        <input
                            type="text"
                            name="name"
                            class="form-control"
                            value="{{ $filters['name'] }}"
                            placeholder="Search by name">
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100">Apply</button>
                        <a href="{{ route('admin-salary-account-details') }}" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card rounded-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle js-admin-datatable" data-admin-datatable="true">
                        <thead>
                            <tr>
                                <th>S.No</th>
                                <th>Emp ID</th>
                                <th>Name</th>
                                <th>Designation</th>
                                <th>Type</th>
                                <th>Branch</th>
                                <th>State</th>
                                <th>City</th>
                                <th>Bank Name</th>
                                <th>Name as per A/C</th>
                                <th>A/C Number</th>
                                <th>IFSC</th>
                                <th>UAN</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    <td></td>
                                    <td>{{ $row['emp_id'] ?: '--' }}</td>
                                    <td>{{ $row['employee_name'] ?: '--' }}</td>
                                    <td>{{ $row['designation'] ?: '--' }}</td>
                                    <td>{{ $row['employee_type'] }}</td>
                                    <td>
                                        <div>{{ $row['branch_id'] ?: '--' }}</div>
                                        <small class="text-muted">{{ $row['branch_name'] ?: 'Branch unavailable' }}</small>
                                    </td>
                                    <td>{{ $row['state'] ?: '--' }}</td>
                                    <td>{{ $row['city'] ?: '--' }}</td>
                                    <td>{{ $row['bank_name'] ?: '--' }}</td>
                                    <td>{{ $row['account_name'] ?: '--' }}</td>
                                    <td>{{ $row['bank_account_number'] ?: '--' }}</td>
                                    <td>{{ $row['ifsc_code'] ?: '--' }}</td>
                                    <td>{{ $row['uan_number'] ?: '--' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="13" class="text-center text-muted py-4">No account details found for the selected filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
