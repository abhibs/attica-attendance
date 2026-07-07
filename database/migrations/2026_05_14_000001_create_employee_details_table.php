<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employeeDetails')) {
            return;
        }

        Schema::create('employeeDetails', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('empName', 150);
            $table->string('employeeId', 50)->index();
            $table->string('designation', 50);
            $table->string('bankName', 150);
            $table->string('bankAcNo', 50);
            $table->string('ifscCode', 30);
            $table->string('passbookDoc', 255)->default('');
            $table->decimal('salary', 12, 2)->default(0);
            $table->string('branchId', 20)->index();
            $table->string('status', 20)->default('Active');
            $table->string('accountVerified', 20)->default('Pending');
            $table->date('date');
            $table->time('time');
            $table->decimal('totalWorkingDays', 6, 2)->default(0);
            $table->decimal('absentDays', 6, 2)->default(0);
            $table->decimal('presentDays', 6, 2)->default(0);
            $table->decimal('penalty', 10, 2)->default(0);
            $table->decimal('advanceSalary', 10, 2)->default(0);
            $table->decimal('finalSalary', 10, 2)->default(0);
            $table->string('salaryPaymentStatus', 20)->default('');
            $table->date('salaryVerifiedDate')->nullable();
            $table->time('salaryVerifiedTime')->nullable();
            $table->date('salaryPaidDate')->nullable();
            $table->time('salaryPaidTime')->nullable();
            $table->string('salaryPaidBy', 50)->default('');
            $table->string('salaryBankName', 50)->default('');
            $table->string('salaryProcessingBy', 50)->default('');
            $table->string('salaryProcessingUser', 100)->default('');
            $table->dateTime('salaryProcessingTime')->nullable();
            $table->string('aadhaarNo', 20)->default('');
            $table->string('uanNumber', 30)->default('');
            $table->decimal('pfAmount', 10, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->date('salaryDate')->nullable();
        });
    }

    public function down(): void
    {
        // This table is managed outside Laravel and contains prefilled employee data.
    }
};
