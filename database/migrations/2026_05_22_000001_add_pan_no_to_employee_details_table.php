<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employeeDetails') || Schema::hasColumn('employeeDetails', 'panNo')) {
            return;
        }

        Schema::table('employeeDetails', function (Blueprint $table): void {
            $table->string('panNo', 20)->default('')->after('aadhaarNo');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employeeDetails') || ! Schema::hasColumn('employeeDetails', 'panNo')) {
            return;
        }

        Schema::table('employeeDetails', function (Blueprint $table): void {
            $table->dropColumn('panNo');
        });
    }
};
