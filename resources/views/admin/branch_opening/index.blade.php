@extends('admin.layout.app')

@section('content')
    @include('admin.attendance.partials.styles')

    <style>
        .branch-opening-grid {
            display: grid;
            grid-template-columns: minmax(280px, 0.9fr) minmax(0, 1.6fr);
            gap: 1rem;
        }

        .branch-opening-list {
            max-height: 680px;
            overflow: auto;
            border: 1px solid var(--admin-border-color);
            border-radius: 18px;
        }

        .branch-opening-list .table {
            margin-bottom: 0;
        }

        .branch-opening-list tr.is-selected {
            background: rgba(var(--admin-primary-color-rgb), 0.08);
        }

        .branch-opening-staff {
            max-height: 560px;
            overflow: auto;
            border: 1px solid var(--admin-border-color);
            border-radius: 18px;
        }

        .branch-opening-staff table {
            margin-bottom: 0;
        }

        .branch-opening-staff thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: var(--admin-surface-color);
        }

        .branch-opening-role-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem 0.55rem;
            border-radius: 999px;
            border: 1px solid rgba(var(--admin-primary-color-rgb), 0.12);
            background: rgba(var(--admin-primary-color-rgb), 0.06);
            font-size: 0.78rem;
            font-weight: 600;
        }

        .branch-opening-alert-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .branch-opening-alert-card {
            border: 1px solid rgba(220, 53, 69, 0.14);
            border-radius: 18px;
            background: #fff5f5;
            padding: 1rem;
        }

        .branch-opening-alert-card.is-resolved {
            border-color: rgba(25, 135, 84, 0.14);
            background: #f4fff8;
        }

        .branch-opening-panel {
            border: 1px solid rgba(var(--admin-primary-color-rgb), 0.1);
            border-radius: 22px;
            background: linear-gradient(180deg, rgba(var(--admin-primary-color-rgb), 0.03), rgba(255, 255, 255, 0.92));
            box-shadow: 0 18px 40px rgba(36, 24, 62, 0.06);
        }

        .branch-opening-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.85rem;
            margin-bottom: 1rem;
        }

        .branch-opening-meta-card {
            border-radius: 18px;
            padding: 0.95rem 1rem;
            background: rgba(var(--admin-primary-color-rgb), 0.05);
        }

        .branch-opening-meta-label {
            font-size: 0.75rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--admin-muted-text-color);
            margin-bottom: 0.3rem;
        }

        .branch-opening-meta-value {
            font-size: 1rem;
            font-weight: 700;
            color: var(--admin-text-color);
        }

        .branch-opening-staff tr.is-hidden {
            display: none;
        }

        @media (max-width: 991.98px) {
            .branch-opening-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="main-content attendance-page">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Branch Opening</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin-dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Opening & Keys</li>
                    </ol>
                </nav>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success border-0">{{ session('status') }}</div>
        @endif

        @if ($activeAlerts->isNotEmpty() || $recentAlerts->isNotEmpty())
            <div class="branch-opening-alert-grid">
                @foreach ($activeAlerts as $alert)
                    <div class="branch-opening-alert-card">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="fw-semibold text-danger">Overdue Branch Opening</div>
                                <div class="small text-muted">{{ trim($alert->branch_id . ' - ' . $alert->branch_name, ' -') }}</div>
                            </div>
                            <span class="badge bg-danger-subtle text-danger">{{ $alert->overdue_minutes }} min late</span>
                        </div>
                        <div class="small mt-2">
                            Opening time: {{ \Illuminate\Support\Carbon::parse($alert->opening_time)->format('H:i') }}
                            on {{ optional($alert->opening_date)->format('d M Y') ?: $alert->opening_date }}
                        </div>
                    </div>
                @endforeach

                @foreach ($recentAlerts->where('status', '!=', 'overdue')->take(4) as $alert)
                    <div class="branch-opening-alert-card is-resolved">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="fw-semibold text-success">Resolved Branch Opening</div>
                                <div class="small text-muted">{{ trim($alert->branch_id . ' - ' . $alert->branch_name, ' -') }}</div>
                            </div>
                            <span class="badge bg-success-subtle text-success">
                                {{ $alert->status === 'resolved_on_time' ? 'On time' : 'Resolved late' }}
                            </span>
                        </div>
                        <div class="small mt-2">
                            @if ($alert->opened_at)
                                Opened at {{ optional($alert->opened_at)->format('d M Y H:i') }}
                                @if ($alert->opener_emp_id || $alert->opener_name)
                                    by {{ trim(($alert->opener_emp_id ?: '') . ' ' . ($alert->opener_name ?: ''), ' ') }}
                                @endif
                            @else
                                Resolved on {{ optional($alert->resolved_at)->format('d M Y H:i') ?: '--' }}
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="card rounded-4 branch-opening-panel">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
                    <div>
                        <h4 class="mb-0 attendance-title">Branch Opening & Key Holders</h4>
                    </div>
                    <div>
                        <a href="{{ route('admin-branch-opening-timings') }}" class="btn btn-outline-primary">
                            View Daily Timings & KPI
                        </a>
                    </div>
                </div>

                <form method="get" action="{{ route('admin-branch-opening-index') }}" class="row g-3 align-items-end mb-4">
                    <div class="col-md-3">
                        <label class="form-label">State</label>
                        <input type="search" name="state" class="form-control" list="branchOpeningStateOptions"
                            value="{{ $filters['state'] }}" placeholder="All active states">
                        <datalist id="branchOpeningStateOptions">
                            @foreach ($filters['states'] as $state)
                                <option value="{{ $state }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">City</label>
                        <input type="search" name="city" class="form-control" list="branchOpeningCityOptions"
                            value="{{ $filters['city'] }}" placeholder="All active cities">
                        <datalist id="branchOpeningCityOptions">
                            @foreach ($filters['cities'] as $city)
                                <option value="{{ $city }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Branch</label>
                        <input type="search" name="branch_search" class="form-control" list="branchOpeningBranchFilterOptions"
                            value="{{ $filters['branch_search'] }}" placeholder="Branch ID or name">
                        <datalist id="branchOpeningBranchFilterOptions">
                            @foreach ($branches as $branch)
                                <option value="{{ trim((string) $branch->branchId . ' - ' . $branch->branchName, ' -') }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100">Apply</button>
                        <a href="{{ route('admin-branch-opening-index') }}" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>

                <div class="branch-opening-grid">
                    <div>
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                            <h5 class="mb-0 attendance-title">Branches</h5>
                            <span class="badge bg-grd-primary">{{ $branchRows->count() }}</span>
                        </div>
                        <div class="branch-opening-list">
                            <table class="table table-bordered table-hover align-middle" data-admin-static-serial="true">
                                <thead>
                                    <tr>
                                        <th>Branch</th>
                                        <th>Staff</th>
                                        <th>Keys</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($branchRows as $row)
                                        @php($isSelected = $row['branch_id'] === $selectedBranchId)
                                        <tr class="{{ $isSelected ? 'is-selected' : '' }}">
                                            <td>
                                                <a href="{{ route('admin-branch-opening-index', array_filter([
                                                    'branch_id' => $row['branch_id'],
                                                    'state' => $filters['state'] ?: null,
                                                    'city' => $filters['city'] ?: null,
                                                    'branch_search' => $filters['branch_search'] ?: null,
                                                ])) }}"
                                                    class="fw-semibold">
                                                    {{ $row['branch_id'] }}
                                                </a>
                                                <div class="small text-muted">{{ $row['branch']->branchName ?: '--' }}</div>
                                                <div class="small text-muted">{{ $row['branch']->city ?: '--' }}, {{ $row['branch']->state ?: '--' }}</div>
                                            </td>
                                            <td>{{ $row['staff_count'] }}</td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-1">
                                                    @if ($row['opening_time'] !== '')
                                                        <span class="branch-opening-role-pill">Time {{ $row['opening_time'] }}</span>
                                                    @endif
                                                    <span class="branch-opening-role-pill">Door {{ $row['door_key_count'] }}</span>
                                                    <span class="branch-opening-role-pill">Locker {{ $row['locker_key_count'] }}</span>
                                                    <span class="branch-opening-role-pill">Open {{ $row['opener_count'] }}</span>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center text-muted py-4">No active branches match the selected filters.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div>
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
                            <div>
                                <h5 class="mb-1 attendance-title">
                                    {{ $selectedBranch ? trim($selectedBranch->branchId . ' - ' . $selectedBranch->branchName, ' -') : 'No branch selected' }}
                                </h5>
                            </div>
                            <form method="get" action="{{ route('admin-branch-opening-index') }}" class="d-flex gap-2 align-items-end">
                                <div>
                                    <label class="form-label mb-1">Jump To Branch</label>
                                    <input
                                        name="branch_id"
                                        class="form-control"
                                        list="branchOpeningBranches"
                                        onchange="this.form.submit()"
                                        value=""
                                        placeholder="Type branch ID or name">
                                    <datalist id="branchOpeningBranches">
                                        @foreach ($branches as $branch)
                                            <option value="{{ trim((string) $branch->branchId . ' - ' . $branch->branchName, ' -') }}"></option>
                                        @endforeach
                                    </datalist>
                                </div>
                            </form>
                        </div>

                        <form method="post" action="{{ route('admin-branch-opening-update') }}">
                            @csrf
                            <input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">

                            <div class="branch-opening-meta">
                                <div class="branch-opening-meta-card">
                                    <div class="branch-opening-meta-label">Admin Number</div>
                                    <div class="branch-opening-meta-value">{{ $adminPhone }}</div>
                                </div>
                                <div class="branch-opening-meta-card">
                                    <div class="branch-opening-meta-label">Door Key Holders</div>
                                    <div class="branch-opening-meta-value">{{ count($assignments[\App\Models\BranchOpeningAssignment::TYPE_DOOR_KEY] ?? []) }}</div>
                                </div>
                                <div class="branch-opening-meta-card">
                                    <div class="branch-opening-meta-label">Locker Key Holders</div>
                                    <div class="branch-opening-meta-value">{{ count($assignments[\App\Models\BranchOpeningAssignment::TYPE_LOCKER_KEY] ?? []) }}</div>
                                </div>
                                <div class="branch-opening-meta-card">
                                    <div class="branch-opening-meta-label">Openers</div>
                                    <div class="branch-opening-meta-value">{{ count($assignments[\App\Models\BranchOpeningAssignment::TYPE_OPENER] ?? []) }}</div>
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <label class="form-label">Branch Opening Time</label>
                                    <input
                                        type="time"
                                        step="60"
                                        name="opening_time"
                                        class="form-control"
                                        list="branchOpeningTimeSuggestions"
                                        value="{{ old('opening_time', $openingTime) }}"
                                        placeholder="HH:MM">
                                    <datalist id="branchOpeningTimeSuggestions">
                                        @foreach ($openingTimeSuggestions as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </datalist>
                                    @error('opening_time')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Admin Number</label>
                                    <input
                                        type="text"
                                        name="admin_phone"
                                        class="form-control"
                                        value="{{ old('admin_phone', $adminPhone) }}"
                                        placeholder="Admin mobile number">
                                    @error('admin_phone')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6 col-lg-5">
                                    <label class="form-label">Search Employees</label>
                                    <input
                                        type="search"
                                        id="branchOpeningEmployeeSearch"
                                        class="form-control"
                                        placeholder="Search by employee ID, name, designation, or contact">
                                </div>
                            </div>

                            <div class="branch-opening-staff mb-3">
                                <table class="table table-bordered table-hover align-middle" data-admin-static-serial="true">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Designation</th>
                                            <th>Contact</th>
                                            @foreach ($assignmentTypes as $type => $label)
                                                <th class="text-center">{{ $label }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($branchStaff as $employee)
                                            <tr
                                                data-branch-opening-employee-row
                                                data-search-text="{{ strtolower(trim(implode(' ', [
                                                    $employee->empId,
                                                    $employee->name,
                                                    $employee->designation,
                                                    $employee->contact,
                                                ]))) }}">
                                                <td>
                                                    <strong>{{ trim($employee->empId) }}</strong>
                                                    <div class="small text-muted">{{ $employee->name ?: '--' }}</div>
                                                </td>
                                                <td>{{ $employee->designation ?: '--' }}</td>
                                                <td>{{ $employee->contact ?: '--' }}</td>
                                                @foreach ($assignmentTypes as $type => $label)
                                                    <td class="text-center">
                                                        <input type="checkbox"
                                                            name="assignments[{{ $type }}][]"
                                                            value="{{ $employee->id }}"
                                                            @checked(in_array((int) $employee->id, $assignments[$type] ?? [], true))>
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @empty
                                            <tr id="branchOpeningEmptyStateRow">
                                                <td colspan="{{ 3 + count($assignmentTypes) }}" class="text-center text-muted py-4">
                                                    No active employees mapped to this branch.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <button type="submit" class="btn btn-primary px-4" @disabled($selectedBranchId === '')>
                                Save Opening Setup & Notify Openers
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('branchOpeningEmployeeSearch');
            const rows = Array.from(document.querySelectorAll('[data-branch-opening-employee-row]'));

            if (!searchInput || rows.length === 0) {
                return;
            }

            function applySearch() {
                const query = (searchInput.value || '').trim().toLowerCase();

                rows.forEach((row) => {
                    const searchable = row.dataset.searchText || '';
                    row.classList.toggle('is-hidden', query !== '' && !searchable.includes(query));
                });
            }

            searchInput.addEventListener('input', applySearch);
        });
    </script>
@endsection
