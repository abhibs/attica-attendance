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
                        <li class="breadcrumb-item active" aria-current="page">Edit Emplyee</li>
                    </ol>
                </nav>
            </div>
            <div class="ms-auto">

            </div>
        </div>
        <!--end breadcrumb-->
        <div class="row">
            <div class="col-xl-12 mx-auto">
                <hr>
                <div class="card">
                    <div class="card-body">
                        @php
                            $employeeIdConflicts = session('employee_id_conflicts', []);
                            $employeeIdConflictValue = session('employee_id_conflict_value');
                            $salaryDetail = $data->detail;
                            $mailNameSource = old('name', $data->name);
                            $generatedMailLocalPart = strtolower(preg_replace('/[^A-Za-z0-9]+/', '', (string) $mailNameSource) ?? '');
                            $generatedMailId = $generatedMailLocalPart !== '' ? $generatedMailLocalPart . '@attica.com' : '';
                            $savedMailId = trim((string) $data->mailId);
                            $mailIdValue = old('mailId', $savedMailId !== '' ? $savedMailId : $generatedMailId);
                            $autoFillMailId = old('mailId') === null && $savedMailId === '';
                        @endphp
                        <form action="{{ route('admin-employee-update') }}" method="post"
                            onsubmit="return confirm('Save changes to this employee?');">
                            @csrf
                            <input type="hidden" name="id" value="{{ $data->id }}">

                            <div class="row g-3">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="formName" class="form-label">Employee ID</label>
                                        <input class="form-control" type="text" name="empId"
                                            placeholder="Enter Employee ID" aria-label="default input example"
                                            value="{{ old('empId', $data->empId) }}" required>
                                        @error('empId')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                        @if (!empty($employeeIdConflicts) && $employeeIdConflictValue === old('empId', $data->empId))
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
                                        <label for="formName" class="form-label">Employee Name</label>
                                        <input class="form-control" type="text" name="name"
                                            placeholder="Enter Employee Name" aria-label="default input example"
                                            value="{{ old('name', $data->name) }}" required data-email-name-source>
                                        @error('name')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>



                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="formName" class="form-label">Employee Contact</label>
                                        <input class="form-control" type="text" name="contact"
                                            placeholder="Enter Employee Contact" aria-label="default input example"
                                            value="{{ old('contact', $data->contact) }}">
                                        @error('contact')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="formName" class="form-label">Employee Mail ID</label>
                                        <input class="form-control" type="email" name="mailId"
                                            placeholder="Enter Employee Mail ID" aria-label="default input example"
                                            value="{{ $mailIdValue }}" data-email-autofill="{{ $autoFillMailId ? '1' : '0' }}">
                                        @error('mailId')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="formName" class="form-label">Employee Designation</label>
                                        <input class="form-control" type="text" name="designation"
                                            placeholder="Enter Employee Designation" aria-label="default input example"
                                            value="{{ old('designation', $data->designation) }}">
                                        @error('designation')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Date of Joining</label>
                                        <input class="form-control" type="date" name="doj"
                                            aria-label="Employee date of joining"
                                            value="{{ old('doj', $employeeDateOfJoining ?? '') }}">
                                        @if (!empty($candidateDateOfJoining))
                                            <div class="form-text">From recruitment candidate DOJ: {{ $candidateDateOfJoining }}</div>
                                        @endif
                                        @error('doj')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label d-block">Employee Type</label>
                                        <div class="form-check form-switch mt-2">
                                            @php
                                                $isOutsourced = old('is_outsourced') !== null
                                                    ? (bool) old('is_outsourced')
                                                    : (bool) $data->is_outsourced;
                                            @endphp
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                id="isOutsourcedSwitch" name="is_outsourced" value="1"
                                                @checked($isOutsourced)>
                                            <label class="form-check-label" for="isOutsourcedSwitch">
                                                Outsourced Employee
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Outsource Locations (for outsourced employees)</label>
                                        @php
                                            $selectedOutsourceIds = collect(old('outsource_location_ids', $selectedOutsourceLocationIds ?? []))
                                                ->map(fn ($id) => (int) $id)
                                                ->all();
                                        @endphp
                                        <select class="form-select" name="outsource_location_ids[]" multiple size="5"
                                            id="outsourceLocationsSelect">
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
                                        <label for="formName" class="form-label">Employee Rating</label>
                                        <input class="form-control" type="number" name="rating"
                                            placeholder="Enter Employee Rating" min="1" max="10"
                                            aria-label="default input example" value="{{ old('rating', $data->rating) }}">
                                        @error('rating')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>



                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="formName" class="form-label">Employee Salary</label>
                                        <input class="form-control" type="text" name="salary"
                                            placeholder="Enter Employee Salary" aria-label="default input example"
                                            value="{{ old('salary', $data->salary) }}">
                                        @error('salary')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-12">
                                    <hr class="my-2">
                                    <h5 class="mb-1">Imported Bank & Salary Details</h5>
                                    <p class="text-muted mb-0">These values are read from the `employeeDetails` table and are not shown in the main employee list.</p>
                                </div>

                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Name as per Bank A/C</label>
                                        <input class="form-control" type="text"
                                            value="{{ $salaryDetail?->empName ?: '--' }}" readonly>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Bank Name</label>
                                        <input class="form-control" type="text"
                                            value="{{ $salaryDetail?->bankName ?: '--' }}" readonly>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Bank A/C Number</label>
                                        <input class="form-control" type="text"
                                            value="{{ $salaryDetail?->bankAcNo ?: '--' }}" readonly>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">IFSC Code</label>
                                        <input class="form-control" type="text"
                                            value="{{ $salaryDetail?->ifscCode ?: '--' }}" readonly>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Imported Salary</label>
                                        <input class="form-control" type="number" name="imported_salary"
                                            step="0.01" min="0"
                                            placeholder="Enter Imported Salary"
                                            value="{{ old('imported_salary', is_numeric($salaryDetail?->salary) ? (float) $salaryDetail->salary : '') }}">
                                        @error('imported_salary')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">PF Amount</label>
                                        <input class="form-control" type="text"
                                            value="{{ is_numeric($salaryDetail?->pfAmount) ? number_format((float) $salaryDetail->pfAmount, 2) : '--' }}"
                                            readonly>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                        <div class="mb-3">
                                            @php
                                                $defaultPfEligible = (bool) $data->pf_eligible || (bool) ($mayPfEligible ?? false);
                                                $pfEligibleValue = old('pf_eligible', $defaultPfEligible ? '1' : '0');
                                            @endphp
                                        <label class="form-label">PF Eligibility</label>
                                        <select class="form-select" name="pf_eligible">
                                            <option value="0" @selected($pfEligibleValue === '0')>Not Eligible</option>
                                            <option value="1" @selected($pfEligibleValue === '1')>Eligible</option>
                                        </select>
                                        @error('pf_eligible')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">UAN Number</label>
                                        <input class="form-control" type="text"
                                            value="{{ $salaryDetail?->uanNumber ?: '--' }}" readonly>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Passbook Document</label>
                                        @if ($salaryDetail?->passbook_doc_url)
                                            <div>
                                                <a href="{{ $salaryDetail->passbook_doc_url }}" target="_blank" rel="noopener"
                                                    class="btn btn-outline-primary">Open Passbook</a>
                                            </div>
                                        @else
                                            <input class="form-control" type="text" value="--" readonly>
                                        @endif
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Shift Timing</label>
                                        <div class="shift-timing-picker-grid" data-shift-timing-picker>
                                            <div>
                                                <label class="form-label small mb-1">Start</label>
                                                <input class="form-control" type="time" data-shift-start
                                                    value="">
                                            </div>
                                            <div>
                                                <label class="form-label small mb-1">End</label>
                                                <input class="form-control" type="time" data-shift-end
                                                    value="">
                                            </div>
                                        </div>
                                        <input class="form-control" type="text" name="shift_timing"
                                            placeholder="10:00 AM - 7:00 PM"
                                            aria-label="Shift timing"
                                            value="{{ old('shift_timing', $data->shift_timing) }}"
                                            data-shift-text>
                                        <div class="form-text">Pick start/end time or type a full range like 09:00 PM - 03:00 AM.</div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Gender</label>
                                        <select class="form-select" name="gender">
                                            <option value="">Select Gender</option>
                                            <option value="Male" @selected(old('gender', $data->gender) === 'Male')>Male</option>
                                            <option value="Female" @selected(old('gender', $data->gender) === 'Female')>Female</option>
                                            <option value="Other" @selected(old('gender', $data->gender) === 'Other')>Other</option>
                                        </select>
                                        @error('gender')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Marital Status</label>
                                        <select class="form-select" name="marital_status">
                                            <option value="">Select Marital Status</option>
                                            <option value="Single" @selected(old('marital_status', $data->marital_status) === 'Single')>Single</option>
                                            <option value="Married" @selected(old('marital_status', $data->marital_status) === 'Married')>Married</option>
                                        </select>
                                        @error('marital_status')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Last Login City</label>
                                        <input class="form-control" type="text"
                                            value="{{ $data->last_login_branch_city ?: '--' }}" readonly>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Last Login State</label>
                                        <input class="form-control" type="text"
                                            value="{{ $data->last_login_branch_state ?: '--' }}" readonly>
                                    </div>
                                </div>

                                @if ($data->status === 'Inactive' || $data->inactive_reason || $data->last_working_date)
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Last Working Date</label>
                                            <input class="form-control" type="text"
                                                value="{{ $data->last_working_date ?: '--' }}" readonly>
                                        </div>
                                    </div>

                                    <div class="col-md-9">
                                        <div class="mb-3">
                                            <label class="form-label">Inactive Reason</label>
                                            <textarea class="form-control" rows="2" readonly>{{ $data->inactive_reason ?: '--' }}</textarea>
                                        </div>
                                    </div>
                                @endif



                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label for="formName" class="form-label">Employee Address</label>
                                        <textarea class="form-control" name="address" aria-label="With textarea" placeholder="Enter Employee Address">{{ old('address', $data->address) }}</textarea>
                                        @error('address')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                @if (!empty($employeeMedia['images']) || !empty($employeeMedia['documents']) || !empty($employeeMedia['videos']))
                                    <div class="col-12">
                                        <hr class="my-2">
                                        <h5 class="mb-1">Employee Documents & Media</h5>
                                        @if (!empty($employeeMedia['candidate']))
                                            <p class="text-muted mb-3">
                                                From recruitment record for {{ $employeeMedia['candidate']->candidate_name ?: $data->name }}.
                                            </p>
                                        @endif
                                    </div>

                                    @foreach ($employeeMedia['images'] as $image)
                                        <div class="col-md-3">
                                            <div class="border rounded-3 p-2 h-100">
                                                <div class="fw-semibold small mb-2">{{ $image['label'] }}</div>
                                                <a href="{{ $image['url'] }}" target="_blank" rel="noopener">
                                                    <img src="{{ $image['url'] }}" alt="{{ $image['label'] }}"
                                                        class="img-fluid rounded border" style="max-height: 180px; width: 100%; object-fit: cover;">
                                                </a>
                                            </div>
                                        </div>
                                    @endforeach

                                    @foreach ($employeeMedia['documents'] as $document)
                                        <div class="col-md-3">
                                            <div class="border rounded-3 p-3 h-100">
                                                <div class="fw-semibold small mb-2">{{ $document['label'] }}</div>
                                                <a href="{{ $document['url'] }}" target="_blank" rel="noopener"
                                                    class="btn btn-outline-primary btn-sm">Open Document</a>
                                            </div>
                                        </div>
                                    @endforeach

                                    @foreach ($employeeMedia['videos'] as $video)
                                        <div class="col-md-6">
                                            <div class="border rounded-3 p-3 h-100">
                                                <div class="fw-semibold small mb-2">{{ $video['label'] }}</div>
                                                <video controls preload="metadata" class="w-100 rounded border"
                                                    src="{{ $video['url'] }}"></video>
                                                <a href="{{ $video['url'] }}" target="_blank" rel="noopener"
                                                    class="btn btn-outline-primary btn-sm mt-2">Open Video</a>
                                            </div>
                                        </div>
                                    @endforeach
                                @endif

                                <div class="col-12">
                                    <div class="mb-3">
                                        <button type="submit" class="btn btn-grd btn-grd-success px-5">Update
                                            Employee</button>
                                        <a href="{{ route('admin-employee-index') }}" class="btn btn-grd btn-grd-royal px-5 ms-2"
                                            onclick="if (window.history.length > 1) { event.preventDefault(); window.history.back(); }">Cancel</a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!--end row-->
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const nameInput = document.querySelector('[data-email-name-source]');
            const mailInput = document.querySelector('[data-email-autofill]');
            const picker = document.querySelector('[data-shift-timing-picker]');
            const startInput = picker?.querySelector('[data-shift-start]');
            const endInput = picker?.querySelector('[data-shift-end]');
            const textInput = document.querySelector('[data-shift-text]');
            const outsourcedSwitch = document.getElementById('isOutsourcedSwitch');
            const outsourceSelect = document.getElementById('outsourceLocationsSelect');
            let lastGeneratedMailId = generateMailId(nameInput?.value || '');

            function generateMailId(name) {
                const localPart = (name || '').replace(/[^A-Za-z0-9]+/g, '').toLowerCase();

                return localPart ? `${localPart}@attica.com` : '';
            }

            function syncMailFromName() {
                if (!nameInput || !mailInput || mailInput.dataset.emailAutofill !== '1') {
                    return;
                }

                const nextMailId = generateMailId(nameInput.value);

                if (!mailInput.value || mailInput.value === lastGeneratedMailId) {
                    mailInput.value = nextMailId;
                    lastGeneratedMailId = nextMailId;
                }
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
            nameInput?.addEventListener('input', syncMailFromName);

            syncMailFromName();
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
