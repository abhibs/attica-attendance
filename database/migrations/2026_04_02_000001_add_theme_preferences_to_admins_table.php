<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->string('theme_preference')->default('blue-theme')->after('image');
            $table->string('card_style')->default('rounded')->after('theme_preference');
            $table->string('table_density')->default('comfortable')->after('card_style');
            $table->boolean('sidebar_collapsed')->default(false)->after('table_density');
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn([
                'theme_preference',
                'card_style',
                'table_density',
                'sidebar_collapsed',
            ]);
        });
    }
};
