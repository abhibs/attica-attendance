<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('branch_opening_assignments')) {
            Schema::create('branch_opening_assignments', function (Blueprint $table): void {
                $table->id();
                $table->string('branch_id', 50);
                $table->unsignedBigInteger('employee_id');
                $table->string('assignment_type', 30);
                $table->unsignedBigInteger('assigned_by')->nullable();
                $table->timestamps();

                $table->unique(['branch_id', 'employee_id', 'assignment_type'], 'branch_opening_assignment_unique');
                $table->index(['branch_id', 'assignment_type']);
                $table->index('employee_id');
            });
        }

        DB::table('admins')->updateOrInsert(
            ['email' => 'hradmin@attica.local'],
            [
                'name' => 'HRadmin',
                'phone' => 9686266994,
                'password' => Hash::make('12345678'),
                'password_hint' => '12345678',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_opening_assignments');
    }
};
