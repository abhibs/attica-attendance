<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAdvanceTransaction extends Model
{
    protected $table = 'employee_advance_transactions';

    protected $fillable = [
        'employee_id',
        'emp_id',
        'advance_date',
        'amount',
        'source_type',
        'source_file',
        'source_row_no',
        'row_hash',
        'remarks',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
