<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employee', 'is_night_shift')) {
            Schema::table('employee', function (Blueprint $table): void {
                $table->boolean('is_night_shift')->default(false)->after('shift_timing');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employee', 'is_night_shift')) {
            Schema::table('employee', function (Blueprint $table): void {
                $table->dropColumn('is_night_shift');
            });
        }
    }
};
