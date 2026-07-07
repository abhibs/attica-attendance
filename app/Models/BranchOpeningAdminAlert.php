<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchOpeningAdminAlert extends Model
{
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_RESOLVED_LATE = 'resolved_late';
    public const STATUS_RESOLVED_ON_TIME = 'resolved_on_time';

    protected $fillable = [
        'branch_id',
        'branch_name',
        'opening_date',
        'opening_time',
        'status',
        'notified_at',
        'opened_at',
        'resolved_at',
        'overdue_minutes',
        'opener_employee_id',
        'opener_emp_id',
        'opener_name',
    ];

    protected $casts = [
        'opening_date' => 'date',
        'notified_at' => 'datetime',
        'opened_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function opener(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'opener_employee_id');
    }
}
