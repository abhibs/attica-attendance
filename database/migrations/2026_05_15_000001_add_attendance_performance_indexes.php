<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->index(['empId', 'id'], 'attendance_emp_id_id_idx');
            $table->index(['empId', 'check_out_date', 'id'], 'attendance_emp_checkout_id_idx');
            $table->index('check_in_date', 'attendance_check_in_date_idx');
            $table->index(['check_in_branch_id', 'check_in_date'], 'attendance_branch_checkin_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropIndex('attendance_emp_id_id_idx');
            $table->dropIndex('attendance_emp_checkout_id_idx');
            $table->dropIndex('attendance_check_in_date_idx');
            $table->dropIndex('attendance_branch_checkin_date_idx');
        });
    }
};
