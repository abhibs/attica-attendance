<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_bank_detail_requests')) {
            return;
        }

        Schema::create('employee_bank_detail_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('employee_id')->index();
            $table->string('emp_id', 50)->index();
            $table->string('status', 30)->default('pending')->index();
            $table->text('request_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->string('requested_emp_name', 150)->nullable();
            $table->string('requested_bank_name', 150)->nullable();
            $table->string('requested_bank_ac_no', 50)->nullable();
            $table->string('requested_ifsc_code', 30)->nullable();
            $table->string('requested_uan_number', 30)->nullable();
            $table->string('requested_passbook_doc', 255)->nullable();
            $table->string('approved_by', 100)->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->string('verified_by', 100)->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_bank_detail_requests');
    }
};
