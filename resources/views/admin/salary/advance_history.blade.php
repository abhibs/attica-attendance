@extends('admin.layout.app')

@section('content')
    <div class="main-content">
        @if (session('flash_success'))
            <div class="alert alert-success border-0">{{ session('flash_success') }}</div>
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

        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Salary</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin-dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin-salary-advance') }}">Add Advance Details</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Advance History</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="card rounded-4 mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
                    <div>
                        <h4 class="mb-1">Advance History</h4>
                        <p class="mb-0 text-muted">
                            {{ $employee->empId }} - {{ $employee->name }} | Effective Total Advance:
                            {{ 'Rs '.number_format((float) $currentAdvance, 0) }} | PF:
                            {{ 'Rs '.number_format((float) ($employee->pf ?? 0), 0) }}
                        </p>
                        <p class="mb-0 text-muted small mt-1">
                            Selected range total: {{ 'Rs '.number_format((float) $filteredAdvance, 0) }} |
                            Entries in range: {{ $transactions->count() }}
                        </p>
                    </div>
                    <a href="{{ route('admin-salary-advance') }}" class="btn btn-outline-primary">Back to Advance Details</a>
                </div>

                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">From</label>
                        <input type="date" name="from_date" value="{{ $filters['from_date'] }}" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To</label>
                        <input type="date" name="to_date" value="{{ $filters['to_date'] }}" class="form-control">
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100">Apply</button>
                        <a href="{{ route('admin-salary-advance-history', ['empId' => $employee->empId]) }}" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card rounded-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                    <div>
                        <h5 class="mb-1">Transactions</h5>
                        <p class="mb-0 text-muted">Copy, CSV and print options are available for this employee's advance ledger. Use this screen to review multiple advance entries before adding more.</p>
                    </div>
                    <div class="datatable-toolbar"></div>
                </div>

                @if ($transactions->count() > 1)
                    <form method="post" action="{{ route('admin-salary-advance-history-merge', ['empId' => $employee->empId]) }}" class="row g-3 align-items-end mb-3">
                        @csrf
                        <input type="hidden" name="from_date" value="{{ $filters['from_date'] }}">
                        <input type="hidden" name="to_date" value="{{ $filters['to_date'] }}">
                        <div class="col-md-3">
                            <label class="form-label">Merged Advance Date</label>
                            <input type="date" name="merge_date" value="{{ old('merge_date', $filters['to_date']) }}" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Remarks</label>
                            <input type="text" name="remarks" value="{{ old('remarks') }}" class="form-control" placeholder="Optional remarks for the merged entry">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">Save Selected As Single Summed Advance</button>
                        </div>

                        <div class="col-12">
                            <div class="alert alert-warning border-0 mb-0">
                                Select two or more entries below. Saving will replace the selected rows with one summed advance entry and keep the effective total advance unchanged.
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle js-admin-datatable" data-admin-datatable="true">
                                    <thead>
                                        <tr>
                                            <th>Select</th>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Source</th>
                                            <th>Reference</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($transactions as $transaction)
                                            <tr>
                                                <td>
                                                    <input
                                                        type="checkbox"
                                                        name="transaction_ids[]"
                                                        value="{{ $transaction->id }}"
                                                        @checked(collect(old('transaction_ids', []))->contains((string) $transaction->id) || collect(old('transaction_ids', []))->contains($transaction->id))>
                                                </td>
                                                <td>{{ $transaction->advance_date }}</td>
                                                <td>{{ 'Rs '.number_format((float) $transaction->amount, 0) }}</td>
                                                <td>{{ ucfirst(str_replace('_', ' ', (string) $transaction->source_type)) }}</td>
                                                <td>
                                                    @if ($transaction->source_file)
                                                        {{ $transaction->source_file }}
                                                        @if ($transaction->source_row_no)
                                                            {{ ' | Row '.$transaction->source_row_no }}
                                                        @endif
                                                    @else
                                                        --
                                                    @endif
                                                </td>
                                                <td>{{ $transaction->remarks ?: '--' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </form>
                @else
                    <div class="alert alert-secondary border-0 mb-3">
                        At least two advance entries are required before they can be merged into one summed advance.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Source</th>
                                    <th>Reference</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($transactions as $transaction)
                                    <tr>
                                        <td>{{ $transaction->advance_date }}</td>
                                        <td>{{ 'Rs '.number_format((float) $transaction->amount, 0) }}</td>
                                        <td>{{ ucfirst(str_replace('_', ' ', (string) $transaction->source_type)) }}</td>
                                        <td>
                                            @if ($transaction->source_file)
                                                {{ $transaction->source_file }}
                                                @if ($transaction->source_row_no)
                                                    {{ ' | Row '.$transaction->source_row_no }}
                                                @endif
                                            @else
                                                --
                                            @endif
                                        </td>
                                        <td>{{ $transaction->remarks ?: '--' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
