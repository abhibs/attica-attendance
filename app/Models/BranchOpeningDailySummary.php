<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchOpeningDailySummary extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ON_TIME = 'on_time';
    public const STATUS_LATE = 'late';
    public const STATUS_NOT_OPENED = 'not_opened';
    public const STATUS_OPENED = 'opened';
    public const STATUS_NO_ACTIVITY = 'no_activity';

    protected $fillable = [
        'branch_id',
        'branch_name',
        'attendance_date',
        'scheduled_opening_time',
        'opening_status',
        'opened_at',
        'opened_by_employee_id',
        'opened_by_emp_id',
        'opened_by_name',
        'first_check_in_at',
        'first_check_in_emp_id',
        'first_check_in_name',
        'closed_at',
        'closed_by_employee_id',
        'closed_by_emp_id',
        'closed_by_name',
        'total_check_ins',
        'total_check_outs',
        'opening_delay_minutes',
        'open_duration_minutes',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'opened_at' => 'datetime',
        'first_check_in_at' => 'datetime',
        'closed_at' => 'datetime',
        'total_check_ins' => 'integer',
        'total_check_outs' => 'integer',
        'opening_delay_minutes' => 'integer',
        'open_duration_minutes' => 'integer',
    ];

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'opened_by_employee_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'closed_by_employee_id');
    }
}
