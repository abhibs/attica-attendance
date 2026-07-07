<?php

use App\Models\RecruitmentCandidate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee') || ! Schema::hasTable('recruitment_candidates')) {
            return;
        }

        RecruitmentCandidate::query()
            ->where('status', RecruitmentCandidate::STATUS_JOINED)
            ->whereNotNull('generated_emp_id')
            ->orderBy('id')
            ->chunkById(100, function ($candidates): void {
                foreach ($candidates as $candidate) {
                    $empId = trim((string) $candidate->generated_emp_id);
                    $dateOfJoining = $this->dateInputValue(data_get($candidate->onboarding_payload, 'date_of_joining'));

                    if ($empId === '' || $dateOfJoining === '') {
                        continue;
                    }

                    DB::table('employee')
                        ->whereRaw('TRIM(empId) = ?', [$empId])
                        ->update(['doj' => $dateOfJoining]);
                }
            });
    }

    public function down(): void
    {
        //
    }

    private function dateInputValue($value): string
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return '';
        }

        $timezone = config('app.timezone', 'Asia/Kolkata');
        $formats = [
            'Y-m-d',
            'Y/m/d',
            'd-m-Y',
            'j-n-Y',
            'd/m/Y',
            'j/n/Y',
            'd.m.Y',
            'j.n.Y',
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $trimmed, $timezone);
                $errors = Carbon::getLastErrors();
            } catch (\Throwable) {
                continue;
            }

            if (
                ($errors['warning_count'] ?? 0) === 0
                && ($errors['error_count'] ?? 0) === 0
                && $date->format($format) === $trimmed
            ) {
                return $date->toDateString();
            }
        }

        try {
            return Carbon::parse($trimmed, $timezone)->toDateString();
        } catch (\Throwable) {
            return '';
        }
    }
};
