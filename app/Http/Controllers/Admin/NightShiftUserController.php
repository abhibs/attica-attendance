<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NightShiftUserController extends Controller
{
    public function index()
    {
        $employees = Employee::query()
            ->where(function ($query): void {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'Inactive');
            })
            ->orderBy('name')
            ->get();

        return view('admin.night_shift.index', [
            'employees' => $employees,
            'nightShiftEmployees' => $employees
                ->filter(fn (Employee $employee): bool => (bool) $employee->is_night_shift)
                ->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer', 'exists:employee,id'],
            'shift_timings' => ['nullable', 'array'],
            'shift_timings.*' => ['nullable', 'string', 'max:100'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            $selectedIds = collect($request->input('employee_ids', []))
                ->map(fn ($id): int => (int) $id)
                ->filter()
                ->unique()
                ->values();
            $shiftTimings = $request->input('shift_timings', []);

            foreach ($selectedIds as $employeeId) {
                $timing = trim((string) ($shiftTimings[$employeeId] ?? ''));

                if ($timing === '') {
                    $validator->errors()->add(
                        "shift_timings.$employeeId",
                        'Each night shift user must have a shift timing.'
                    );
                }
            }
        });

        $data = $validator->validate();

        $employeeIds = collect($data['employee_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $shiftTimings = collect($data['shift_timings'] ?? [])
            ->map(fn ($value): string => trim((string) $value));

        Employee::query()
            ->where('is_night_shift', true)
            ->when(
                $employeeIds->isNotEmpty(),
                fn ($query) => $query->whereNotIn('id', $employeeIds->all())
            )
            ->update(['is_night_shift' => false]);

        if ($employeeIds->isNotEmpty()) {
            $employees = Employee::query()
                ->whereIn('id', $employeeIds->all())
                ->get()
                ->keyBy('id');

            foreach ($employeeIds as $employeeId) {
                /** @var Employee|null $employee */
                $employee = $employees->get($employeeId);

                if (! $employee) {
                    continue;
                }

                $employee->update([
                    'is_night_shift' => true,
                    'shift_timing' => $shiftTimings->get($employeeId, trim((string) $employee->shift_timing)),
                ]);
            }
        }

        return redirect()
            ->route('admin-night-shift-users')
            ->with('status', 'Night shift users updated successfully with individual shift timings.');
    }
}
