<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminNotification extends Model
{
    protected $fillable = [
        'audience_type',
        'audience_value',
        'title',
        'body',
        'sent_by',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function deliveries(): HasMany
    {
        return $this->hasMany(EmployeeNotificationDelivery::class);
    }
}
