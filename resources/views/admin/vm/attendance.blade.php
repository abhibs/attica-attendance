<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VM Branch Attendance</title>
    <link href="{{ \App\Support\ProjectAsset::url('public/admin/assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <style>
        body {
            background: #f5f7fb;
            color: #172033;
        }

        .vm-page {
            padding: 24px;
        }

        .vm-topbar,
        .vm-card,
        .vm-stat {
            background: #fff;
            border: 1px solid rgba(23, 32, 51, 0.08);
            border-radius: 16px;
            box-shadow: 0 14px 36px rgba(23, 32, 51, 0.05);
        }

        .vm-topbar,
        .vm-card {
            padding: 20px;
        }

        .vm-stat {
            padding: 16px;
            height: 100%;
        }

        .vm-stat span {
            display: block;
            color: #667085;
            font-size: 0.84rem;
            margin-bottom: 4px;
        }

        .vm-stat strong {
            font-size: 1.5rem;
        }

        .vm-logo {
            width: 46px;
            height: 46px;
            object-fit: contain;
        }

        .vm-photo {
            width: 82px;
            height: 82px;
            object-fit: cover;
            border-radius: 12px;
            border: 1px solid rgba(23, 32, 51, 0.08);
            background: #eef1f6;
        }

        .vm-photo-empty {
            width: 82px;
            height: 82px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eef1f6;
            color: #667085;
            font-size: 0.75rem;
            text-align: center;
        }

        .vm-table {
            min-width: 1500px;
        }

        .vm-location {
            min-width: 170px;
        }

        .vm-muted {
            color: #667085;
        }

        .vm-status {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
        }

        .vm-status.full_day,
        .vm-status.full_day_remote {
            color: #0f6a42;
            background: rgba(25, 135, 84, 0.14);
        }

        .vm-status.half_day {
            color: #8a6300;
            background: rgba(255, 193, 7, 0.22);
        }

        .vm-status.single_punch {
            color: #0c58ca;
            background: rgba(13, 110, 253, 0.14);
        }

        .vm-status.absent {
            color: #b42318;
            background: rgba(220, 53, 69, 0.14);
        }

        @media (max-width: 767.98px) {
            .vm-page {
                padding: 14px;
            }
        }
    </style>
</head>
<body>
    <main class="vm-page">
        <div class="vm-topbar mb-4">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <img src="{{ \App\Support\ProjectAsset::url('public/admin/assets/images/attica_logo.png') }}" class="vm-logo" alt="Attica Pagar logo">
                    <div>
                        <h3 class="mb-1">VM Branch Attendance</h3>
                        <p class="mb-0 text-muted">Read-only attendance for assigned branches.</p>
                    </div>
                </div>
                <form method="post" action="{{ route('admin-vm-logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary">Logout</button>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="vm-stat">
                    <span>Assigned Branches</span>
                    <strong>{{ $summary['assigned_branches'] }}</strong>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="vm-stat">
                    <span>Shown Branches</span>
                    <strong>{{ $summary['visible_branches'] }}</strong>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="vm-stat">
                    <span>Attendance Rows</span>
                    <strong>{{ $summary['attendance_count'] }}</strong>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="vm-stat">
                    <span>Unique Employees</span>
                    <strong>{{ $summary['employees'] }}</strong>
                </div>
            </div>
        </div>

        <div class="vm-card mb-4">
            <form method="get" action="{{ route('admin-vm-attendance') }}" class="row g-3 align-items-end">
                <div class="col-lg-3 col-md-4">
                    <label class="form-label">Attendance Date</label>
                    <input type="date" name="date" value="{{ $filters['date'] }}" class="form-control">
                </div>
                <div class="col-lg-5 col-md-5">
                    <label class="form-label">Branch</label>
                    <select name="branch_id" class="form-select">
                        <option value="">All assigned branches</option>
                        @foreach ($assignedBranches as $branch)
                            @php($branchId = trim((string) $branch->branchId))
                            <option value="{{ $branchId }}" @selected($filters['branch_id'] === $branchId)>
                                {{ $branchId }} - {{ trim((string) $branch->branchName) ?: 'Unnamed Branch' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Apply</button>
                </div>
                <div class="col-lg-2 col-md-3">
                    <a href="{{ route('admin-vm-attendance') }}" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>

        <div class="vm-card">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-2 mb-3">
                <div>
                    <h5 class="mb-1">Attendance Details</h5>
                    <p class="mb-0 text-muted">
                        Showing {{ $summary['attendance_count'] }} row(s) for {{ \Illuminate\Support\Carbon::parse($filters['date'])->format('d M Y') }}.
                    </p>
                </div>
                <div class="text-muted">
                    Completed: {{ $summary['completed'] }} | Single Punch: {{ $summary['single_punch'] }}
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle vm-table">
                    <thead class="table-light">
                        <tr>
                            <th>Emp ID</th>
                            <th>Employee</th>
                            <th>Designation</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Check-In</th>
                            <th>Check-Out</th>
                            <th>Worked</th>
                            <th>Check-In Branch</th>
                            <th>Check-Out Branch</th>
                            <th>Check-In Location</th>
                            <th>Check-Out Location</th>
                            <th>Login Photo</th>
                            <th>Logout Photo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ $row['emp_id'] }}</td>
                                <td>
                                    <strong>{{ $row['employee_name'] }}</strong>
                                    <div class="small vm-muted">{{ $row['employee_status'] }}</div>
                                </td>
                                <td>{{ $row['designation'] }}</td>
                                <td>{{ $row['contact'] }}</td>
                                <td><span class="vm-status {{ $row['status'] }}">{{ $row['status_label'] }}</span></td>
                                <td>
                                    <strong>{{ $row['check_in_time'] }}</strong>
                                    <div class="small vm-muted">{{ $row['check_in_date'] }}</div>
                                    <div class="small vm-muted">Distance: {{ $row['check_in_distance'] }}</div>
                                </td>
                                <td>
                                    <strong>{{ $row['check_out_time'] }}</strong>
                                    <div class="small vm-muted">{{ $row['check_out_date'] }}</div>
                                    <div class="small vm-muted">Distance: {{ $row['check_out_distance'] }}</div>
                                </td>
                                <td>{{ $row['worked_time'] }}</td>
                                <td>
                                    <strong>{{ $row['check_in_branch_id'] }}</strong>
                                    <div class="small vm-muted">{{ $row['check_in_branch'] }}</div>
                                </td>
                                <td>
                                    <strong>{{ $row['check_out_branch_id'] }}</strong>
                                    <div class="small vm-muted">{{ $row['check_out_branch'] }}</div>
                                </td>
                                <td class="vm-location">
                                    @if ($row['check_in_location']['url'])
                                        <a href="{{ $row['check_in_location']['url'] }}" target="_blank" rel="noopener">{{ $row['check_in_location']['label'] }}</a>
                                    @else
                                        <span class="vm-muted">--</span>
                                    @endif
                                </td>
                                <td class="vm-location">
                                    @if ($row['check_out_location']['url'])
                                        <a href="{{ $row['check_out_location']['url'] }}" target="_blank" rel="noopener">{{ $row['check_out_location']['label'] }}</a>
                                    @else
                                        <span class="vm-muted">--</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($row['login_photo_url'])
                                        <button type="button" class="btn btn-link p-0" onclick="showVmPhoto(@js($row['login_photo_url']), 'Login Photo')">
                                            <img src="{{ $row['login_photo_url'] }}" alt="Login Photo" class="vm-photo">
                                        </button>
                                    @else
                                        <span class="vm-photo-empty">No Photo</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($row['logout_photo_url'])
                                        <button type="button" class="btn btn-link p-0" onclick="showVmPhoto(@js($row['logout_photo_url']), 'Logout Photo')">
                                            <img src="{{ $row['logout_photo_url'] }}" alt="Logout Photo" class="vm-photo">
                                        </button>
                                    @else
                                        <span class="vm-photo-empty">No Photo</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14" class="text-center py-4 text-muted">No attendance found for the selected date and assigned branches.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div class="modal fade" id="vmPhotoModal" tabindex="-1" aria-labelledby="vmPhotoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="vmPhotoModalLabel">Attendance Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="vmPhotoModalImage" src="" alt="Attendance Photo" class="img-fluid rounded">
                    <div class="mt-3">
                        <a id="vmPhotoModalLink" href="#" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">Open full size</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ \App\Support\ProjectAsset::url('public/admin/assets/js/bootstrap.bundle.min.js') }}"></script>
    <script>
        let vmPhotoModalInstance = null;

        function showVmPhoto(url, title) {
            const modalElement = document.getElementById('vmPhotoModal');
            const image = document.getElementById('vmPhotoModalImage');
            const label = document.getElementById('vmPhotoModalLabel');
            const link = document.getElementById('vmPhotoModalLink');

            if (!modalElement || !url) {
                return;
            }

            label.textContent = title || 'Attendance Photo';
            image.src = url;
            link.href = url;
            vmPhotoModalInstance = vmPhotoModalInstance || new bootstrap.Modal(modalElement);
            vmPhotoModalInstance.show();
        }
    </script>
</body>
</html>
