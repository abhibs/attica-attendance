<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_notifications')) {
            Schema::create('admin_notifications', function (Blueprint $table): void {
                $table->id();
                $table->string('audience_type', 20);
                $table->string('audience_value')->nullable();
                $table->string('title')->default('Attica Pagar');
                $table->text('body');
                $table->unsignedBigInteger('sent_by')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->index(['audience_type', 'audience_value']);
            });
        }

        if (! Schema::hasTable('employee_notification_deliveries')) {
            Schema::create('employee_notification_deliveries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('admin_notification_id')
                    ->constrained('admin_notifications')
                    ->cascadeOnDelete();
                $table->unsignedBigInteger('employee_id');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->unique(['admin_notification_id', 'employee_id'], 'employee_notification_unique');
                $table->index(['employee_id', 'read_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_notification_deliveries');
        Schema::dropIfExists('admin_notifications');
    }
};
