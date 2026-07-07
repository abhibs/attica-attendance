<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchOpeningSetting extends Model
{
    protected $fillable = [
        'branch_id',
        'opening_time',
        'admin_phone',
        'updated_by',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by');
    }
}
