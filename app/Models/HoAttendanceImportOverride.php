<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HoAttendanceImportOverride extends Model
{
    protected $table = 'ho_attendance_import_overrides';

    protected $fillable = [
        'emp_id',
        'attendance_date',
        'final_status',
        'created_by',
        'updated_by',
    ];
}
