<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('te_tracker_visits')) {
            return;
        }

        Schema::create('te_tracker_visits', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('emp_id', 50);
            $table->string('branch_id', 50);
            $table->string('branch_name')->nullable();
            $table->decimal('branch_latitude', 10, 7)->nullable();
            $table->decimal('branch_longitude', 10, 7)->nullable();
            $table->decimal('captured_latitude', 10, 7);
            $table->decimal('captured_longitude', 10, 7);
            $table->decimal('distance_from_branch', 10, 2)->nullable();
            $table->string('photo_path');
            $table->date('visit_date');
            $table->time('visit_time');
            $table->timestamps();

            $table->index(['employee_id', 'visit_date']);
            $table->index(['emp_id', 'visit_date']);
            $table->index(['branch_id', 'visit_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('te_tracker_visits');
    }
};
