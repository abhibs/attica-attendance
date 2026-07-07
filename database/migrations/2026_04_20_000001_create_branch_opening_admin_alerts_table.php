<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('branch_opening_admin_alerts')) {
            Schema::create('branch_opening_admin_alerts', function (Blueprint $table): void {
                $table->id();
                $table->string('branch_id', 50);
                $table->string('branch_name')->nullable();
                $table->date('opening_date');
                $table->time('opening_time');
                $table->string('status', 30)->default('overdue');
                $table->timestamp('notified_at')->nullable();
                $table->timestamp('opened_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->unsignedInteger('overdue_minutes')->default(0);
                $table->unsignedBigInteger('opener_employee_id')->nullable();
                $table->string('opener_emp_id', 50)->nullable();
                $table->string('opener_name')->nullable();
                $table->timestamps();

                $table->unique(['branch_id', 'opening_date'], 'branch_opening_admin_alert_unique');
                $table->index(['status', 'opening_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_opening_admin_alerts');
    }
};
