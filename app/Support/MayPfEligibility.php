<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use RuntimeException;

class MayPfEligibility
{
    private const EFFECTIVE_FROM_MONTH = '2026-05';

    private static ?array $employees = null;

    public static function deductionFor(
        Carbon $payrollMonth,
        ?string $empId,
        ?string $uanNumber,
        float $configuredPf,
        mixed $pfEligible = null
    ): float {
        if (self::isExplicitlyIneligible($pfEligible)) {
            return 0.0;
        }

        if ($payrollMonth->format('Y-m') < self::EFFECTIVE_FROM_MONTH) {
            return 0.0;
        }

        $eligibleEmployee = self::eligibleEmployee($empId, $uanNumber);

        if (! is_array($eligibleEmployee) && ! self::isExplicitlyEligible($pfEligible)) {
            return 0.0;
        }

        if ($configuredPf > 0) {
            return $configuredPf;
        }

        $data = self::data();
        $allowlistPf = $eligibleEmployee['pfAmount'] ?? $data['defaultPfAmount'] ?? 0;

        return is_numeric($allowlistPf) ? (float) $allowlistPf : 0.0;
    }

    public static function isEligible(
        Carbon $payrollMonth,
        ?string $empId,
        ?string $uanNumber,
        mixed $pfEligible = null
    ): bool
    {
        if (self::isExplicitlyIneligible($pfEligible)) {
            return false;
        }

        return $payrollMonth->format('Y-m') >= self::EFFECTIVE_FROM_MONTH
            && (self::isExplicitlyEligible($pfEligible) || is_array(self::eligibleEmployee($empId, $uanNumber)));
    }

    private static function isExplicitlyEligible(mixed $pfEligible): bool
    {
        if ($pfEligible === null || $pfEligible === '') {
            return false;
        }

        if (is_bool($pfEligible)) {
            return $pfEligible;
        }

        if (is_numeric($pfEligible)) {
            return (int) $pfEligible === 1;
        }

        $normalized = strtolower(trim((string) $pfEligible));

        return in_array($normalized, ['true', 'yes', 'y', 'active', 'eligible'], true);
    }

    private static function isExplicitlyIneligible(mixed $pfEligible): bool
    {
        if ($pfEligible === null || $pfEligible === '') {
            return false;
        }

        if (is_bool($pfEligible)) {
            return ! $pfEligible;
        }

        if (is_numeric($pfEligible)) {
            return (int) $pfEligible === 0;
        }

        $normalized = strtolower(trim((string) $pfEligible));

        return in_array($normalized, ['false', 'no', 'n', 'inactive', 'ineligible'], true);
    }

    private static function eligibleEmployee(?string $empId, ?string $uanNumber): ?array
    {
        $empId = self::normalizeEmpId($empId);
        $uanNumber = self::normalizeUan($uanNumber);
        $data = self::data();

        $eligibleEmployee = collect($data['employees'])->first(function (array $employee) use ($empId, $uanNumber): bool {
            $allowedEmpId = self::normalizeEmpId($employee['empId'] ?? null);
            $allowedUan = self::normalizeUan($employee['uan'] ?? null);

            return ($allowedEmpId !== '' && $allowedEmpId === $empId)
                || ($allowedUan !== '' && $allowedUan === $uanNumber);
        });

        return is_array($eligibleEmployee) ? $eligibleEmployee : null;
    }

    public static function dataPath(): string
    {
        return __DIR__.'/data/may-2026-pf-employees.json';
    }

    public static function reset(): void
    {
        self::$employees = null;
    }

    public static function uanForEmployee(?string $empId): string
    {
        $empId = self::normalizeEmpId($empId);

        if ($empId === '') {
            return '';
        }

        $employee = collect(self::data()['employees'])->first(
            fn (array $employee): bool => self::normalizeEmpId($employee['empId'] ?? null) === $empId
        );

        return is_array($employee)
            ? self::normalizeUan($employee['uan'] ?? null)
            : '';
    }

    private static function data(): array
    {
        if (self::$employees !== null) {
            return self::$employees;
        }

        $contents = file_get_contents(self::dataPath());

        if ($contents === false) {
            throw new RuntimeException('Unable to read the PF employee allowlist effective from May 2026.');
        }

        $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        return self::$employees = [
            'defaultPfAmount' => is_numeric($data['defaultPfAmount'] ?? null)
                ? (float) $data['defaultPfAmount']
                : 0.0,
            'employees' => is_array($data['employees'] ?? null) ? $data['employees'] : [],
        ];
    }

    private static function normalizeEmpId(?string $empId): string
    {
        return trim((string) $empId);
    }

    private static function normalizeUan(?string $uanNumber): string
    {
        return preg_replace('/\D+/', '', (string) $uanNumber) ?? '';
    }
}
