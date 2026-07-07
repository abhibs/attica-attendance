<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->string('attendance_status_override')
                ->nullable()
                ->after('check_out_distance');
        });

        Schema::table('employee', function (Blueprint $table) {
            $table->date('attendance_blocked_on')
                ->nullable()
                ->after('status');
            $table->date('attendance_unblocked_on')
                ->nullable()
                ->after('attendance_blocked_on');
        });
    }

    public function down(): void
    {
        Schema::table('employee', function (Blueprint $table) {
            $table->dropColumn([
                'attendance_blocked_on',
                'attendance_unblocked_on',
            ]);
        });

        Schema::table('attendance', function (Blueprint $table) {
            $table->dropColumn('attendance_status_override');
        });
    }
};
