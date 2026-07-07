<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceDayOverride extends Model
{
    protected $table = 'attendance_day_overrides';

    protected $fillable = [
        'emp_id',
        'attendance_date',
        'final_status',
        'reason',
        'created_by',
        'updated_by',
    ];
}
