<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeBankDetailRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_VERIFIED = 'verified';

    protected $fillable = [
        'employee_id',
        'emp_id',
        'status',
        'request_note',
        'admin_note',
        'requested_emp_name',
        'requested_bank_name',
        'requested_bank_ac_no',
        'requested_ifsc_code',
        'requested_uan_number',
        'requested_passbook_doc',
        'approved_by',
        'approved_at',
        'verified_by',
        'verified_at',
        'submitted_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'verified_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
