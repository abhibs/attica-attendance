<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;


class Admin extends Authenticatable
{
    use HasFactory, Notifiable;
    protected $guarded = [];

    public const ROLE_HR_ADMIN = 'hr_admin';
    public const ROLE_HIRING = 'hiring';
    public const ROLE_JOINING = 'joining';
    public const ROLE_OPENING = 'opening';
    public const ROLE_ACCOUNTS = 'accounts';
    public const ROLE_SUBHR = 'subhr';
    public const ROLE_ZONAL = 'zonal';

    public function hasAnyRole(array $roles): bool
    {
        $role = strtolower(trim((string) $this->role));

        if ($role === '') {
            $role = self::ROLE_HR_ADMIN;
        }

        if ($role === self::ROLE_ZONAL && in_array(self::ROLE_HR_ADMIN, $roles, true)) {
            return true;
        }

        return in_array($role, $roles, true);
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(AdminMessage::class, 'sender_admin_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(AdminMessage::class, 'receiver_admin_id');
    }
}
