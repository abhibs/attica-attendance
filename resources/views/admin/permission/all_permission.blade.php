@extends('admin.layout.app')

@section('content')
    @php
        $permissionGroups = [
            'dashboard' => 'Dashboard',
            'branch' => 'Branch',
            'admins' => 'Admins',
            'employee' => 'Employee',
            'messenger' => 'Messenger',
            'recruitment' => 'Recruitment',
            'attendance' => 'Attendance',
            'salary' => 'Salary',
            'outsource' => 'Outsource',
            'leaves' => 'Leaves',
            'work_visit' => 'Work Visit',
            'notifications' => 'Notifications',
            'te_tracker' => 'TE Tracker',
            'reports' => 'Reports',
            'role_permission' => 'Role and Permission',
        ];
    @endphp

    <style>
        .permission-table {
            width: 100% !important;
            font-size: 0.86rem;
        }

        .permission-table th,
        .permission-table td {
            vertical-align: middle;
        }

        .permission-action {
            width: 40px;
            height: 32px;
            padding: 0;
        }
    </style>

    <div class="main-content">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Roles And Permission</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin-dashboard') }}"><i class="bx bx-home-alt"></i></a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">All Permission</li>
                    </ol>
                </nav>
            </div>
            <div class="ms-auto">
                <a href="{{ route('admin-add-permission') }}" class="btn btn-primary">Add Permission</a>
            </div>
        </div>

        @if (session('flash_success'))
            <div class="alert alert-success border-0">{{ session('flash_success') }}</div>
        @endif

        @if (session('flash_warning'))
            <div class="alert alert-warning border-0">{{ session('flash_warning') }}</div>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle permission-table" data-admin-datatable="true" data-admin-scroll-x="false">
                        <thead>
                            <tr>
                                <th>Sl No</th>
                                <th>Permission Name</th>
                                <th>Group Name</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($permissions as $permission)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $permission->name }}</td>
                                    <td>{{ $permissionGroups[$permission->group_name] ?? ($permission->group_name ?: '--') }}</td>
                                    <td>
                                        <a href="{{ route('edit.permission', $permission->id) }}" class="btn btn-outline-primary btn-sm permission-action" title="Edit Permission">
                                            <i class="material-icons-outlined">edit</i>
                                        </a>
                                        <a href="{{ route('delete.permission', $permission->id) }}" class="btn btn-outline-danger btn-sm permission-action" title="Delete Permission" onclick="return confirm('Are you sure you want to delete this permission?')">
                                            <i class="material-icons-outlined">delete</i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center">No permissions found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
