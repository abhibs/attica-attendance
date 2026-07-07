<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_salary_holds')) {
            return;
        }

        Schema::create('employee_salary_holds', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('employee_id')->index();
            $table->string('emp_id', 50)->index();
            $table->date('payroll_month')->index();
            $table->string('reason')->nullable();
            $table->string('held_by', 150)->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'payroll_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_holds');
    }
};
