<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAdvanceRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'employee_id',
        'emp_id',
        'request_date',
        'amount',
        'request_note',
        'status',
        'admin_note',
        'verified_by',
        'verified_at',
        'rejected_by',
        'rejected_at',
    ];

    protected $casts = [
        'request_date' => 'date',
        'amount' => 'decimal:2',
        'verified_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
