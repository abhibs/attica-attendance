@extends('admin.layout.app')
@section('content')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

    <style>
        .branch-action-buttons {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .branch-action-buttons .btn {
            width: 42px;
            height: 30px;
            padding: 0;
        }

        .branch-url-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border: 1px solid #0d6efd;
            border-radius: 50%;
            color: #0d6efd;
            text-decoration: none;
        }

        .branch-url-link:hover {
            background-color: #0d6efd;
            color: #fff;
        }

        .branch-map-card,
        .branch-summary-card {
            border: 1px solid var(--admin-border-color);
            border-radius: 1rem;
            background: var(--admin-surface-color);
        }

        .branch-summary-card {
            padding: 1rem 1.25rem;
            height: 100%;
        }

        .branch-summary-card span {
            display: block;
            font-size: 0.85rem;
            color: var(--admin-muted-text-color);
            margin-bottom: 0.35rem;
        }

        .branch-summary-card strong {
            font-size: 1.6rem;
            line-height: 1;
        }

        .branch-map-canvas {
            height: 460px;
            width: 100%;
            border-radius: 1rem;
            overflow: hidden;
            border: 1px solid var(--admin-border-color);
        }

        .branch-map-canvas .leaflet-tile,
        .branch-map-canvas .leaflet-marker-icon,
        .branch-map-canvas .leaflet-marker-shadow {
            max-width: none !important;
            max-height: none !important;
        }

        .branch-map-popup {
            min-width: 220px;
        }

        .branch-map-popup h6 {
            margin-bottom: 0.25rem;
            font-size: 1rem;
        }

        .branch-map-popup p {
            margin-bottom: 0.5rem;
            font-size: 0.88rem;
            color: #5f6368;
        }

        .branch-map-popup .branch-map-popup-meta {
            display: grid;
            gap: 0.35rem;
            font-size: 0.86rem;
        }

        .branch-map-popup .branch-map-popup-region {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.5rem;
            margin: 0.75rem 0;
        }

        .branch-map-popup .branch-map-popup-region-item {
            padding: 0.55rem 0.7rem;
            border: 1px solid rgba(var(--admin-primary-color-rgb), 0.12);
            border-radius: 0.75rem;
            background: rgba(var(--admin-primary-color-rgb), 0.05);
        }

        .branch-map-popup .branch-map-popup-region-item span {
            display: block;
            margin-bottom: 0.15rem;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            color: var(--admin-muted-text-color);
        }

        .branch-map-popup .branch-map-popup-region-item strong {
            display: block;
            font-size: 0.9rem;
            line-height: 1.3;
            color: var(--admin-text-color);
        }

        .branch-map-popup .branch-map-popup-count {
            margin-top: 0.75rem;
            padding: 0.65rem 0.8rem;
            border-radius: 0.8rem;
            background: rgba(var(--admin-primary-color-rgb), 0.08);
            font-weight: 600;
        }

        .branch-map-filter-form .form-control {
            min-width: 200px;
        }

        .branch-region-card {
            border: 1px solid var(--admin-border-color);
            border-radius: 1rem;
            background: var(--admin-surface-color);
            padding: 1.25rem;
        }

        .branch-region-selector {
            min-width: 240px;
        }

        .branch-region-card .branch-summary-card {
            padding: 0.9rem 1rem;
        }

        .branch-region-description {
            margin-bottom: 1rem;
            color: var(--admin-muted-text-color);
        }

        .branch-region-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
        }

        .branch-region-pill {
            display: inline-flex;
            flex-direction: column;
            gap: 0.1rem;
            padding: 0.7rem 0.85rem;
            border-radius: 0.9rem;
            border: 1px solid rgba(var(--admin-primary-color-rgb), 0.12);
            background: rgba(var(--admin-primary-color-rgb), 0.05);
        }

        .branch-region-pill strong {
            font-size: 0.9rem;
            line-height: 1.3;
        }

        .branch-region-pill span {
            font-size: 0.78rem;
            color: var(--admin-muted-text-color);
        }

        .branch-region-empty {
            padding: 1rem;
            border: 1px dashed var(--admin-border-color);
            border-radius: 0.9rem;
            color: var(--admin-muted-text-color);
        }

        @media (max-width: 991.98px) {
            .branch-map-canvas {
                height: 360px;
            }
        }
    </style>

    <div class="main-content">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Admin</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">All Branches</li>
                    </ol>
                </nav>
            </div>
            <div class="ms-auto">
                <a href="{{ route('admin-branch-create') }}" type="button" class="btn btn-primary">Add Branch</a>
            </div>
        </div>

        <div class="card branch-map-card mt-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-3 mb-4">
                    <div>
                        <h4 class="mb-1">Branch Map</h4>
                        <p class="mb-0 text-muted">
                            Click any {{ strtolower($selectedStatusLabel) }} branch pin to view the branch details and
                            distinct employee check-ins for {{ $selectedDateLabel }}.
                        </p>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="branch-summary-card">
                            <span>{{ $selectedStatusLabel }} Branches</span>
                            <strong>{{ count($datas) }}</strong>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="branch-summary-card">
                            <span>{{ $selectedStatusLabel }} Branches Pinned On Map</span>
                            <strong>{{ $mappedBranchCount }}</strong>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="branch-summary-card">
                            <span>Distinct Employee Check-Ins On {{ $selectedDateLabel }}</span>
                            <strong>{{ $selectedDateLoginTotal }}</strong>
                        </div>
                    </div>
                </div>

                @if ($mappedBranchCount > 0)
                    <div id="branchMap" class="branch-map-canvas" data-branches='@json($mapBranches)'
                        data-selected-date="{{ $selectedDateLabel }}"></div>
                    <div class="form-text mt-2">
                        Branches without valid latitude and longitude are skipped from the map but still shown in the
                        table above.
                    </div>
                @else
                    <div class="alert alert-warning mb-0">
                        No branches currently have valid latitude and longitude coordinates, so the map cannot be shown.
                    </div>
                @endif

                @if (!empty($regionSummaries))
                    <div class="branch-region-card mt-4" id="branchRegionSummary"
                        data-regions='@json($regionSummaries)'>
                        <div class="d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-3 mb-3">
                            <div>
                                <h5 class="mb-1">City / State Coverage</h5>
                                <p class="mb-0 text-muted">
                                    Select a metro or state bucket. State buckets exclude the main metro city as
                                    requested.
                                </p>
                            </div>
                            <div class="branch-region-selector">
                                <label for="branchRegionSelect" class="form-label mb-1">Region Bucket</label>
                                <select id="branchRegionSelect" class="form-select">
                                    <option value="__all__" selected>All locations</option>
                                    @foreach ($regionSummaries as $regionSummary)
                                        <option value="{{ $regionSummary['id'] }}">{{ $regionSummary['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <p class="branch-region-description" id="branchRegionDescription"></p>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <div class="branch-summary-card">
                                    <span>Branches In Selected Bucket</span>
                                    <strong id="branchRegionBranchCount">0</strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="branch-summary-card">
                                    <span>Mapped Branches In Selected Bucket</span>
                                    <strong id="branchRegionMappedCount">0</strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="branch-summary-card">
                                    <span>Check-Ins On {{ $selectedDateLabel }}</span>
                                    <strong id="branchRegionCheckins">0</strong>
                                </div>
                            </div>
                        </div>

                        <div id="branchRegionList" class="branch-region-list"></div>
                    </div>
                @endif
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-3 mb-4">
                    <div>
                        <h4 class="mb-1">All Branches</h4>
                        <p class="mb-0 text-muted">
                            Review {{ strtolower($selectedStatusLabel) }} branch records and employee check-ins for
                            {{ $selectedDateLabel }}.
                        </p>
                    </div>
                    <form method="get" action="{{ route('admin-branch-index') }}"
                        class="branch-map-filter-form d-flex flex-column flex-sm-row gap-2">
                        <div>
                            <label for="branchStatusFilter" class="form-label mb-1">Branch Status</label>
                            <select id="branchStatusFilter" name="status" class="form-select">
                                <option value="active" @selected($selectedStatus === 'active')>Active</option>
                                <option value="inactive" @selected($selectedStatus === 'inactive')>Inactive</option>
                                <option value="all" @selected($selectedStatus === 'all')>All</option>
                            </select>
                        </div>
                        <div>
                            <label for="branchMapDate" class="form-label mb-1">Selected Date</label>
                            <input type="date" id="branchMapDate" name="date" class="form-control"
                                value="{{ $selectedDate }}" max="{{ $maxSelectableDate }}">
                        </div>
                        <div class="d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary">Apply</button>
                            <a href="{{ route('admin-branch-index') }}" class="btn btn-outline-secondary">Today</a>
                        </div>
                    </form>
                </div>

                <div class="table-responsive">
                    <table id="example2" class="table table-bordered table-hover" data-admin-scroll-x="false">
                        <thead>
                            <tr>
                                <th>SL No</th>
                                <th>Branch Id</th>
                                <th>Branch Name</th>
                                <th>Area</th>
                                <th>City</th>
                                <th>State</th>
                                <th>Pincode</th>
                                <th>Check-Ins ({{ $selectedDateLabel }})</th>
                                <th>Timings</th>
                                <th>Latitude</th>
                                <th>Longitude</th>
                                <th>URL</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($datas as $key => $item)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>{{ $item->branchId }}</td>
                                    <td>{{ $item->branchName }}</td>
                                    <td>{{ $item->area }}</td>
                                    <td>{{ $item->city }}</td>
                                    <td>{{ $item->state }}</td>
                                    <td>{{ $item->pincode }}</td>
                                    <td>{{ $item->selected_date_logins ?? 0 }}</td>
                                    <td>{{ $item->timings }}</td>
                                    <td>{{ $item->latitude }}</td>
                                    <td>{{ $item->longitude }}</td>
                                    <td class="text-center">
                                        @if (! empty($item->url))
                                            <a href="{{ $item->url }}" target="_blank" rel="noopener noreferrer"
                                                class="branch-url-link" title="Open branch location"
                                                aria-label="Open branch location">
                                                <i class="bx bx-link-external"></i>
                                            </a>
                                        @else
                                            --
                                        @endif
                                    </td>
                                    <td>
                                        @if ($item->status == 1)
                                            <span class="badge bg-grd-primary">Active</span>
                                        @else
                                            <span class="badge bg-grd-danger">InActive</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="branch-action-buttons">
                                            <a href="{{ route('admin-branch-edit', $item->id) }}"
                                                class="btn btn-outline-primary d-inline-flex align-items-center justify-content-center"
                                                title="Edit" aria-label="Edit">
                                                <i class="fadeIn animated bx bx-pencil"></i>
                                            </a>
                                            <a href="{{ route('admin-branch-delete', $item->id) }}"
                                                class="btn btn-outline-danger d-inline-flex align-items-center justify-content-center"
                                                id="delete" title="Delete" aria-label="Delete">
                                                <i class="fadeIn animated bx bx-trash-alt"></i>
                                            </a>

                                            @if ($item->status == 1)
                                                <a href="{{ route('admin-branch-inactive', $item->id) }}"
                                                    class="btn btn-primary rounded-pill waves-effect waves-light"
                                                    title="Inactive"><i class="fa-solid fa-thumbs-down"></i></a>
                                            @else
                                                <a href="{{ route('admin-branch-active', $item->id) }}"
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

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var mapElement = document.getElementById('branchMap');
            var selectedDateLabel = mapElement ? (mapElement.dataset.selectedDate || '') : '';
            var branches = [];
            var map = null;
            var markerBoundsByBranchId = {};
            var markersByBranchId = {};
            var markerLayer = null;
            var allBranchBounds = [];

            if (mapElement) {
                try {
                    branches = JSON.parse(mapElement.dataset.branches || '[]');
                } catch (error) {
                    branches = [];
                }
            }

            function escapeHtml(value) {
                return String(value || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            if (mapElement && typeof L !== 'undefined') {
                var validBranches = branches.filter(function(branch) {
                    return Number.isFinite(Number(branch.latitude)) && Number.isFinite(Number(branch.longitude));
                });

                if (validBranches.length) {
                    map = L.map(mapElement, {
                        scrollWheelZoom: true
                    });
                    markerLayer = L.layerGroup().addTo(map);

                    map.createPane('adminBoundariesLabels');
                    map.getPane('adminBoundariesLabels').style.zIndex = 650;
                    map.getPane('adminBoundariesLabels').style.pointerEvents = 'none';

                    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png', {
                        subdomains: 'abcd',
                        maxZoom: 20,
                        attribution: '&copy; OpenStreetMap contributors &copy; CARTO'
                    }).addTo(map);

                    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_only_labels/{z}/{x}/{y}{r}.png', {
                        subdomains: 'abcd',
                        maxZoom: 19,
                        pane: 'adminBoundariesLabels',
                        attribution: '&copy; OpenStreetMap contributors &copy; CARTO'
                    }).addTo(map);

                    validBranches.forEach(function(branch) {
                        var latitude = Number(branch.latitude);
                        var longitude = Number(branch.longitude);
                        var marker = L.marker([latitude, longitude]);
                        var branchId = String(branch.branch_id || '');
                        var popupHtml = [
                            '<div class="branch-map-popup">',
                            '<h6>' + escapeHtml(branch.branch_name || branch.branch_id || 'Branch') + '</h6>',
                            '<p>' + escapeHtml(branch.branch_id || '') + ' | ' + escapeHtml(branch.status_label || '') + '</p>',
                            '<div class="branch-map-popup-region">',
                            '<div class="branch-map-popup-region-item"><span>City</span><strong>' + escapeHtml(branch.city || '--') +
                            '</strong></div>',
                            '<div class="branch-map-popup-region-item"><span>State</span><strong>' + escapeHtml(branch.state || '--') +
                            '</strong></div>',
                            '</div>',
                            '<div class="branch-map-popup-meta">',
                            '<div><strong>Location:</strong> ' + escapeHtml(branch.address || 'Address not available') + '</div>',
                            '<div><strong>Area:</strong> ' + escapeHtml(branch.area || '--') + '</div>',
                            '<div><strong>Pincode:</strong> ' + escapeHtml(branch.pincode || '--') + '</div>',
                            '<div><strong>Timings:</strong> ' + escapeHtml(branch.timings || '--') + '</div>',
                            '<div><strong>Coordinates:</strong> ' + escapeHtml(latitude.toFixed(6)) + ', ' + escapeHtml(longitude
                                .toFixed(6)) + '</div>',
                            branch.url ?
                            '<div><a href="' + escapeHtml(branch.url) + '" target="_blank" rel="noopener noreferrer">Open map</a></div>' :
                            '',
                            '</div>',
                            '<div class="branch-map-popup-count">Employees checked in on ' + escapeHtml(selectedDateLabel) + ': ' +
                            escapeHtml(branch.logged_in_count) + '</div>',
                            '</div>'
                        ].join('');

                        marker.bindPopup(popupHtml, {
                            maxWidth: 320
                        });

                        allBranchBounds.push([latitude, longitude]);
                        markerBoundsByBranchId[branchId] = [latitude, longitude];
                        markersByBranchId[branchId] = marker;
                        markerLayer.addLayer(marker);
                    });

                    if (allBranchBounds.length === 1) {
                        map.setView(allBranchBounds[0], 14);
                    } else {
                        map.fitBounds(allBranchBounds, {
                            padding: [30, 30]
                        });
                    }

                    window.setTimeout(function() {
                        map.invalidateSize();
                    }, 0);
                }
            }

            var regionSummaryElement = document.getElementById('branchRegionSummary');

            if (!regionSummaryElement) {
                return;
            }

            var regionSelect = document.getElementById('branchRegionSelect');
            var regionDescription = document.getElementById('branchRegionDescription');
            var regionBranchCount = document.getElementById('branchRegionBranchCount');
            var regionMappedCount = document.getElementById('branchRegionMappedCount');
            var regionCheckins = document.getElementById('branchRegionCheckins');
            var regionList = document.getElementById('branchRegionList');
            var regions = [];

            try {
                regions = JSON.parse(regionSummaryElement.dataset.regions || '[]');
            } catch (error) {
                regions = [];
            }

            if (!regionSelect || !regions.length) {
                return;
            }

            function showAllMapMarkers() {
                if (!map || !markerLayer) {
                    return;
                }

                markerLayer.clearLayers();

                Object.keys(markersByBranchId).forEach(function(branchId) {
                    markerLayer.addLayer(markersByBranchId[branchId]);
                });

                if (!allBranchBounds.length) {
                    return;
                }

                if (allBranchBounds.length === 1) {
                    map.setView(allBranchBounds[0], 14);
                    return;
                }

                map.fitBounds(allBranchBounds, {
                    padding: [30, 30]
                });
            }

            function renderRegion(regionId) {
                if (regionId === '__all__') {
                    regionSelect.value = '__all__';

                    if (regionDescription) {
                        regionDescription.textContent =
                            'Showing every mapped branch location for the current status and date filters.';
                    }

                    if (regionBranchCount) {
                        regionBranchCount.textContent = String(branches.length || 0);
                    }

                    if (regionMappedCount) {
                        regionMappedCount.textContent = String(allBranchBounds.length || 0);
                    }

                    if (regionCheckins) {
                        regionCheckins.textContent = String({{ (int) $selectedDateLoginTotal }});
                    }

                    if (regionList) {
                        var allPreview = branches.slice(0, 24);

                        regionList.innerHTML = allPreview.length ? allPreview.map(function(branch) {
                            return '<div class=\"branch-region-pill\">' +
                                '<strong>' + escapeHtml(branch.branch_id || '--') + '</strong>' +
                                '<span>' + escapeHtml(branch.branch_name || 'Branch') +
                                ((branch.city || '') ? ' | ' + escapeHtml(branch.city) : '') + '</span>' +
                                '</div>';
                        }).join('') : '<div class=\"branch-region-empty\">No branches found for the current status filter.</div>';
                    }

                    showAllMapMarkers();
                    return;
                }

                var region = regions.find(function(item) {
                    return item.id === regionId;
                }) || regions[0];

                if (!region) {
                    return;
                }

                regionSelect.value = region.id;

                if (regionDescription) {
                    regionDescription.textContent = region.description || '';
                }

                if (regionBranchCount) {
                    regionBranchCount.textContent = String(region.branch_count || 0);
                }

                if (regionMappedCount) {
                    regionMappedCount.textContent = String(region.mapped_branch_count || 0);
                }

                if (regionCheckins) {
                    regionCheckins.textContent = String(region.selected_date_checkins || 0);
                }

                if (regionList) {
                    var preview = Array.isArray(region.branch_preview) ? region.branch_preview : [];

                    regionList.innerHTML = preview.length ? preview.map(function(branch) {
                        return '<div class=\"branch-region-pill\">' +
                            '<strong>' + escapeHtml(branch.branch_id || '--') + '</strong>' +
                            '<span>' + escapeHtml(branch.branch_name || 'Branch') +
                            ((branch.city || '') ? ' | ' + escapeHtml(branch.city) : '') + '</span>' +
                            '</div>';
                    }).join('') : '<div class=\"branch-region-empty\">No branches found in this bucket for the current status filter.</div>';
                }

                var regionBounds = (Array.isArray(region.branch_ids) ? region.branch_ids : []).map(function(branchId) {
                    return markerBoundsByBranchId[String(branchId || '')] || null;
                }).filter(function(item) {
                    return Array.isArray(item);
                });

                if (!regionBounds.length || !map) {
                    return;
                }

                if (markerLayer) {
                    markerLayer.clearLayers();

                    (Array.isArray(region.branch_ids) ? region.branch_ids : []).forEach(function(branchId) {
                        var marker = markersByBranchId[String(branchId || '')];

                        if (marker) {
                            markerLayer.addLayer(marker);
                        }
                    });
                }

                if (regionBounds.length === 1) {
                    map.setView(regionBounds[0], 13);
                    return;
                }

                map.fitBounds(regionBounds, {
                    padding: [30, 30]
                });
            }

            regionSelect.addEventListener('change', function() {
                renderRegion(regionSelect.value);
            });

            renderRegion(regionSelect.value);
        });
    </script>
@endsection
