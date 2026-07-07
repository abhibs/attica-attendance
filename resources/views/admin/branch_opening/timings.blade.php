@extends('admin.layout.app')

@section('content')
    @include('admin.attendance.partials.styles')

    <style>
        .branch-timing-filter-card {
            border: 1px solid rgba(var(--admin-primary-color-rgb), 0.1);
            background: linear-gradient(180deg, rgba(var(--admin-primary-color-rgb), 0.03), rgba(255, 255, 255, 0.95));
            box-shadow: 0 18px 40px rgba(36, 24, 62, 0.06);
        }

        .branch-timing-card {
            border: 1px solid var(--admin-border-color);
            border-radius: 18px;
            background: var(--admin-surface-color);
            height: 100%;
        }

        .branch-timing-card .card-body {
            padding: 1.1rem;
        }

        .branch-timing-kpi-label {
            color: var(--admin-muted-text-color);
            font-size: 0.84rem;
            margin-bottom: 0.35rem;
        }

        .branch-timing-kpi-value {
            color: var(--admin-text-color);
            font-size: 1.85rem;
            font-weight: 700;
            line-height: 1.1;
        }

        .branch-timing-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 110px;
            padding: 0.28rem 0.7rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .branch-timing-status.is-on-time {
            background: rgba(25, 135, 84, 0.14);
            color: #198754;
        }

        .branch-timing-status.is-late,
        .branch-timing-status.is-not-opened {
            background: rgba(220, 53, 69, 0.12);
            color: #b42318;
        }

        .branch-timing-status.is-pending,
        .branch-timing-status.is-opened,
        .branch-timing-status.is-no-activity {
            background: rgba(var(--admin-primary-color-rgb), 0.12);
            color: var(--admin-primary-color);
        }

        .branch-timing-table-title {
            letter-spacing: -0.02em;
            font-size: 1.05rem;
        }
    </style>

    <div class="main-content attendance-page">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Branch Opening Timings</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin-dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin-branch-opening-index') }}">Opening & Keys</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Daily Timings</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="card rounded-4 mb-3 branch-timing-filter-card">
            <div class="card-body">
                <form method="get" action="{{ route('admin-branch-opening-timings') }}" class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label">Month</label>
                        <input type="month" name="month" class="form-control" value="{{ $selectedMonth }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">From</label>
                        <input type="date" name="from" class="form-control" value="{{ $fromDate }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To</label>
                        <input type="date" name="to" class="form-control" value="{{ $toDate }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Branch</label>
                        <input
                            type="text"
                            name="branch_id"
                            class="form-control"
                            list="branchTimingBranchList"
                            value="{{ $selectedBranchId }}"
                            placeholder="All active branches">
                        <datalist id="branchTimingBranchList">
                            @foreach ($branches as $branch)
                                <option value="{{ trim((string) $branch->branchId) }}">{{ trim((string) $branch->branchId . ' - ' . $branch->branchName, ' -') }}</option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-primary">Load Report</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-12 col-md-6 col-xl-2">
                <div class="branch-timing-card">
                    <div class="card-body">
                        <div class="branch-timing-kpi-label">Tracked Records</div>
                        <div class="branch-timing-kpi-value">{{ $metrics['tracked_days'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <div class="branch-timing-card">
                    <div class="card-body">
                        <div class="branch-timing-kpi-label">On Time</div>
                        <div class="branch-timing-kpi-value">{{ $metrics['on_time_count'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <div class="branch-timing-card">
                    <div class="card-body">
                        <div class="branch-timing-kpi-label">Late Opened</div>
                        <div class="branch-timing-kpi-value">{{ $metrics['late_count'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <div class="branch-timing-card">
                    <div class="card-body">
                        <div class="branch-timing-kpi-label">Not Opened</div>
                        <div class="branch-timing-kpi-value">{{ $metrics['not_opened_count'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <div class="branch-timing-card">
                    <div class="card-body">
                        <div class="branch-timing-kpi-label">Avg Opening Time</div>
                        <div class="branch-timing-kpi-value fs-4">{{ $metrics['average_opening_time'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <div class="branch-timing-card">
                    <div class="card-body">
                        <div class="branch-timing-kpi-label">Avg Closing Time</div>
                        <div class="branch-timing-kpi-value fs-4">{{ $metrics['average_closing_time'] }}</div>
                        <div class="small text-muted mt-2">Avg open duration {{ $metrics['average_open_duration'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-12 col-xl-6">
                <div class="card rounded-4">
                    <div class="card-body">
                        <h5 class="attendance-title branch-timing-table-title mb-3">Most Late Opened Branches</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0" data-admin-static-serial="true">
                                <thead>
                                    <tr>
                                        <th>Branch</th>
                                        <th>Late Days</th>
                                        <th>Avg Delay</th>
                                        <th>Max Delay</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($mostLateBranches as $row)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $row['branch_name'] !== '' ? $row['branch_name'] : $row['branch_id'] }}</div>
                                                <div class="small text-muted">{{ $row['branch_id'] }}</div>
                                            </td>
                                            <td>{{ $row['late_count'] }}</td>
                                            <td>{{ $row['average_delay_minutes'] }} min</td>
                                            <td>{{ $row['max_delay_minutes'] }} min</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No late opening records in this period.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="card rounded-4">
                    <div class="card-body">
                        <h5 class="attendance-title branch-timing-table-title mb-3">Shortest Branch Open Times</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0" data-admin-static-serial="true">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Branch</th>
                                        <th>Opened</th>
                                        <th>Closed</th>
                                        <th>Open Duration</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($shortestOpenTimes as $row)
                                        <tr>
                                            <td>{{ optional($row->attendance_date)->format('d M Y') ?: '--' }}</td>
                                            <td>
                                                <div class="fw-semibold">{{ $row->branch_name ?: $row->branch_id }}</div>
                                                <div class="small text-muted">{{ $row->branch_id }}</div>
                                            </td>
                                            <td>{{ optional($row->opened_at)->format('H:i') ?: '--' }}</td>
                                            <td>{{ optional($row->closed_at)->format('H:i') ?: '--' }}</td>
                                            <td>{{ $row->open_duration_minutes ? $row->open_duration_minutes.' min' : '--' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No branch open-duration data is available in this period.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card rounded-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-2 align-items-lg-center mb-3">
                    <div>
                        <h5 class="attendance-title branch-timing-table-title mb-0">Daily Branch Opening and Closing Log</h5>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0" data-admin-static-serial="true">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Branch</th>
                                <th>Scheduled</th>
                                <th>Opened At</th>
                                <th>First Branch Check-In</th>
                                <th>Status</th>
                                <th>Delay</th>
                                <th>Opened By</th>
                                <th>Closed At</th>
                                <th>Closed By</th>
                                <th>Open Duration</th>
                                <th>Check-Ins</th>
                                <th>Check-Outs</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                @php
                                    $statusClass = match ($row->opening_status) {
                                        'on_time' => 'is-on-time',
                                        'late' => 'is-late',
                                        'not_opened' => 'is-not-opened',
                                        'pending' => 'is-pending',
                                        'opened' => 'is-opened',
                                        default => 'is-no-activity',
                                    };
                                    $statusLabel = match ($row->opening_status) {
                                        'on_time' => 'On Time',
                                        'late' => 'Late',
                                        'not_opened' => 'Not Opened',
                                        'pending' => 'Pending',
                                        'opened' => 'Opened',
                                        default => 'No Activity',
                                    };
                                @endphp
                                <tr>
                                    <td>{{ optional($row->attendance_date)->format('d M Y') ?: '--' }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $row->branch_name ?: $row->branch_id }}</div>
                                        <div class="small text-muted">{{ $row->branch_id }}</div>
                                    </td>
                                    <td>{{ $row->scheduled_opening_time ? \Illuminate\Support\Carbon::parse($row->scheduled_opening_time)->format('H:i') : '--' }}</td>
                                    <td>{{ optional($row->opened_at)->format('H:i') ?: '--' }}</td>
                                    <td>{{ optional($row->first_check_in_at)->format('H:i') ?: '--' }}</td>
                                    <td><span class="branch-timing-status {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                    <td>{{ $row->opening_delay_minutes > 0 ? $row->opening_delay_minutes.' min' : '--' }}</td>
                                    <td>
                                        @if ($row->opened_by_emp_id || $row->opened_by_name)
                                            <div class="fw-semibold">{{ trim(($row->opened_by_emp_id ?: '').' '.($row->opened_by_name ?: ''), ' ') }}</div>
                                        @else
                                            --
                                        @endif
                                    </td>
                                    <td>{{ optional($row->closed_at)->format('H:i') ?: '--' }}</td>
                                    <td>
                                        @if ($row->closed_by_emp_id || $row->closed_by_name)
                                            <div class="fw-semibold">{{ trim(($row->closed_by_emp_id ?: '').' '.($row->closed_by_name ?: ''), ' ') }}</div>
                                        @else
                                            --
                                        @endif
                                    </td>
                                    <td>{{ $row->open_duration_minutes ? $row->open_duration_minutes.' min' : '--' }}</td>
                                    <td>{{ $row->total_check_ins }}</td>
                                    <td>{{ $row->total_check_outs }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="13" class="text-center text-muted py-4">No branch timing data is available for this filter.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const monthInput = document.querySelector('input[name="month"]');
            const fromInput = document.querySelector('input[name="from"]');
            const toInput = document.querySelector('input[name="to"]');

            if (!monthInput || !fromInput || !toInput) {
                return;
            }

            monthInput.addEventListener('change', () => {
                const value = monthInput.value;

                if (!value) {
                    return;
                }

                const [year, month] = value.split('-').map(Number);
                const lastDay = new Date(year, month, 0).getDate();
                const paddedMonth = String(month).padStart(2, '0');
                const today = new Date();
                const selectedIsCurrentMonth = today.getFullYear() === year && (today.getMonth() + 1) === month;
                const toDay = selectedIsCurrentMonth ? today.getDate() : lastDay;

                fromInput.value = `${year}-${paddedMonth}-01`;
                toInput.value = `${year}-${paddedMonth}-${String(toDay).padStart(2, '0')}`;
            });
        });
    </script>
@endsection
