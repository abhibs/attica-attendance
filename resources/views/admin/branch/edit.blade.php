@extends('admin.layout.app')
@section('content')
    <div class="main-content">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Admin</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Edit Branch</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-12 mx-auto">
                <hr>
                <div class="card">
                    <div class="card-body">
                        <form action="{{ route('admin-branch-update') }}" method="post">
                            @csrf
                            <input type="hidden" name="id" value="{{ $data->id }}">

                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Branch ID</label>
                                    <input class="form-control" type="text" name="branchId"
                                        value="{{ old('branchId', $data->branchId) }}" placeholder="Enter Branch ID" required>
                                </div>

                                <div class="col-md-5">
                                    <label class="form-label">Branch Name</label>
                                    <input class="form-control" type="text" name="branchName"
                                        value="{{ old('branchName', $data->branchName) }}" placeholder="Enter Branch Name" required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Timings</label>
                                    <input class="form-control" type="text" name="timings"
                                        value="{{ old('timings', $data->timings) }}" placeholder="10:00 AM - 7:00 PM">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Address Line</label>
                                    <input class="form-control" type="text" name="addressline"
                                        value="{{ old('addressline', $data->addressline) }}" placeholder="Enter Address Line" required>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Area</label>
                                    <input class="form-control" type="text" name="area"
                                        value="{{ old('area', $data->area) }}" placeholder="Enter Area" required>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Pincode</label>
                                    <input class="form-control" type="text" name="pincode"
                                        value="{{ old('pincode', $data->pincode) }}" placeholder="Enter Pincode" required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">City</label>
                                    <input class="form-control" type="text" name="city"
                                        value="{{ old('city', $data->city) }}" placeholder="Enter City" required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">State</label>
                                    <input class="form-control" type="text" name="state"
                                        value="{{ old('state', $data->state) }}" placeholder="Enter State" required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Google Maps URL</label>
                                    <input class="form-control" type="text" name="url"
                                        value="{{ old('url', $data->url) }}" placeholder="Enter Map URL" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Latitude</label>
                                    <input class="form-control" type="text" name="latitude"
                                        value="{{ old('latitude', $data->latitude) }}" placeholder="Enter Latitude" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Longitude</label>
                                    <input class="form-control" type="text" name="longitude"
                                        value="{{ old('longitude', $data->longitude) }}" placeholder="Enter Longitude" required>
                                </div>

                                <div class="col-12">
                                    <button type="submit" class="btn btn-grd btn-grd-success px-5">Update Branch</button>
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
