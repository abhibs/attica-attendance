<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('admins', 'position')) {
            $afterColumn = Schema::hasColumn('admins', 'role') ? 'role' : 'password_hint';

            Schema::table('admins', function (Blueprint $table) use ($afterColumn): void {
                $table->string('position')->nullable()->after($afterColumn);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('admins', 'position')) {
            Schema::table('admins', function (Blueprint $table): void {
                $table->dropColumn('position');
            });
        }
    }
};
