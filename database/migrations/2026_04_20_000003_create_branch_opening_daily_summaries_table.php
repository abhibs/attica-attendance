<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('branch_opening_daily_summaries')) {
            Schema::create('branch_opening_daily_summaries', function (Blueprint $table): void {
                $table->id();
                $table->string('branch_id', 50);
                $table->string('branch_name')->nullable();
                $table->date('attendance_date');
                $table->time('scheduled_opening_time')->nullable();
                $table->string('opening_status', 30)->default('pending');
                $table->timestamp('opened_at')->nullable();
                $table->unsignedBigInteger('opened_by_employee_id')->nullable();
                $table->string('opened_by_emp_id', 50)->nullable();
                $table->string('opened_by_name')->nullable();
                $table->timestamp('first_check_in_at')->nullable();
                $table->string('first_check_in_emp_id', 50)->nullable();
                $table->string('first_check_in_name')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('closed_by_employee_id')->nullable();
                $table->string('closed_by_emp_id', 50)->nullable();
                $table->string('closed_by_name')->nullable();
                $table->unsignedInteger('total_check_ins')->default(0);
                $table->unsignedInteger('total_check_outs')->default(0);
                $table->unsignedInteger('opening_delay_minutes')->default(0);
                $table->unsignedInteger('open_duration_minutes')->nullable();
                $table->timestamps();

                $table->unique(['branch_id', 'attendance_date'], 'branch_opening_daily_summary_unique');
                $table->index(['attendance_date', 'opening_status'], 'branch_open_daily_date_status_idx');
                $table->index(['branch_id', 'attendance_date'], 'branch_open_daily_branch_date_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_opening_daily_summaries');
    }
};
