@extends('admin.layout.app')
@section('content')
    <div class="main-content">


        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Admin</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Change Password</li>
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
                        <form action="{{ route('admin-password-update') }}" method="post">
                            @csrf


                            @if (session('status'))
                                <div class="alert alert-success" role="alert">
                                    {{ session('status') }}
                                </div>
                            @elseif(session('error'))
                                <div class="alert alert-danger" role="alert">
                                    {{ session('error') }}
                                </div>
                            @endif

                            <div class="mb-3">
                                <label for="formName" class="form-label">Old Password</label>
                                <input class="form-control" type="password" name="old_password"
                                    placeholder="Enter Old Password" aria-label="default input example">
                                @error('old_password')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>


                            <div class="mb-3">
                                <label for="formName" class="form-label">New Password</label>
                                <input class="form-control" type="password" name="new_password"
                                    placeholder="Enter New Password" aria-label="default input example">
                                @error('new_password')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>


                            <div class="mb-3">
                                <label for="formName" class="form-label">Confirm Password</label>
                                <input class="form-control" type="password" name="new_password_confirmation"
                                    placeholder="Enter New Confirmation Password" aria-label="default input example">
                                @error('new_password_confirmation')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>


                            <div class="mb-3">
                                <button type="submit" class="btn btn-grd btn-grd-success px-5">Change Password</button>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!--end row-->
    </div>
@endsection