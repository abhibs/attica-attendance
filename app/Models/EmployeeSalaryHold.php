<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeSalaryHold extends Model
{
    protected $fillable = [
        'employee_id',
        'emp_id',
        'payroll_month',
        'reason',
        'held_by',
    ];

    protected $casts = [
        'payroll_month' => 'date:Y-m-d',
    ];
}
