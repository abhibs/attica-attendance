<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\TeTrackerVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;

class TeTrackerController extends Controller
{
    private const TRACKER_RADIUS_METERS = 5000000.0;

    public function branches(Request $request): JsonResponse
    {
        $employee = $request->user();

        abort_unless($employee instanceof Employee, 401);

        if ($guard = $this->ensureTeEmployee($employee)) {
            return $guard;
        }

        $branches = Branch::query()
            ->where('status', 1)
            ->orderBy('branchId')
            ->get()
            ->map(function (Branch $branch): array {
                $latitude = $this->parseCoordinate($branch->latitude);
                $longitude = $this->parseCoordinate($branch->longitude);

                return [
                    'branchId' => $this->clean($branch->branchId),
                    'branchName' => $this->clean($branch->branchName),
                    'address' => collect([
                        $this->clean($branch->addressline),
                        $this->clean($branch->area),
                        $this->clean($branch->city),
                        $this->clean($branch->state),
                        $this->clean($branch->pincode),
                    ])->filter()->implode(', '),
                    'city' => $this->clean($branch->city),
                    'state' => $this->clean($branch->state),
                    'timings' => $this->clean($branch->timings),
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'mapUrl' => $this->mapUrl($branch->url, $latitude, $longitude),
                ];
            })
            ->values();

        return response()->json([
            'branches' => $branches,
        ]);
    }

    public function visits(Request $request): JsonResponse
    {
        $employee = $request->user();

        abort_unless($employee instanceof Employee, 401);

        if ($guard = $this->ensureTeEmployee($employee)) {
            return $guard;
        }

        $date = $this->resolveSelectedDate($request->query('date'));
        $visits = $this->visitCollection($employee, $date);
        $journey = $this->buildJourneyPayload($visits);

        return response()->json([
            'date' => $date,
            ...$journey,
        ]);
    }

    public function checkIn(Request $request): JsonResponse
    {
        $employee = $request->user();

        abort_unless($employee instanceof Employee, 401);

        if ($guard = $this->ensureTeEmployee($employee)) {
            return $guard;
        }

        $data = $request->validate([
            'branch_id' => ['required', 'string'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'photo' => ['required', 'image', 'max:5120'],
        ]);

        $branch = $this->findActiveBranch($data['branch_id']);

        if (! $branch) {
            return response()->json([
                'message' => 'Selected branch could not be found.',
            ], 422);
        }

        $branchLatitude = $this->parseCoordinate($branch->latitude);
        $branchLongitude = $this->parseCoordinate($branch->longitude);

        if ($branchLatitude === null || $branchLongitude === null) {
            return response()->json([
                'message' => 'Selected branch location is not configured.',
            ], 422);
        }

        $capturedLatitude = (float) $data['latitude'];
        $capturedLongitude = (float) $data['longitude'];
        $distanceFromBranch = round(
            $this->calculateDistanceMeters(
                $capturedLatitude,
                $capturedLongitude,
                $branchLatitude,
                $branchLongitude
            ),
            2
        );

        if ($distanceFromBranch > self::TRACKER_RADIUS_METERS) {
            return response()->json([
                'message' => sprintf(
                    'You are %s away from %s. TE tracking is allowed only within %s.',
                    $this->formatDistance($distanceFromBranch),
                    $this->clean((string) ($branch->branchName ?: $branch->branchId)),
                    $this->formatDistance(self::TRACKER_RADIUS_METERS)
                ),
                'distanceMeters' => $distanceFromBranch,
                'allowedRadiusMeters' => self::TRACKER_RADIUS_METERS,
            ], 422);
        }

        $timezone = $this->appTimezone();
        $now = Carbon::now($timezone);
        $photoPath = $this->storeTrackerPhoto(
            $request->file('photo'),
            $employee,
            $this->clean($branch->branchId)
        );

        $visit = TeTrackerVisit::query()->create([
            'employee_id' => $employee->id,
            'emp_id' => $this->clean($employee->empId),
            'branch_id' => $this->clean($branch->branchId),
            'branch_name' => $this->clean($branch->branchName),
            'branch_latitude' => $branchLatitude,
            'branch_longitude' => $branchLongitude,
            'captured_latitude' => $capturedLatitude,
            'captured_longitude' => $capturedLongitude,
            'distance_from_branch' => $distanceFromBranch,
            'photo_path' => $photoPath,
            'visit_date' => $now->toDateString(),
            'visit_time' => $now->format('H:i:s'),
        ]);

        $journey = $this->buildJourneyPayload(
            $this->visitCollection($employee, $now->toDateString())
        );

        return response()->json([
            'message' => 'TE tracker visit recorded successfully.',
            'visit' => $this->visitPayload($visit),
            'date' => $now->toDateString(),
            ...$journey,
        ], 201);
    }

    private function visitCollection(Employee $employee, string $date): Collection
    {
        return TeTrackerVisit::query()
            ->where('employee_id', $employee->id)
            ->where('visit_date', $date)
            ->orderBy('visit_time')
            ->orderBy('id')
            ->get();
    }

    private function buildJourneyPayload(Collection $visits): array
    {
        $orderedVisits = $visits->values();
        $routePoints = [];
        $visitPayloads = [];
        $previousPoint = null;
        $cumulativeDistance = 0.0;

        foreach ($orderedVisits as $index => $visit) {
            if (! $visit instanceof TeTrackerVisit) {
                continue;
            }

            $branchLatitude = $visit->branch_latitude;
            $branchLongitude = $visit->branch_longitude;
            $point = $branchLatitude !== null && $branchLongitude !== null
                ? [
                    'latitude' => (float) $branchLatitude,
                    'longitude' => (float) $branchLongitude,
                    'branchId' => $this->clean($visit->branch_id),
                    'branchName' => $this->clean($visit->branch_name),
                    'visitTime' => $this->formatTime($visit->visit_time),
                    'sequence' => $index + 1,
                ]
                : null;

            $distanceFromPrevious = null;
            if ($previousPoint && $point) {
                $distanceFromPrevious = round(
                    $this->calculateDistanceMeters(
                        $previousPoint['latitude'],
                        $previousPoint['longitude'],
                        $point['latitude'],
                        $point['longitude']
                    ),
                    2
                );
                $cumulativeDistance += $distanceFromPrevious;
            }

            if ($point) {
                $routePoints[] = $point;
                $previousPoint = $point;
            }

            $visitPayloads[] = [
                ...$this->visitPayload($visit),
                'sequence' => $index + 1,
                'distanceFromPreviousMeters' => $distanceFromPrevious,
                'distanceFromPreviousLabel' => $distanceFromPrevious === null
                    ? 'Start'
                    : $this->formatDistance($distanceFromPrevious),
                'cumulativeDistanceMeters' => round($cumulativeDistance, 2),
                'cumulativeDistanceLabel' => $this->formatDistance($cumulativeDistance),
            ];
        }

        /** @var TeTrackerVisit|null $firstVisit */
        $firstVisit = $orderedVisits->first();
        /** @var TeTrackerVisit|null $lastVisit */
        $lastVisit = $orderedVisits->last();

        return [
            'visits' => $visitPayloads,
            'routePoints' => $routePoints,
            'summary' => [
                'totalVisits' => count($visitPayloads),
                'uniqueBranches' => $orderedVisits
                    ->map(fn (TeTrackerVisit $visit): string => $this->clean($visit->branch_id))
                    ->filter()
                    ->unique()
                    ->count(),
                'totalDistanceMeters' => round($cumulativeDistance, 2),
                'totalDistanceLabel' => $this->formatDistance($cumulativeDistance),
                'startBranchLabel' => $firstVisit
                    ? $this->visitBranchLabel($firstVisit)
                    : 'No visits',
                'endBranchLabel' => $lastVisit
                    ? $this->visitBranchLabel($lastVisit)
                    : 'No visits',
            ],
        ];
    }

    private function visitPayload(TeTrackerVisit $visit): array
    {
        return [
            'id' => $visit->id,
            'branchId' => $this->clean($visit->branch_id),
            'branchName' => $this->clean($visit->branch_name),
            'visitDate' => $visit->visit_date?->toDateString() ?? '',
            'visitTime' => $this->formatTime($visit->visit_time),
            'photoUrl' => $this->resolvePhotoUrl('public/'.$visit->photo_path),
            'capturedLatitude' => $visit->captured_latitude,
            'capturedLongitude' => $visit->captured_longitude,
            'branchLatitude' => $visit->branch_latitude,
            'branchLongitude' => $visit->branch_longitude,
            'distanceFromBranchMeters' => $visit->distance_from_branch,
            'distanceFromBranchLabel' => $visit->distance_from_branch === null
                ? '--'
                : $this->formatDistance((float) $visit->distance_from_branch),
        ];
    }

    private function visitBranchLabel(TeTrackerVisit $visit): string
    {
        $branchId = $this->clean($visit->branch_id);
        $branchName = $this->clean($visit->branch_name);

        return trim($branchId.($branchName !== '' ? ' - '.$branchName : ''));
    }

    private function ensureTeEmployee(Employee $employee): ?JsonResponse
    {
        return $this->isTeEmployee($employee)
            ? null
            : response()->json([
                'message' => 'TE Tracker is available only for TE employees.',
            ], 403);
    }

    private function isTeEmployee(Employee $employee): bool
    {
        return strtoupper($this->clean($employee->designation)) === 'TE';
    }

    private function findActiveBranch(?string $branchId): ?Branch
    {
        $branchId = $this->clean($branchId);

        if ($branchId === '') {
            return null;
        }

        return Branch::query()
            ->where('status', 1)
            ->whereRaw('TRIM(branchId) = ?', [$branchId])
            ->first();
    }

    private function resolveSelectedDate(?string $value): string
    {
        $date = trim((string) $value);

        if ($date === '') {
            return Carbon::now($this->appTimezone())->toDateString();
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $date, $this->appTimezone())->toDateString();
        } catch (\Throwable $exception) {
            return Carbon::now($this->appTimezone())->toDateString();
        }
    }

    private function storeTrackerPhoto($image, Employee $employee, string $branchId): string
    {
        $directory = public_path('storage/TeTrackerImage');

        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0777, true);
        }

        $extension = strtolower($image->getClientOriginalExtension() ?: 'jpg');
        $safeBranchId = preg_replace('/[^A-Za-z0-9_-]+/', '-', $this->clean($branchId));
        $empId = preg_replace('/[^A-Za-z0-9_-]+/', '-', $this->clean($employee->empId));
        $filename = sprintf(
            'te_tracker_%s_%s_%s_%s.%s',
            $safeBranchId ?: 'branch',
            $empId ?: 'employee',
            Carbon::now($this->appTimezone())->format('YmdHis'),
            bin2hex(random_bytes(4)),
            $extension
        );

        Image::make($image)
            ->orientate()
            ->resize(512, 512)
            ->save($directory.'/'.$filename);

        return 'storage/TeTrackerImage/'.$filename;
    }

    private function resolvePhotoUrl(?string $path): string
    {
        $trimmed = trim((string) $path);

        if ($trimmed === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $trimmed) === 1) {
            return $trimmed;
        }

        $normalizedPath = ltrim($trimmed, '/');


        return asset($normalizedPath);
    }

    private function mapUrl(?string $existingUrl, ?float $latitude, ?float $longitude): string
    {
        $url = $this->clean($existingUrl);

        if ($url !== '') {
            return $url;
        }

        if ($latitude === null || $longitude === null) {
            return '';
        }

        return 'https://www.google.com/maps?q='.urlencode($latitude.','.$longitude);
    }

    private function calculateDistanceMeters(
        float $fromLatitude,
        float $fromLongitude,
        float $toLatitude,
        float $toLongitude
    ): float {
        $earthRadius = 6371000.0;
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);
        $fromLatitudeRad = deg2rad($fromLatitude);
        $toLatitudeRad = deg2rad($toLatitude);

        $a = sin($latitudeDelta / 2) ** 2 +
            cos($fromLatitudeRad) * cos($toLatitudeRad) *
            sin($longitudeDelta / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function formatDistance(float $distanceMeters): string
    {
        if ($distanceMeters >= 1000) {
            return number_format($distanceMeters / 1000, 2).' km';
        }

        return round($distanceMeters).' m';
    }

    private function formatTime(?string $value): string
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return '--';
        }

        try {
            return Carbon::parse($trimmed)->format('h:i A');
        } catch (\Throwable $exception) {
            return $trimmed;
        }
    }

    private function parseCoordinate($value): ?float
    {
        $coordinate = trim((string) $value);

        if ($coordinate === '') {
            return null;
        }

        if (is_numeric($coordinate)) {
            return (float) $coordinate;
        }

        $normalized = str_replace(',', '.', strtoupper($coordinate));

        if (! preg_match('/[-+]?\d*\.?\d+/', $normalized, $matches)) {
            return null;
        }

        $parsed = (float) $matches[0];

        if (preg_match('/\b[SW]\b/', $normalized) === 1) {
            return -abs($parsed);
        }

        if (preg_match('/\b[NE]\b/', $normalized) === 1) {
            return abs($parsed);
        }

        return $parsed;
    }

    private function clean(?string $value): string
    {
        return trim((string) $value);
    }

    private function appTimezone(): string
    {
        return (string) config('app.timezone', 'Asia/Kolkata');
    }
}
