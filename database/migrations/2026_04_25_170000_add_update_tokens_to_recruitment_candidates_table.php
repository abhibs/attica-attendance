<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table): void {
            $table->string('hiring_update_token')->nullable()->unique()->after('submission_code');
            $table->timestamp('hiring_update_requested_at')->nullable()->after('hiring_update_token');
            $table->string('joining_update_token')->nullable()->unique()->after('hiring_update_requested_at');
            $table->timestamp('joining_update_requested_at')->nullable()->after('joining_update_token');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table): void {
            $table->dropUnique(['hiring_update_token']);
            $table->dropUnique(['joining_update_token']);
            $table->dropColumn([
                'hiring_update_token',
                'hiring_update_requested_at',
                'joining_update_token',
                'joining_update_requested_at',
            ]);
        });
    }
};
