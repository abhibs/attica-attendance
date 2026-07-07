<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateIds = DB::table('employee')
            ->selectRaw('TRIM(empId) as emp_id, COUNT(*) as total')
            ->groupByRaw('TRIM(empId)')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('total', 'emp_id');

        if ($duplicateIds->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot add a unique employee ID index because duplicate IDs already exist: '
                .implode(', ', array_keys($duplicateIds->all()))
            );
        }

        $blankIdCount = DB::table('employee')
            ->whereRaw("TRIM(COALESCE(empId, '')) = ''")
            ->count();

        if ($blankIdCount > 0) {
            throw new RuntimeException(
                'Cannot add a unique employee ID index because blank employee IDs already exist.'
            );
        }

        Schema::table('employee', function (Blueprint $table): void {
            $table->unique('empId');
        });
    }

    public function down(): void
    {
        Schema::table('employee', function (Blueprint $table): void {
            $table->dropUnique(['empId']);
        });
    }
};
