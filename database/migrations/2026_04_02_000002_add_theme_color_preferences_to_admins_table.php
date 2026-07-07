<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->string('theme_primary_color', 7)->nullable()->after('sidebar_collapsed');
            $table->string('theme_background_color', 7)->nullable()->after('theme_primary_color');
            $table->string('theme_surface_color', 7)->nullable()->after('theme_background_color');
            $table->string('theme_text_color', 7)->nullable()->after('theme_surface_color');
            $table->string('theme_muted_text_color', 7)->nullable()->after('theme_text_color');
            $table->string('theme_border_color', 7)->nullable()->after('theme_muted_text_color');
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn([
                'theme_primary_color',
                'theme_background_color',
                'theme_surface_color',
                'theme_text_color',
                'theme_muted_text_color',
                'theme_border_color',
            ]);
        });
    }
};
