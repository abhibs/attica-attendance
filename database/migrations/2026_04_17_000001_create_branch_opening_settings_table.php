<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('branch_opening_settings')) {
            Schema::create('branch_opening_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('branch_id', 50)->unique();
                $table->time('opening_time');
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->index('opening_time');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_opening_settings');
    }
};
