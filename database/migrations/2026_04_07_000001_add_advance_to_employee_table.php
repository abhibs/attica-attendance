<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee') || Schema::hasColumn('employee', 'advance')) {
            return;
        }

        Schema::table('employee', function (Blueprint $table): void {
            $table->decimal('advance', 12, 2)->default(0)->after('salary');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employee') || ! Schema::hasColumn('employee', 'advance')) {
            return;
        }

        Schema::table('employee', function (Blueprint $table): void {
            $table->dropColumn('advance');
        });
    }
};
