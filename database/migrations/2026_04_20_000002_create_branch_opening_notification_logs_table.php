<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('branch_opening_notification_logs')) {
            Schema::create('branch_opening_notification_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('branch_id', 50);
                $table->unsignedBigInteger('employee_id')->default(0);
                $table->string('notification_type', 40);
                $table->string('notification_key', 120);
                $table->string('title', 150)->nullable();
                $table->text('body')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['branch_id', 'employee_id', 'notification_type', 'notification_key'],
                    'branch_opening_notification_log_unique'
                );
                $table->index(['notification_type', 'notification_key'], 'branch_opening_log_type_key_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_opening_notification_logs');
    }
};
