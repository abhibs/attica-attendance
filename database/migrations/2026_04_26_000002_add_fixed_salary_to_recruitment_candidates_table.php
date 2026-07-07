<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table): void {
            $table->decimal('fixed_salary', 12, 2)->nullable()->after('onboarding_payload');
        });

        DB::table('recruitment_candidates')
            ->select(['id', 'fixed_salary', 'onboarding_payload'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    if ($row->fixed_salary !== null) {
                        continue;
                    }

                    $payload = json_decode((string) ($row->onboarding_payload ?? '[]'), true);
                    $fixedSalary = $this->normalizedDecimal(data_get($payload, 'fixed_salary'));

                    if ($fixedSalary === null) {
                        continue;
                    }

                    DB::table('recruitment_candidates')
                        ->where('id', $row->id)
                        ->update(['fixed_salary' => $fixedSalary]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table): void {
            $table->dropColumn('fixed_salary');
        });
    }

    private function normalizedDecimal($value): ?string
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '' || ! is_numeric($trimmed)) {
            return null;
        }

        return number_format((float) $trimmed, 2, '.', '');
    }
};
