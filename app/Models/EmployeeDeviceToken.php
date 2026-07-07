<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDeviceToken extends Model
{
    protected $fillable = [
        'employee_id',
        'token',
        'token_hash',
        'platform',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public static function hashToken(string $token): string
    {
        return hash('sha256', trim($token));
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
