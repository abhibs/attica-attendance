@extends('admin.layout.app')

@section('content')
    @include('admin.attendance.partials.styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

    <style>
        .te-tracker-page .te-tracker-map {
            height: 460px;
            border-radius: 1rem;
            border: 1px solid var(--admin-border-color);
            overflow: hidden;
        }

        .te-tracker-page .te-tracker-summary-card {
            border: 1px solid var(--admin-border-color);
            border-radius: 1rem;
            background: var(--admin-surface-color);
            padding: 1rem 1.2rem;
            height: 100%;
        }

        .te-tracker-page .te-tracker-summary-card span {
            display: block;
            font-size: 0.86rem;
            color: var(--admin-muted-text-color);
            margin-bottom: 0.4rem;
        }

        .te-tracker-page .te-tracker-summary-card strong {
            font-size: 1.45rem;
            line-height: 1.1;
        }

        .te-tracker-page .te-tracker-map-popup {
            min-width: 220px;
        }

        .te-tracker-page .te-tracker-map-popup-image {
            display: block;
            width: 100%;
            max-width: 220px;
            height: 140px;
            object-fit: cover;
            border-radius: 0.8rem;
            margin-top: 0.75rem;
            border: 1px solid var(--admin-border-color);
        }

        .te-tracker-page .te-tracker-map-popup-link {
            display: inline-block;
            margin-top: 0.55rem;
            font-size: 0.82rem;
            font-weight: 600;
        }

        .te-tracker-page .te-tracker-map-popup-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin-top: 0.55rem;
        }

        .te-tracker-page .te-tracker-map-popup-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.18rem 0.55rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .te-tracker-page .te-tracker-map-popup-badge.is-start {
            background: rgba(40, 167, 69, 0.14);
            color: #1d7b33;
        }

        .te-tracker-page .te-tracker-map-popup-badge.is-end {
            background: rgba(217, 72, 65, 0.12);
            color: #d94841;
        }

        .te-tracker-page .te-tracker-map-sequence {
            width: 30px;
            height: 30px;
            border-radius: 999px;
            background: linear-gradient(135deg, var(--admin-primary-color) 0%, var(--admin-highlight-color) 100%);
            color: var(--admin-primary-contrast-color);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
            border: 2px solid #fff;
            box-shadow: 0 0.45rem 1rem rgba(var(--admin-primary-color-rgb), 0.28);
        }

        .te-tracker-page .te-tracker-route-status {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.45rem 0.7rem;
            border-radius: 999px;
            background: rgba(var(--admin-primary-color-rgb), 0.08);
            color: var(--admin-primary-color);
            font-size: 0.82rem;
            font-weight: 600;
        }

        .te-tracker-page .te-tracker-route-status.is-warning {
            background: rgba(217, 72, 65, 0.12);
            color: #d94841;
        }

        @media (max-width: 991.98px) {
            .te-tracker-page .te-tracker-map {
                height: 340px;
            }
        }
    </style>

    <div class="main-content attendance-page te-tracker-page">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Attendance</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">TE Tracker</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="card rounded-4 mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
                    <div>
                        <h4 class="mb-1 attendance-title">TE Tracker</h4>
                        <p class="mb-0 attendance-muted">
                            Review the exact branch-to-branch path travelled by a TE for a selected date.
                        </p>
                    </div>
                    <form method="get" action="{{ route('admin-attendance-te-tracker') }}"
                        class="d-flex flex-column flex-md-row gap-2">
                        <div>
                            <label class="form-label mb-1" for="teTrackerEmpId">TE Employee</label>
                            <select id="teTrackerEmpId" name="emp_id" class="form-select">
                                @forelse ($teEmployees as $employee)
                                    <option value="{{ $employee->empId }}" @selected($selectedEmpId === trim($employee->empId))>
                                        {{ $employee->empId }} - {{ $employee->name }}
                                    </option>
                                @empty
                                    <option value="">No TE employees found</option>
                                @endforelse
                            </select>
                        </div>
                        <div>
                            <label class="form-label mb-1" for="teTrackerDate">Date</label>
                            <input type="date" id="teTrackerDate" name="date" class="form-control"
                                value="{{ $selectedDate }}" max="{{ $maxSelectableDate }}">
                        </div>
                        <div class="d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary">Apply</button>
                            <a href="{{ route('admin-attendance-te-tracker') }}" class="btn btn-outline-secondary">Today</a>
                        </div>
                    </form>
                </div>

                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="te-tracker-summary-card">
                            <span>Selected TE</span>
                            <strong>{{ $selectedEmployee ? $selectedEmployee->name : 'No TE selected' }}</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="te-tracker-summary-card">
                            <span>Total Visits On {{ $selectedDateLabel }}</span>
                            <strong>{{ $summary['total_visits'] }}</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="te-tracker-summary-card">
                            <span>Unique Branches</span>
                            <strong>{{ $summary['unique_branches'] }}</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="te-tracker-summary-card">
                            <span>Total Distance Travelled</span>
                            <strong id="teTrackerTotalDistance">{{ $summary['total_distance_label'] }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-7">
                <div class="card rounded-4 mb-0">
                    <div class="card-body">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
                            <div>
                                <h5 class="mb-1 attendance-title">Route Map</h5>
                                <p class="mb-0 attendance-muted">
                                    {{ $summary['start_branch'] }} to {{ $summary['end_branch'] }} on {{ $selectedDateLabel }}.
                                </p>
                            </div>
                            @if (count($routePoints) > 0)
                                <div id="teTrackerRouteStatus" class="te-tracker-route-status">
                                    Calculating road route...
                                </div>
                            @endif
                        </div>

                        @if (count($routePoints) > 0)
                            <div id="teTrackerRouteMap" class="te-tracker-map" data-route='@json($routePoints)'></div>
                        @else
                            <div class="alert alert-warning mb-0">
                                No TE tracker visits were recorded for the selected employee and date.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="card rounded-4 mb-0 h-100">
                    <div class="card-body">
                        <h5 class="mb-1 attendance-title">Route Summary</h5>
                        <p class="mb-3 attendance-muted">
                            Route segments are calculated in sequence: 1 -> 2 -> 3 -> 4 -> 5.
                        </p>

                        @if (count($visitRows) === 0)
                            <div class="alert alert-warning mb-0">
                                No visits available for this filter.
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover" data-admin-datatable="true">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Segment</th>
                                            <th>Time</th>
                                            <th>Branch</th>
                                            <th>Segment Distance</th>
                                            <th>Cumulative</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($visitRows as $row)
                                            <tr data-route-row="{{ $row['sequence'] }}">
                                                <td>{{ $row['sequence'] }}</td>
                                                <td data-segment-label>{{ $row['segment_label'] }}</td>
                                                <td>{{ $row['visit_time'] }}</td>
                                                <td>
                                                    <div>{{ $row['branch_id'] }}</div>
                                                    <small class="text-muted">{{ $row['branch_name'] ?: 'Branch unavailable' }}</small>
                                                </td>
                                                <td data-distance-from-previous>{{ $row['distance_from_previous_label'] }}</td>
                                                <td data-cumulative-distance>{{ $row['cumulative_distance_label'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var mapElement = document.getElementById('teTrackerRouteMap');

            if (!mapElement || typeof L === 'undefined') {
                return;
            }

            var route = [];
            try {
                route = JSON.parse(mapElement.dataset.route || '[]');
            } catch (error) {
                route = [];
            }

            if (!route.length) {
                return;
            }

            var totalDistanceElement = document.getElementById('teTrackerTotalDistance');
            var routeStatusElement = document.getElementById('teTrackerRouteStatus');

            function escapeHtml(value) {
                return String(value || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function popupImageHtml(photoUrl) {
                if (!photoUrl) {
                    return '';
                }

                var escapedUrl = escapeHtml(photoUrl);

                return '<img src="' + escapedUrl + '" alt="TE visit photo" class="te-tracker-map-popup-image">' +
                    '<a href="' + escapedUrl + '" target="_blank" rel="noopener noreferrer" class="te-tracker-map-popup-link">Open image</a>';
            }

            function popupBadgesHtml(point) {
                var badges = [];

                if (point.is_start) {
                    badges.push('<span class="te-tracker-map-popup-badge is-start">Start</span>');
                }

                if (point.sequence === route.length) {
                    badges.push('<span class="te-tracker-map-popup-badge is-end">End</span>');
                }

                if (!badges.length) {
                    return '';
                }

                return '<div class="te-tracker-map-popup-badges">' + badges.join('') + '</div>';
            }

            function formatDistance(distanceMeters) {
                if (!Number.isFinite(distanceMeters) || distanceMeters <= 0) {
                    return '0 m';
                }

                if (distanceMeters >= 1000) {
                    return (distanceMeters / 1000).toFixed(2) + ' km';
                }

                return Math.round(distanceMeters) + ' m';
            }

            function haversineDistanceMeters(fromLatitude, fromLongitude, toLatitude, toLongitude) {
                var earthRadius = 6371000;
                var latitudeDelta = (toLatitude - fromLatitude) * Math.PI / 180;
                var longitudeDelta = (toLongitude - fromLongitude) * Math.PI / 180;
                var fromLatitudeRad = fromLatitude * Math.PI / 180;
                var toLatitudeRad = toLatitude * Math.PI / 180;
                var a = Math.sin(latitudeDelta / 2) * Math.sin(latitudeDelta / 2) +
                    Math.cos(fromLatitudeRad) * Math.cos(toLatitudeRad) *
                    Math.sin(longitudeDelta / 2) * Math.sin(longitudeDelta / 2);
                var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

                return earthRadius * c;
            }

            function updateRouteStatus(message, isWarning) {
                if (!routeStatusElement) {
                    return;
                }

                routeStatusElement.textContent = message;
                routeStatusElement.classList.toggle('is-warning', Boolean(isWarning));
            }

            function updateDistanceTable(segmentDistances) {
                var rows = document.querySelectorAll('[data-route-row]');
                var cumulativeDistance = 0;

                rows.forEach(function(row, index) {
                    var segmentLabelElement = row.querySelector('[data-segment-label]');
                    var distanceFromPreviousElement = row.querySelector('[data-distance-from-previous]');
                    var cumulativeDistanceElement = row.querySelector('[data-cumulative-distance]');

                    if (index === 0) {
                        if (segmentLabelElement) {
                            segmentLabelElement.textContent = '1';
                        }

                        if (distanceFromPreviousElement) {
                            distanceFromPreviousElement.textContent = 'Waiting for 1 -> 2';
                        }

                        if (cumulativeDistanceElement) {
                            cumulativeDistanceElement.textContent = '0 m';
                        }

                        return;
                    }

                    var segmentDistance = Number(segmentDistances[index - 1] || 0);
                    cumulativeDistance += segmentDistance;

                    if (segmentLabelElement) {
                        segmentLabelElement.textContent = index + ' -> ' + (index + 1);
                    }

                    if (distanceFromPreviousElement) {
                        distanceFromPreviousElement.textContent = formatDistance(segmentDistance);
                    }

                    if (cumulativeDistanceElement) {
                        cumulativeDistanceElement.textContent = formatDistance(cumulativeDistance);
                    }
                });

                if (totalDistanceElement) {
                    totalDistanceElement.textContent = formatDistance(cumulativeDistance);
                }
            }

            var map = L.map(mapElement, {
                scrollWheelZoom: true
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            var latLngs = route.map(function(point) {
                return [Number(point.latitude), Number(point.longitude)];
            });

            route.forEach(function(point) {
                var marker = L.marker([Number(point.latitude), Number(point.longitude)], {
                    icon: L.divIcon({
                        className: '',
                        html: '<span class="te-tracker-map-sequence">' + point.sequence + '</span>',
                        iconSize: [30, 30],
                        iconAnchor: [15, 15]
                    })
                }).addTo(map);

                marker.bindPopup(
                    '<div class="te-tracker-map-popup">' +
                    '<strong>' + escapeHtml(point.branch_name || point.branch_id || 'Branch') + '</strong><br>' +
                    '<span>' + escapeHtml(point.branch_id || '') + '</span><br>' +
                    '<span>Visited at ' + escapeHtml(point.visit_time || '--') + '</span>' +
                    popupBadgesHtml(point) +
                    popupImageHtml(point.photo_url || '') +
                    '</div>'
                );
            });

            var routedPolyline = null;

            function drawPolyline(polylineLatLngs) {
                if (routedPolyline) {
                    map.removeLayer(routedPolyline);
                }

                routedPolyline = L.polyline(polylineLatLngs, {
                    color: '#d94841',
                    weight: 4,
                    opacity: 0.85
                }).addTo(map);

                if (polylineLatLngs.length === 1) {
                    map.setView(polylineLatLngs[0], 14);
                } else {
                    map.fitBounds(polylineLatLngs, {
                        padding: [30, 30]
                    });
                }
            }

            if (latLngs.length === 1) {
                updateRouteStatus('Only one visit recorded for this date.', false);
                if (totalDistanceElement) {
                    totalDistanceElement.textContent = '0 m';
                }
                map.setView(latLngs[0], 14);
            } else {
                var segmentRequests = [];

                for (var index = 1; index < route.length; index += 1) {
                    (function(previousPoint, currentPoint) {
                        var previousLatitude = Number(previousPoint.latitude);
                        var previousLongitude = Number(previousPoint.longitude);
                        var currentLatitude = Number(currentPoint.latitude);
                        var currentLongitude = Number(currentPoint.longitude);
                        var osrmUrl = 'https://router.project-osrm.org/route/v1/driving/' +
                            previousLongitude + ',' + previousLatitude + ';' +
                            currentLongitude + ',' + currentLatitude +
                            '?overview=full&geometries=geojson&steps=false';

                        segmentRequests.push(
                            fetch(osrmUrl)
                                .then(function(response) {
                                    if (!response.ok) {
                                        throw new Error('Routing request failed');
                                    }

                                    return response.json();
                                })
                                .then(function(payload) {
                                    if (!payload || !Array.isArray(payload.routes) || !payload.routes.length) {
                                        throw new Error('No road route found');
                                    }

                                    var routedSegment = payload.routes[0];
                                    var geometry = Array.isArray(routedSegment.geometry && routedSegment.geometry.coordinates)
                                        ? routedSegment.geometry.coordinates.map(function(coordinate) {
                                            return [Number(coordinate[1]), Number(coordinate[0])];
                                        })
                                        : [
                                            [previousLatitude, previousLongitude],
                                            [currentLatitude, currentLongitude]
                                        ];

                                    return {
                                        distance: Number(routedSegment.distance || 0),
                                        geometry: geometry,
                                        fallback: false
                                    };
                                })
                                .catch(function() {
                                    return {
                                        distance: haversineDistanceMeters(
                                            previousLatitude,
                                            previousLongitude,
                                            currentLatitude,
                                            currentLongitude
                                        ),
                                        geometry: [
                                            [previousLatitude, previousLongitude],
                                            [currentLatitude, currentLongitude]
                                        ],
                                        fallback: true
                                    };
                                })
                        );
                    })(route[index - 1], route[index]);
                }

                Promise.all(segmentRequests).then(function(segments) {
                    var roadLatLngs = [];
                    var segmentDistances = [];
                    var usedFallback = false;

                    segments.forEach(function(segment, segmentIndex) {
                        segmentDistances.push(Number(segment.distance || 0));

                        if (segment.fallback) {
                            usedFallback = true;
                        }

                        segment.geometry.forEach(function(point, pointIndex) {
                            if (segmentIndex > 0 && pointIndex === 0) {
                                return;
                            }

                            roadLatLngs.push(point);
                        });
                    });

                    drawPolyline(roadLatLngs.length ? roadLatLngs : latLngs);
                    updateDistanceTable(segmentDistances);
                    updateRouteStatus(
                        usedFallback
                            ? 'Road route unavailable for some segments. Straight-line fallback was used there.'
                            : 'Road route calculated as ' + route.map(function(point) { return point.sequence; }).join(' -> ') + '.',
                        usedFallback
                    );
                });
            }

            window.setTimeout(function() {
                map.invalidateSize();
            }, 0);
        });
    </script>
@endsection
