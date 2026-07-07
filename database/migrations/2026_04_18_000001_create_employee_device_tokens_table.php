<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_device_tokens')) {
            Schema::create('employee_device_tokens', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->text('token');
                $table->char('token_hash', 64)->unique();
                $table->string('platform', 20)->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();

                $table->index('employee_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_device_tokens');
    }
};
