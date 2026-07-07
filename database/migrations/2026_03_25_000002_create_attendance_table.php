<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->string('empId');
            $table->string('check_in_branch_id')->nullable();
            $table->string('check_out_branch_id')->nullable();
            $table->string('photo_path');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('check_out_latitude', 10, 7)->nullable();
            $table->decimal('check_out_longitude', 10, 7)->nullable();
            $table->date('check_in_date');
            $table->time('check_in_time');
            $table->decimal('check_in_distance', 10, 2)->nullable();
            $table->date('check_out_date')->nullable();
            $table->time('check_out_time')->nullable();
            $table->decimal('check_out_distance', 10, 2)->nullable();
            $table->timestamps();

            $table->index(['empId', 'check_in_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance');
    }
};
