@extends('admin.layout.app')
@section('content')
    @php
        $dashboardAdminUser = Auth::guard('admin')->user();
        $showOnlyRecruitmentGraphsForHr = strtolower(trim((string) ($dashboardAdminUser?->name ?? ''))) === 'hr'
            && strtolower(trim((string) ($dashboardAdminUser?->email ?? ''))) === 'hr.attica@gmail.com';
        $showRecruitmentGraphs = ($showRecruitmentDashboard ?? false) || $showOnlyRecruitmentGraphsForHr;
    @endphp

    <style>
        .dashboard-stats-shell {
            border: 1px solid var(--admin-border-color);
            border-radius: 1rem;
            background: var(--admin-surface-color);
            padding: 1rem;
        }

        .dashboard-stat-card {
            height: 160px;
            border: 1px solid var(--admin-border-color);
            border-radius: 1rem;
            box-shadow: none;
            overflow: hidden;
            background: linear-gradient(180deg, rgba(var(--admin-primary-color-rgb), 0.02) 0%, rgba(var(--admin-primary-color-rgb), 0.06) 100%);
        }

        .dashboard-stat-card::before {
            content: "";
            display: block;
            height: 4px;
            background: linear-gradient(90deg, var(--admin-primary-color) 0%, var(--admin-highlight-color) 100%);
        }

        .dashboard-stat-card .card-body {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            height: calc(160px - 4px);
            gap: 0.5rem;
        }

        .dashboard-stat-card h5 {
            margin-bottom: 0;
            color: var(--admin-text-color);
            font-weight: 600;
        }

        .dashboard-stat-number {
            color: var(--admin-text-color);
            font-size: 50px;
            font-weight: 700;
            line-height: 1;
        }

        .dashboard-stat-trigger {
            display: block;
            width: 100%;
            padding: 0;
            border: 0;
            background: transparent;
            appearance: none;
            text-align: inherit;
            cursor: pointer;
            color: inherit;
            text-decoration: none;
        }

        .dashboard-stat-trigger:focus-visible {
            outline: 0;
            box-shadow: 0 0 0 0.25rem rgba(var(--admin-primary-color-rgb), 0.18);
        }

        .dashboard-stat-card.stat-accent-soft {
            background: linear-gradient(180deg, rgba(var(--admin-primary-color-rgb), 0.03) 0%, rgba(var(--admin-primary-color-rgb), 0.08) 100%);
        }

        .dashboard-stat-card.stat-accent-gold {
            background: linear-gradient(180deg, rgba(200, 162, 74, 0.08) 0%, rgba(200, 162, 74, 0.16) 100%);
        }

        .dashboard-stat-card.stat-accent-neutral {
            background: linear-gradient(180deg, rgba(var(--admin-text-color-rgb), 0.02) 0%, rgba(var(--admin-text-color-rgb), 0.05) 100%);
        }

        .dashboard-stat-card.stat-accent-alert {
            background: linear-gradient(180deg, rgba(166, 61, 47, 0.06) 0%, rgba(166, 61, 47, 0.12) 100%);
        }

        .dashboard-branch-modal .modal-content {
            border: 1px solid var(--admin-border-color);
            border-radius: 1rem;
            background: var(--admin-surface-color);
        }

        .dashboard-branch-modal .modal-header,
        .dashboard-branch-modal .modal-footer {
            border-color: var(--admin-border-color);
        }

        .dashboard-branch-summary {
            border: 1px solid var(--admin-border-color);
            border-radius: 0.9rem;
            background: rgba(var(--admin-primary-color-rgb), 0.03);
        }

        .dashboard-branch-summary+.dashboard-branch-summary {
            margin-top: 1rem;
        }

        .dashboard-branch-summary__header {
            padding: 1rem 1.1rem 0.75rem;
            border-bottom: 1px solid var(--admin-border-color);
        }

        .dashboard-branch-summary__employees {
            padding: 0.75rem 1.1rem 1rem;
        }

        .dashboard-branch-summary__employees .list-group-item {
            padding-left: 0;
            padding-right: 0;
            border-color: rgba(var(--admin-text-color-rgb), 0.08);
            background: transparent;
        }

        .dashboard-chart-card {
            border: 1px solid var(--admin-border-color);
            border-radius: 1rem;
            background: var(--admin-surface-color);
            height: 100%;
        }

        .dashboard-chart-card .card-body {
            padding: 1.25rem;
        }

        .dashboard-chart-title {
            margin-bottom: 0.35rem;
            color: var(--admin-text-color);
            font-weight: 600;
        }

        .dashboard-chart-subtitle {
            margin-bottom: 0;
            color: var(--admin-muted-text-color);
            font-size: 0.92rem;
        }

        .dashboard-chart-meta {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            margin-top: 0.9rem;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            background: rgba(var(--admin-primary-color-rgb), 0.08);
            color: var(--admin-text-color);
            font-size: 0.82rem;
            font-weight: 600;
        }

        .dashboard-chart-canvas {
            min-height: 320px;
            margin-top: 1rem;
        }

        .dashboard-punctuality-card {
            border: 1px solid var(--admin-border-color);
            border-radius: 1rem;
            background: var(--admin-surface-color);
            height: 100%;
        }

        .dashboard-punctuality-card .card-body {
            padding: 1.15rem;
        }

        .dashboard-punctuality-card__label {
            color: var(--admin-muted-text-color);
            font-size: 0.84rem;
            margin-bottom: 0.35rem;
        }

        .dashboard-punctuality-card__value {
            color: var(--admin-text-color);
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
        }

        .dashboard-punctuality-table td,
        .dashboard-punctuality-table th {
            vertical-align: middle;
        }

        .dashboard-punctuality-scope {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 68px;
            padding: 0.22rem 0.6rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
        }

        .dashboard-punctuality-scope.scope-ho {
            background: rgba(var(--admin-primary-color-rgb), 0.12);
            color: var(--admin-primary-color);
        }

        .dashboard-punctuality-scope.scope-branch {
            background: rgba(200, 162, 74, 0.16);
            color: #8b6b18;
        }

        .dashboard-branch-opening-attention td,
        .dashboard-branch-opening-attention th {
            vertical-align: middle;
        }

        .dashboard-branch-opening-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 102px;
            padding: 0.22rem 0.65rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
        }

        .dashboard-branch-opening-status.is-late,
        .dashboard-branch-opening-status.is-not-opened {
            background: rgba(166, 61, 47, 0.14);
            color: #a63d2f;
        }
    </style>

    <div class="main-content">
        @if (! $showOnlyRecruitmentGraphsForHr)
        <div class="col">
            <div class="card dashboard-stats-shell">
                <div class="card-body">
                    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3">
                        <div class="col">
                            <div class="card shadow-none mb-0 dashboard-stat-card stat-accent-soft">
                                <div class="card-body">
                                    <h5>Active Branches</h5>
                                    <div class="dashboard-stat-number">{{ $activeBranchCount }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card shadow-none mb-0 dashboard-stat-card stat-accent-gold">
                                <div class="card-body">
                                    <h5>Employee Count</h5>
                                    <div class="dashboard-stat-number">{{ $employeeCount }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card shadow-none mb-0 dashboard-stat-card stat-accent-neutral">
                                <div class="card-body">
                                    <h5>HO Employees</h5>
                                    <div class="dashboard-stat-number">{{ $hoEmployeeCount }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card shadow-none mb-0 dashboard-stat-card stat-accent-alert">
                                <div class="card-body">
                                    <h5>Branch Employees</h5>
                                    <div class="dashboard-stat-number">{{ $branchEmployeeCount }}</div>
                                </div>
                            </div>
                        </div>
                        @if ($showRecruitmentDashboard)
                            <div class="col">
                                <div class="card shadow-none mb-0 dashboard-stat-card stat-accent-soft">
                                    <div class="card-body">
                                        <h5>People Applied</h5>
                                        <div class="dashboard-stat-number">{{ $appliedCandidatesCount }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card shadow-none mb-0 dashboard-stat-card stat-accent-neutral">
                                    <div class="card-body">
                                        <h5>People Selected</h5>
                                        <div class="dashboard-stat-number">{{ $selectedCandidatesCount }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card shadow-none mb-0 dashboard-stat-card stat-accent-gold">
                                    <div class="card-body">
                                        <h5>People Hired</h5>
                                        <div class="dashboard-stat-number">{{ $hiredCandidatesCount }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card shadow-none mb-0 dashboard-stat-card stat-accent-soft">
                                    <div class="card-body">
                                        <h5>People Onboarded</h5>
                                        <div class="dashboard-stat-number">{{ $onboardedCandidatesCount }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card shadow-none mb-0 dashboard-stat-card stat-accent-alert">
                                    <div class="card-body">
                                        <h5>Marked On Duty</h5>
                                        <div class="dashboard-stat-number">{{ $markedOnDutyCandidatesCount }}</div>
                                    </div>
                                </div>
                            </div>
                        @endif
                        @if ($showAttendanceDashboard)
                            <div class="col">
                                <button type="button" class="dashboard-stat-trigger" data-bs-toggle="modal"
                                    data-bs-target="#hoEmployeesModal">
                                    <div class="card shadow-none mb-0 dashboard-stat-card stat-accent-soft">
                                        <div class="card-body">
                                            <h5>HO Checked-In Today</h5>
                                            <div class="dashboard-stat-number">{{ $hoCheckedInCount }}</div>
                                        </div>
                                    </div>
                                </button>
                            </div>
                            <div class="col">
                                <button type="button" class="dashboard-stat-trigger" data-bs-toggle="modal"
                                    data-bs-target="#branchEmployeesModal">
                                    <div class="card shadow-none mb-0 dashboard-stat-card stat-accent-gold">
                                        <div class="card-body">
                                            <h5>Branch Checked-In Today</h5>
                                            <div class="dashboard-stat-number">{{ $branchCheckedInCount }}</div>
                                        </div>
                                    </div>
                                </button>
                            </div>
                            <div class="col">
                                <a href="{{ route('admin-leaves-review') }}" class="dashboard-stat-trigger">
                                    <div class="card shadow-none mb-0 dashboard-stat-card stat-accent-neutral">
                                        <div class="card-body">
                                            <h5>Pending Leaves</h5>
                                            <div class="dashboard-stat-number">{{ $pendingLeaveCount }}</div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col">
                                <a href="{{ route('admin-work-visits-review') }}" class="dashboard-stat-trigger">
                                    <div class="card shadow-none mb-0 dashboard-stat-card stat-accent-neutral">
                                        <div class="card-body">
                                            <h5>Pending Work Visits</h5>
                                            <div class="dashboard-stat-number">{{ $pendingWorkVisitCount }}</div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col">
                                <a href="{{ route('admin-branch-opening-timings') }}" class="dashboard-stat-trigger">
                                    <div class="card shadow-none mb-0 dashboard-stat-card stat-accent-alert">
                                        <div class="card-body">
                                            <h5>Late Branch Openings</h5>
                                            <div class="dashboard-stat-number">{{ $branchOpeningLateCount }}</div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col">
                                <a href="{{ route('admin-branch-opening-timings') }}" class="dashboard-stat-trigger">
                                    <div class="card shadow-none mb-0 dashboard-stat-card stat-accent-alert">
                                        <div class="card-body">
                                            <h5>Still Closed After Time</h5>
                                            <div class="dashboard-stat-number">{{ $branchOpeningOverdueCount }}</div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if ($showRecruitmentGraphs)
            <div class="row g-3 mt-1">
                <div class="col-12 col-xl-6">
                    <div class="card shadow-none mb-0 dashboard-chart-card">
                        <div class="card-body">
                            <h5 class="dashboard-chart-title">{{ $recruitmentRegionChart['title'] }}</h5>
                            <p class="dashboard-chart-subtitle">{{ $recruitmentRegionChart['description'] }}</p>
                            <div class="dashboard-chart-meta">
                                <i class="bx bx-pie-chart-alt-2"></i>
                                <span>{{ $recruitmentRegionChart['total_candidates'] }} submitted applications
                                    tracked</span>
                            </div>
                            <div id="dashboardRecruitmentRegionChart" class="dashboard-chart-canvas"></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-6">
                    <div class="card shadow-none mb-0 dashboard-chart-card">
                        <div class="card-body">
                            <h5 class="dashboard-chart-title">{{ $recruitmentPositionChart['title'] }}</h5>
                            <p class="dashboard-chart-subtitle">{{ $recruitmentPositionChart['description'] }}</p>
                            <div class="dashboard-chart-meta">
                                <i class="bx bx-briefcase"></i>
                                <span>{{ $recruitmentPositionChart['total_candidates'] }} submitted applications
                                    tracked</span>
                            </div>
                            <div id="dashboardRecruitmentPositionChart" class="dashboard-chart-canvas"></div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($showAttendanceDashboard && ! $showOnlyRecruitmentGraphsForHr)
            <div class="row g-3 mt-1">
                <div class="col-12">
                    <div class="card shadow-none mb-0 dashboard-chart-card">
                        <div class="card-body">
                            <div
                                class="d-flex flex-column flex-lg-row justify-content-between gap-2 align-items-lg-center mb-3">
                                <div>
                                    <h5 class="dashboard-chart-title">Today's Branch Opening Watch</h5>
                                    <p class="dashboard-chart-subtitle">
                                        Branches whose assigned opener checked in late or still has not checked in after the
                                        scheduled opening time.
                                    </p>
                                </div>
                                <div>
                                    <a href="{{ route('admin-branch-opening-timings') }}"
                                        class="btn btn-outline-primary btn-sm">View Full Timings</a>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table
                                    class="table table-bordered table-hover align-middle mb-0 dashboard-branch-opening-attention"
                                    data-admin-static-serial="true">
                                    <thead>
                                        <tr>
                                            <th>Branch</th>
                                            <th>Scheduled</th>
                                            <th>Opened At</th>
                                            <th>Status</th>
                                            <th>Delay</th>
                                            <th>Opened By</th>
                                            <th>Latest Checkout</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($branchOpeningAttentionRows as $row)
                                            @php
                                                $statusClass =
                                                    $row->opening_status === 'not_opened' ? 'is-not-opened' : 'is-late';
                                                $statusLabel =
                                                    $row->opening_status === 'not_opened' ? 'Not Opened' : 'Late';
                                            @endphp
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold">{{ $row->branch_name ?: $row->branch_id }}
                                                    </div>
                                                    <div class="text-secondary small">{{ $row->branch_id }}</div>
                                                </td>
                                                <td>{{ $row->scheduled_opening_time ? \Illuminate\Support\Carbon::parse($row->scheduled_opening_time)->format('H:i') : '--' }}
                                                </td>
                                                <td>{{ optional($row->opened_at)->format('H:i') ?: '--' }}</td>
                                                <td><span
                                                        class="dashboard-branch-opening-status {{ $statusClass }}">{{ $statusLabel }}</span>
                                                </td>
                                                <td>{{ $row->opening_delay_minutes > 0 ? $row->opening_delay_minutes . ' min' : '--' }}
                                                </td>
                                                <td>
                                                    @if ($row->opened_by_emp_id || $row->opened_by_name)
                                                        <div class="fw-semibold">
                                                            {{ trim(($row->opened_by_emp_id ?: '') . ' ' . ($row->opened_by_name ?: ''), ' ') }}
                                                        </div>
                                                    @else
                                                        --
                                                    @endif
                                                </td>
                                                <td>{{ optional($row->closed_at)->format('H:i') ?: '--' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted">All scheduled branch
                                                    openings are on time right now.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($showAttendanceDashboard && ! $showOnlyRecruitmentGraphsForHr)
            <div class="row g-3 mt-1">
                <div class="col-12">
                    <div class="card shadow-none mb-0 dashboard-chart-card">
                        <div class="card-body">
                            <div
                                class="d-flex flex-column flex-lg-row justify-content-between gap-2 align-items-lg-center mb-3">
                                <div>
                                    <h5 class="dashboard-chart-title">Punctuality Overview</h5>
                                    <p class="dashboard-chart-subtitle">
                                        Late logins and early logouts from
                                        {{ $dashboardPunctualityStart->format('d M Y') }} to
                                        {{ $today->format('d M Y') }}.
                                    </p>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-12 col-md-6 col-xl-3">
                                    <div class="dashboard-punctuality-card">
                                        <div class="card-body">
                                            <div class="dashboard-punctuality-card__label">Regular Employees</div>
                                            <div class="dashboard-punctuality-card__value">{{ $regularEmployeeCount }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6 col-xl-3">
                                    <div class="dashboard-punctuality-card">
                                        <div class="card-body">
                                            <div class="dashboard-punctuality-card__label">Irregular Employees</div>
                                            <div class="dashboard-punctuality-card__value">{{ $irregularEmployeeCount }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6 col-xl-3">
                                    <div class="dashboard-punctuality-card">
                                        <div class="card-body">
                                            <div class="dashboard-punctuality-card__label">HO Late Logins</div>
                                            <div class="dashboard-punctuality-card__value">{{ $hoLateDays }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6 col-xl-3">
                                    <div class="dashboard-punctuality-card">
                                        <div class="card-body">
                                            <div class="dashboard-punctuality-card__label">Branch Late Logins</div>
                                            <div class="dashboard-punctuality-card__value">{{ $branchLateDays }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-6">
                    <div class="card shadow-none mb-0 dashboard-chart-card">
                        <div class="card-body">
                            <h5 class="dashboard-chart-title">{{ $hoAttendanceChart['title'] }}</h5>
                            <p class="dashboard-chart-subtitle">{{ $hoAttendanceChart['description'] }}</p>
                            <div class="dashboard-chart-meta">
                                <i class="bx bx-buildings"></i>
                                <span>{{ $hoAttendanceChart['employee_count'] }} HO employees tracked</span>
                            </div>
                            <div id="dashboardHoAttendanceChart" class="dashboard-chart-canvas"></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-6">
                    <div class="card shadow-none mb-0 dashboard-chart-card">
                        <div class="card-body">
                            <h5 class="dashboard-chart-title">{{ $branchAttendanceChart['title'] }}</h5>
                            <p class="dashboard-chart-subtitle">{{ $branchAttendanceChart['description'] }}</p>
                            <div class="dashboard-chart-meta">
                                <i class="bx bx-git-branch"></i>
                                <span>{{ $branchAttendanceChart['employee_count'] }} branch employees tracked</span>
                            </div>
                            <div id="dashboardBranchAttendanceChart" class="dashboard-chart-canvas"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-12">
                    <div class="card shadow-none mb-0 dashboard-chart-card">
                        <div class="card-body">
                            <div
                                class="d-flex flex-column flex-lg-row justify-content-between gap-2 align-items-lg-center mb-3">
                                <div>
                                    <h5 class="dashboard-chart-title">Late Login Ranking</h5>
                                    <p class="dashboard-chart-subtitle">
                                        Employees who were repeatedly late or logged out early in the current month.
                                    </p>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table
                                    class="table table-bordered table-hover align-middle mb-0 dashboard-punctuality-table"
                                    data-admin-static-serial="true">
                                    <thead>
                                        <tr>
                                            <th>Rank</th>
                                            <th>Employee</th>
                                            <th>Scope</th>
                                            <th>Late Days</th>
                                            <th>Early Logouts</th>
                                            <th>Irregular Days</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($lateLeaderboard as $index => $row)
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td>
                                                    <div class="fw-semibold">{{ $row['employee_name'] }}</div>
                                                    <div class="text-secondary small">
                                                        {{ $row['emp_id'] }}{{ $row['designation'] ? ' | ' . $row['designation'] : '' }}
                                                    </div>
                                                </td>
                                                <td>
                                                    <span
                                                        class="dashboard-punctuality-scope {{ $row['scope'] === 'HO' ? 'scope-ho' : 'scope-branch' }}">
                                                        {{ $row['scope'] }}
                                                    </span>
                                                </td>
                                                <td>{{ $row['late_days'] }}</td>
                                                <td>{{ $row['early_logout_days'] }}</td>
                                                <td>{{ $row['irregular_days'] }}</td>
                                                <td>
                                                    <button type="button" class="btn btn-outline-primary btn-sm"
                                                        data-late-payload='{{ json_encode(
                                                            [
                                                                'empId' => $row['emp_id'],
                                                                'employeeName' => $row['employee_name'],
                                                                'schedule' => $row['schedule'],
                                                                'lateDays' => $row['late_days'],
                                                                'earlyLogoutDays' => $row['early_logout_days'],
                                                                'irregularDays' => $row['irregular_days'],
                                                                'details' => $row['details'],
                                                            ],
                                                            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP,
                                                        ) }}'
                                                        onclick="openDashboardLateModal(this.dataset.latePayload)">
                                                        View Days
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted">No late login data is
                                                    available for the selected dashboard period.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($showAttendanceDashboard && ! $showOnlyRecruitmentGraphsForHr)
            <div class="modal fade dashboard-branch-modal" id="hoEmployeesModal" tabindex="-1"
                aria-labelledby="hoEmployeesModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="hoEmployeesModalLabel">Today's HO Check-Ins</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            @forelse ($hoCheckInSummary as $branchSummary)
                                <section class="dashboard-branch-summary">
                                    <div class="dashboard-branch-summary__header">
                                        <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
                                            <div>
                                                <h6 class="mb-1">{{ $branchSummary['branch_name'] }}</h6>
                                                <p class="mb-0 text-secondary small">
                                                    {{ $branchSummary['branch_id'] }}
                                                    @if ($branchSummary['city'] !== '' || $branchSummary['state'] !== '')
                                                        |
                                                        {{ trim($branchSummary['city'] . ', ' . $branchSummary['state'], ', ') }}
                                                    @endif
                                                </p>
                                            </div>
                                            <span class="badge text-bg-light">{{ $branchSummary['employee_count'] }}
                                                checked-in</span>
                                        </div>
                                    </div>
                                    <div class="dashboard-branch-summary__employees">
                                        <div class="list-group list-group-flush">
                                            @foreach ($branchSummary['employees'] as $employee)
                                                <div
                                                    class="list-group-item d-flex flex-wrap justify-content-between gap-2">
                                                    <div>
                                                        <div class="fw-semibold">{{ $employee['name'] }}</div>
                                                        <div class="text-secondary small">{{ $employee['emp_id'] }}</div>
                                                    </div>
                                                    <div class="text-secondary small">
                                                        {{ $employee['check_in_time'] !== '' ? 'Checked-in at ' . $employee['check_in_time'] : 'Check-in time unavailable' }}
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </section>
                            @empty
                                <div class="text-center py-4">
                                    <h6 class="mb-2">No HO employees checked-in today.</h6>
                                    <p class="mb-0 text-secondary small">This list will populate as Head Office employees
                                        mark attendance.</p>
                                </div>
                            @endforelse
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary"
                                data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade dashboard-branch-modal" id="branchEmployeesModal" tabindex="-1"
                aria-labelledby="branchEmployeesModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="branchEmployeesModalLabel">Today's Branch Check-Ins</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            @forelse ($branchCheckInSummary as $branchSummary)
                                <section class="dashboard-branch-summary">
                                    <div class="dashboard-branch-summary__header">
                                        <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
                                            <div>
                                                <h6 class="mb-1">{{ $branchSummary['branch_name'] }}</h6>
                                                <p class="mb-0 text-secondary small">
                                                    {{ $branchSummary['branch_id'] }}
                                                    @if ($branchSummary['city'] !== '' || $branchSummary['state'] !== '')
                                                        |
                                                        {{ trim($branchSummary['city'] . ', ' . $branchSummary['state'], ', ') }}
                                                    @endif
                                                </p>
                                            </div>
                                            <span class="badge text-bg-light">{{ $branchSummary['employee_count'] }}
                                                checked-in</span>
                                        </div>
                                    </div>
                                    <div class="dashboard-branch-summary__employees">
                                        <div class="list-group list-group-flush">
                                            @foreach ($branchSummary['employees'] as $employee)
                                                <div
                                                    class="list-group-item d-flex flex-wrap justify-content-between gap-2">
                                                    <div>
                                                        <div class="fw-semibold">{{ $employee['name'] }}</div>
                                                        <div class="text-secondary small">{{ $employee['emp_id'] }}</div>
                                                    </div>
                                                    <div class="text-secondary small">
                                                        {{ $employee['check_in_time'] !== '' ? 'Checked-in at ' . $employee['check_in_time'] : 'Check-in time unavailable' }}
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </section>
                            @empty
                                <div class="text-center py-4">
                                    <h6 class="mb-2">No branch employees checked-in today.</h6>
                                    <p class="mb-0 text-secondary small">This list will populate as employees mark
                                        attendance from branches.</p>
                                </div>
                            @endforelse
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary"
                                data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade dashboard-branch-modal" id="dashboardLateModal" tabindex="-1"
                aria-labelledby="dashboardLateModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div>
                                <h5 class="modal-title" id="dashboardLateModalLabel">Late Login Details</h5>
                                <p class="mb-0 text-secondary small" id="dashboardLateModalSubtitle">Selected employee</p>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3 mb-3" id="dashboardLateModalSummary"></div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>S.No</th>
                                            <th>Date</th>
                                            <th>Source</th>
                                            <th>Check In</th>
                                            <th>Shift Start</th>
                                            <th>Late By</th>
                                            <th>Check Out</th>
                                            <th>Shift End</th>
                                            <th>Left Early By</th>
                                        </tr>
                                    </thead>
                                    <tbody id="dashboardLateModalBody">
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">Choose an employee from the
                                                ranking table.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <script>
        (function() {
            const recruitmentRegionChart = @json($recruitmentRegionChart);
            const recruitmentPositionChart = @json($recruitmentPositionChart);
            const hoAttendanceChart = @json($hoAttendanceChart);
            const branchAttendanceChart = @json($branchAttendanceChart);

            function escapeDashboardHtml(value) {
                return String(value || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function parseDashboardLatePayload(payload) {
                if (!payload) {
                    return {};
                }

                if (typeof payload === 'string') {
                    try {
                        return JSON.parse(payload);
                    } catch (error) {
                        console.error('Unable to parse dashboard late payload.', error);
                        return {};
                    }
                }

                return payload;
            }

            function getDashboardLateModal() {
                const modalElement = document.getElementById('dashboardLateModal');

                if (!modalElement || !window.bootstrap || !bootstrap.Modal) {
                    return {
                        element: modalElement,
                        instance: null
                    };
                }

                return {
                    element: modalElement,
                    instance: bootstrap.Modal.getOrCreateInstance(modalElement)
                };
            }

            window.openDashboardLateModal = function(payload) {
                const parsedPayload = parseDashboardLatePayload(payload);
                const {
                    element: dashboardLateModalElement,
                    instance: dashboardLateModalInstance
                } = getDashboardLateModal();

                if (!dashboardLateModalElement || !dashboardLateModalInstance) {
                    return;
                }

                const subtitle = document.getElementById('dashboardLateModalSubtitle');
                const summary = document.getElementById('dashboardLateModalSummary');
                const body = document.getElementById('dashboardLateModalBody');
                const details = Array.isArray(parsedPayload?.details) ? parsedPayload.details : [];

                if (subtitle) {
                    subtitle.textContent =
                        `${parsedPayload?.employeeName || 'Employee'} (${parsedPayload?.empId || '--'}) | Shift ${parsedPayload?.schedule || '--'}`;
                }

                if (summary) {
                    summary.innerHTML = `
                        <div class="col-md-4">
                            <div class="dashboard-punctuality-card">
                                <div class="card-body">
                                    <div class="dashboard-punctuality-card__label">Late Days</div>
                                    <div class="dashboard-punctuality-card__value">${escapeDashboardHtml(parsedPayload?.lateDays ?? 0)}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="dashboard-punctuality-card">
                                <div class="card-body">
                                    <div class="dashboard-punctuality-card__label">Early Logout Days</div>
                                    <div class="dashboard-punctuality-card__value">${escapeDashboardHtml(parsedPayload?.earlyLogoutDays ?? 0)}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="dashboard-punctuality-card">
                                <div class="card-body">
                                    <div class="dashboard-punctuality-card__label">Irregular Days</div>
                                    <div class="dashboard-punctuality-card__value">${escapeDashboardHtml(parsedPayload?.irregularDays ?? 0)}</div>
                                </div>
                            </div>
                        </div>
                    `;
                }

                if (body) {
                    body.innerHTML = details.length ?
                        details.map((detail, index) => `
                            <tr>
                                <td>${index + 1}</td>
                                <td>${escapeDashboardHtml(detail.date_label || detail.date || '--')}</td>
                                <td>${escapeDashboardHtml(detail.source || '--')}</td>
                                <td>${escapeDashboardHtml(detail.check_in || '--')}</td>
                                <td>${escapeDashboardHtml(detail.shift_start || '--')}</td>
                                <td>${escapeDashboardHtml(detail.late_label || '--')}</td>
                                <td>${escapeDashboardHtml(detail.check_out || '--')}</td>
                                <td>${escapeDashboardHtml(detail.shift_end || '--')}</td>
                                <td>${escapeDashboardHtml(detail.early_logout_label || '--')}</td>
                            </tr>
                        `).join('') :
                        '<tr><td colspan="9" class="text-center text-muted">No late or early logout days in this period.</td></tr>';
                }

                dashboardLateModalInstance.show();
            };

            function renderAttendanceChart(elementId, chartData, palette) {
                const element = document.querySelector(elementId);

                if (!element || element.dataset.chartRendered === 'true') {
                    return;
                }

                const rootStyles = getComputedStyle(document.documentElement);
                const textColor = rootStyles.getPropertyValue('--admin-text-color').trim() || '#35231D';
                const mutedColor = rootStyles.getPropertyValue('--admin-muted-text-color').trim() || '#8B6B61';
                const borderColor = rootStyles.getPropertyValue('--admin-border-color').trim() || '#E9D8CC';

                const options = {
                    chart: {
                        type: 'bar',
                        height: 320,
                        stacked: true,
                        toolbar: {
                            show: false
                        },
                        fontFamily: 'inherit'
                    },
                    series: chartData.series || [],
                    colors: palette,
                    plotOptions: {
                        bar: {
                            horizontal: false,
                            columnWidth: '52%',
                            borderRadius: 6
                        }
                    },
                    dataLabels: {
                        enabled: false
                    },
                    stroke: {
                        show: true,
                        width: 1,
                        colors: ['transparent']
                    },
                    legend: {
                        position: 'top',
                        horizontalAlign: 'left',
                        labels: {
                            colors: textColor
                        }
                    },
                    grid: {
                        borderColor: borderColor,
                        strokeDashArray: 4
                    },
                    xaxis: {
                        categories: chartData.labels || [],
                        axisBorder: {
                            show: false
                        },
                        axisTicks: {
                            show: false
                        },
                        labels: {
                            style: {
                                colors: mutedColor
                            }
                        }
                    },
                    yaxis: {
                        min: 0,
                        forceNiceScale: true,
                        labels: {
                            style: {
                                colors: mutedColor
                            }
                        }
                    },
                    tooltip: {
                        shared: true,
                        intersect: false
                    }
                };

                new ApexCharts(element, options).render();
                element.dataset.chartRendered = 'true';
            }

            function renderRecruitmentRegionChart(elementId, chartData, palette) {
                const element = document.querySelector(elementId);

                if (!element || element.dataset.chartRendered === 'true') {
                    return;
                }

                if (!Array.isArray(chartData.series) || !chartData.series.length) {
                    element.innerHTML =
                        '<div class="text-center text-muted py-5">No submitted applications are available for the selected dashboard scope.</div>';
                    element.dataset.chartRendered = 'true';

                    return;
                }

                const rootStyles = getComputedStyle(document.documentElement);
                const textColor = rootStyles.getPropertyValue('--admin-text-color').trim() || '#35231D';
                const borderColor = rootStyles.getPropertyValue('--admin-border-color').trim() || '#E9D8CC';

                const options = {
                    chart: {
                        type: 'donut',
                        height: 320,
                        toolbar: {
                            show: false
                        },
                        fontFamily: 'inherit'
                    },
                    series: chartData.series,
                    labels: chartData.labels || [],
                    colors: palette,
                    legend: {
                        position: 'bottom',
                        labels: {
                            colors: textColor
                        }
                    },
                    stroke: {
                        colors: [borderColor]
                    },
                    dataLabels: {
                        enabled: true
                    },
                    plotOptions: {
                        pie: {
                            donut: {
                                size: '62%'
                            }
                        }
                    },
                    tooltip: {
                        y: {
                            formatter: function(value) {
                                return value + ' applicants';
                            }
                        }
                    }
                };

                new ApexCharts(element, options).render();
                element.dataset.chartRendered = 'true';
            }

            function renderRecruitmentPositionChart(elementId, chartData, palette) {
                const element = document.querySelector(elementId);

                if (!element || element.dataset.chartRendered === 'true') {
                    return;
                }

                if (!Array.isArray(chartData.series) || !chartData.series.length) {
                    element.innerHTML =
                        '<div class="text-center text-muted py-5">No submitted applications are available yet.</div>';
                    element.dataset.chartRendered = 'true';

                    return;
                }

                const rootStyles = getComputedStyle(document.documentElement);
                const textColor = rootStyles.getPropertyValue('--admin-text-color').trim() || '#35231D';
                const mutedColor = rootStyles.getPropertyValue('--admin-muted-text-color').trim() || '#8B6B61';
                const borderColor = rootStyles.getPropertyValue('--admin-border-color').trim() || '#E9D8CC';

                const options = {
                    chart: {
                        type: 'bar',
                        height: 320,
                        toolbar: {
                            show: false
                        },
                        fontFamily: 'inherit'
                    },
                    series: [{
                        name: 'Applicants',
                        data: chartData.series
                    }],
                    colors: palette,
                    plotOptions: {
                        bar: {
                            horizontal: true,
                            borderRadius: 6,
                            barHeight: '58%'
                        }
                    },
                    dataLabels: {
                        enabled: false
                    },
                    grid: {
                        borderColor: borderColor,
                        strokeDashArray: 4
                    },
                    xaxis: {
                        categories: chartData.labels || [],
                        labels: {
                            style: {
                                colors: mutedColor
                            }
                        }
                    },
                    yaxis: {
                        labels: {
                            style: {
                                colors: textColor
                            }
                        }
                    },
                    tooltip: {
                        y: {
                            formatter: function(value) {
                                return value + ' applicants';
                            }
                        }
                    }
                };

                new ApexCharts(element, options).render();
                element.dataset.chartRendered = 'true';
            }

            function bootDashboardCharts(attempt) {
                if (typeof ApexCharts === 'undefined') {
                    if (attempt < 10) {
                        window.setTimeout(function() {
                            bootDashboardCharts(attempt + 1);
                        }, 300);
                    }

                    return;
                }

                const rootStyles = getComputedStyle(document.documentElement);
                const primaryColor = rootStyles.getPropertyValue('--admin-primary-color').trim() || '#A63D2F';
                const highlightColor = rootStyles.getPropertyValue('--admin-highlight-color').trim() || '#C8A24A';

                renderRecruitmentRegionChart('#dashboardRecruitmentRegionChart', recruitmentRegionChart, [
                    primaryColor,
                    highlightColor,
                    '#2563EB',
                    '#0EA5E9',
                    '#22C55E',
                    '#94A3B8'
                ]);

                renderRecruitmentPositionChart('#dashboardRecruitmentPositionChart', recruitmentPositionChart, [
                    primaryColor
                ]);

                renderAttendanceChart('#dashboardHoAttendanceChart', hoAttendanceChart, [
                    primaryColor,
                    highlightColor,
                    '#F97316',
                    '#94A3B8'
                ]);

                renderAttendanceChart('#dashboardBranchAttendanceChart', branchAttendanceChart, [
                    '#2563EB',
                    '#F59E0B',
                    '#DC2626',
                    '#64748B'
                ]);
            }

            if (document.readyState === 'complete') {
                bootDashboardCharts(0);
            } else {
                window.addEventListener('load', function() {
                    bootDashboardCharts(0);
                }, {
                    once: true
                });
            }
        })();
    </script>
@endsection
