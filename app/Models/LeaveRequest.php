<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    protected $fillable = [
        'employee_id',
        'emp_id',
        'leave_date',
        'reason',
        'status',
        'review_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'leave_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
