@extends('admin.layout.app')
@section('content')
    @php
        $activeTab =
            request('tab') === 'onboarded'
                ? 'onboarded'
                : (request('tab') === 'inactive'
                    ? 'inactive'
                    : (request('tab') === 'outsource'
                        ? 'outsource'
                        : 'active'));
        $employeeIndexAdminUser = Auth::guard('admin')->user();
        $isHrLimitedEmployeeIndexUser = strtolower(trim((string) ($employeeIndexAdminUser?->name ?? ''))) === 'hr'
            && strtolower(trim((string) ($employeeIndexAdminUser?->email ?? ''))) === 'hr.attica@gmail.com';
        $reopenOnboardedCandidateId = old('_candidate_id');
        $duplicateConflict = session('onboarded_duplicate_conflict');
        $duplicateConflicts = session('onboarded_duplicate_conflicts');
        $resolveProjectAsset = static function (?string $path = null): string {
            $trimmedPath = trim((string) $path);

            if ($trimmedPath === '') {
                return '';
            }

            if (function_exists('project_asset')) {
                return \App\Support\ProjectAsset::url($trimmedPath);
            }

            if (preg_match('/^https?:\/\//i', $trimmedPath) === 1) {
                return $trimmedPath;
            }

            $normalizedPath = ltrim($trimmedPath, '/');

            if (str_starts_with($normalizedPath, 'public/')) {
                $normalizedPath = substr($normalizedPath, 7);
            }

            return asset($normalizedPath);
        };
    @endphp
    <style>
        .branch-action-buttons {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .branch-action-buttons .btn {
            width: 42px;
            height: 30px;
            padding: 0;
        }

        .branch-action-buttons.branch-action-text .btn {
            width: auto;
            height: auto;
            min-width: 0;
            padding: 0.25rem 0.5rem;
        }

        .employee-photo-cell {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .employee-photo-thumb {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(90, 22, 201, 0.18);
            background: #f5f1fb;
        }

        .employee-photo-placeholder {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #5a16c9, #8a52e2);
            color: #fff;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .employee-list-tabs {
            gap: 8px;
        }

        .employee-list-tabs .nav-link {
            border-radius: 8px;
        }

        .employee-filter-card {
            border: 1px solid var(--admin-border-color);
            border-radius: 12px;
            padding: 1rem;
            background: var(--admin-surface-color);
        }

        .employee-login-branch {
            min-width: 120px;
        }

        .employee-directory-table {
            width: 100% !important;
            table-layout: auto;
            font-size: 0.78rem;
        }

        .employee-directory-table th,
        .employee-directory-table td {
            padding: 0.45rem 0.4rem;
            vertical-align: middle;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: normal;
        }

        .employee-directory-table th {
            font-size: 0.72rem;
            line-height: 1.2;
        }

        .employee-directory-table .badge {
            white-space: nowrap;
        }

        @media (min-width: 1400px) {
            .employee-directory-table {
                font-size: 0.82rem;
            }
        }

        @media (max-width: 1199.98px) {
            .employee-directory-table {
                font-size: 0.72rem;
            }

            .employee-directory-table th,
            .employee-directory-table td {
                padding: 0.35rem 0.3rem;
            }

            .employee-photo-thumb,
            .employee-photo-placeholder {
                width: 36px;
                height: 36px;
            }
        }

        @media (max-width: 767.98px) {
            .employee-list-tabs {
                flex-direction: column;
            }

            .employee-list-tabs .nav-item,
            .employee-list-tabs .nav-link {
                width: 100%;
            }

            .employee-directory-table,
            .employee-directory-table thead,
            .employee-directory-table tbody,
            .employee-directory-table th,
            .employee-directory-table td,
            .employee-directory-table tr {
                display: block;
                width: 100% !important;
            }

            .employee-directory-table {
                border: 0 !important;
                font-size: 0.9rem;
            }

            .employee-directory-table thead {
                position: absolute;
                width: 1px !important;
                height: 1px;
                padding: 0;
                margin: -1px;
                overflow: hidden;
                clip: rect(0, 0, 0, 0);
                white-space: nowrap;
                border: 0;
            }

            .employee-directory-table tbody tr {
                margin-bottom: 1rem;
                border: 1px solid var(--admin-border-color);
                border-radius: 8px;
                overflow: hidden;
                background-color: var(--admin-surface-color);
            }

            .employee-directory-table tbody td {
                display: grid;
                grid-template-columns: minmax(120px, 42%) minmax(0, 1fr);
                gap: 0.75rem;
                align-items: center;
                min-height: 42px;
                padding: 0.65rem 0.75rem;
                border-width: 0 0 1px !important;
                text-align: right;
            }

            .employee-directory-table tbody td:last-child {
                border-bottom-width: 0 !important;
            }

            .employee-directory-table tbody td::before {
                content: attr(data-label);
                font-weight: 700;
                color: var(--admin-muted-text-color);
                text-align: left;
            }

            .employee-directory-table .employee-photo-cell {
                display: grid;
                justify-content: stretch;
            }

            .employee-directory-table .employee-photo-cell img,
            .employee-directory-table .employee-photo-cell span {
                justify-self: end;
            }

            .employee-directory-table .branch-action-buttons {
                justify-content: flex-end;
                flex-wrap: wrap;
                white-space: normal;
            }

            .employee-directory-table .employee-login-branch {
                min-width: 0;
            }
        }
    </style>

    <div class="main-content">
        <!--breadcrumb-->
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Admin</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">All Employees</li>
                    </ol>
                </nav>
            </div>
            <div class="ms-auto">
                <a href="{{ route('admin-employee-create') }}" type="button" class="btn btn-primary">Add Employee</a>
            </div>
        </div>
        <!--end breadcrumb-->
        <div class="card">
            <div class="card-body">
                <form method="get" action="{{ route('admin-employee-index') }}" class="employee-filter-card mb-3">
                    <input type="hidden" name="tab" id="employeeFilterTabInput" value="{{ $activeTab }}">
                    <div class="row g-3 align-items-end">
                        <div class="col-xl-3 col-md-6">
                            <label class="form-label">State</label>
                            <select name="state" class="form-select">
                                <option value="">All States</option>
                                @foreach ($availableStates as $state)
                                    <option value="{{ $state['key'] }}" @selected($selectedState === $state['key'])>{{ $state['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <label class="form-label">City</label>
                            <select name="city" class="form-select">
                                <option value="">All Cities</option>
                                @foreach ($availableCities as $city)
                                    <option value="{{ $city['key'] }}" @selected($selectedCity === $city['key'])>{{ $city['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-4 col-md-8">
                            <label class="form-label">Branch</label>
                            <select name="branch" class="form-select">
                                <option value="">All Branches</option>
                                @foreach ($availableBranches as $branch)
                                    @php
                                        $branchLabel = trim($branch->branchId . ' - ' . $branch->branchName, ' -');
                                        $branchMeta = collect([$branch->city, $branch->state])
                                            ->filter()
                                            ->implode(', ');
                                    @endphp
                                    <option value="{{ $branch->branchId }}" @selected($selectedBranch === trim((string) $branch->branchId))>
                                        {{ $branchLabel }}{{ $branchMeta !== '' ? ' | ' . $branchMeta : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-fill">Apply</button>
                            <a href="{{ route('admin-employee-index', ['tab' => $activeTab]) }}"
                                id="employeeFilterResetLink" class="btn btn-outline-secondary flex-fill">Reset</a>
                        </div>
                    </div>
                </form>

                <ul class="nav nav-pills employee-list-tabs mb-3" id="employeeStatusTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $activeTab === 'active' ? 'active' : '' }}" id="active-employees-tab"
                            data-bs-toggle="tab" data-bs-target="#active-employees-pane" type="button" role="tab"
                            aria-controls="active-employees-pane"
                            aria-selected="{{ $activeTab === 'active' ? 'true' : 'false' }}">
                            Employees ({{ $employees->count() }})
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $activeTab === 'inactive' ? 'active' : '' }}"
                            id="inactive-employees-tab" data-bs-toggle="tab" data-bs-target="#inactive-employees-pane"
                            type="button" role="tab" aria-controls="inactive-employees-pane"
                            aria-selected="{{ $activeTab === 'inactive' ? 'true' : 'false' }}">
                            Inactive Employees ({{ $inactiveEmployees->count() }})
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $activeTab === 'outsource' ? 'active' : '' }}"
                            id="outsource-employees-tab" data-bs-toggle="tab" data-bs-target="#outsource-employees-pane"
                            type="button" role="tab" aria-controls="outsource-employees-pane"
                            aria-selected="{{ $activeTab === 'outsource' ? 'true' : 'false' }}">
                            Outsource Employees ({{ $outsourceEmployees->count() }})
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $activeTab === 'onboarded' ? 'active' : '' }}"
                            id="onboarded-employees-tab" data-bs-toggle="tab" data-bs-target="#onboarded-employees-pane"
                            type="button" role="tab" aria-controls="onboarded-employees-pane"
                            aria-selected="{{ $activeTab === 'onboarded' ? 'true' : 'false' }}">
                            Newly Onboarded ({{ $onboardedCandidates->count() }})
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="employeeStatusTabContent">
                    <div class="tab-pane fade {{ $activeTab === 'active' ? 'show active' : '' }}"
                        id="active-employees-pane" role="tabpanel" aria-labelledby="active-employees-tab" tabindex="0">
                        <div class="table-responsive">
                            <table id="example2" class="table table-bordered table-hover employee-directory-table"
                                data-admin-scroll-x="false">
                                <thead>
                                    <tr>
                                        @unless ($isHrLimitedEmployeeIndexUser)
                                        <th>SL No</th>
                                        <th>Photo</th>
                                        @endunless
                                        <th>Id</th>
                                        <th>Name</th>
                                        <th>Designation</th>
                                        <th>Date of Joining</th>
                                        @unless ($isHrLimitedEmployeeIndexUser)
                                        <th>Shift Timing</th>
                                        <th>Last Login Branch</th>
                                        @endunless
                                        <th>Status</th>
                                        @unless ($isHrLimitedEmployeeIndexUser)
                                        <th>Action</th>
                                        @endunless
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($employees as $key => $item)
                                        <tr>
                                            @unless ($isHrLimitedEmployeeIndexUser)
                                            <td data-label="SL No"> {{ $key + 1 }} </td>
                                            <td class="employee-photo-cell" data-label="Photo">
                                                @if (!empty($item->photo_url))
                                                    <img src="{{ $item->photo_url }}" alt="{{ $item->name }}"
                                                        class="employee-photo-thumb" data-admin-image-fallback
                                                        data-admin-image-alt="No Image">
                                                @else
                                                    <span class="employee-photo-placeholder">
                                                        {{ strtoupper(substr(trim($item->name ?: 'EM'), 0, 2)) }}
                                                    </span>
                                                @endif
                                            </td>
                                            @endunless
                                            <td data-label="Id">
                                                <!--<a href="{{ route('admin-employee-edit', $item->id) }}" class="text-decoration-none fw-semibold">-->
                                                {{ $item->empId }}
                                                <!--</a>-->
                                            </td>
                                            <td data-label="Name">{{ $item->name }}</td>
                                            <td data-label="Designation">{{ $item->designation }}</td>
                                            <td data-label="Date of Joining">{{ $item->display_date_of_joining ?: '--' }}</td>
                                            @unless ($isHrLimitedEmployeeIndexUser)
                                            <td data-label="Shift Timing">
                                                {{ trim((string) $item->shift_timing) !== '' ? $item->shift_timing : '10:00 AM - 7:00 PM' }}
                                            </td>
                                            <td class="employee-login-branch" data-label="Last Login Branch">
                                                {{ $item->last_login_branch_name ?: '--' }}</td>
                                            @endunless
                                            <td data-label="Status">
                                                @if ($item->status === 'Inactive')
                                                    <span class="badge bg-grd-danger">InActive</span>
                                                @elseif ($item->status === 'Blocked')
                                                    <span class="badge bg-grd-danger">Blocked</span>
                                                @else
                                                    <span
                                                        class="badge bg-grd-primary">{{ $item->status ?: 'Active' }}</span>
                                                @endif
                                            </td>
                                            @unless ($isHrLimitedEmployeeIndexUser)
                                            <td data-label="Action">
                                                <div class="branch-action-buttons branch-action-text">
                                                    <a href="{{ route('admin-employee-edit', $item->id) }}"
                                                        class="btn btn-outline-primary d-inline-flex align-items-center justify-content-center"
                                                        title="Edit" aria-label="Edit">
                                                        <i class="fadeIn animated bx bx-pencil"></i>
                                                    </a>
                                                    <a href="{{ route('admin-employee-delete', $item->id) }}"
                                                        class="btn btn-outline-danger d-inline-flex align-items-center justify-content-center"
                                                        id="delete" title="Delete" aria-label="Delete"
                                                        onclick="return confirm('Delete this employee? This action cannot be undone.');">
                                                        <i class="fadeIn animated bx bx-trash-alt"></i>
                                                    </a>
                                                    @if ($item->status === 'Inactive')
                                                        <a href="{{ route('admin-employee-active', $item->id) }}"
                                                            class="btn btn-primary rounded-pill waves-effect waves-light"
                                                            title="Active"><i class="fa-solid fa-thumbs-up"></i></a>
                                                    @else
                                                        <button type="button"
                                                            class="btn btn-primary rounded-pill waves-effect waves-light"
                                                            title="Inactive" data-bs-toggle="modal"
                                                            data-bs-target="#inactiveEmployeeModal{{ $item->id }}">
                                                            <i class="fa-solid fa-thumbs-down"></i>
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                            @endunless
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @unless ($isHrLimitedEmployeeIndexUser)
                        @foreach ($employees->merge($outsourceEmployees)->where('status', '!=', 'Inactive') as $item)
                            <div class="modal fade" id="inactiveEmployeeModal{{ $item->id }}" tabindex="-1"
                                aria-labelledby="inactiveEmployeeModalLabel{{ $item->id }}" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form action="{{ route('admin-employee-inactive', $item->id) }}" method="post">
                                            @csrf
                                            <div class="modal-header">
                                                <h5 class="modal-title"
                                                    id="inactiveEmployeeModalLabel{{ $item->id }}">
                                                    Mark {{ $item->name }} Inactive
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                    aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Last Working Date</label>
                                                    <input type="date" class="form-control" name="last_working_date"
                                                        value="{{ now()->toDateString() }}" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Inactive Reason</label>
                                                    <textarea class="form-control" name="inactive_reason" rows="4"
                                                        placeholder="Enter reason for marking employee inactive" required></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-light"
                                                    data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Mark Inactive</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        @endunless
                    </div>

                    <div class="tab-pane fade {{ $activeTab === 'inactive' ? 'show active' : '' }}"
                        id="inactive-employees-pane" role="tabpanel" aria-labelledby="inactive-employees-tab"
                        tabindex="0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover employee-directory-table"
                                data-admin-datatable="true" data-admin-scroll-x="false">
                                <thead>
                                    <tr>
                                        @unless ($isHrLimitedEmployeeIndexUser)
                                        <th>SL No</th>
                                        <th>Photo</th>
                                        @endunless
                                        <th>Id</th>
                                        <th>Name</th>
                                        <th>Designation</th>
                                        <th>Date of Joining</th>
                                        @unless ($isHrLimitedEmployeeIndexUser)
                                        <th>Last Login Branch</th>
                                        <th>Last Working Date</th>
                                        <th>Inactive Reason</th>
                                        @endunless
                                        <th>Status</th>
                                        @unless ($isHrLimitedEmployeeIndexUser)
                                        <th>Action</th>
                                        @endunless
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($inactiveEmployees as $key => $item)
                                        <tr>
                                            @unless ($isHrLimitedEmployeeIndexUser)
                                            <td data-label="SL No"> {{ $key + 1 }} </td>
                                            <td class="employee-photo-cell" data-label="Photo">
                                                @if (!empty($item->photo_url))
                                                    <img src="{{ $item->photo_url }}" alt="{{ $item->name }}"
                                                        class="employee-photo-thumb" data-admin-image-fallback
                                                        data-admin-image-alt="No Image">
                                                @else
                                                    <span class="employee-photo-placeholder">
                                                        {{ strtoupper(substr(trim($item->name ?: 'EM'), 0, 2)) }}
                                                    </span>
                                                @endif
                                            </td>
                                            @endunless
                                            <td data-label="Id">
                                                @if ($isHrLimitedEmployeeIndexUser)
                                                    {{ $item->empId }}
                                                @else
                                                <!--<a href="{{ route('admin-employee-edit', $item->id) }}"-->
                                                <!--    class="text-decoration-none fw-semibold">-->
                                                    {{ $item->empId }}
                                                <!--</a>-->
                                                @endif
                                            </td>
                                            <td data-label="Name">{{ $item->name }}</td>
                                            <td data-label="Designation">{{ $item->designation }}</td>
                                            <td data-label="Date of Joining">{{ $item->display_date_of_joining ?: '--' }}</td>
                                            @unless ($isHrLimitedEmployeeIndexUser)
                                            <td class="employee-login-branch" data-label="Last Login Branch">
                                                {{ $item->last_login_branch_name ?: '--' }}</td>
                                            <td data-label="Last Working Date">{{ $item->last_working_date ?: '--' }}</td>
                                            <td data-label="Inactive Reason">{{ $item->inactive_reason ?: '--' }}</td>
                                            @endunless
                                            <td data-label="Status"><span class="badge bg-grd-danger">InActive</span></td>
                                            @unless ($isHrLimitedEmployeeIndexUser)
                                            <td data-label="Action">
                                                <div class="branch-action-buttons branch-action-text">
                                                    <a href="{{ route('admin-employee-edit', $item->id) }}"
                                                        class="btn btn-outline-primary d-inline-flex align-items-center justify-content-center"
                                                        title="Edit" aria-label="Edit">
                                                        <i class="fadeIn animated bx bx-pencil"></i>
                                                    </a>
                                                    <a href="{{ route('admin-employee-delete', $item->id) }}"
                                                        class="btn btn-outline-danger d-inline-flex align-items-center justify-content-center"
                                                        id="delete" title="Delete" aria-label="Delete"
                                                        onclick="return confirm('Delete this employee? This action cannot be undone.');">
                                                        <i class="fadeIn animated bx bx-trash-alt"></i>
                                                    </a>
                                                    <a href="{{ route('admin-employee-active', $item->id) }}"
                                                        class="btn btn-primary rounded-pill waves-effect waves-light"
                                                        title="Active"><i class="fa-solid fa-thumbs-up"></i></a>
                                                </div>
                                            </td>
                                            @endunless
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade {{ $activeTab === 'outsource' ? 'show active' : '' }}"
                        id="outsource-employees-pane" role="tabpanel" aria-labelledby="outsource-employees-tab"
                        tabindex="0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover employee-directory-table"
                                data-admin-datatable="true" data-admin-scroll-x="false">
                                <thead>
                                    <tr>
                                        @unless ($isHrLimitedEmployeeIndexUser)
                                        <th>SL No</th>
                                        <th>Photo</th>
                                        @endunless
                                        <th>Id</th>
                                        <th>Name</th>
                                        <th>Designation</th>
                                        <th>Date of Joining</th>
                                        @unless ($isHrLimitedEmployeeIndexUser)
                                        <th>Shift Timing</th>
                                        <th>Last Login Branch</th>
                                        @endunless
                                        <th>Status</th>
                                        @unless ($isHrLimitedEmployeeIndexUser)
                                        <th>Action</th>
                                        @endunless
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($outsourceEmployees as $key => $item)
                                        <tr>
                                            @unless ($isHrLimitedEmployeeIndexUser)
                                            <td data-label="SL No"> {{ $key + 1 }} </td>
                                            <td class="employee-photo-cell" data-label="Photo">
                                                @if (!empty($item->photo_url))
                                                    <img src="{{ $item->photo_url }}" alt="{{ $item->name }}"
                                                        class="employee-photo-thumb" data-admin-image-fallback
                                                        data-admin-image-alt="No Image">
                                                @else
                                                    <span class="employee-photo-placeholder">
                                                        {{ strtoupper(substr(trim($item->name ?: 'EM'), 0, 2)) }}
                                                    </span>
                                                @endif
                                            </td>
                                            @endunless
                                            <td data-label="Id">
                                                @if ($isHrLimitedEmployeeIndexUser)
                                                    {{ $item->empId }}
                                                @else
                                                <!--<a href="{{ route('admin-employee-edit', $item->id) }}"-->
                                                <!--    class="text-decoration-none fw-semibold">-->
                                                    {{ $item->empId }}
                                                <!--</a>-->
                                                @endif
                                            </td>
                                            <td data-label="Name">{{ $item->name }}</td>
                                            <td data-label="Designation">{{ $item->designation }}</td>
                                            <td data-label="Date of Joining">{{ $item->display_date_of_joining ?: '--' }}</td>
                                            @unless ($isHrLimitedEmployeeIndexUser)
                                            <td data-label="Shift Timing">
                                                {{ trim((string) $item->shift_timing) !== '' ? $item->shift_timing : '10:00 AM - 7:00 PM' }}
                                            </td>
                                            <td class="employee-login-branch" data-label="Last Login Branch">
                                                {{ $item->last_login_branch_name ?: '--' }}</td>
                                            @endunless
                                            <td data-label="Status">
                                                @if ($item->status === 'Inactive')
                                                    <span class="badge bg-grd-danger">InActive</span>
                                                @elseif ($item->status === 'Blocked')
                                                    <span class="badge bg-grd-danger">Blocked</span>
                                                @else
                                                    <span
                                                        class="badge bg-grd-primary">{{ $item->status ?: 'Active' }}</span>
                                                @endif
                                            </td>
                                            @unless ($isHrLimitedEmployeeIndexUser)
                                            <td data-label="Action">
                                                <div class="branch-action-buttons branch-action-text">
                                                    <a href="{{ route('admin-employee-edit', $item->id) }}"
                                                        class="btn btn-outline-primary d-inline-flex align-items-center justify-content-center"
                                                        title="Edit" aria-label="Edit">
                                                        <i class="fadeIn animated bx bx-pencil"></i>
                                                    </a>
                                                    <a href="{{ route('admin-employee-delete', $item->id) }}"
                                                        class="btn btn-outline-danger d-inline-flex align-items-center justify-content-center"
                                                        id="delete" title="Delete" aria-label="Delete"
                                                        onclick="return confirm('Delete this employee? This action cannot be undone.');">
                                                        <i class="fadeIn animated bx bx-trash-alt"></i>
                                                    </a>
                                                    @if ($item->status === 'Inactive')
                                                        <a href="{{ route('admin-employee-active', $item->id) }}"
                                                            class="btn btn-primary rounded-pill waves-effect waves-light"
                                                            title="Active"><i class="fa-solid fa-thumbs-up"></i></a>
                                                    @else
                                                        <button type="button"
                                                            class="btn btn-primary rounded-pill waves-effect waves-light"
                                                            title="Inactive" data-bs-toggle="modal"
                                                            data-bs-target="#inactiveEmployeeModal{{ $item->id }}">
                                                            <i class="fa-solid fa-thumbs-down"></i>
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                            @endunless
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade {{ $activeTab === 'onboarded' ? 'show active' : '' }}"
                        id="onboarded-employees-pane" role="tabpanel" aria-labelledby="onboarded-employees-tab"
                        tabindex="0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover employee-directory-table"
                                data-admin-datatable="true" data-admin-scroll-x="false">
                                <thead>
                                    <tr>
                                        @if ($isHrLimitedEmployeeIndexUser)
                                        <th>Id</th>
                                        <th>Name</th>
                                        <th>Designation</th>
                                        <th>Joining Date</th>
                                        <th>Status</th>
                                        @else
                                        <th>SL No</th>
                                        <th>Photo</th>
                                        <th>Employee ID</th>
                                        <th>Candidate</th>
                                        <th>Position</th>
                                        <th>Hiring User</th>
                                        <th>Joining User</th>
                                        <th>Appointed Designation</th>
                                        <th>Deployed Branch</th>
                                        <th>Shift Timing</th>
                                        <th>Joining Date</th>
                                        <th>Onboarded At</th>
                                        <th>Action</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($onboardedCandidates as $key => $candidate)
                                        @php
                                            $onboardingPayload = $candidate->onboarding_payload ?? [];
                                        @endphp
                                        <tr>
                                            @if ($isHrLimitedEmployeeIndexUser)
                                            <td data-label="Id">{{ $candidate->generated_emp_id ?: '--' }}</td>
                                            <td data-label="Name">{{ $candidate->candidate_name }}</td>
                                            <td data-label="Designation">
                                                {{ data_get($onboardingPayload, 'appointed_designation') ?: $candidate->position_applied_for ?: '--' }}
                                            </td>
                                            <td data-label="Joining Date">{{ $candidate->date_of_joining_input_value ?: '--' }}</td>
                                            <td data-label="Status"><span class="badge bg-grd-primary">Onboarded</span></td>
                                            @else
                                            <td data-label="SL No">{{ $key + 1 }}</td>
                                            <td class="employee-photo-cell" data-label="Photo">
                                                @if (!empty($candidate->employee_photo_path ?: $candidate->candidate_photo_path))
                                                    <img src="{{ $resolveProjectAsset($candidate->employee_photo_path ?: $candidate->candidate_photo_path) }}"
                                                        alt="{{ $candidate->candidate_name }}"
                                                        class="employee-photo-thumb" data-admin-image-fallback
                                                        data-admin-image-alt="No Image">
                                                @else
                                                    <span class="employee-photo-placeholder">
                                                        {{ strtoupper(substr(trim($candidate->candidate_name ?: 'EM'), 0, 2)) }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td data-label="Employee ID">
                                                <div>{{ $candidate->generated_emp_id ?: '--' }}</div>
                                                @if ($candidate->has_existing_employee_id_conflict)
                                                    <div class="small text-danger fw-semibold mt-1">Employee ID already
                                                        exists.</div>
                                                    @foreach ($candidate->employee_id_conflicts ?? [] as $conflict)
                                                        <div class="small text-muted">
                                                            {{ $conflict['location'] ?? 'Assigned' }}:
                                                            {{ $conflict['title'] ?? 'Record' }}
                                                        </div>
                                                    @endforeach
                                                @endif
                                            </td>
                                            <td data-label="Candidate">{{ $candidate->candidate_name }}</td>
                                            <td data-label="Position">{{ $candidate->position_applied_for ?: '--' }}</td>
                                            <td data-label="Hiring User">{{ $candidate->display_hiring_admin_name }}</td>
                                            <td data-label="Joining User">{{ $candidate->display_joining_admin_name }}
                                            </td>
                                            <td data-label="Appointed Designation">
                                                {{ data_get($onboardingPayload, 'appointed_designation') ?: $candidate->position_applied_for ?: '--' }}
                                            </td>
                                            <td data-label="Deployed Branch">
                                                {{ data_get($onboardingPayload, 'deployed_branch_name') ?: '--' }}</td>
                                            <td data-label="Shift Timing">
                                                {{ data_get($onboardingPayload, 'shift_timing') ?: '--' }}</td>
                                            <td data-label="Joining Date">{{ $candidate->date_of_joining_input_value ?: '--' }}</td>
                                            <td data-label="Onboarded At">
                                                {{ optional($candidate->onboarding_completed_at)->format('d-m-Y H:i') ?: '--' }}
                                            </td>
                                            <td data-label="Action">
                                                <div class="branch-action-buttons branch-action-text">
                                                    <button type="button" class="btn btn-primary btn-sm px-2"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#markOnDutyModal{{ $candidate->id }}">
                                                        Mark On Duty
                                                    </button>
                                                    <form method="post"
                                                        action="{{ route('admin-employee-onboarded-delete', ['candidateId' => $candidate->id, 'tab' => 'onboarded']) }}"
                                                        onsubmit="return confirm('Delete this newly onboarded entry?');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-outline-danger btn-sm px-2">
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @unless ($isHrLimitedEmployeeIndexUser)
                        @foreach ($onboardedCandidates as $candidate)
                            @php
                                $onboardingPayload = $candidate->onboarding_payload ?? [];
                                $isCurrentCandidateModal = (string) old('_candidate_id') === (string) $candidate->id;
                                $currentDuplicateConflict =
                                    is_array($duplicateConflict) &&
                                    (int) ($duplicateConflict['candidate_id'] ?? 0) === (int) $candidate->id
                                        ? $duplicateConflict
                                        : null;
                                $currentDuplicateConflicts =
                                    is_array($duplicateConflicts) &&
                                    (int) ($duplicateConflicts['candidate_id'] ?? 0) === (int) $candidate->id
                                        ? $duplicateConflicts['conflicts'] ?? []
                                        : [];
                                $displayDuplicateConflict = $currentDuplicateConflict;
                                $displayDuplicateConflicts = $currentDuplicateConflicts;

                                if (!$displayDuplicateConflict && $candidate->existing_employee) {
                                    $displayDuplicateConflict = [
                                        'employee_id' => $candidate->existing_employee?->empId,
                                        'existing_employee_name' => $candidate->existing_employee?->name,
                                        'existing_employee_designation' => $candidate->existing_employee?->designation,
                                        'existing_employee_doj' => $candidate->existing_employee?->doj,
                                    ];
                                }

                                if (
                                    empty($displayDuplicateConflicts) &&
                                    $candidate->has_existing_employee_id_conflict
                                ) {
                                    $displayDuplicateConflicts = $candidate->employee_id_conflicts ?? [];
                                }

                            @endphp
                            <div class="modal fade" id="markOnDutyModal{{ $candidate->id }}" tabindex="-1"
                                aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="post" id="markOnDutyForm{{ $candidate->id }}"
                                            action="{{ route('admin-employee-onboarded-join', ['candidateId' => $candidate->id, 'tab' => 'onboarded']) }}">
                                            @csrf
                                            <input type="hidden" name="_candidate_id" value="{{ $candidate->id }}">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Mark {{ $candidate->candidate_name }} On Duty</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                    aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                @if ($displayDuplicateConflict || !empty($displayDuplicateConflicts))
                                                    <div class="alert alert-warning">
                                                        <div class="fw-semibold">Employee ID already exists.</div>
                                                        @if (!empty($displayDuplicateConflicts))
                                                            <ul class="mb-2 ps-3">
                                                                @foreach ($displayDuplicateConflicts as $conflict)
                                                                    <li>
                                                                        <strong>{{ $conflict['title'] ?? 'Record' }}</strong>
                                                                        <span
                                                                            class="text-muted">({{ $conflict['location'] ?? 'Unknown location' }})</span>
                                                                        @if (!empty($conflict['details']))
                                                                            <div class="small text-muted">
                                                                                {{ $conflict['details'] }}</div>
                                                                        @endif
                                                                    </li>
                                                                @endforeach
                                                            </ul>
                                                        @elseif ($displayDuplicateConflict)
                                                            <div class="small mt-1">
                                                                Existing employee:
                                                                {{ $displayDuplicateConflict['existing_employee_name'] ?: 'Employee' }}
                                                                @if (!empty($displayDuplicateConflict['employee_id']))
                                                                    ({{ $displayDuplicateConflict['employee_id'] }})
                                                                @endif
                                                            </div>
                                                            @if (
                                                                !empty($displayDuplicateConflict['existing_employee_designation']) ||
                                                                    !empty($displayDuplicateConflict['existing_employee_doj']))
                                                                <div class="small text-muted mt-1">
                                                                    {{ $displayDuplicateConflict['existing_employee_designation'] ?: 'Designation not set' }}
                                                                    @if (!empty($displayDuplicateConflict['existing_employee_doj']))
                                                                        | DOJ:
                                                                        {{ $displayDuplicateConflict['existing_employee_doj'] }}
                                                                    @endif
                                                                </div>
                                                            @endif
                                                        @endif
                                                        <div class="small text-danger fw-semibold mt-2">
                                                            Use a different Employee ID. Existing assignments cannot be cleared or reassigned.
                                                        </div>
                                                    </div>
                                                @endif
                                                <div class="mb-3">
                                                    <label class="form-label">Employee ID</label>
                                                    <input type="text" name="generated_emp_id"
                                                        class="form-control{{ $isCurrentCandidateModal && $errors->has('generated_emp_id') ? ' is-invalid' : '' }}"
                                                        value="{{ old('_candidate_id') == $candidate->id ? old('generated_emp_id', $candidate->generated_emp_id ?: ($nextAvailableEmpId ?? '')) : ($candidate->generated_emp_id ?: ($nextAvailableEmpId ?? '')) }}"
                                                        placeholder="Enter Employee ID" required>
                                                    <div class="form-text">
                                                        Next available Employee ID{{ count($nextAvailableEmpIdSuggestions ?? []) > 1 ? 's' : '' }}:
                                                        <strong>{{ implode(', ', $nextAvailableEmpIdSuggestions ?? [($nextAvailableEmpId ?? '--')]) }}</strong>
                                                    </div>
                                                    @if ($isCurrentCandidateModal && $errors->has('generated_emp_id'))
                                                        <div class="invalid-feedback d-block">
                                                            {{ $errors->first('generated_emp_id') }}</div>
                                                    @endif
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Contact Number</label>
                                                    <input type="text" class="form-control"
                                                        value="{{ data_get($onboardingPayload, 'contact_number') ?: $candidate->contact_number ?: '--' }}"
                                                        readonly>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Date of Joining</label>
                                                    <input type="date" name="date_of_joining" class="form-control"
                                                        value="{{ old('_candidate_id') == $candidate->id ? old('date_of_joining', $candidate->date_of_joining_input_value) : $candidate->date_of_joining_input_value }}"
                                                        required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Appointed Designation</label>
                                                    <input type="text" name="appointed_designation"
                                                        class="form-control"
                                                        value="{{ old('_candidate_id') == $candidate->id ? old('appointed_designation', data_get($onboardingPayload, 'appointed_designation') ?: $candidate->position_applied_for) : (data_get($onboardingPayload, 'appointed_designation') ?: $candidate->position_applied_for) }}"
                                                        required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Deployed Branch Name</label>
                                                    <select name="deployed_branch_id" class="form-select" required>
                                                        <option value="">Select Branch</option>
                                                        @foreach ($branches as $branch)
                                                            @php
                                                                $selectedBranchId =
                                                                    old('_candidate_id') == $candidate->id
                                                                        ? old(
                                                                            'deployed_branch_id',
                                                                            data_get(
                                                                                $onboardingPayload,
                                                                                'deployed_branch_id',
                                                                            ),
                                                                        )
                                                                        : data_get(
                                                                            $onboardingPayload,
                                                                            'deployed_branch_id',
                                                                        );
                                                            @endphp
                                                            <option value="{{ $branch->branchId }}"
                                                                @selected($selectedBranchId === $branch->branchId)>
                                                                {{ $branch->branchId }} - {{ $branch->branchName }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="mb-0">
                                                    <label class="form-label">Shift Timing</label>
                                                    <input type="text" name="shift_timing" class="form-control"
                                                        value="{{ old('_candidate_id') == $candidate->id ? old('shift_timing', data_get($onboardingPayload, 'shift_timing')) : data_get($onboardingPayload, 'shift_timing') }}"
                                                        required>
                                                </div>
                                                <div class="mt-3">
                                                    <label class="form-label">Fixed Salary</label>
                                                    <input type="number" step="0.01" min="0"
                                                        name="fixed_salary" class="form-control"
                                                        value="{{ old('_candidate_id') == $candidate->id ? old('fixed_salary', $candidate->fixed_salary ?: data_get($onboardingPayload, 'fixed_salary')) : ($candidate->fixed_salary ?: data_get($onboardingPayload, 'fixed_salary')) }}"
                                                        placeholder="Enter fixed salary" readonly>
                                                </div>
                                            </div>
                                        </form>
                                        <form method="post" id="deleteOnboardedForm{{ $candidate->id }}"
                                            action="{{ route('admin-employee-onboarded-delete', ['candidateId' => $candidate->id, 'tab' => 'onboarded']) }}">
                                            @csrf
                                        </form>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary"
                                                data-bs-dismiss="modal">Close</button>
                                            @if ($displayDuplicateConflict || !empty($displayDuplicateConflicts))
                                                <button type="submit" form="deleteOnboardedForm{{ $candidate->id }}"
                                                    class="btn btn-outline-danger"
                                                    onclick="return confirm('Delete this newly onboarded entry?');">
                                                    Delete Onboarded Entry
                                                </button>
                                            @endif
                                            <button type="submit" form="markOnDutyForm{{ $candidate->id }}"
                                                class="btn btn-primary">Mark On Duty</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        @endunless
                    </div>
                </div>
            </div>
        </div>


    </div>

    <script>
        document.addEventListener('shown.bs.tab', function(event) {
            if (window.jQuery && jQuery.fn.dataTable) {
                jQuery.fn.dataTable.tables({
                    visible: true,
                    api: true
                }).columns.adjust();
            }

            const tabInput = document.getElementById('employeeFilterTabInput');
            const resetLink = document.getElementById('employeeFilterResetLink');
            const targetId = event?.target?.id || '';

            if (!tabInput) {
                return;
            }

            if (targetId === 'inactive-employees-tab') {
                tabInput.value = 'inactive';
                if (resetLink) {
                    resetLink.href = @json(route('admin-employee-index')) + '?tab=inactive';
                }
                return;
            }

            if (targetId === 'outsource-employees-tab') {
                tabInput.value = 'outsource';
                if (resetLink) {
                    resetLink.href = @json(route('admin-employee-index')) + '?tab=outsource';
                }
                return;
            }

            if (targetId === 'onboarded-employees-tab') {
                tabInput.value = 'onboarded';
                if (resetLink) {
                    resetLink.href = @json(route('admin-employee-index')) + '?tab=onboarded';
                }
                return;
            }

            tabInput.value = 'active';
            if (resetLink) {
                resetLink.href = @json(route('admin-employee-index')) + '?tab=active';
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const reopenCandidateId = @json($reopenOnboardedCandidateId);

            if (!reopenCandidateId || !window.bootstrap) {
                return;
            }

            const modalElement = document.getElementById(`markOnDutyModal${reopenCandidateId}`);

            if (!modalElement) {
                return;
            }

            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        });
    </script>
@endsection
