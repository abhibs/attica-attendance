<?php

namespace App\Support;

use Illuminate\Support\Collection;

class SalaryReportRegion
{
    public static function groups(Collection $rows): Collection
    {
        return collect(self::definitions())
            ->map(function (array $definition) use ($rows): array {
                return [
                    'title' => $definition['title'],
                    'rows' => $rows
                        ->filter(fn (array $row): bool => $definition['matcher']($row))
                        ->values(),
                ];
            });
    }

    private static function definitions(): array
    {
        return [
            [
                'title' => 'HO Salary Report',
                'matcher' => fn (array $row): bool => self::isHeadOffice($row),
            ],
            [
                'title' => 'Bangalore Except HO',
                'matcher' => fn (array $row): bool => ! self::isHeadOffice($row)
                    && self::matchesCity($row, ['bangalore', 'bengaluru']),
            ],
            [
                'title' => 'KA Except Bangalore HO',
                'matcher' => fn (array $row): bool => ! self::isHeadOffice($row)
                    && self::matchesState($row, ['karnataka'])
                    && ! self::matchesCity($row, ['bangalore', 'bengaluru']),
            ],
            [
                'title' => 'Andhra Pradesh',
                'matcher' => fn (array $row): bool => ! self::isHeadOffice($row)
                    && self::matchesState($row, ['andhra pradesh']),
            ],
            [
                'title' => 'Telangana',
                'matcher' => fn (array $row): bool => ! self::isHeadOffice($row)
                    && self::matchesState($row, ['telangana']),
            ],
            [
                'title' => 'Tamilnadu Incl Pondicherry',
                'matcher' => fn (array $row): bool => ! self::isHeadOffice($row)
                    && self::matchesState(
                        $row,
                        ['tamil nadu', 'tamilnadu', 'pondicherry', 'puducherry']
                    ),
            ],
        ];
    }

    private static function isHeadOffice(array $row): bool
    {
        return self::normalize($row['branch_id'] ?? '') === 'agpl000'
            || in_array(self::normalize($row['branch_name'] ?? ''), ['headoffice', 'ho'], true);
    }

    private static function matchesCity(array $row, array $cities): bool
    {
        $city = self::normalize($row['city'] ?? '');

        foreach ($cities as $target) {
            if ($city !== '' && str_contains($city, self::normalize($target))) {
                return true;
            }
        }

        return false;
    }

    private static function matchesState(array $row, array $states): bool
    {
        $state = self::normalize($row['state'] ?? '');

        return in_array($state, array_map([self::class, 'normalize'], $states), true);
    }

    private static function normalize(?string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower(trim((string) $value))) ?? '';
    }
}
