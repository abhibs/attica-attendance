<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_requests')) {
            return;
        }

        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('emp_id', 50);
            $table->date('leave_date');
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->text('review_note')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'leave_date']);
            $table->index(['status', 'leave_date']);
            $table->index(['emp_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
