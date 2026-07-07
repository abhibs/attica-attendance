@extends('admin.layout.app')

@section('content')
    <div class="main-content">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Admin</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin-dashboard') }}"><i class="bx bx-home-alt"></i></a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin-index') }}">All Admins</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Edit Admin</li>
                    </ol>
                </nav>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger border-0">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card rounded-4">
            <div class="card-body p-4">
                <form action="{{ route('admin-update', $admin->id) }}" method="post">
                    @csrf

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" id="name"
                                value="{{ old('name', $admin->name) }}" required>
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="email"
                                value="{{ old('email', $admin->email) }}" required>
                        </div>

                        <div class="col-md-6">
                            <label for="position" class="form-label">Position</label>
                            <input type="text" class="form-control" name="position" id="position"
                                value="{{ old('position', $admin->position) }}" required>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-grd-primary px-4">Update Admin</button>
                        <a href="{{ route('admin-index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
