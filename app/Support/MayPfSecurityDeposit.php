<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class MayPfSecurityDeposit
{
    private const PAYROLL_MONTH = '2026-05';

    private const AMOUNT = 5000.0;

    private const ELIGIBLE_DESIGNATIONS = [
        'abm',
        'assistantbranchmanager',
        'bm',
        'branchmanager',
        'branchcoordinator',
        'bmbranchmanager',
        'gunman',
        'te',
        'tetransactionexecutive',
        'transactionexecutive',
    ];

    public static function amountFor(
        Carbon $payrollMonth,
        ?string $empId,
        ?string $uanNumber,
        ?string $designation,
        mixed $pfEligible = null,
        mixed $storedAmount = null
    ): float {
        if ($payrollMonth->format('Y-m') !== self::PAYROLL_MONTH) {
            return 0.0;
        }

        if (! MayPfEligibility::isEligible($payrollMonth, $empId, $uanNumber, $pfEligible)) {
            return 0.0;
        }

        if ($storedAmount !== null && $storedAmount !== '' && is_numeric($storedAmount)) {
            return max(0.0, (float) $storedAmount);
        }

        $designation = preg_replace('/[^a-z0-9]+/', '', strtolower(trim((string) $designation))) ?? '';

        return in_array($designation, self::ELIGIBLE_DESIGNATIONS, true)
            ? self::AMOUNT
            : 0.0;
    }
}
