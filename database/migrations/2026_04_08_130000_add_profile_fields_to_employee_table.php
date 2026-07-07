<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee')) {
            return;
        }

        Schema::table('employee', function (Blueprint $table): void {
            if (! Schema::hasColumn('employee', 'mailId')) {
                $table->string('mailId')->nullable()->after('contact');
            }

            if (! Schema::hasColumn('employee', 'location')) {
                $table->string('location')->nullable()->after('address');
            }

            if (! Schema::hasColumn('employee', 'photo')) {
                $table->string('photo')->nullable()->after('location');
            }

            if (! Schema::hasColumn('employee', 'rating')) {
                $table->unsignedTinyInteger('rating')->nullable()->after('photo');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employee')) {
            return;
        }

        Schema::table('employee', function (Blueprint $table): void {
            $columnsToDrop = [];

            foreach (['mailId', 'location', 'photo', 'rating'] as $column) {
                if (Schema::hasColumn('employee', $column)) {
                    $columnsToDrop[] = $column;
                }
            }

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
