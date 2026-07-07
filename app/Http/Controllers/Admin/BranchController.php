<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BranchController extends Controller
{

    public function create()
    {
        return view('admin.branch.create');
    }


    public function store(Request $request)
    {
        $request->validate([
            'branchId' => 'required',
            'addressline' => 'required',
            'area' => 'required',
            'city' => 'required',
            'state' => 'required',
            'pincode' => 'required',
            'branchName' => 'required',
            'latitude' => 'required',
            'longitude' => 'required',
            'url' => 'required',
        ], [
            'branchId.required' => 'Branch Id Required',
            'addressline.required' => 'Branch Address Required',
            'area.required' => 'Branch Area Required',
            'city.required' => 'Branch City Required',
            'state.required' => 'Branch State Required',
            'pincode.required' => 'Branch Pincode Required',
            'branchName.required' => 'Branch Name Required',
            'latitude.required' => 'Branch Latitude Required',
            'longitude.required' => 'Branch Longitude Required',
            'url.required' => 'Branch URL Required',
        ]);




        $branch = new Branch();
        $branch->branchId = $request->branchId;
        $branch->addressline = $request->addressline;
        $branch->area = $request->area;
        $branch->city = $request->city;
        $branch->state = $request->state;
        $branch->pincode = $request->pincode;
        $branch->branchName = $request->branchName;
        $branch->timings = $request->timings;
        $branch->latitude = $request->latitude;
        $branch->longitude = $request->longitude;
        $branch->url = $request->url;
        $branch->save();

        $notification = array(
            'message' => 'Branch Inserted Successfully',
            'alert-type' => 'success'
        );
        return redirect()->route('admin-branch-index')->with($notification);
    }


    public function index(Request $request)
    {
        $selectedDate = $this->resolveSelectedDate($request->query('date'));
        $selectedDateLabel = Carbon::parse($selectedDate)->format('d M Y');
        $maxSelectableDate = Carbon::now(config('app.timezone', 'Asia/Kolkata'))->toDateString();
        $selectedStatus = $this->resolveStatusFilter($request->query('status'));
        $selectedStatusLabel = ucfirst($selectedStatus);

        $datasQuery = Branch::query();

        if ($selectedStatus === 'active') {
            $datasQuery->where('status', 1);
        } elseif ($selectedStatus === 'inactive') {
            $datasQuery->where('status', 0);
        }

        $datas = $datasQuery
            ->orderBy('branchId', 'asc')
            ->get();

        $loginCounts = Attendance::query()
            ->selectRaw('TRIM(check_in_branch_id) as branch_id, COUNT(DISTINCT TRIM(empId)) as logged_in_count')
            ->whereDate('check_in_date', $selectedDate)
            ->whereNotNull('check_in_branch_id')
            ->whereRaw("TRIM(check_in_branch_id) <> ''")
            ->groupByRaw('TRIM(check_in_branch_id)')
            ->pluck('logged_in_count', 'branch_id');

        $mapBranches = $datas->map(function (Branch $branch) use ($loginCounts) {
            $branchId = $this->clean($branch->branchId);
            $latitude = $this->parseCoordinate($branch->latitude);
            $longitude = $this->parseCoordinate($branch->longitude);
            $loggedInCount = (int) $loginCounts->get($branchId, 0);

            $branch->selected_date_logins = $loggedInCount;

            return [
                'branch_id' => $branchId,
                'branch_name' => $this->clean($branch->branchName),
                'address' => collect([
                    $this->clean($branch->addressline),
                    $this->clean($branch->area),
                    $this->clean($branch->city),
                    $this->clean($branch->state),
                    $this->clean($branch->pincode),
                ])->filter()->implode(', '),
                'area' => $this->clean($branch->area),
                'city' => $this->clean($branch->city),
                'state' => $this->clean($branch->state),
                'pincode' => $this->clean($branch->pincode),
                'timings' => $this->clean($branch->timings),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'url' => $this->mapUrl($branch->url, $latitude, $longitude),
                'status_label' => (int) $branch->status === 1 ? 'Active' : 'Inactive',
                'logged_in_count' => $loggedInCount,
            ];
        })->values();

        $mappedBranchCount = $mapBranches
            ->filter(fn (array $branch): bool => $branch['latitude'] !== null && $branch['longitude'] !== null)
            ->count();

        $branchIds = $datas
            ->map(fn (Branch $branch): string => $this->clean($branch->branchId))
            ->filter()
            ->values();

        $selectedDateLoginTotal = $branchIds->isEmpty()
            ? 0
            : (int) Attendance::query()
                ->selectRaw('COUNT(DISTINCT TRIM(empId)) as total')
                ->whereDate('check_in_date', $selectedDate)
                ->whereNotNull('empId')
                ->whereRaw("TRIM(empId) <> ''")
                ->whereIn(DB::raw('TRIM(check_in_branch_id)'), $branchIds->all())
                ->value('total');

        $regionSummaries = $this->buildRegionSummaries($datas, $mapBranches);

        return view('admin.branch.index', compact(
            'datas',
            'selectedDate',
            'selectedDateLabel',
            'maxSelectableDate',
            'selectedStatus',
            'selectedStatusLabel',
            'mapBranches',
            'mappedBranchCount',
            'selectedDateLoginTotal',
            'regionSummaries'
        ));
    }

    public function logins(Request $request)
    {
        $filters = [
            'date' => $this->resolveOptionalDate($request->query('date')),
            'branch_id' => $this->normalizeBranchFilter($request->query('branch_id')),
            'emp_id' => $this->clean($request->query('emp_id')),
            'employee_name' => $this->clean($request->query('employee_name')),
            'state' => $this->clean($request->query('state')),
            'city' => $this->clean($request->query('city')),
        ];

        $branches = Branch::query()
            ->orderBy('branchName')
            ->get(['id', 'branchId', 'branchName', 'city', 'state', 'status']);
        $branchMap = $branches->keyBy(fn (Branch $branch): string => $this->clean($branch->branchId));

        $query = Employee::query()
            ->whereNotNull('last_login_branch_id')
            ->whereRaw("TRIM(COALESCE(last_login_branch_id, '')) <> ''");

        if ($filters['date'] !== '') {
            $query->whereDate('last_login_at', $filters['date']);
        }

        if ($filters['branch_id'] !== '') {
            $query->whereRaw('TRIM(last_login_branch_id) = ?', [$filters['branch_id']]);
        }

        if ($filters['emp_id'] !== '') {
            $query->where('empId', 'like', '%'.$filters['emp_id'].'%');
        }

        if ($filters['employee_name'] !== '') {
            $query->where('name', 'like', '%'.$filters['employee_name'].'%');
        }

        $employees = $query
            ->orderByDesc('last_login_at')
            ->orderBy('name')
            ->get([
                'id',
                'empId',
                'name',
                'designation',
                'status',
                'contact',
                'last_login_branch_id',
                'last_login_at',
            ]);

        $rows = $employees
            ->map(function (Employee $employee) use ($branchMap): ?array {
                $branchId = $this->clean($employee->last_login_branch_id);
                /** @var Branch|null $branch */
                $branch = $branchMap->get($branchId);

                return [
                    'emp_id' => $this->clean($employee->empId),
                    'employee_name' => $this->clean($employee->name) ?: '--',
                    'designation' => $this->clean($employee->designation) ?: '--',
                    'contact' => $this->clean($employee->contact) ?: '--',
                    'status' => $this->clean($employee->status) ?: '--',
                    'branch_id' => $branchId,
                    'branch_name' => $this->clean($branch?->branchName) ?: '--',
                    'city' => $this->clean($branch?->city),
                    'state' => $this->clean($branch?->state),
                    'last_login_at' => $employee->last_login_at
                        ? Carbon::parse($employee->last_login_at, config('app.timezone', 'Asia/Kolkata'))->format('d M Y h:i A')
                        : '--',
                    'last_login_sort' => $employee->last_login_at
                        ? Carbon::parse($employee->last_login_at, config('app.timezone', 'Asia/Kolkata'))->timestamp
                        : 0,
                ];
            })
            ->filter()
            ->when($filters['state'] !== '', function (Collection $rows) use ($filters): Collection {
                return $rows->filter(fn (array $row): bool => strcasecmp($row['state'], $filters['state']) === 0);
            })
            ->when($filters['city'] !== '', function (Collection $rows) use ($filters): Collection {
                return $rows->filter(fn (array $row): bool => strcasecmp($row['city'], $filters['city']) === 0);
            })
            ->sortByDesc('last_login_sort')
            ->values();

        $summaryByBranch = $rows
            ->groupBy('branch_id')
            ->map(function (Collection $branchRows, string $branchId): array {
                $first = $branchRows->first();

                return [
                    'branch_id' => $branchId,
                    'branch_name' => $first['branch_name'] ?? '--',
                    'city' => $first['city'] ?? '',
                    'state' => $first['state'] ?? '',
                    'login_count' => $branchRows->count(),
                    'latest_login' => $first['last_login_at'] ?? '--',
                ];
            })
            ->sortByDesc('login_count')
            ->values();

        $branchOptions = $branches
            ->map(fn (Branch $branch): array => [
                'id' => $this->clean($branch->branchId),
                'label' => trim($branch->branchId.' - '.$branch->branchName),
                'meta' => trim(implode(', ', array_filter([
                    $this->clean($branch->city),
                    $this->clean($branch->state),
                ]))),
            ])
            ->values();

        return view('admin.branch.logins', [
            'filters' => [
                ...$filters,
                'states' => $branches->pluck('state')->filter()->map(fn ($value) => $this->clean($value))->unique()->sort()->values(),
                'cities' => $branches->pluck('city')->filter()->map(fn ($value) => $this->clean($value))->unique()->sort()->values(),
                'branch_options' => $branchOptions,
                'selected_branch_search' => $this->selectedBranchSearch($branches, $filters['branch_id']),
            ],
            'rows' => $rows,
            'summaryByBranch' => $summaryByBranch,
            'totalEmployees' => $rows->count(),
            'totalBranches' => $summaryByBranch->count(),
        ]);
    }


    public function edit($id)
    {
        $data = Branch::findOrFail($id);
        return view('admin.branch.edit', compact('data'));
    }

    public function update(Request $request)
    {

        $id = $request->id;

        Branch::findOrFail($id)->update([
            'branchId' => $request->branchId,
            'addressline' => $request->addressline,
            'area' => $request->area,
            'city' => $request->city,
            'state' => $request->state,
            'pincode' => $request->pincode,
            'branchName' => $request->branchName,
            'timings' => $request->timings,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'url' => $request->url,

        ]);

        $notification = array(
            'message' => 'Branch Updated Successfully',
            'alert-type' => 'success'

        );

        return redirect()->route('admin-branch-index')->with($notification);
    }



    public function inactive($id)
    {
        Branch::findOrFail($id)->update(['status' => 0]);

        $notification = array(
            'message' => 'Branch InActive Successfully',
            'alert-type' => 'error'

        );
        return redirect()->back()->with($notification);
    }

    public function active($id)
    {
        Branch::findOrFail($id)->update(['status' => 1]);

        $notification = array(
            'message' => 'Branch Active Successfully',
            'alert-type' => 'success'

        );
        return redirect()->back()->with($notification);
    }

    public function delete($id)
    {

        Branch::findOrFail($id)->delete();

        $notification = array(
            'message' => 'Branch Deleted Successfully',
            'alert-type' => 'success'

        );

        return redirect()->back()->with($notification);
    }

    private function clean(?string $value): string
    {
        return trim((string) $value);
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

    private function resolveSelectedDate(?string $value): string
    {
        $date = trim((string) $value);

        if ($date === '') {
            return Carbon::now(config('app.timezone', 'Asia/Kolkata'))->toDateString();
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $date, config('app.timezone', 'Asia/Kolkata'))->toDateString();
        } catch (\Throwable $exception) {
            return Carbon::now(config('app.timezone', 'Asia/Kolkata'))->toDateString();
        }
    }

    private function resolveOptionalDate(mixed $value): string
    {
        $date = trim((string) $value);

        if ($date === '') {
            return '';
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $date, config('app.timezone', 'Asia/Kolkata'))->toDateString();
        } catch (\Throwable $exception) {
            return '';
        }
    }

    private function normalizeBranchFilter(mixed $value): string
    {
        $normalized = $this->clean($value);

        if ($normalized === '') {
            return '';
        }

        $parts = preg_split('/\s*-\s*/', $normalized, 2);

        return $this->clean($parts[0] ?? $normalized);
    }

    private function selectedBranchSearch(Collection $branches, string $branchId): string
    {
        if ($branchId === '') {
            return '';
        }

        /** @var Branch|null $branch */
        $branch = $branches->first(fn (Branch $branch): bool => $this->clean($branch->branchId) === $branchId);

        return $branch ? trim($branch->branchId.' - '.$branch->branchName) : '';
    }

    private function resolveStatusFilter(?string $value): string
    {
        $status = strtolower(trim((string) $value));

        return in_array($status, ['active', 'inactive', 'all'], true) ? $status : 'active';
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

    private function buildRegionSummaries(Collection $branches, Collection $mapBranches): array
    {
        $definitions = [
            [
                'id' => 'bengaluru',
                'label' => 'BLR',
                'description' => 'Bengaluru city branches.',
                'matcher' => fn (Branch $branch): bool => $this->matchesCity($branch->city, ['bengaluru', 'bangalore']),
            ],
            [
                'id' => 'karnataka_except_bengaluru',
                'label' => 'KA except BLR',
                'description' => 'Karnataka branches outside Bengaluru city.',
                'matcher' => fn (Branch $branch): bool => $this->matchesState($branch->state, ['karnataka']) &&
                    ! $this->matchesCity($branch->city, ['bengaluru', 'bangalore']),
            ],
            [
                'id' => 'chennai',
                'label' => 'Chennai',
                'description' => 'Chennai city branches.',
                'matcher' => fn (Branch $branch): bool => $this->matchesCity($branch->city, ['chennai']),
            ],
            [
                'id' => 'tn_except_chennai_py',
                'label' => 'TN except Chennai + PY',
                'description' => 'Tamil Nadu branches outside Chennai, plus Pondicherry / Puducherry.',
                'matcher' => fn (Branch $branch): bool => $this->matchesState($branch->state, ['tamilnadu', 'pondicherry', 'puducherry']) &&
                    ! $this->matchesCity($branch->city, ['chennai']),
            ],
            [
                'id' => 'andhra_pradesh',
                'label' => 'AP',
                'description' => 'All Andhra Pradesh branches.',
                'matcher' => fn (Branch $branch): bool => $this->matchesState($branch->state, ['andhrapradesh']),
            ],
            [
                'id' => 'telangana',
                'label' => 'TS',
                'description' => 'All Telangana branches, including Hyderabad.',
                'matcher' => fn (Branch $branch): bool => $this->matchesState($branch->state, ['telangana']),
            ],
            [
                'id' => 'hyderabad',
                'label' => 'HYD',
                'description' => 'Hyderabad city branches.',
                'matcher' => fn (Branch $branch): bool => $this->matchesCity($branch->city, ['hyderabad']),
            ],
            [
                'id' => 'ap_ts_except_hyderabad',
                'label' => 'AP / TS except HYD',
                'description' => 'Andhra Pradesh and Telangana branches outside Hyderabad city.',
                'matcher' => fn (Branch $branch): bool => $this->matchesState($branch->state, ['andhrapradesh', 'telangana']) &&
                    ! $this->matchesCity($branch->city, ['hyderabad']),
            ],
        ];

        $metroCityKeys = [
            $this->cityGroupKey('Bengaluru'),
            $this->cityGroupKey('Bangalore'),
            $this->cityGroupKey('Chennai'),
            $this->cityGroupKey('Hyderabad'),
        ];

        $majorCityDefinitions = $branches
            ->groupBy(fn (Branch $branch): string => $this->cityGroupKey($branch->city))
            ->filter(function (Collection $cityBranches, string $cityKey) use ($metroCityKeys): bool {
                return $cityKey !== '' &&
                    ! in_array($cityKey, $metroCityKeys, true) &&
                    $cityBranches->count() > 1;
            })
            ->sortByDesc(fn (Collection $cityBranches): int => $cityBranches->count())
            ->map(function (Collection $cityBranches, string $cityKey): array {
                $displayCity = $this->cityGroupLabel($cityKey, $cityBranches);

                return [
                    'id' => 'city_'.$cityKey,
                    'label' => $displayCity,
                    'description' => $displayCity.' branches.',
                    'matcher' => fn (Branch $branch): bool => $this->cityGroupKey($branch->city) === $cityKey,
                ];
            })
            ->values()
            ->all();

        $definitions = array_merge($definitions, $majorCityDefinitions);

        $mapBranchesById = $mapBranches->keyBy('branch_id');

        return collect($definitions)
            ->map(function (array $definition) use ($branches, $mapBranchesById): array {
                $matchedBranches = $branches
                    ->filter($definition['matcher'])
                    ->values();

                $branchIds = $matchedBranches
                    ->map(fn (Branch $branch): string => $this->clean($branch->branchId))
                    ->filter()
                    ->values();

                $mappedBranches = $branchIds
                    ->map(fn (string $branchId) => $mapBranchesById->get($branchId))
                    ->filter()
                    ->values();

                $branchPreview = $matchedBranches
                    ->map(fn (Branch $branch): array => [
                        'branch_id' => $this->clean($branch->branchId),
                        'branch_name' => $this->clean($branch->branchName),
                        'city' => $this->clean($branch->city),
                    ])
                    ->sortBy('branch_id')
                    ->values()
                    ->all();

                return [
                    'id' => $definition['id'],
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'branch_count' => $branchIds->count(),
                    'mapped_branch_count' => $mappedBranches->count(),
                    'selected_date_checkins' => $matchedBranches->sum(fn (Branch $branch): int => (int) ($branch->selected_date_logins ?? 0)),
                    'branch_ids' => $branchIds->all(),
                    'branch_preview' => $branchPreview,
                ];
            })
            ->values()
            ->all();
    }

    private function matchesCity(?string $value, array $targets): bool
    {
        $normalizedValue = $this->normalizeLocationValue($value);

        if ($normalizedValue === '') {
            return false;
        }

        foreach ($targets as $target) {
            $normalizedTarget = $this->normalizeLocationValue($target);

            if ($normalizedTarget !== '' && str_contains($normalizedValue, $normalizedTarget)) {
                return true;
            }
        }

        return false;
    }

    private function matchesState(?string $value, array $targets): bool
    {
        $normalizedValue = $this->normalizeLocationValue($value);

        if ($normalizedValue === '') {
            return false;
        }

        foreach ($targets as $target) {
            if ($normalizedValue === $this->normalizeLocationValue($target)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeLocationValue(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));

        if ($normalized === '') {
            return '';
        }

        return preg_replace('/[^a-z0-9]+/', '', $normalized) ?? '';
    }

    private function cityGroupKey(?string $value): string
    {
        $normalized = $this->normalizeLocationValue($value);

        if ($normalized === '') {
            return '';
        }

        foreach ($this->cityAliases() as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                $normalizedAlias = $this->normalizeLocationValue($alias);

                if ($normalizedAlias !== '' && str_contains($normalized, $normalizedAlias)) {
                    return $canonical;
                }
            }
        }

        return $normalized;
    }

    private function cityGroupLabel(string $cityKey, Collection $cityBranches): string
    {
        $labels = [
            'bengaluru' => 'Bengaluru',
            'chennai' => 'Chennai',
            'hyderabad' => 'Hyderabad',
            'visakhapatnam' => 'Visakhapatnam',
            'madurai' => 'Madurai',
            'coimbatore' => 'Coimbatore',
            'vijayawada' => 'Vijayawada',
            'mysore' => 'Mysore',
            'guntur' => 'Guntur',
            'kanchipuram' => 'Kanchipuram',
            'tiruppur' => 'Tiruppur',
            'trichy' => 'Trichy',
            'rajahmundry' => 'Rajahmundry',
            'tirupati' => 'Tirupati',
            'hosur' => 'Hosur',
        ];

        if (isset($labels[$cityKey])) {
            return $labels[$cityKey];
        }

        return $cityBranches
            ->map(fn (Branch $branch): string => $this->clean($branch->city))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first() ?? strtoupper($cityKey);
    }

    private function cityAliases(): array
    {
        return [
            'bengaluru' => ['bengaluru', 'bangalore'],
            'chennai' => ['chennai'],
            'hyderabad' => ['hyderabad'],
            'visakhapatnam' => ['visakhapatnam'],
            'madurai' => ['madurai'],
            'coimbatore' => ['coimbatore'],
            'vijayawada' => ['vijayawada'],
            'mysore' => ['mysore'],
            'guntur' => ['guntur'],
            'kanchipuram' => ['kanchipuram'],
            'tiruppur' => ['tiruppur'],
            'trichy' => ['trichy'],
            'rajahmundry' => ['rajahmundry'],
            'tirupati' => ['tirupati'],
            'hosur' => ['hosur'],
        ];
    }
}
