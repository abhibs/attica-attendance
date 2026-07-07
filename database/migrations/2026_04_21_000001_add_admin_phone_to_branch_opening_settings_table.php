<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('branch_opening_settings') && ! Schema::hasColumn('branch_opening_settings', 'admin_phone')) {
            Schema::table('branch_opening_settings', function (Blueprint $table): void {
                $table->string('admin_phone', 30)->nullable()->after('opening_time');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('branch_opening_settings') && Schema::hasColumn('branch_opening_settings', 'admin_phone')) {
            Schema::table('branch_opening_settings', function (Blueprint $table): void {
                $table->dropColumn('admin_phone');
            });
        }
    }
};
