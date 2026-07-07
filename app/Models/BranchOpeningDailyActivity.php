<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchOpeningDailyActivity extends Model
{
    protected $fillable = [
        'branch_id',
        'attendance_date',
        'opened_at',
        'opened_by_employee_id',
        'opened_by_emp_id',
        'opened_by_name',
        'closed_at',
        'closed_by_employee_id',
        'closed_by_emp_id',
        'closed_by_name',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
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
