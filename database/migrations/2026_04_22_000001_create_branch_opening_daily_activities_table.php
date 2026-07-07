<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('branch_opening_daily_activities')) {
            Schema::create('branch_opening_daily_activities', function (Blueprint $table): void {
                $table->id();
                $table->string('branch_id', 50);
                $table->date('attendance_date');
                $table->timestamp('opened_at')->nullable();
                $table->unsignedBigInteger('opened_by_employee_id')->nullable();
                $table->string('opened_by_emp_id', 50)->nullable();
                $table->string('opened_by_name', 255)->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('closed_by_employee_id')->nullable();
                $table->string('closed_by_emp_id', 50)->nullable();
                $table->string('closed_by_name', 255)->nullable();
                $table->timestamps();

                $table->unique(
                    ['branch_id', 'attendance_date'],
                    'branch_opening_daily_activities_unique'
                );
                $table->index(['attendance_date', 'branch_id'], 'branch_opening_daily_activities_date_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_opening_daily_activities');
    }
};
