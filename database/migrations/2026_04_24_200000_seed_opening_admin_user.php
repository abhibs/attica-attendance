<?php

use App\Models\Admin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        $admin = Admin::query()->firstOrNew(['email' => 'opening@attica.local']);

        if (! $admin->exists) {
            $admin->name = 'opening';
            $admin->password = Hash::make('12345678');
            $admin->password_hint = '12345678';
        }

        $admin->role = Admin::ROLE_OPENING;
        $admin->save();
    }

    public function down(): void
    {
        Admin::query()
            ->where('email', 'opening@attica.local')
            ->delete();
    }
};
