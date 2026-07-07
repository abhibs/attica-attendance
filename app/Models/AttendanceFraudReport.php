<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceFraudReport extends Model
{
    protected $fillable = [
        'employee_id',
        'emp_id',
        'attendance_id',
        'branch_id',
        'fraud_type',
        'confidence',
        'reason',
        'source',
        'proof_path',
        'reported_at',
    ];

    protected $casts = [
        'confidence' => 'float',
        'reported_at' => 'datetime',
    ];
}
