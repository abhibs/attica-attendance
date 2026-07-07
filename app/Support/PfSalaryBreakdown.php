<?php

namespace App\Support;

class PfSalaryBreakdown
{
    private const BASIC_DA_BY_EMPLOYEE = [
        '1000652' => ['basic' => 21000, 'da' => 7000],
        '1001604' => ['basic' => 21000, 'da' => 7000],
        '1002187' => ['basic' => 12750, 'da' => 5000],
        '1003276' => ['basic' => 12750, 'da' => 5000],
        '1004912' => ['basic' => 12750, 'da' => 5000],
        '1004948' => ['basic' => 15000, 'da' => 5000],
        '1005065' => ['basic' => 12750, 'da' => 5000],
        '1004940' => ['basic' => 24000, 'da' => 7000],
        '1004265' => ['basic' => 15000, 'da' => 4550],
        '1000042' => ['basic' => 18000, 'da' => 7000],
        '1005113' => ['basic' => 21000, 'da' => 7000],
        '1004823' => ['basic' => 16000, 'da' => 4550],
        '1003040' => ['basic' => 12750, 'da' => 5000],
        '1002651' => ['basic' => 20000, 'da' => 7000],
        '1002867' => ['basic' => 12750, 'da' => 5000],
        '1000423' => ['basic' => 16000, 'da' => 4550],
        '1000735' => ['basic' => 17000, 'da' => 6000],
        '1001627' => ['basic' => 17000, 'da' => 6000],
        '1000336' => ['basic' => 17000, 'da' => 6000],
        '1004975' => ['basic' => 14000, 'da' => 6000],
    ];

    private const BASIC_DA_BY_STATE_AND_TIER = [
        'KA' => [
            'senior' => ['basic' => 14200, 'da' => 4550],
            'middle' => ['basic' => 12750, 'da' => 4550],
            'junior' => ['basic' => 11600, 'da' => 4550],
        ],
        'AP-TS' => [
            'te' => ['basic' => 4102, 'da' => 9408],
            'abm' => ['basic' => 4722, 'da' => 9408],
            'bm' => ['basic' => 5557, 'da' => 9408],
        ],
        'TN' => [
            'te-junior' => ['basic' => 6691, 'da' => 7353],
            'gunman' => ['basic' => 8000, 'da' => 7353],
            'abm' => ['basic' => 6880, 'da' => 7353],
            'bm' => ['basic' => 7390, 'da' => 7353],
        ],
    ];

    public static function forReportRow(array $row): array
    {
        $salaryDaysInMonth = max(1, (int) ($row['salary_days_in_month'] ?? 30));
        $creditedDays = is_numeric($row['credited_days'] ?? null) ? (float) $row['credited_days'] : 0.0;
        $grossSalaryInput = is_numeric($row['gross_payable_salary'] ?? null)
            ? (float) $row['gross_payable_salary']
            : 0.0;
        $monthly = self::monthlyBasicDa(
            $row['emp_id'] ?? null,
            $row['state'] ?? null,
            $row['designation'] ?? null
        );
        $basic = ($monthly['basic'] / $salaryDaysInMonth) * $creditedDays;
        $da = ($monthly['da'] / $salaryDaysInMonth) * $creditedDays;
        $basicPlusDa = $basic + $da;

        if ($basicPlusDa > $grossSalaryInput && $basicPlusDa > 0) {
            $adjustedBasicPlusDa = max(0, $grossSalaryInput);
            $basic = round($adjustedBasicPlusDa * ($basic / $basicPlusDa), 2);
            $da = $adjustedBasicPlusDa - $basic;
            $basicPlusDa = $adjustedBasicPlusDa;
        }

        $pfRateOfWages = min(15000, $basicPlusDa);
        $otherAllowances = max(0, $grossSalaryInput - $basicPlusDa);
        $grossSalary = $basicPlusDa + $otherAllowances;
        $eePf = round($pfRateOfWages * 0.12, 2);
        $esi = 0.0;
        $pt = 0.0;
        $advance = is_numeric($row['advance'] ?? null) ? (float) $row['advance'] : 0.0;
        $securityDeposit = is_numeric($row['security_deposit'] ?? null) ? (float) $row['security_deposit'] : 0.0;
        $totalDeductions = $eePf + $esi + $pt + $advance + $securityDeposit;
        $erPf = round($pfRateOfWages * 0.12, 2);
        $erEsi = 0.0;
        $takeHomeSalary = $grossSalary - $totalDeductions;

        return [
            'state_basic_monthly' => $monthly['basic'],
            'state_da_monthly' => $monthly['da'],
            'state_basic_plus_da_monthly' => $monthly['basic'] + $monthly['da'],
            'gross_salary_input' => $grossSalaryInput,
            'basic' => $basic,
            'da' => $da,
            'basic_plus_da' => $basicPlusDa,
            'pf_rate_of_wages' => $pfRateOfWages,
            'other_allowances' => $otherAllowances,
            'gross_salary' => $grossSalary,
            'ee_pf' => $eePf,
            'esi' => $esi,
            'pt' => $pt,
            'advance' => $advance,
            'security_deposit' => $securityDeposit,
            'total_deductions' => $totalDeductions,
            'take_home_salary' => $takeHomeSalary,
            'er_pf' => $erPf,
            'er_esi' => $erEsi,
            'ctc' => $grossSalary + $erPf + $erEsi,
        ];
    }

    private static function monthlyBasicDa(?string $empId, ?string $state, ?string $designation): array
    {
        $employeeOverride = self::BASIC_DA_BY_EMPLOYEE[trim((string) $empId)] ?? null;

        if (is_array($employeeOverride)) {
            return $employeeOverride;
        }

        $stateKey = self::stateKey($state);
        $tierKey = self::designationTier($stateKey, $designation);

        return self::BASIC_DA_BY_STATE_AND_TIER[$stateKey][$tierKey]
            ?? self::defaultBasicDa($stateKey);
    }

    private static function stateKey(?string $state): string
    {
        $state = strtoupper(trim((string) $state));

        if ($state === 'KA' || str_contains($state, 'KARNATAKA')) {
            return 'KA';
        }

        if ($state === 'TN' || str_contains($state, 'TAMIL') || str_contains($state, 'PONDICHERRY') || str_contains($state, 'PUDUCHERRY')) {
            return 'TN';
        }

        if (str_contains($state, 'ANDHRA') || str_contains($state, 'TELANGANA') || in_array($state, ['AP', 'TS', 'AP-TS', 'AP/TS'], true)) {
            return 'AP-TS';
        }

        return $state;
    }

    private static function designationTier(string $stateKey, ?string $designation): string
    {
        $designation = strtolower(trim((string) $designation));
        $normalized = preg_replace('/[_\s]+/', '-', $designation) ?? '';

        return match ($stateKey) {
            'KA' => self::karnatakaTier($normalized),
            'AP-TS' => self::apTelanganaTier($normalized),
            'TN' => self::tamilNaduTier($normalized),
            default => '',
        };
    }

    private static function karnatakaTier(string $designation): string
    {
        if (
            self::containsAny($designation, ['senior', 'cashier', 'zonal'])
            || self::isBmDesignation($designation)
        ) {
            return 'senior';
        }

        if (self::containsAny($designation, ['driver', 'bouncer', 'housekeeping', 'house-keeping', 'house-keeper', 'gunman', 'junior'])) {
            return 'junior';
        }

        return 'middle';
    }

    private static function apTelanganaTier(string $designation): string
    {
        if (self::containsAny($designation, ['abm', 'assistant-branch-manager'])) {
            return 'abm';
        }

        if (self::isBmDesignation($designation)) {
            return 'bm';
        }

        return 'te';
    }

    private static function tamilNaduTier(string $designation): string
    {
        if (self::containsAny($designation, ['gunman', 'gun-man'])) {
            return 'gunman';
        }

        if (self::containsAny($designation, ['abm', 'assistant-branch-manager'])) {
            return 'abm';
        }

        if (self::isBmDesignation($designation)) {
            return 'bm';
        }

        return 'te-junior';
    }

    private static function defaultBasicDa(string $stateKey): array
    {
        return match ($stateKey) {
            'AP-TS' => self::BASIC_DA_BY_STATE_AND_TIER['AP-TS']['te'],
            'TN' => self::BASIC_DA_BY_STATE_AND_TIER['TN']['te-junior'],
            default => self::BASIC_DA_BY_STATE_AND_TIER['KA']['middle'],
        };
    }

    private static function containsAny(string $value, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($value === $needle || str_contains($value, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function isBmDesignation(string $designation): bool
    {
        return $designation === 'bm'
            || str_contains($designation, 'branch-manager')
            || str_starts_with($designation, 'bm-')
            || str_ends_with($designation, '-bm');
    }
}
