<?php

use App\Models\Admin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        $admin = Admin::query()->firstOrNew(['email' => 'accounts@attica.local']);

        if (! $admin->exists) {
            $admin->name = 'accounts';
            $admin->password = Hash::make('12345678');
            $admin->password_hint = '12345678';
        }

        $admin->role = Admin::ROLE_ACCOUNTS;

        if (property_exists($admin, 'position') || \Illuminate\Support\Facades\Schema::hasColumn('admins', 'position')) {
            $admin->position = $admin->position ?: 'Accounts';
        }

        $admin->save();
    }

    public function down(): void
    {
        Admin::query()
            ->where('email', 'accounts@attica.local')
            ->delete();
    }
};
