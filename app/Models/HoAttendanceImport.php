<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HoAttendanceImport extends Model
{
    protected $table = 'ho_attendance_imports';

    protected $fillable = [
        'source_file',
        'import_batch',
        'source_row_no',
        'emp_id',
        'employee_name',
        'branch_name',
        'attendance_date',
        'login_time',
        'logout_time',
        'attendance_status',
        'work_duration',
        'late_bucket',
        'raw_row',
        'row_hash',
    ];
}
