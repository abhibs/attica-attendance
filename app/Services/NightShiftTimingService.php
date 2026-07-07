<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Support\Carbon;

class NightShiftTimingService
{
    public const WINDOW_HOURS = 6;

    public function isWithinCheckOutWindow(Employee $employee, Attendance $attendance, Carbon $now, ?string $timezone = null): bool
    {
        $deadline = $this->checkOutDeadline($employee, $attendance, $timezone);

        return $deadline !== null && $now->lessThanOrEqualTo($deadline);
    }

    public function checkOutDeadline(Employee $employee, Attendance $attendance, ?string $timezone = null): ?Carbon
    {
        if (! $attendance->check_in_date || ! $attendance->check_in_time) {
            return null;
        }

        $timezone ??= config('app.timezone', 'Asia/Kolkata');
        $checkInAt = Carbon::parse($attendance->check_in_date.' '.$attendance->check_in_time, $timezone);
        $schedule = $this->resolveTimingRange(trim((string) $employee->shift_timing));

        if (! $schedule['start']) {
            return $checkInAt->copy()->addHours(self::WINDOW_HOURS);
        }

        $shiftStartAt = Carbon::parse($attendance->check_in_date.' '.$schedule['start'], $timezone);
        $shiftEndAt = $schedule['end']
            ? Carbon::parse($attendance->check_in_date.' '.$schedule['end'], $timezone)
            : $shiftStartAt->copy()->addHours(self::WINDOW_HOURS);

        if ($shiftEndAt->lte($shiftStartAt)) {
            $shiftEndAt->addDay();
        }

        return $shiftEndAt->copy()->addHours(self::WINDOW_HOURS);
    }

    public function canCheckInNow(Employee $employee, Carbon $now, ?string $timezone = null): bool
    {
        $timezone ??= config('app.timezone', 'Asia/Kolkata');
        $schedule = $this->resolveTimingRange(trim((string) $employee->shift_timing));

        if (! $schedule['start']) {
            return true;
        }

        foreach ([-1, 0, 1] as $offsetDays) {
            $shiftStartAt = Carbon::parse(
                $now->copy()->addDays($offsetDays)->toDateString().' '.$schedule['start'],
                $timezone
            );

            if ($now->betweenIncluded(
                $shiftStartAt->copy()->subHours(self::WINDOW_HOURS),
                $shiftStartAt->copy()->addHours(self::WINDOW_HOURS)
            )) {
                return true;
            }
        }

        return false;
    }

    public function configuredTimingLabel(Employee $employee): string
    {
        $schedule = $this->resolveTimingRange(trim((string) $employee->shift_timing));

        if (! $schedule['start']) {
            return '';
        }

        $startLabel = Carbon::createFromFormat('H:i:s', $schedule['start'])->format('h:i A');
        $endTime = $schedule['end'];

        if (! $endTime) {
            $endTime = Carbon::createFromFormat('H:i:s', $schedule['start'])
                ->addHours(self::WINDOW_HOURS)
                ->format('H:i:s');
        }

        $endLabel = Carbon::createFromFormat('H:i:s', $endTime)->format('h:i A');

        return $startLabel.' - '.$endLabel;
    }

    private function resolveTimingRange(string $value): array
    {
        if ($value === '') {
            return [
                'start' => null,
                'end' => null,
            ];
        }

        preg_match_all(
            '/\d{1,2}(?::\d{2})?(?::\d{2})?\s*(?:[APap][Mm])?/',
            str_replace([' to ', ' TO ', 'â€“', 'â€”'], '-', $value),
            $matches
        );

        $times = collect($matches[0] ?? [])
            ->map(fn ($time): ?string => $this->normalizeTime($time))
            ->filter()
            ->values();

        return [
            'start' => $times->get(0),
            'end' => $times->get(1),
        ];
    }

    private function normalizeTime(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^\d{1,2}$/', $trimmed) === 1) {
            $hour = (int) $trimmed;

            if ($hour >= 0 && $hour <= 23) {
                return sprintf('%02d:00:00', $hour);
            }
        }

        $formats = ['H:i:s', 'H:i', 'g:i A', 'g:iA', 'h:i A', 'h:iA', 'g A', 'gA', 'h A', 'hA'];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, strtoupper($trimmed))->format('H:i:s');
            } catch (\Throwable $exception) {
                continue;
            }
        }

        try {
            return Carbon::parse($trimmed)->format('H:i:s');
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
