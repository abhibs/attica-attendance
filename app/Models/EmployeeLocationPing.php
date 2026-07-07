<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeLocationPing extends Model
{
    protected $table = 'employee_location_pings';

    protected $fillable = [
        'employee_id',
        'emp_id',
        'attendance_id',
        'branch_id',
        'latitude',
        'longitude',
        'branch_latitude',
        'branch_longitude',
        'distance_meters',
        'is_out_of_office',
        'recorded_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'branch_latitude' => 'float',
        'branch_longitude' => 'float',
        'distance_meters' => 'float',
        'is_out_of_office' => 'boolean',
        'recorded_at' => 'datetime',
    ];
}
