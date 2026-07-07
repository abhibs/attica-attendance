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
            $table->string('aadhaar_number', 12)->nullable()->after('whatsapp_number')->index();
        });

        DB::table('recruitment_candidates')
            ->select(['id', 'aadhaar_number', 'hiring_payload'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $currentValue = $this->normalizedAadhaarNumber($row->aadhaar_number ?? null);

                    if ($currentValue !== '') {
                        continue;
                    }

                    $payload = json_decode((string) ($row->hiring_payload ?? '[]'), true);
                    $aadhaarNumber = $this->normalizedAadhaarNumber(data_get($payload, 'aadhaar_number'));

                    if ($aadhaarNumber === '') {
                        continue;
                    }

                    DB::table('recruitment_candidates')
                        ->where('id', $row->id)
                        ->update(['aadhaar_number' => $aadhaarNumber]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table): void {
            $table->dropIndex(['aadhaar_number']);
            $table->dropColumn('aadhaar_number');
        });
    }

    private function normalizedAadhaarNumber(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return strlen($digits) === 12 ? $digits : '';
    }
};
