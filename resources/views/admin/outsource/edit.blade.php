@extends('admin.layout.app')
@section('content')
    <div class="main-content">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Admin</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Edit Outsource Location</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-12 mx-auto">
                <hr>
                <div class="card">
                    <div class="card-body">
                        <form action="{{ route('admin-outsource-update') }}" method="post">
                            @csrf
                            <input type="hidden" name="id" value="{{ $data->id }}">

                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Location Code</label>
                                    <input class="form-control" type="text" name="location_code"
                                        value="{{ old('location_code', $data->location_code) }}" placeholder="OUT001" required>
                                </div>

                                <div class="col-md-5">
                                    <label class="form-label">Location Name</label>
                                    <input class="form-control" type="text" name="name" value="{{ old('name', $data->name) }}"
                                        placeholder="Enter Location Name" required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Google Maps URL</label>
                                    <input class="form-control" type="text" name="url" value="{{ old('url', $data->url) }}"
                                        placeholder="Enter Map URL">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Address Line</label>
                                    <input class="form-control" type="text" name="addressline" value="{{ old('addressline', $data->addressline) }}"
                                        placeholder="Enter Address Line">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Area</label>
                                    <input class="form-control" type="text" name="area" value="{{ old('area', $data->area) }}"
                                        placeholder="Enter Area">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Pincode</label>
                                    <input class="form-control" type="text" name="pincode" value="{{ old('pincode', $data->pincode) }}"
                                        placeholder="Enter Pincode">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">City</label>
                                    <input class="form-control" type="text" name="city" value="{{ old('city', $data->city) }}"
                                        placeholder="Enter City">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">State</label>
                                    <input class="form-control" type="text" name="state" value="{{ old('state', $data->state) }}"
                                        placeholder="Enter State">
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Latitude</label>
                                    <input class="form-control" type="text" name="latitude" value="{{ old('latitude', $data->latitude) }}"
                                        placeholder="Latitude" required>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Longitude</label>
                                    <input class="form-control" type="text" name="longitude" value="{{ old('longitude', $data->longitude) }}"
                                        placeholder="Longitude" required>
                                </div>

                                <div class="col-12">
                                    <button type="submit" class="btn btn-grd btn-grd-success px-5">Update Location</button>
                                    <a href="{{ route('admin-outsource-index') }}" class="btn btn-grd btn-grd-royal px-5 ms-2">Cancel</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
