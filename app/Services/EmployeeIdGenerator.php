<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\RecruitmentCandidate;
use Illuminate\Support\Collection;

class EmployeeIdGenerator
{
    public function generate(): string
    {
        $suggestions = $this->suggestions(2);

        return $suggestions !== [] ? (string) $suggestions[0] : '1000000';
    }

    public function suggestions(int $limit = 2): array
    {
        $limit = max($limit, 1);
        $existingIds = $this->numericEmployeeIds();

        if ($existingIds->isEmpty()) {
            return ['1000000'];
        }

        $blocks = [];
        $blockStart = null;
        $blockEnd = null;

        foreach ($existingIds as $empId) {
            if ($blockStart === null) {
                $blockStart = $empId;
                $blockEnd = $empId;

                continue;
            }

            if ($empId === ($blockEnd + 1)) {
                $blockEnd = $empId;

                continue;
            }

            $blocks[] = [
                'start' => $blockStart,
                'end' => $blockEnd,
            ];
            $blockStart = $empId;
            $blockEnd = $empId;
        }

        if ($blockStart !== null && $blockEnd !== null) {
            $blocks[] = [
                'start' => $blockStart,
                'end' => $blockEnd,
            ];
        }

        return collect($blocks)
            ->sortByDesc('end')
            ->take($limit)
            ->sortBy('end')
            ->map(fn (array $block): string => (string) ($block['end'] + 1))
            ->unique()
            ->filter(fn (string $empId): bool => ! $this->exists($empId))
            ->values()
            ->all();
    }

    public function exists(string $empId, ?int $ignoreEmployeeId = null): bool
    {
        $normalizedEmpId = trim($empId);

        if ($normalizedEmpId === '') {
            return true;
        }

        $employeeQuery = Employee::query()
            ->whereRaw('TRIM(empId) = ?', [$normalizedEmpId]);

        if ($ignoreEmployeeId !== null) {
            $employeeQuery->where('id', '!=', $ignoreEmployeeId);
        }

        if ($employeeQuery->exists()) {
            return true;
        }

        return RecruitmentCandidate::query()
            ->where('generated_emp_id', $normalizedEmpId)
            ->exists();
    }

    private function numericEmployeeIds(): Collection
    {
        return Employee::query()
            ->pluck('empId')
            ->map(fn ($empId): string => trim((string) $empId))
            ->filter(fn (string $empId): bool => preg_match('/^100\d{4}$/', $empId) === 1)
            ->map(fn (string $empId): int => (int) $empId)
            ->filter(fn (int $empId): bool => $empId > 0)
            ->unique()
            ->sort()
            ->values();
    }
}
