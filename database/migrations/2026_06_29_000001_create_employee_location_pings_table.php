<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_location_pings')) {
            return;
        }

        Schema::create('employee_location_pings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->string('emp_id', 100)->index();
            $table->unsignedBigInteger('attendance_id')->nullable()->index();
            $table->string('branch_id', 50)->nullable()->index();
            $table->decimal('latitude', 11, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('branch_latitude', 11, 8)->nullable();
            $table->decimal('branch_longitude', 11, 8)->nullable();
            $table->decimal('distance_meters', 10, 2)->nullable();
            $table->boolean('is_out_of_office')->default(false)->index();
            $table->timestamp('recorded_at')->index();
            $table->timestamps();

            $table->index(['emp_id', 'recorded_at'], 'employee_location_emp_recorded_idx');
            $table->index(['branch_id', 'recorded_at'], 'employee_location_branch_recorded_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_location_pings');
    }
};
