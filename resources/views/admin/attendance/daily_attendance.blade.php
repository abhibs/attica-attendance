@extends('admin.layout.app')

@section('content')
    @include('admin.attendance.partials.styles')

    @php
        $pageTitle = $pageTitle ?? 'Daily Attendance';
        $pageSubtitle = $pageSubtitle ?? 'Filter a date, branch, or employee ID to inspect daily check-in and check-out records.';
        $breadcrumbTitle = $breadcrumbTitle ?? 'Attendance';
        $resetRoute = $resetRoute ?? 'admin-attendance-daily';
        $summary = array_merge([
            'total' => 0,
            'completed' => 0,
            'single_punch' => 0,
        ], $summary ?? []);
    @endphp

    <style>
        .daily-attendance-thumb {
            width: 68px;
            height: 68px;
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid var(--admin-border-color);
            background: var(--admin-surface-color);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .daily-attendance-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .daily-attendance-thumb.is-empty {
            flex-direction: column;
            gap: 4px;
            background: linear-gradient(135deg, rgba(90, 22, 201, 0.08), rgba(200, 162, 74, 0.12));
            color: var(--admin-muted-text-color);
            border-style: dashed;
        }

        .daily-attendance-thumb.is-empty i {
            font-size: 1.35rem;
            color: var(--admin-primary-color);
            opacity: 0.8;
        }

        .daily-attendance-thumb.is-empty span {
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .daily-attendance-link {
            color: var(--admin-primary-color) !important;
            text-decoration: underline;
            text-underline-offset: 3px;
            font-weight: 600;
        }

        .daily-attendance-image-modal .modal-content {
            background: var(--admin-surface-color);
            border: 1px solid var(--admin-border-color);
            border-radius: 22px;
            overflow: hidden;
        }

        .daily-attendance-image-modal .modal-dialog {
            max-width: min(92vw, 900px);
        }

        .daily-attendance-image-modal .modal-body {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 1rem 1.5rem 1.5rem;
            min-height: 220px;
        }

        .daily-attendance-image-modal img {
            width: auto;
            max-width: 100%;
            max-height: 75vh;
            border-radius: 18px;
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.18);
            background: var(--admin-background-color);
            object-fit: contain;
        }
    </style>

    <div class="main-content attendance-page">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">{{ $breadcrumbTitle }}</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin-dashboard') }}"><i class="bx bx-home-alt"></i></a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">{{ $pageTitle }}</li>
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
                        <p class="mb-1">Records</p>
                        <h4 class="mb-0">{{ $summary['total'] }}</h4>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card rounded-4 mb-0 attendance-stat-card">
                    <div class="card-body">
                        <p class="mb-1">Completed Punches</p>
                        <h4 class="mb-0">{{ $summary['completed'] }}</h4>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card rounded-4 mb-0 attendance-stat-card">
                    <div class="card-body">
                        <p class="mb-1">Single Punch</p>
                        <h4 class="mb-0">{{ $summary['single_punch'] }}</h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="card rounded-4 mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
                    <div>
                        <h4 class="mb-1 attendance-title">{{ $pageTitle }}</h4>
                        <p class="mb-0 attendance-muted">{{ $pageSubtitle }}</p>
                    </div>
                </div>

                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="date" value="{{ $filters['date'] }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        @php
                            $selectedBranch = $filters['branches']->first(
                                fn ($branch) => trim((string) $branch->branchId) === $filters['branch_id'],
                            );
                            $selectedBranchSearch = $selectedBranch
                                ? trim($selectedBranch->branchId . ' - ' . $selectedBranch->branchName)
                                : '';
                        @endphp
                        <label class="form-label">Branch</label>
                        <input type="hidden" name="branch_id" id="dailyAttendanceBranchId"
                            value="{{ $filters['branch_id'] }}">
                        <input type="text" id="dailyAttendanceBranchSearch" class="form-control"
                            value="{{ $selectedBranchSearch }}" list="dailyAttendanceBranchOptions"
                            placeholder="All active branches" autocomplete="off">
                        <datalist id="dailyAttendanceBranchOptions">
                            @foreach ($filters['branches'] as $branch)
                                @php($branchSearchLabel = trim($branch->branchId . ' - ' . $branch->branchName))
                                <option value="{{ $branchSearchLabel }}">
                                    {{ trim(implode(', ', array_filter([$branch->city ?? null, $branch->state ?? null]))) }}
                                </option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Employee ID</label>
                        <input type="text" name="emp_id" value="{{ $filters['emp_id'] }}" class="form-control"
                            placeholder="Optional">
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100">Apply</button>
                        <a href="{{ route($resetRoute) }}" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card rounded-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                    <div>
                            <h5 class="mb-1 attendance-title">{{ $pageTitle }} List</h5>
                            <p class="mb-0 attendance-muted">Login and logout details with branch, location, and image previews.</p>
                    </div>
                    <div class="datatable-toolbar"></div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle js-admin-datatable"
                        data-admin-datatable="true" data-admin-scroll-x="false" data-admin-page-length="10">
                        <thead>
                            <tr>
                                <th>Emp ID</th>
                                <th>Name</th>
                                <th>Designation</th>
                                <th>Login Time</th>
                                <th>Login Branch</th>
                                <th>Login Location</th>
                                <th>Login Image</th>
                                <th>Logout Time</th>
                                <th>Logout Branch</th>
                                <th>Logout Location</th>
                                <th>Logout Image</th>
                                <th>Worked Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td>{{ $row['emp_id'] }}</td>
                                    <td>{{ $row['employee_name'] }}</td>
                                    <td>{{ $row['designation'] }}</td>
                                    <td>{{ $row['check_in_time'] }}</td>
                                    <td>
                                        @if (($row['check_in_branch']['url'] ?? '') !== '')
                                            <a href="{{ $row['check_in_branch']['url'] }}" target="_blank" rel="noopener"
                                                class="daily-attendance-link">
                                                {{ $row['check_in_branch']['label'] }}
                                            </a>
                                        @else
                                            {{ $row['check_in_branch']['label'] }}
                                        @endif
                                    </td>
                                    <td>
                                        @if (($row['check_in_location']['url'] ?? '') !== '')
                                            <a href="{{ $row['check_in_location']['url'] }}" target="_blank" rel="noopener"
                                                class="daily-attendance-link">
                                                {{ $row['check_in_location']['label'] }}
                                            </a>
                                        @else
                                            {{ $row['check_in_location']['label'] }}
                                        @endif
                                    </td>
                                    <td>
                                        @if ($row['check_in_image_url'])
                                            <button type="button" class="btn p-0 border-0 bg-transparent"
                                                onclick="openDailyAttendanceImage('Login Image', '{{ $row['check_in_image_url'] }}')">
                                                <span class="daily-attendance-thumb">
                                                    <img src="{{ $row['check_in_image_url'] }}" alt="Login image"
                                                        data-admin-image-fallback data-admin-image-alt="No Image">
                                                </span>
                                            </button>
                                        @else
                                            <span class="daily-attendance-thumb is-empty" aria-label="No image available">
                                                <i class="material-icons-outlined">image_not_supported</i>
                                                <span>No Image</span>
                                            </span>
                                        @endif
                                    </td>
                                    <td>{{ $row['check_out_time'] }}</td>
                                    <td>
                                        @if (($row['check_out_branch']['url'] ?? '') !== '')
                                            <a href="{{ $row['check_out_branch']['url'] }}" target="_blank" rel="noopener"
                                                class="daily-attendance-link">
                                                {{ $row['check_out_branch']['label'] }}
                                            </a>
                                        @else
                                            {{ $row['check_out_branch']['label'] }}
                                        @endif
                                    </td>
                                    <td>
                                        @if (($row['check_out_location']['url'] ?? '') !== '')
                                            <a href="{{ $row['check_out_location']['url'] }}" target="_blank" rel="noopener"
                                                class="daily-attendance-link">
                                                {{ $row['check_out_location']['label'] }}
                                            </a>
                                        @else
                                            {{ $row['check_out_location']['label'] }}
                                        @endif
                                    </td>
                                    <td>
                                        @if ($row['check_out_image_url'])
                                            <button type="button" class="btn p-0 border-0 bg-transparent"
                                                onclick="openDailyAttendanceImage('Logout Image', '{{ $row['check_out_image_url'] }}')">
                                                <span class="daily-attendance-thumb">
                                                    <img src="{{ $row['check_out_image_url'] }}" alt="Logout image"
                                                        data-admin-image-fallback data-admin-image-alt="No Image">
                                                </span>
                                            </button>
                                        @else
                                            <span class="daily-attendance-thumb is-empty" aria-label="No image available">
                                                <i class="material-icons-outlined">image_not_supported</i>
                                                <span>No Image</span>
                                            </span>
                                        @endif
                                    </td>
                                    <td>{{ $row['worked_time'] }}</td>
                                    <td>
                                        <span class="attendance-status-pill {{ $row['status'] }}">
                                            {{ str_replace('_', ' ', ucfirst($row['status'])) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade daily-attendance-image-modal" id="dailyAttendanceImageModal" tabindex="-1"
        aria-labelledby="dailyAttendanceImageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <h5 class="modal-title" id="dailyAttendanceImageModalLabel">Attendance Image</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3 text-center">
                    <img id="dailyAttendanceImageModalPreview" src="" alt="Attendance image" class="img-fluid"
                        data-admin-image-fallback data-admin-image-alt="No Image">
                </div>
            </div>
        </div>
    </div>

    <script>
        let dailyAttendanceImageModalInstance = null;
        const dailyAttendanceBranches = @json(
            $filters['branches']->map(fn ($branch) => [
                    'id' => trim((string) $branch->branchId),
                    'label' => trim($branch->branchId . ' - ' . $branch->branchName),
                ])->values()
        );

        const dailyAttendanceBranchIdInput = document.getElementById('dailyAttendanceBranchId');
        const dailyAttendanceBranchSearchInput = document.getElementById('dailyAttendanceBranchSearch');

        function syncDailyAttendanceBranchId() {
            if (!dailyAttendanceBranchIdInput || !dailyAttendanceBranchSearchInput) {
                return;
            }

            const searchValue = dailyAttendanceBranchSearchInput.value.trim();

            if (searchValue === '') {
                dailyAttendanceBranchIdInput.value = '';
                return;
            }

            const exactMatch = dailyAttendanceBranches.find((branch) => {
                return branch.label === searchValue || branch.id === searchValue;
            });

            dailyAttendanceBranchIdInput.value = exactMatch ? exactMatch.id : '';
        }

        dailyAttendanceBranchSearchInput?.addEventListener('change', syncDailyAttendanceBranchId);
        dailyAttendanceBranchSearchInput?.form?.addEventListener('submit', syncDailyAttendanceBranchId);

        function openDailyAttendanceImage(title, imageUrl) {
            const modalElement = document.getElementById('dailyAttendanceImageModal');
            const imageElement = document.getElementById('dailyAttendanceImageModalPreview');
            const titleElement = document.getElementById('dailyAttendanceImageModalLabel');

            if (!modalElement || !imageElement || !titleElement || typeof bootstrap === 'undefined') {
                return;
            }

            dailyAttendanceImageModalInstance = dailyAttendanceImageModalInstance || new bootstrap.Modal(modalElement);
            titleElement.textContent = title || 'Attendance Image';
            imageElement.removeAttribute('data-admin-fallback-applied');
            imageElement.src = imageUrl || '';
            imageElement.alt = title || 'Attendance image';
            dailyAttendanceImageModalInstance.show();
        }
    </script>
@endsection
