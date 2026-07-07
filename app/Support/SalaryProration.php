<?php

namespace App\Support;

class SalaryProration
{
    public static function dailyRate(float $monthlySalary, int $daysInMonth): float
    {
        return $daysInMonth > 0 ? round($monthlySalary / $daysInMonth, 2) : 0.0;
    }

    public static function grossPayable(float $monthlySalary, int $daysInMonth, float $payableDays): float
    {
        return $daysInMonth > 0
            ? round(($monthlySalary / $daysInMonth) * $payableDays)
            : 0.0;
    }
}
