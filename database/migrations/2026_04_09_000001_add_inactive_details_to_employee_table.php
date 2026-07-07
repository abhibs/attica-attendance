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
            if (! Schema::hasColumn('employee', 'inactive_reason')) {
                $table->text('inactive_reason')->nullable()->after('status');
            }

            if (! Schema::hasColumn('employee', 'last_working_date')) {
                $table->date('last_working_date')->nullable()->after('inactive_reason');
            }

            if (! Schema::hasColumn('employee', 'last_login_branch_id')) {
                $table->string('last_login_branch_id')->nullable()->after('last_working_date');
            }

            if (! Schema::hasColumn('employee', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('last_login_branch_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employee')) {
            return;
        }

        Schema::table('employee', function (Blueprint $table): void {
            $columnsToDrop = [];

            foreach (['inactive_reason', 'last_working_date', 'last_login_branch_id', 'last_login_at'] as $column) {
                if (Schema::hasColumn('employee', $column)) {
                    $columnsToDrop[] = $column;
                }
            }

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
