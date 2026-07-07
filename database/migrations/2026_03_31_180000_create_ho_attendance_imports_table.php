<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ho_attendance_imports', function (Blueprint $table) {
            $table->id();
            $table->string('source_file');
            $table->string('import_batch', 50);
            $table->unsignedInteger('source_row_no');
            $table->string('emp_id', 100);
            $table->string('employee_name')->default('');
            $table->string('branch_name')->default('HO');
            $table->date('attendance_date');
            $table->time('login_time')->nullable();
            $table->time('logout_time')->nullable();
            $table->string('attendance_status', 100)->default('');
            $table->string('work_duration', 50)->default('');
            $table->string('late_bucket', 30)->default('Unknown');
            $table->longText('raw_row')->nullable();
            $table->char('row_hash', 32)->unique();
            $table->timestamps();

            $table->index(['emp_id', 'attendance_date']);
            $table->index('attendance_date');
            $table->index('import_batch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ho_attendance_imports');
    }
};
