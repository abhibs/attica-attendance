<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_day_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('emp_id', 100);
            $table->date('attendance_date');
            $table->string('final_status', 20);
            $table->string('reason', 500)->nullable();
            $table->string('created_by')->default('');
            $table->string('updated_by')->default('');
            $table->timestamps();

            $table->unique(['emp_id', 'attendance_date']);
            $table->index('attendance_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_day_overrides');
    }
};
