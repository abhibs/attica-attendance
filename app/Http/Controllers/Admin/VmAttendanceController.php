<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class VmAttendanceController extends Controller
{
    private const SESSION_KEY = 'vm_attendance_user';
    private const LOGIN_NAME = 'vm-login';
    private const DEFAULT_PASSWORD = 'vm-login';
    private const STATUS_FULL_DAY = 'full_day';
    private const STATUS_FULL_DAY_REMOTE = 'full_day_remote';
    private const STATUS_HALF_DAY = 'half_day';
    private const STATUS_SINGLE_PUNCH = 'single_punch';
    private const STATUS_ABSENT = 'absent';
    private const FULL_DAY_MINUTES = 460;

    public function login(Request $request): View|RedirectResponse
    {
        if ($this->vmSession($request) !== null) {
            return redirect()->route('admin-vm-attendance');
        }

        return view('admin.vm.login', [
            'defaultUsername' => self::LOGIN_NAME,
        ]);
    }

    public function loginPost(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:200'],
            'assigned_branches' => ['nullable'],
            'branches' => ['nullable'],
            'branch_ids' => ['nullable'],
        ]);

        if ($this->clean($data['username'] ?? '') !== self::LOGIN_NAME) {
            return back()->withInput($request->except('password'))->with('status', 'Invalid VM login.');
        }

        if (! hash_equals($this->vmPassword(), (string) ($data['password'] ?? ''))) {
            return back()->withInput($request->except('password'))->with('status', 'Invalid VM password.');
        }

        $branchIds = $this->normalizeBranchIds($this->firstFilledBranchPayload([
            $data['assigned_branches'] ?? null,
            $data['branches'] ?? null,
            $data['branch_ids'] ?? null,
        ]));

        if ($branchIds->isEmpty()) {
            return back()->withInput($request->except('password'))->with('status', 'At least one assigned branch is required.');
        }

        $existingBranchIds = Branch::query()
            ->whereIn('branchId', $branchIds->all())
            ->pluck('branchId')
            ->map(fn ($branchId): string => $this->clean($branchId))
            ->filter()
            ->unique()
            ->values();

        if ($existingBranchIds->isEmpty()) {
            return back()->withInput($request->except('password'))->with('status', 'No matching assigned branches were found.');
        }

        $request->session()->put(self::SESSION_KEY, [
            'username' => self::LOGIN_NAME,
            'assigned_branches' => $existingBranchIds->all(),
            'logged_in_at' => now()->toDateTimeString(),
        ]);

        return redirect()->route('admin-vm-attendance');
    }

    public function attendance(Request $request): View|RedirectResponse
    {
        $session = $this->vmSession($request);

        if ($session === null) {
            return redirect()->route('admin-vm-login')->with('status', 'Please login as VM first.');
        }

        $assignedBranchIds = collect($session['assigned_branches'] ?? [])
            ->map(fn ($branchId): string => $this->clean($branchId))
            ->filter()
            ->unique()
            ->values();

        if ($assignedBranchIds->isEmpty()) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('admin-vm-login')->with('status', 'No assigned branches are available for this VM session.');
        }

        $timezone = (string) config('app.timezone', 'Asia/Kolkata');
        $date = $this->resolveDate($request, $timezone);
        $selectedBranchId = $this->clean($request->input('branch_id'));

        if ($selectedBranchId !== '' && ! $assignedBranchIds->contains($selectedBranchId)) {
            $selectedBranchId = '';
        }

        $visibleBranchIds = $selectedBranchId !== ''
            ? collect([$selectedBranchId])
            : $assignedBranchIds;

        $branchMap = Branch::query()
            ->whereIn('branchId', $assignedBranchIds->all())
            ->orderBy('branchName')
            ->get(['branchId', 'branchName', 'city', 'state', 'latitude', 'longitude', 'url'])
            ->keyBy(fn (Branch $branch): string => $this->clean($branch->branchId));

        $attendanceRows = Attendance::query()
            ->whereDate('check_in_date', $date->toDateString())
            ->where(function ($query) use ($visibleBranchIds): void {
                $query->whereIn('check_in_branch_id', $visibleBranchIds->all())
                    ->orWhereIn('check_out_branch_id', $visibleBranchIds->all());
            })
            ->orderBy('check_in_time')
            ->orderBy('id')
            ->get();

        $employeeMap = Employee::query()
            ->whereIn(
                'empId',
                $attendanceRows
                    ->pluck('empId')
                    ->map(fn ($empId): string => $this->clean($empId))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all()
            )
            ->get(['empId', 'name', 'designation', 'contact', 'status'])
            ->keyBy(fn (Employee $employee): string => $this->clean($employee->empId));

        $rows = $attendanceRows
            ->map(fn (Attendance $attendance): array => $this->attendanceRow($attendance, $employeeMap, $branchMap))
            ->values();

        return view('admin.vm.attendance', [
            'filters' => [
                'date' => $date->toDateString(),
                'branch_id' => $selectedBranchId,
            ],
            'assignedBranches' => $branchMap->values(),
            'rows' => $rows,
            'summary' => [
                'assigned_branches' => $assignedBranchIds->count(),
                'visible_branches' => $visibleBranchIds->count(),
                'attendance_count' => $rows->count(),
                'employees' => $rows->pluck('emp_id')->unique()->count(),
                'completed' => $rows->filter(fn (array $row): bool => $row['check_out_time'] !== '--')->count(),
                'single_punch' => $rows->filter(fn (array $row): bool => $row['check_out_time'] === '--')->count(),
            ],
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('admin-vm-login')->with('status', 'VM logged out.');
    }

    private function attendanceRow(Attendance $attendance, Collection $employeeMap, Collection $branchMap): array
    {
        $empId = $this->clean($attendance->empId);
        /** @var Employee|null $employee */
        $employee = $employeeMap->get($empId);
        $checkInBranchId = $this->clean($attendance->check_in_branch_id);
        $checkOutBranchId = $this->clean($attendance->check_out_branch_id);
        /** @var Branch|null $checkInBranch */
        $checkInBranch = $branchMap->get($checkInBranchId);
        /** @var Branch|null $checkOutBranch */
        $checkOutBranch = $branchMap->get($checkOutBranchId);

        return [
            'attendance_id' => $attendance->id,
            'emp_id' => $empId,
            'employee_name' => $this->clean($employee?->name) ?: '--',
            'designation' => $this->clean($employee?->designation) ?: '--',
            'contact' => $this->clean($employee?->contact) ?: '--',
            'employee_status' => $this->clean($employee?->status) ?: '--',
            'status' => $this->effectiveAttendanceStatus($attendance),
            'status_label' => $this->statusLabel($this->effectiveAttendanceStatus($attendance)),
            'check_in_branch_id' => $checkInBranchId ?: '--',
            'check_in_branch' => $this->branchLabel($checkInBranch, $checkInBranchId),
            'check_out_branch_id' => $checkOutBranchId ?: '--',
            'check_out_branch' => $this->branchLabel($checkOutBranch, $checkOutBranchId),
            'check_in_date' => $attendance->check_in_date ?: '--',
            'check_in_time' => $this->formatTime($attendance->check_in_time),
            'check_out_date' => $attendance->check_out_date ?: '--',
            'check_out_time' => $this->formatTime($attendance->check_out_time),
            'worked_time' => $this->formatWorkedMinutes($this->workedMinutes($attendance)),
            'check_in_distance' => $this->formatDistanceValue($attendance->check_in_distance),
            'check_out_distance' => $this->formatDistanceValue($attendance->check_out_distance),
            'check_in_location' => $this->locationPayload($attendance->latitude, $attendance->longitude),
            'check_out_location' => $this->locationPayload($attendance->check_out_latitude, $attendance->check_out_longitude),
            'login_photo_url' => $this->resolvePhotoUrl($attendance->photo_path),
            'logout_photo_url' => $this->resolvePhotoUrl($attendance->check_out_photo_path),
        ];
    }

    private function normalizeBranchIds(mixed $value): Collection
    {
        if ($value instanceof Collection) {
            $items = $value->all();
        } elseif (is_array($value)) {
            $items = $value;
        } else {
            $items = preg_split('/[\s,;|]+/', (string) $value) ?: [];
        }

        return collect($items)
            ->map(fn ($branchId): string => strtoupper($this->clean($branchId)))
            ->filter(fn (string $branchId): bool => $branchId !== '' && preg_match('/^[A-Z0-9_-]+$/', $branchId) === 1)
            ->unique()
            ->values();
    }

    private function firstFilledBranchPayload(array $values): mixed
    {
        foreach ($values as $value) {
            if (is_array($value) && count(array_filter($value, fn ($item): bool => $this->clean($item) !== '')) > 0) {
                return $value;
            }

            if (! is_array($value) && $this->clean($value) !== '') {
                return $value;
            }
        }

        return '';
    }

    private function vmSession(Request $request): ?array
    {
        $session = $request->session()->get(self::SESSION_KEY);

        return is_array($session) ? $session : null;
    }

    private function vmPassword(): string
    {
        $configured = trim((string) env('VM_LOGIN_PASSWORD', ''));

        return $configured !== '' ? $configured : self::DEFAULT_PASSWORD;
    }

    private function resolveDate(Request $request, string $timezone): Carbon
    {
        $date = $this->clean($request->input('date'));

        if ($date === '') {
            return Carbon::now($timezone)->startOfDay();
        }

        try {
            return Carbon::parse($date, $timezone)->startOfDay();
        } catch (\Throwable) {
            return Carbon::now($timezone)->startOfDay();
        }
    }

    private function branchLabel(?Branch $branch, string $fallbackBranchId): string
    {
        if (! $branch) {
            return $fallbackBranchId !== '' ? $fallbackBranchId : '--';
        }

        $parts = [
            $this->clean($branch->branchId),
            $this->clean($branch->branchName),
        ];

        return collect($parts)->filter()->implode(' - ') ?: '--';
    }

    private function locationPayload($latitude, $longitude): array
    {
        if ($latitude === null || $longitude === null || ! is_numeric($latitude) || ! is_numeric($longitude)) {
            return [
                'label' => '--',
                'url' => '',
            ];
        }

        $lat = (float) $latitude;
        $lng = (float) $longitude;
        $label = number_format($lat, 6, '.', '').', '.number_format($lng, 6, '.', '');

        return [
            'label' => $label,
            'url' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($label),
        ];
    }

    private function effectiveAttendanceStatus(Attendance $attendance): string
    {
        $override = $this->clean($attendance->attendance_status_override);

        if (in_array($override, [self::STATUS_FULL_DAY, self::STATUS_FULL_DAY_REMOTE, self::STATUS_HALF_DAY, self::STATUS_SINGLE_PUNCH, self::STATUS_ABSENT], true)) {
            return $override;
        }

        if (! $attendance->check_out_date || ! $attendance->check_out_time) {
            return self::STATUS_SINGLE_PUNCH;
        }

        return $this->workedMinutes($attendance) < self::FULL_DAY_MINUTES
            ? self::STATUS_HALF_DAY
            : self::STATUS_FULL_DAY;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_FULL_DAY => 'Full Day',
            self::STATUS_FULL_DAY_REMOTE => 'Full Day Remote',
            self::STATUS_HALF_DAY => 'Half Day',
            self::STATUS_SINGLE_PUNCH => 'Single Punch',
            self::STATUS_ABSENT => 'Absent',
            default => 'Unknown',
        };
    }

    private function workedMinutes(Attendance $attendance): int
    {
        if (! $attendance->check_in_date || ! $attendance->check_in_time || ! $attendance->check_out_date || ! $attendance->check_out_time) {
            return 0;
        }

        $checkIn = Carbon::parse($attendance->check_in_date.' '.$attendance->check_in_time);
        $checkOut = Carbon::parse($attendance->check_out_date.' '.$attendance->check_out_time);

        if ($checkOut->lte($checkIn)) {
            return 0;
        }

        return (int) $checkIn->diffInMinutes($checkOut);
    }

    private function formatWorkedMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '--';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return sprintf('%02dh %02dm', $hours, $remainingMinutes);
    }

    private function formatTime(?string $time): string
    {
        $time = $this->clean($time);

        if ($time === '') {
            return '--';
        }

        return Carbon::parse($time)->format('h:i A');
    }

    private function formatDistanceValue($distance): string
    {
        if ($distance === null || $this->clean($distance) === '' || ! is_numeric($distance)) {
            return '--';
        }

        $meters = (float) $distance;

        if ($meters >= 1000) {
            return number_format($meters / 1000, 2).' km';
        }

        return number_format($meters, 0).' m';
    }

    private function resolvePhotoUrl(?string $path): string
    {
        $trimmed = $this->clean($path);

        if ($trimmed === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $trimmed) === 1) {
            return $trimmed;
        }

        $normalizedPath = ltrim($trimmed, '/');

        if (str_starts_with($normalizedPath, 'public/')) {
            $normalizedPath = substr($normalizedPath, 7);
        }

        return function_exists('project_asset')
            ? \project_asset($normalizedPath)
            : asset($normalizedPath);
    }

    private function clean($value): string
    {
        return trim((string) $value);
    }
}
