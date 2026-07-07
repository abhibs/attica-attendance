<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteVisitRequest extends Model
{
    protected $fillable = [
        'employee_id',
        'emp_id',
        'visit_date',
        'site_location',
        'latitude',
        'longitude',
        'photo_path',
        'reason',
        'approved_by',
        'status',
        'review_note',
        'reviewed_by',
        'reviewed_at',
        'attendance_id',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'reviewed_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
