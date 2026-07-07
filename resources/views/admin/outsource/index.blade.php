@extends('admin.layout.app')
@section('content')
    <style>
        .outsource-action-buttons {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .outsource-action-buttons .btn {
            width: 42px;
            height: 30px;
            padding: 0;
        }
    </style>

    <div class="main-content">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Admin</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Outsource Locations</li>
                    </ol>
                </nav>
            </div>
            <div class="ms-auto">
                <a href="{{ route('admin-outsource-create') }}" type="button" class="btn btn-primary">Add Outsource Location</a>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="example2" class="table table-bordered table-hover" data-admin-scroll-x="false">
                        <thead>
                            <tr>
                                <th>SL No</th>
                                <th>Location Code</th>
                                <th>Name</th>
                                <th>Area</th>
                                <th>City</th>
                                <th>State</th>
                                <th>Pincode</th>
                                <th>Latitude</th>
                                <th>Longitude</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($datas as $key => $item)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>{{ $item->location_code }}</td>
                                    <td>{{ $item->name }}</td>
                                    <td>{{ $item->area }}</td>
                                    <td>{{ $item->city }}</td>
                                    <td>{{ $item->state }}</td>
                                    <td>{{ $item->pincode }}</td>
                                    <td>{{ $item->latitude }}</td>
                                    <td>{{ $item->longitude }}</td>
                                    <td>
                                        @if ((int) $item->status === 1)
                                            <span class="badge bg-grd-primary">Active</span>
                                        @else
                                            <span class="badge bg-grd-danger">InActive</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="outsource-action-buttons">
                                            <a href="{{ route('admin-outsource-edit', $item->id) }}"
                                                class="btn btn-outline-primary d-inline-flex align-items-center justify-content-center"
                                                title="Edit" aria-label="Edit">
                                                <i class="fadeIn animated bx bx-pencil"></i>
                                            </a>
                                            <a href="{{ route('admin-outsource-delete', $item->id) }}"
                                                class="btn btn-outline-danger d-inline-flex align-items-center justify-content-center"
                                                title="Delete" aria-label="Delete">
                                                <i class="fadeIn animated bx bx-trash-alt"></i>
                                            </a>
                                            @if ((int) $item->status === 1)
                                                <a href="{{ route('admin-outsource-inactive', $item->id) }}"
                                                    class="btn btn-primary rounded-pill waves-effect waves-light"
                                                    title="Inactive"><i class="fa-solid fa-thumbs-down"></i></a>
                                            @else
                                                <a href="{{ route('admin-outsource-active', $item->id) }}"
                                                    class="btn btn-primary rounded-pill waves-effect waves-light"
                                                    title="Active"><i class="fa-solid fa-thumbs-up"></i></a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
