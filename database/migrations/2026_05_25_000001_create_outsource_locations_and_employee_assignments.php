<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('outsource_locations')) {
            Schema::create('outsource_locations', function (Blueprint $table): void {
                $table->id();
                $table->string('location_code')->unique();
                $table->string('name');
                $table->string('addressline')->nullable();
                $table->string('area')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('pincode')->nullable();
                $table->string('url')->nullable();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->unsignedTinyInteger('status')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('outsource_employee_locations')) {
            Schema::create('outsource_employee_locations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('outsource_location_id');
                $table->timestamps();

                $table->unique(
                    ['employee_id', 'outsource_location_id'],
                    'outsource_employee_location_unique'
                );
                $table->index('outsource_location_id', 'outsource_employee_location_location_idx');
            });
        }

        if (Schema::hasTable('employee')) {
            Schema::table('employee', function (Blueprint $table): void {
                if (! Schema::hasColumn('employee', 'is_outsourced')) {
                    $table->unsignedTinyInteger('is_outsourced')
                        ->default(0)
                        ->after('is_night_shift');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employee') && Schema::hasColumn('employee', 'is_outsourced')) {
            Schema::table('employee', function (Blueprint $table): void {
                $table->dropColumn('is_outsourced');
            });
        }

        Schema::dropIfExists('outsource_employee_locations');
        Schema::dropIfExists('outsource_locations');
    }
};
