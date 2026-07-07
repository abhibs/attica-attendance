@extends('admin.layout.app')

@section('content')
    <div class="main-content">
        @php
            $pendingImport = session('advance_import_pending');
            $skippedImportDetails = session('advance_import_skipped_details', []);
        @endphp

        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Salary</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin-dashboard') }}"><i class="bx bx-home-alt"></i></a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Add Advance Details</li>
                    </ol>
                </nav>
            </div>
        </div>

        @if (session('flash_success'))
            <div class="alert alert-success border-0">{{ session('flash_success') }}</div>
        @endif

        @if (session('flash_warning'))
            <div class="alert alert-warning border-0">{{ session('flash_warning') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger border-0">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3 mb-4">
            <div class="col">
                <div class="card rounded-4 mb-0">
                    <div class="card-body">
                        <p class="mb-1 text-muted">Employees With Advance</p>
                        <h4 class="mb-0">{{ $employeesWithAdvanceCount }}</h4>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card rounded-4 mb-0">
                    <div class="card-body">
                        <p class="mb-1 text-muted">Pending Advance Requests</p>
                        <h4 class="mb-0">{{ $pendingAdvanceRequests->count() }}</h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="card rounded-4 mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                    <div>
                        <h4 class="mb-1">Import Advance Details</h4>
                        <p class="mb-0 text-muted">Upload a CSV, XLSX or XLS file with the fixed columns: SL. NO, ID, DATE, NAME, DESIGNATION, BRANCH and AMOUNT.</p>
                    </div>
                </div>

                <form method="post" action="{{ route('admin-salary-advance-import') }}" enctype="multipart/form-data" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-lg-8">
                        <label class="form-label">Advance File</label>
                        <input type="file" name="advance_file" class="form-control" accept=".csv,.xlsx,.xls">
                    </div>
                    <div class="col-lg-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Import Advance File</button>
                    </div>
                    <div class="col-12">
                        <p class="mb-0 text-muted small">
                            The import matches spreadsheet <strong>ID</strong> to employee <strong>Emp ID</strong>.
                            Each valid row is stored with its DATE and AMOUNT, and the employee's total advance is recalculated from the ledger.
                        </p>
                    </div>
                </form>
            </div>
        </div>

        @if (is_array($skippedImportDetails) && $skippedImportDetails !== [])
            <div class="card rounded-4 mb-4 border-danger">
                <div class="card-body">
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-3">
                        <div>
                            <h4 class="mb-1">Rows Not Inserted</h4>
                            <p class="mb-0 text-muted">
                                These rows were found in the uploaded advance file but were not inserted.
                            </p>
                        </div>
                        <span class="badge bg-danger">{{ count($skippedImportDetails) }} row{{ count($skippedImportDetails) === 1 ? '' : 's' }}</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0" data-admin-static-serial="true">
                            <thead>
                                <tr>
                                    <th>Excel Row</th>
                                    <th>Emp ID</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($skippedImportDetails as $skippedRow)
                                    <tr>
                                        <td>{{ $skippedRow['row_no'] ?? '--' }}</td>
                                        <td>{{ trim((string) ($skippedRow['emp_id'] ?? '')) !== '' ? $skippedRow['emp_id'] : '--' }}</td>
                                        <td>{{ trim((string) ($skippedRow['advance_date'] ?? '')) !== '' ? $skippedRow['advance_date'] : '--' }}</td>
                                        <td>
                                            @if (is_numeric($skippedRow['amount'] ?? null))
                                                {{ 'Rs ' . number_format((float) $skippedRow['amount'], 2) }}
                                            @else
                                                --
                                            @endif
                                        </td>
                                        <td class="text-danger fw-semibold">{{ $skippedRow['reason'] ?? 'Not inserted.' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        @if (is_array($pendingImport) && ! empty($pendingImport['conflicts']))
            <div class="card rounded-4 mb-4 border-warning">
                <div class="card-body">
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-3">
                        <div>
                            <h4 class="mb-1">Import Confirmation Required</h4>
                            <p class="mb-0 text-muted">
                                Matching manual advance entries already exist for the rows below. Confirm to add both the manual
                                and imported amounts together and recalculate the employee totals everywhere.
                            </p>
                        </div>
                        <div class="d-flex gap-2">
                            <form method="post" action="{{ route('admin-salary-advance-import') }}">
                                @csrf
                                <input type="hidden" name="import_token" value="{{ $pendingImport['token'] }}">
                                <input type="hidden" name="confirm_conflicts" value="1">
                                <button type="submit" class="btn btn-primary">Add Both Entries And Import</button>
                            </form>
                            <form method="post" action="{{ route('admin-salary-advance-import') }}">
                                @csrf
                                <input type="hidden" name="import_token" value="{{ $pendingImport['token'] }}">
                                <input type="hidden" name="cancel_pending_import" value="1">
                                <button type="submit" class="btn btn-outline-secondary">Cancel</button>
                            </form>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0" data-admin-static-serial="true">
                            <thead>
                                <tr>
                                    <th>Row</th>
                                    <th>Emp ID</th>
                                    <th>Name</th>
                                    <th>Date</th>
                                    <th>Manual Amount</th>
                                    <th>Import Amount</th>
                                    <th>Combined Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pendingImport['conflicts'] as $conflict)
                                    <tr>
                                        <td>{{ $conflict['row_no'] }}</td>
                                        <td>{{ $conflict['emp_id'] }}</td>
                                        <td>{{ $conflict['employee_name'] ?: '--' }}</td>
                                        <td>{{ \Illuminate\Support\Carbon::parse($conflict['advance_date'])->format('d-m-Y') }}</td>
                                        <td>
                                            {{ collect($conflict['manual_amounts'])
                                                ->map(fn ($amount) => 'Rs '.number_format((float) $amount, 0))
                                                ->implode(', ') }}
                                        </td>
                                        <td>{{ 'Rs '.number_format((float) $conflict['import_amount'], 0) }}</td>
                                        <td>{{ 'Rs '.number_format((float) $conflict['combined_total'], 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <div class="card rounded-4 mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                    <div>
                        <h4 class="mb-1">Advance Requests From App</h4>
                        <p class="mb-0 text-muted">Verify requests to add them into advance deductions and salary calculations.</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0" data-admin-static-serial="true">
                        <thead>
                            <tr>
                                <th>Emp ID</th>
                                <th>Name</th>
                                <th>Designation</th>
                                <th>Request Date</th>
                                <th>Amount</th>
                                <th>Request Note</th>
                                <th>Requested At</th>
                                <th style="min-width: 360px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($pendingAdvanceRequests as $advanceRequest)
                                <tr>
                                    <td>{{ $advanceRequest->emp_id }}</td>
                                    <td>{{ $advanceRequest->employee?->name ?: '--' }}</td>
                                    <td>{{ $advanceRequest->employee?->designation ?: '--' }}</td>
                                    <td>{{ optional($advanceRequest->request_date)?->format('d-m-Y') ?: '--' }}</td>
                                    <td>{{ 'Rs '.number_format((float) $advanceRequest->amount, 0) }}</td>
                                    <td>{{ trim((string) $advanceRequest->request_note) ?: '--' }}</td>
                                    <td>{{ optional($advanceRequest->created_at)?->format('d M Y h:i A') ?: '--' }}</td>
                                    <td>
                                        <form method="post" class="row g-2 align-items-center">
                                            @csrf
                                            <div class="col-md-8">
                                                <input type="text"
                                                    name="admin_note"
                                                    class="form-control"
                                                    placeholder="Optional admin note">
                                            </div>
                                            <div class="col-md-4 d-flex gap-2">
                                                <button
                                                    type="submit"
                                                    formaction="{{ route('admin-salary-advance-request-verify', ['advanceRequest' => $advanceRequest->id]) }}"
                                                    class="btn btn-success w-100">
                                                    Verify
                                                </button>
                                                <button
                                                    type="submit"
                                                    formaction="{{ route('admin-salary-advance-request-reject', ['advanceRequest' => $advanceRequest->id]) }}"
                                                    class="btn btn-outline-danger w-100">
                                                    Reject
                                                </button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No pending advance requests.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card rounded-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                    <div>
                        <h4 class="mb-1">Add Advance Details</h4>
                        <p class="mb-0 text-muted">Add dated advance entries, maintain PF, and review each employee's current deductions.</p>
                        <small class="text-muted d-block mt-1">
                            Advance deduction cycle for selected date:
                            {{ \Illuminate\Support\Carbon::parse($currentAdvanceWindowStart)->format('d M Y') }}
                            to
                            {{ \Illuminate\Support\Carbon::parse($currentAdvanceWindowEnd)->format('d M Y') }}.
                            @if (!($hasOpenCurrentAdvanceWindow ?? true))
                                No active current-advance period right now.
                            @endif
                        </small>
                    </div>
                    <div class="datatable-toolbar"></div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle js-admin-datatable" data-admin-datatable="true" data-admin-scroll-x="false">
                        <thead>
                            <tr>
                                <th>S.No</th>
                                <th>Emp ID</th>
                                <th>Name</th>
                                <th>Designation</th>
                                <th>Status</th>
                                <th>Salary</th>
                                <th>Current Advance</th>
                                <th>PF</th>
                                <th>UAN</th>
                                <th>Net Salary</th>
                                <th style="min-width: 420px;">Add Advance / PF</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                                @foreach ($employees as $employee)
                                @php
                                    $currentAdvanceTotal = is_numeric($employee->current_advance_total ?? null)
                                        ? (float) $employee->current_advance_total
                                        : 0.0;
                                    $advanceEntryCount = (int) ($employee->advance_transactions_count ?? 0);
                                    $advanceDates = $employee->advanceTransactions
                                        ->pluck('advance_date')
                                        ->filter()
                                        ->map(fn ($date) => \Illuminate\Support\Carbon::parse($date)->format('d M Y'))
                                        ->unique()
                                        ->values();
                                @endphp
                                <tr>
                                    <td></td>
                                    <td>{{ $employee->empId }}</td>
                                    <td>{{ $employee->name }}</td>
                                    <td>{{ $employee->designation ?: '--' }}</td>
                                    <td>{{ $employee->status ?: 'Unknown' }}</td>
                                    <td>{{ is_numeric($employee->salary) ? 'Rs '.number_format((float) $employee->salary, 0) : '--' }}</td>
                                    <td>
                                        <div>{{ 'Rs '.number_format($currentAdvanceTotal, 0) }}</div>
                                        <small class="text-muted d-block mt-1">
                                            {{ $advanceEntryCount }} {{ \Illuminate\Support\Str::plural('entry', $advanceEntryCount) }} recorded
                                        </small>
                                        <small class="text-muted d-block mt-1">
                                            @if ($advanceDates->isNotEmpty())
                                                {{ $advanceDates->implode(', ') }}
                                            @else
                                                No advance dates
                                            @endif
                                        </small>
                                    </td>
                                    <td>{{ is_numeric($employee->pf) ? 'Rs '.number_format((float) $employee->pf, 0) : 'Rs 0' }}</td>
                                    <td>{{ $employee->detail?->uanNumber ?: '--' }}</td>
                                    <td>
                                        @if (is_numeric($employee->salary))
                                            {{ 'Rs '.number_format(round((float) $employee->salary - $currentAdvanceTotal - (float) ($employee->pf ?? 0)), 0) }}
                                        @else
                                            --
                                        @endif
                                    </td>
                                    <td>
                                        <form method="post" action="{{ route('admin-salary-advance-update') }}" class="row g-2 align-items-center">
                                            @csrf
                                            <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                                            <div class="col-md-4">
                                                <input
                                                    type="date"
                                                    name="advance_date"
                                                    class="form-control"
                                                    onchange="window.location.href='{{ route('admin-salary-advance') }}?advance_date=' + encodeURIComponent(this.value)"
                                                    value="{{ old('employee_id') == $employee->id ? old('advance_date', $defaultAdvanceDate) : $defaultAdvanceDate }}">
                                            </div>
                                            <div class="col-md-3">
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    name="advance"
                                                    class="form-control"
                                                    value="{{ old('employee_id') == $employee->id ? old('advance', 0) : 0 }}"
                                                    placeholder="Advance amount">
                                            </div>
                                            <div class="col-md-3">
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    name="pf"
                                                    class="form-control"
                                                    value="{{ old('employee_id') == $employee->id ? old('pf') : '' }}"
                                                    placeholder="PF (optional)">
                                            </div>
                                            <div class="col-md-2">
                                                <button type="submit" class="btn btn-primary w-100">Save Advance</button>
                                            </div>
                                        </form>
                                    </td>
                                    <td>
                                        @php
                                            $employeeHistoryEmpId = trim((string) $employee->empId);
                                        @endphp
                                        @if ($employeeHistoryEmpId !== '')
                                            <a href="{{ route('admin-salary-advance-history', ['empId' => $employeeHistoryEmpId]) }}" class="btn btn-outline-primary btn-sm">
                                                {{ $advanceEntryCount > 1 ? 'Review Multiple Advances' : 'View Details' }}
                                            </a>
                                        @else
                                            <span class="badge bg-danger">Employee ID missing</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
