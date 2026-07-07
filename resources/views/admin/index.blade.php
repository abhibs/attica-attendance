@extends('admin.layout.app')

@section('content')
    <style>
        .admin-index-table {
            width: 100% !important;
            font-size: 0.82rem;
        }

        .admin-index-table th,
        .admin-index-table td {
            vertical-align: middle;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .admin-index-table th {
            white-space: nowrap;
        }

        .admin-image-thumb {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(var(--admin-primary-color-rgb), 0.18);
            background: rgba(var(--admin-primary-color-rgb), 0.06);
        }

        .admin-index-action {
            width: 42px;
            height: 32px;
            padding: 0;
        }
    </style>

    <div class="main-content">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Admin</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin-dashboard') }}"><i class="bx bx-home-alt"></i></a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">All Admins</li>
                    </ol>
                </nav>
            </div>
            <div class="ms-auto">
                <a href="{{ route('admin-create') }}" class="btn btn-primary">Add Admin</a>
            </div>
        </div>

        @if (session('flash_success'))
            <div class="alert alert-success border-0">{{ session('flash_success') }}</div>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle admin-index-table" data-admin-datatable="true">
                        <thead>
                            <tr>
                                @foreach ($adminColumns as $label)
                                    <th>{{ $label }}</th>
                                @endforeach
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($admins as $admin)
                                <tr>
                                    @foreach ($adminColumns as $column => $label)
                                        @php
                                            $value = $admin->{$column};
                                            $displayValue = trim((string) $value);
                                            $roleLabel = match (strtolower($displayValue)) {
                                                'hr_admin', 'hradmin', '' => 'HRManager',
                                                'md' => 'MD',
                                                'hiring' => 'Hiring',
                                                'joining' => 'Joining',
                                                'opening' => 'Opening',
                                                'accounts' => 'Accounts',
                                                default => ucwords(str_replace('_', ' ', $displayValue)),
                                            };
                                        @endphp
                                        <td data-label="{{ $label }}">
                                            @if ($column === 'role')
                                                <span class="badge bg-primary">{{ $roleLabel }}</span>
                                            @elseif ($displayValue === '')
                                                --
                                            @elseif ($column === 'image')
                                                <img src="{{ \App\Support\ProjectAsset::url('public/storage/admin/' . $displayValue) }}"
                                                    class="admin-image-thumb"
                                                    alt="{{ $admin->name ?: 'Admin' }}"
                                                    data-admin-image-fallback
                                                    data-admin-image-alt="{{ $admin->name ?: 'Admin' }}">
                                            @else
                                                {{ $displayValue }}
                                            @endif
                                        </td>
                                    @endforeach
                                    <td data-label="Action">
                                        <a href="{{ route('admin-edit', $admin->id) }}"
                                            class="btn btn-outline-primary btn-sm admin-index-action"
                                            title="Edit Admin">
                                            <i class="material-icons-outlined">edit</i>
                                        </a>
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
