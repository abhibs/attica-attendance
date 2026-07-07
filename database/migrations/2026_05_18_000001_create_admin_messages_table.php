<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sender_admin_id')->constrained('admins')->cascadeOnDelete();
            $table->foreignId('receiver_admin_id')->constrained('admins')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['sender_admin_id', 'receiver_admin_id', 'created_at'], 'admin_messages_conversation_index');
            $table->index(['receiver_admin_id', 'read_at'], 'admin_messages_unread_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_messages');
    }
};
