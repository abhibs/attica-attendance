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
            if (! Schema::hasColumn('employee', 'assigned_branch_id')) {
                $table->string('assigned_branch_id')->nullable()->after('location')->index();
            }

            if (! Schema::hasColumn('employee', 'pf_eligible')) {
                $table->boolean('pf_eligible')->nullable()->after('pf');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employee')) {
            return;
        }

        Schema::table('employee', function (Blueprint $table): void {
            if (Schema::hasColumn('employee', 'assigned_branch_id')) {
                $table->dropColumn('assigned_branch_id');
            }

            if (Schema::hasColumn('employee', 'pf_eligible')) {
                $table->dropColumn('pf_eligible');
            }
        });
    }
};
