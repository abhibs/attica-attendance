<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminMessage extends Model
{
    protected $guarded = [];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'sender_admin_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'receiver_admin_id');
    }
}
