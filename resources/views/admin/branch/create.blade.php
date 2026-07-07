@extends('admin.layout.app')
@section('content')
    <div class="main-content">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Admin</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Add Branch</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-12 mx-auto">
                <hr>
                <div class="card">
                    <div class="card-body">
                        <form action="{{ route('admin-branch-store') }}" method="post">
                            @csrf

                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Branch ID</label>
                                    <input class="form-control" type="text" name="branchId" value="{{ old('branchId') }}"
                                        placeholder="Enter Branch ID" required>
                                    @error('branchId')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="col-md-5">
                                    <label class="form-label">Branch Name</label>
                                    <input class="form-control" type="text" name="branchName" value="{{ old('branchName') }}"
                                        placeholder="Enter Branch Name" required>
                                    @error('branchName')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Timings</label>
                                    <input class="form-control" type="text" name="timings" value="{{ old('timings') }}"
                                        placeholder="10:00 AM - 7:00 PM">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Address Line</label>
                                    <input class="form-control" type="text" name="addressline" value="{{ old('addressline') }}"
                                        placeholder="Enter Address Line" required>
                                    @error('addressline')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Area</label>
                                    <input class="form-control" type="text" name="area" value="{{ old('area') }}"
                                        placeholder="Enter Area" required>
                                    @error('area')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Pincode</label>
                                    <input class="form-control" type="text" name="pincode" value="{{ old('pincode') }}"
                                        placeholder="Enter Pincode" required>
                                    @error('pincode')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">City</label>
                                    <input class="form-control" type="text" name="city" value="{{ old('city') }}"
                                        placeholder="Enter City" required>
                                    @error('city')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">State</label>
                                    <input class="form-control" type="text" name="state" value="{{ old('state') }}"
                                        placeholder="Enter State" required>
                                    @error('state')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Google Maps URL</label>
                                    <input class="form-control" type="text" name="url" value="{{ old('url') }}"
                                        placeholder="Enter Map URL" required>
                                    @error('url')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Latitude</label>
                                    <input class="form-control" type="text" name="latitude" value="{{ old('latitude') }}"
                                        placeholder="Enter Latitude" required>
                                    @error('latitude')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Longitude</label>
                                    <input class="form-control" type="text" name="longitude" value="{{ old('longitude') }}"
                                        placeholder="Enter Longitude" required>
                                    @error('longitude')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <button type="submit" class="btn btn-grd btn-grd-success px-5">Add Branch</button>
                                    <a href="{{ route('admin-branch-index') }}" class="btn btn-grd btn-grd-royal px-5 ms-2">Cancel</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
