<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeNotificationDelivery extends Model
{
    protected $fillable = [
        'admin_notification_id',
        'employee_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(AdminNotification::class, 'admin_notification_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
