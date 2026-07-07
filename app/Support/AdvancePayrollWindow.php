<?php

namespace Tests\Unit;

use App\Support\AdvancePayrollWindow;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdvancePayrollWindowTest extends TestCase
{
    public function test_payroll_month_window_uses_23rd_to_next_month_11th(): void
    {
        $window = AdvancePayrollWindow::forPayrollMonth(
            Carbon::parse('2026-04-01', 'Asia/Kolkata')
        );

        $this->assertSame('2026-04-23', $window['start']->toDateString());
        $this->assertSame('2026-05-11', $window['end']->toDateString());
    }

    public function test_selected_payroll_month_window_stays_empty_before_23rd(): void
    {
        $window = AdvancePayrollWindow::currentOpenWindowForPayrollMonth(
            Carbon::parse('2026-05-01', 'Asia/Kolkata'),
            Carbon::parse('2026-05-05', 'Asia/Kolkata')
        );

        $this->assertSame('2026-05-23', $window['start']->toDateString());
        $this->assertSame('2026-05-22', $window['end']->toDateString());
    }

    public function test_selected_previous_payroll_month_window_remains_open_until_the_11th(): void
    {
        $window = AdvancePayrollWindow::currentOpenWindowForPayrollMonth(
            Carbon::parse('2026-04-01', 'Asia/Kolkata'),
            Carbon::parse('2026-05-05', 'Asia/Kolkata')
        );

        $this->assertSame('2026-04-23', $window['start']->toDateString());
        $this->assertSame('2026-05-05', $window['end']->toDateString());
    }

    public function test_selected_previous_payroll_month_window_stops_at_the_11th_after_cutoff(): void
    {
        $window = AdvancePayrollWindow::currentOpenWindowForPayrollMonth(
            Carbon::parse('2026-04-01', 'Asia/Kolkata'),
            Carbon::parse('2026-05-20', 'Asia/Kolkata')
        );

        $this->assertSame('2026-04-23', $window['start']->toDateString());
        $this->assertSame('2026-05-11', $window['end']->toDateString());
    }

    public function test_selected_payroll_month_window_collects_advances_after_23rd(): void
    {
        $window = AdvancePayrollWindow::currentOpenWindowForPayrollMonth(
            Carbon::parse('2026-05-01', 'Asia/Kolkata'),
            Carbon::parse('2026-05-28', 'Asia/Kolkata')
        );

        $this->assertSame('2026-05-23', $window['start']->toDateString());
        $this->assertSame('2026-05-28', $window['end']->toDateString());
    }

    public function test_may_5_is_outside_the_may_salary_advance_window(): void
    {
        $window = AdvancePayrollWindow::forPayrollMonth(
            Carbon::parse('2026-05-01', 'Asia/Kolkata')
        );

        $advanceDate = Carbon::parse('2026-05-05', 'Asia/Kolkata');

        $this->assertFalse($advanceDate->betweenIncluded($window['start'], $window['end']));
    }

    public function test_first_eleven_days_use_previous_month_salary_advance_window(): void
    {
        $window = AdvancePayrollWindow::currentOpenWindow(
            Carbon::parse('2026-06-05', 'Asia/Kolkata')
        );

        $this->assertSame('2026-05-23', $window['start']->toDateString());
        $this->assertSame('2026-06-05', $window['end']->toDateString());
    }

    public function test_salary_payment_day_has_no_open_advance_window(): void
    {
        $this->assertNull(AdvancePayrollWindow::currentOpenWindow(
            Carbon::parse('2026-06-12', 'Asia/Kolkata')
        ));
    }

    public function test_june_5_advance_date_belongs_to_may_salary_cycle(): void
    {
        $window = AdvancePayrollWindow::forAdvanceDate(
            Carbon::parse('2026-06-05', 'Asia/Kolkata')
        );

        $this->assertSame('2026-05-23', $window['start']->toDateString());
        $this->assertSame('2026-06-11', $window['end']->toDateString());
    }

    public function test_date_between_salary_cycles_has_no_deduction_cycle(): void
    {
        $this->assertNull(AdvancePayrollWindow::forAdvanceDate(
            Carbon::parse('2026-06-15', 'Asia/Kolkata')
        ));
    }
}
