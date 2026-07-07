<?php

use App\Models\Admin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $admin = Admin::query()->firstOrNew(['email' => 'javeriya@attica.local']);
        $admin->name = 'Javeriya';
        $admin->password = Hash::make('12345678');
        $admin->password_hint = '12345678';
        $admin->role = Admin::ROLE_SUBHR;

        if (Schema::hasColumn('admins', 'position')) {
            $admin->position = null;
        }

        $admin->save();
    }

    public function down(): void
    {
        Admin::query()
            ->where('email', 'javeriya@attica.local')
            ->where('role', Admin::ROLE_SUBHR)
            ->delete();
    }
};
