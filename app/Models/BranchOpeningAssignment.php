<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchOpeningAssignment extends Model
{
    public const TYPE_DOOR_KEY = 'door_key';
    public const TYPE_LOCKER_KEY = 'locker_key';
    public const TYPE_OPENER = 'opener';

    public const TYPES = [
        self::TYPE_DOOR_KEY,
        self::TYPE_LOCKER_KEY,
        self::TYPE_OPENER,
    ];

    protected $fillable = [
        'branch_id',
        'employee_id',
        'assignment_type',
        'assigned_by',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_by');
    }
}
