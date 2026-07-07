<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_advance_transactions')) {
            return;
        }

        Schema::create('employee_advance_transactions', function (Blueprint $table): void {
            $table->id();
            $table->integer('employee_id');
            $table->string('emp_id', 50);
            $table->date('advance_date');
            $table->decimal('amount', 12, 2);
            $table->string('source_type', 20)->default('manual');
            $table->string('source_file')->nullable();
            $table->unsignedInteger('source_row_no')->nullable();
            $table->string('row_hash', 64)->nullable()->unique();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['emp_id', 'advance_date']);
            $table->index('employee_id');
        });

        $today = Carbon::now(config('app.timezone', 'Asia/Kolkata'))->toDateString();
        $employees = DB::table('employee')
            ->select(['id', 'empId', 'advance'])
            ->whereNotNull('advance')
            ->where('advance', '>', 0)
            ->get();

        foreach ($employees as $employee) {
            DB::table('employee_advance_transactions')->insert([
                'employee_id' => $employee->id,
                'emp_id' => trim((string) $employee->empId),
                'advance_date' => $today,
                'amount' => $employee->advance,
                'source_type' => 'opening',
                'remarks' => 'Opening balance migrated from employee.advance',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_advance_transactions');
    }
};
