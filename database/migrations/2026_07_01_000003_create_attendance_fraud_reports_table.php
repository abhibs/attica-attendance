<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_fraud_reports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->string('emp_id')->index();
            $table->unsignedBigInteger('attendance_id')->nullable()->index();
            $table->string('branch_id')->nullable()->index();
            $table->string('fraud_type')->default('mobile_screen')->index();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->string('source')->nullable()->index();
            $table->text('reason')->nullable();
            $table->string('proof_path');
            $table->timestamp('reported_at')->nullable()->index();
            $table->timestamps();

            $table->index(['emp_id', 'reported_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_fraud_reports');
    }
};
