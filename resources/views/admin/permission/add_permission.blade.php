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

    <div class="main-content">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Roles And Permission</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin-dashboard') }}"><i class="bx bx-home-alt"></i></a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin-all-permission') }}">All Permission</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Add Permission</li>
                    </ol>
                </nav>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger border-0">
                Please check the form and try again.
            </div>
        @endif

        <div class="card">
            <div class="card-body">
                <form id="myForm" method="post" action="{{ route('permission.store') }}">
                    @csrf

                    <div class="row">
                        <div class="form-group col-md-6 mb-3">
                            <label for="permission_name" class="form-label">Permission Name</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                id="permission_name" value="{{ old('name') }}" placeholder="Add Permission">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-6 mb-3">
                            <label for="group_name" class="form-label">Group Name</label>
                            <select name="group_name" class="form-select @error('group_name') is-invalid @enderror" id="group_name">
                                <option value="">Select Sidebar Option</option>
                                @foreach ($permissionGroups as $value => $label)
                                    <option value="{{ $value }}" @selected(old('group_name') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('group_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary waves-effect waves-light">Save Changes</button>
                        <a href="{{ route('admin-all-permission') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
