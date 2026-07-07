<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee') || Schema::hasColumn('employee', 'pf')) {
            return;
        }

        Schema::table('employee', function (Blueprint $table): void {
            $table->decimal('pf', 12, 2)->default(0)->after('advance');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employee') || ! Schema::hasColumn('employee', 'pf')) {
            return;
        }

        Schema::table('employee', function (Blueprint $table): void {
            $table->dropColumn('pf');
        });
    }
};
