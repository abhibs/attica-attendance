@extends('admin.layout.app')
@section('content')
    <div class="main-content">
        <style>
            .shift-timing-picker-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0.75rem;
                margin-bottom: 0.75rem;
            }

            @media (max-width: 575.98px) {
                .shift-timing-picker-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>

        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Admin</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Add Employee</li>
                    </ol>
                </nav>
            </div>
            <div class="ms-auto"></div>
        </div>

        @if (session('status'))
            <div class="alert alert-success border-0">{{ session('status') }}</div>
        @endif

        <div class="row">
            <div class="col-xl-12 mx-auto">
                <hr>

                <div class="card mb-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <h5 class="mb-1">Import Employees From Excel</h5>
                            <p class="text-muted mb-0">
                                Upload `.xlsx`, `.xls`, or `.csv`. Existing employees are matched by <strong>Employee ID</strong> and all mapped Excel columns overwrite the employee record. New IDs are inserted.
                            </p>
                        </div>

                        <form action="{{ route('admin-employee-import') }}" method="post" enctype="multipart/form-data" class="row g-3 align-items-end">
                            @csrf
                            <div class="col-md-6">
                                <label class="form-label">Employee File</label>
                                <input class="form-control" type="file" name="employee_file" accept=".csv,.xlsx,.xls" required>
                                @error('employee_file')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <div class="form-text mb-2">
                                    Supported headers include: `NEW E.COD`, `EMPLOYEE NAMES`, `CITY`, `DOJ`, `DESIGNATION`, `PER MONTH`.
                                </div>
                                <button type="submit" class="btn btn-grd btn-grd-primary px-5">Import Employees</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        @php
                            $employeeIdConflicts = session('employee_id_conflicts', []);
                            $employeeIdConflictValue = session('employee_id_conflict_value');
                        @endphp
                        <div class="mb-3">
                            <h5 class="mb-1">Add Single Employee</h5>
                            <p class="text-muted mb-0">Use this form for manual entry when you are not importing from Excel.</p>
                        </div>

                        <form action="{{ route('admin-employee-store') }}" method="post">
                            @csrf

                            <div class="row g-3">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Employee ID</label>
                                        <input class="form-control" type="text" name="empId"
                                            placeholder="Employee ID will be auto generated"
                                            value="{{ old('empId', $generatedEmpId ?? '') }}">
                                        <div class="form-text">This ID is generated automatically. Edit only when HR needs to assign a specific ID.</div>
                                        @error('empId')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                        @if (!empty($employeeIdConflicts) && $employeeIdConflictValue === old('empId'))
                                            <div class="alert alert-warning mt-2 mb-0">
                                                <div class="fw-semibold">Employee ID {{ $employeeIdConflictValue }} is already assigned.</div>
                                                <ul class="mb-2 ps-3">
                                                    @foreach ($employeeIdConflicts as $conflict)
                                                        <li>
                                                            <strong>{{ $conflict['title'] ?? 'Record' }}</strong>
                                                            <span class="text-muted">({{ $conflict['location'] ?? 'Unknown location' }})</span>
                                                            @if (!empty($conflict['details']))
                                                                <div class="small text-muted">{{ $conflict['details'] }}</div>
                                                            @endif
                                                        </li>
                                                    @endforeach
                                                </ul>
                                                <div class="small text-danger fw-semibold">
                                                    Use a different Employee ID. Existing assignments cannot be cleared or reassigned.
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Employee Name</label>
                                        <input class="form-control" type="text" name="name"
                                            placeholder="Enter Employee Name" value="{{ old('name') }}">
                                        @error('name')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Employee Contact</label>
                                        <input class="form-control" type="text" name="contact"
                                            placeholder="Enter Employee Contact" value="{{ old('contact') }}">
                                        @error('contact')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Employee Designation</label>
                                        <input class="form-control" type="text" name="designation"
                                            placeholder="Enter Employee Designation" value="{{ old('designation') }}">
                                        @error('designation')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label d-block">Employee Type</label>
                                        <div class="form-check form-switch mt-2">
                                            @php
                                                $defaultOutsourced = (bool) old('is_outsourced', request()->boolean('is_outsourced'));
                                            @endphp
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                id="isOutsourcedSwitch" name="is_outsourced" value="1"
                                                @checked($defaultOutsourced)>
                                            <label class="form-check-label" for="isOutsourcedSwitch">
                                                Outsourced Employee
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Outsource Locations (for outsourced employees)</label>
                                        <select class="form-select" name="outsource_location_ids[]" multiple size="5"
                                            id="outsourceLocationsSelect">
                                            @php
                                                $selectedOutsourceIds = collect(old('outsource_location_ids', []))
                                                    ->map(fn ($id) => (int) $id)
                                                    ->all();
                                            @endphp
                                            @foreach ($outsourceLocations as $outsourceLocation)
                                                @php
                                                    $locationMeta = collect([
                                                        $outsourceLocation->city,
                                                        $outsourceLocation->state,
                                                    ])->filter()->implode(', ');
                                                @endphp
                                                <option value="{{ $outsourceLocation->id }}" @selected(in_array((int) $outsourceLocation->id, $selectedOutsourceIds, true))>
                                                    {{ $outsourceLocation->location_code }} - {{ $outsourceLocation->name }}{{ $locationMeta !== '' ? ' | '.$locationMeta : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="form-text">Use Ctrl/Cmd click to select multiple locations.</div>
                                        @error('outsource_location_ids')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Employee Salary</label>
                                        <input class="form-control" type="text" name="salary"
                                            placeholder="Enter Employee Salary" value="{{ old('salary') }}">
                                        @error('salary')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">PF Eligibility</label>
                                        <select class="form-select" name="pf_eligible">
                                            <option value="0" @selected(old('pf_eligible', '0') === '0')>Not Eligible</option>
                                            <option value="1" @selected(old('pf_eligible') === '1')>Eligible</option>
                                        </select>
                                        @error('pf_eligible')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Shift Timing</label>
                                        <div class="shift-timing-picker-grid" data-shift-timing-picker>
                                            <div>
                                                <label class="form-label small mb-1">Start</label>
                                                <input class="form-control" type="time" data-shift-start value="">
                                            </div>
                                            <div>
                                                <label class="form-label small mb-1">End</label>
                                                <input class="form-control" type="time" data-shift-end value="">
                                            </div>
                                        </div>
                                        <input class="form-control" type="text" name="shift_timing"
                                            placeholder="10:00 AM - 7:00 PM"
                                            value="{{ old('shift_timing') }}" aria-label="Shift timing"
                                            data-shift-text>
                                        <div class="form-text">Pick start/end time or type a full range like 09:00 PM - 03:00 AM.</div>
                                        @error('shift_timing')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Employee Date of Joining</label>
                                        <input class="form-control" type="date" name="doj"
                                            aria-label="Employee date of joining" value="{{ old('doj') }}">
                                        @error('doj')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Employee Mail ID</label>
                                        <input class="form-control" type="text" name="mailId"
                                            placeholder="Enter Employee Mail ID" value="{{ old('mailId') }}">
                                        @error('mailId')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label class="form-label">Employee Address</label>
                                        <textarea class="form-control" name="address" aria-label="With textarea" placeholder="Enter Employee Address">{{ old('address') }}</textarea>
                                        @error('address')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="mb-3">
                                        <button type="submit" class="btn btn-grd btn-grd-success px-5">Add Employee</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const picker = document.querySelector('[data-shift-timing-picker]');
            const startInput = picker?.querySelector('[data-shift-start]');
            const endInput = picker?.querySelector('[data-shift-end]');
            const textInput = document.querySelector('[data-shift-text]');
            const outsourcedSwitch = document.getElementById('isOutsourcedSwitch');
            const outsourceSelect = document.getElementById('outsourceLocationsSelect');

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
                    .replace(/[\u2013\u2014]/g, '-')
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

            function syncTextFromPickers() {
                if (!textInput || !startInput || !endInput) {
                    return;
                }

                const start = startInput.value;
                const end = endInput.value;

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

            function syncOutsourceSelectionState() {
                if (!outsourceSelect || !outsourcedSwitch) {
                    return;
                }

                outsourceSelect.disabled = !outsourcedSwitch.checked;
            }

            outsourcedSwitch?.addEventListener('change', syncOutsourceSelectionState);
            syncOutsourceSelectionState();
        });
    </script>
@endsection
