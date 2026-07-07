<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee')) {
            return;
        }

        Schema::table('employee', function (Blueprint $table): void {
            if (! Schema::hasColumn('employee', 'date_of_birth')) {
                $table->date('date_of_birth')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employee') || ! Schema::hasColumn('employee', 'date_of_birth')) {
            return;
        }

        Schema::table('employee', function (Blueprint $table): void {
            $table->dropColumn('date_of_birth');
        });
    }
};
