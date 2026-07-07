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
            if (! Schema::hasColumn('employee', 'pfsecuritydeposit')) {
                $table->decimal('pfsecuritydeposit', 12, 2)->nullable()->after('pf');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employee')) {
            return;
        }

        Schema::table('employee', function (Blueprint $table): void {
            if (Schema::hasColumn('employee', 'pfsecuritydeposit')) {
                $table->dropColumn('pfsecuritydeposit');
            }
        });
    }

};
