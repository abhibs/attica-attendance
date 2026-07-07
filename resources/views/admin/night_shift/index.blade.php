@extends('admin.layout.app')

@section('content')
    @include('admin.attendance.partials.styles')

    <style>
        .night-shift-user-table {
            max-height: 520px;
            overflow: auto;
            border: 1px solid var(--admin-border-color);
            border-radius: 18px;
        }

        .night-shift-user-table table {
            margin-bottom: 0;
        }

        .night-shift-user-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: var(--admin-surface-color);
        }

        .night-shift-user-table tr.is-hidden {
            display: none;
        }

        .night-shift-timing-cell {
            min-width: 280px;
        }

        .night-shift-timing-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .night-shift-timing-grid label {
            display: block;
            font-size: 0.72rem;
            color: var(--admin-muted-text-color);
            margin-bottom: 0.2rem;
        }
    </style>

    <div class="main-content attendance-page">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Nightshift Users</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin-dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Nightshift Users</li>
                    </ol>
                </nav>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success border-0">{{ session('status') }}</div>
        @endif

        <div class="row row-cols-1 row-cols-md-3 g-3 mb-4">
            <div class="col">
                <div class="card rounded-4 mb-0 attendance-stat-card">
                    <div class="card-body">
                        <p class="mb-1">Nightshift Users</p>
                        <h4 class="mb-0">{{ $nightShiftEmployees->count() }}</h4>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card rounded-4 mb-0 attendance-stat-card">
                    <div class="card-body">
                        <p class="mb-1">Eligible Employees</p>
                        <h4 class="mb-0">{{ $employees->count() }}</h4>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card rounded-4 mb-0 attendance-stat-card">
                    <div class="card-body">
                        <p class="mb-1">Timing Rule</p>
                        <h4 class="mb-0">6 hrs</h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="card rounded-4 mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
                    <div>
                        <h4 class="mb-1 attendance-title">Add Nightshift Users</h4>
                        <p class="mb-0 attendance-muted">Selected employees are excluded from Daily Attendance, use evening punch as check-in, and must follow their own overnight shift timing.</p>
                    </div>
                </div>

                <form method="post" action="{{ route('admin-night-shift-users-update') }}" id="nightShiftUsersForm">
                    @csrf
                    <div class="row g-3">
                        <div class="col-lg-8">
                            <label class="form-label">Search Employees</label>
                            <input type="search" id="nightShiftEmployeeSearch" class="form-control"
                                placeholder="Search by employee ID, name, designation, contact, or status">
                        </div>
                        <div class="col-lg-4 d-flex align-items-end gap-2">
                            <button type="button" class="btn btn-outline-secondary w-100" id="nightShiftSelectVisible">
                                Select Visible
                            </button>
                            <button type="button" class="btn btn-outline-secondary w-100" id="nightShiftClearVisible">
                                Clear Visible
                            </button>
                        </div>

                        <div class="col-12">
                            <div class="night-shift-user-table">
                                <table class="table table-bordered table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th style="width: 56px;">
                                                <input type="checkbox" id="nightShiftSelectAll">
                                            </th>
                                            <th>Emp ID</th>
                                            <th>Name</th>
                                            <th>Shift Timing</th>
                                            <th>Designation</th>
                                            <th>Contact</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="nightShiftEmployeeRows">
                                        @foreach ($employees as $employee)
                                            @php
                                                $searchText = strtolower(trim(implode(' ', [
                                                    $employee->empId,
                                                    $employee->name,
                                                    $employee->designation,
                                                    $employee->contact,
                                                    $employee->status,
                                                ])));
                                            @endphp
                                            <tr data-night-shift-user-row data-search-text="{{ $searchText }}">
                                                <td>
                                                    <input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}"
                                                        class="night-shift-user-checkbox" @checked((bool) $employee->is_night_shift)>
                                                </td>
                                                <td>{{ trim($employee->empId) }}</td>
                                                <td>{{ $employee->name }}</td>
                                                <td class="night-shift-timing-cell">
                                                    <div class="night-shift-timing-grid" data-shift-timing-picker>
                                                        <div>
                                                            <label>Start</label>
                                                            <input type="time"
                                                                class="form-control form-control-sm"
                                                                data-shift-start
                                                                value="">
                                                        </div>
                                                        <div>
                                                            <label>End</label>
                                                            <input type="time"
                                                                class="form-control form-control-sm"
                                                                data-shift-end
                                                                value="">
                                                        </div>
                                                    </div>
                                                    <input type="text"
                                                        name="shift_timings[{{ $employee->id }}]"
                                                        class="form-control form-control-sm"
                                                        value="{{ old('shift_timings.'.$employee->id, $employee->shift_timing) }}"
                                                        placeholder="09:00 PM - 03:00 AM"
                                                        data-shift-text>
                                                </td>
                                                <td>{{ $employee->designation ?: '--' }}</td>
                                                <td>{{ $employee->contact ?: '--' }}</td>
                                                <td>
                                                    @if ((bool) $employee->is_night_shift)
                                                        <span class="attendance-status-pill active">Nightshift</span>
                                                    @else
                                                        <span class="attendance-status-pill pending">{{ $employee->status ?: 'Active' }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="form-text">
                                Search the list, tick multiple employees, set each night shift timing such as `09:00 PM - 03:00 AM`, then save. Night shift check-in and check-out are each limited to a 6-hour window around that schedule.
                            </div>
                            @if ($errors->has('shift_timings') || collect($errors->getMessages())->keys()->contains(fn ($key) => str_starts_with($key, 'shift_timings.')))
                                <div class="text-danger small mt-2">Each selected night shift user needs a shift timing.</div>
                            @endif
                        </div>
                        <div class="col-lg-3">
                            <button type="submit" class="btn btn-primary w-100">Save Nightshift Users</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card rounded-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                    <div>
                        <h5 class="mb-1 attendance-title">Current Nightshift Users</h5>
                        <p class="mb-0 attendance-muted">These employees use evening check-in, next-morning check-out, and a 6-hour schedule window.</p>
                    </div>
                    <div class="datatable-toolbar"></div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle js-admin-datatable" data-admin-datatable="true">
                        <thead>
                            <tr>
                                <th>Emp ID</th>
                                <th>Name</th>
                                <th>Shift Timing</th>
                                <th>Designation</th>
                                <th>Contact</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($nightShiftEmployees as $employee)
                                <tr>
                                    <td>{{ trim($employee->empId) }}</td>
                                    <td>{{ $employee->name }}</td>
                                    <td>{{ trim((string) $employee->shift_timing) !== '' ? $employee->shift_timing : '--' }}</td>
                                    <td>{{ $employee->designation ?: '--' }}</td>
                                    <td>{{ $employee->contact ?: '--' }}</td>
                                    <td><span class="attendance-status-pill active">Nightshift</span></td>
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
            const searchInput = document.getElementById('nightShiftEmployeeSearch');
            const selectAllInput = document.getElementById('nightShiftSelectAll');
            const selectVisibleButton = document.getElementById('nightShiftSelectVisible');
            const clearVisibleButton = document.getElementById('nightShiftClearVisible');
            const rows = Array.from(document.querySelectorAll('[data-night-shift-user-row]'));

            function visibleRows() {
                return rows.filter((row) => !row.classList.contains('is-hidden'));
            }

            function rowCheckbox(row) {
                return row.querySelector('.night-shift-user-checkbox');
            }

            function parseTime(value) {
                const trimmed = (value || '').trim();
                if (!trimmed) {
                    return '';
                }

                const match = trimmed.match(/^(\d{1,2})(?::(\d{2}))?(?::\d{2})?\s*([APap][Mm])?$/);
                if (!match) {
                    return '';
                }

                let hours = Number(match[1]);
                const minutes = Number(match[2] || '0');
                const suffix = (match[3] || '').toUpperCase();

                if (minutes < 0 || minutes > 59 || hours < 0 || hours > 23) {
                    return '';
                }

                if (suffix) {
                    if (hours < 1 || hours > 12) {
                        return '';
                    }

                    if (suffix === 'AM') {
                        hours = hours === 12 ? 0 : hours;
                    } else {
                        hours = hours === 12 ? 12 : hours + 12;
                    }
                }

                return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
            }

            function formatTime(value) {
                if (!value || !value.includes(':')) {
                    return '';
                }

                const [hoursText, minutesText] = value.split(':');
                let hours = Number(hoursText);
                const minutes = Number(minutesText);

                if (Number.isNaN(hours) || Number.isNaN(minutes)) {
                    return '';
                }

                const suffix = hours >= 12 ? 'PM' : 'AM';
                hours = hours % 12 || 12;

                return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')} ${suffix}`;
            }

            function parseRange(value) {
                const normalized = (value || '')
                    .replace(/\s+to\s+/ig, ' - ')
                    .replace(/[–—]/g, '-')
                    .trim();

                if (!normalized) {
                    return ['', ''];
                }

                const parts = normalized.split(/\s*-\s*/);
                if (parts.length < 2) {
                    return ['', ''];
                }

                return [parseTime(parts[0]), parseTime(parts[1])];
            }

            rows.forEach((row) => {
                const textInput = row.querySelector('[data-shift-text]');
                const startInput = row.querySelector('[data-shift-start]');
                const endInput = row.querySelector('[data-shift-end]');

                function syncTextFromPickers() {
                    const start = startInput?.value || '';
                    const end = endInput?.value || '';

                    if (!textInput) {
                        return;
                    }

                    if (!start && !end) {
                        textInput.value = '';
                        return;
                    }

                    if (!start || !end) {
                        return;
                    }

                    textInput.value = `${formatTime(start)} - ${formatTime(end)}`;
                }

                function syncPickersFromText() {
                    if (!textInput || !startInput || !endInput) {
                        return;
                    }

                    const [start, end] = parseRange(textInput.value);
                    startInput.value = start;
                    endInput.value = end;
                }

                startInput?.addEventListener('change', syncTextFromPickers);
                endInput?.addEventListener('change', syncTextFromPickers);
                textInput?.addEventListener('input', syncPickersFromText);

                syncPickersFromText();
            });

            function syncSelectAllState() {
                if (!selectAllInput) {
                    return;
                }

                const currentRows = visibleRows();
                const checkedRows = currentRows.filter((row) => rowCheckbox(row)?.checked);

                selectAllInput.checked = currentRows.length > 0 && checkedRows.length === currentRows.length;
                selectAllInput.indeterminate = checkedRows.length > 0 && checkedRows.length < currentRows.length;
            }

            function applySearch() {
                const query = (searchInput?.value || '').trim().toLowerCase();

                rows.forEach((row) => {
                    const searchable = row.dataset.searchText || '';
                    row.classList.toggle('is-hidden', query !== '' && !searchable.includes(query));
                });

                syncSelectAllState();
            }

            searchInput?.addEventListener('input', applySearch);

            selectAllInput?.addEventListener('change', function() {
                visibleRows().forEach((row) => {
                    const checkbox = rowCheckbox(row);
                    if (checkbox) {
                        checkbox.checked = selectAllInput.checked;
                    }
                });
                syncSelectAllState();
            });

            selectVisibleButton?.addEventListener('click', function() {
                visibleRows().forEach((row) => {
                    const checkbox = rowCheckbox(row);
                    if (checkbox) {
                        checkbox.checked = true;
                    }
                });
                syncSelectAllState();
            });

            clearVisibleButton?.addEventListener('click', function() {
                visibleRows().forEach((row) => {
                    const checkbox = rowCheckbox(row);
                    if (checkbox) {
                        checkbox.checked = false;
                    }
                });
                syncSelectAllState();
            });

            rows.forEach((row) => rowCheckbox(row)?.addEventListener('change', syncSelectAllState));
            applySearch();
        });
    </script>
@endsection
