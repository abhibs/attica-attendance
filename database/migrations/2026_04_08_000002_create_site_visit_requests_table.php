<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_visit_requests')) {
            return;
        }

        Schema::create('site_visit_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('emp_id', 50);
            $table->date('visit_date');
            $table->string('site_location');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('photo_path');
            $table->text('reason');
            $table->string('approved_by');
            $table->string('status', 20)->default('pending');
            $table->text('review_note')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('attendance_id')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'visit_date']);
            $table->index(['status', 'visit_date']);
            $table->index(['emp_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_visit_requests');
    }
};
