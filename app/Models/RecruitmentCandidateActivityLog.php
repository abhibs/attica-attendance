<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentCandidateActivityLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'changed_fields' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(RecruitmentCandidate::class, 'recruitment_candidate_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
