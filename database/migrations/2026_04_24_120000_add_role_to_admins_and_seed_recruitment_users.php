<?php

use App\Models\Admin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table): void {
            if (! Schema::hasColumn('admins', 'role')) {
                $table->string('role')->default('hr_admin')->after('password_hint');
            }
        });

        Admin::query()
            ->whereNull('role')
            ->orWhere('role', '')
            ->update(['role' => Admin::ROLE_HR_ADMIN]);

        $accounts = [
            [
                'email' => 'hiring@attica.local',
                'name' => 'hiring',
                'role' => Admin::ROLE_HIRING,
            ],
            [
                'email' => 'joining@attica.local',
                'name' => 'joining',
                'role' => Admin::ROLE_JOINING,
            ],
        ];

        foreach ($accounts as $account) {
            $admin = Admin::query()->firstOrNew(['email' => $account['email']]);

            if (! $admin->exists) {
                $admin->name = $account['name'];
                $admin->password = Hash::make('12345678');
                $admin->password_hint = '12345678';
            }

            $admin->role = $account['role'];
            $admin->save();
        }
    }

    public function down(): void
    {
        Admin::query()
            ->whereIn('email', ['hiring@attica.local', 'joining@attica.local'])
            ->delete();

        if (Schema::hasColumn('admins', 'role')) {
            Schema::table('admins', function (Blueprint $table): void {
                $table->dropColumn('role');
            });
        }
    }
};
