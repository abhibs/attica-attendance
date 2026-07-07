<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecruitmentFormLink extends Model
{
    protected $guarded = [];

    protected $casts = [
        'question_bank' => 'array',
        'hiring_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(RecruitmentCandidate::class, 'recruitment_form_link_id');
    }

    public function settings(): array
    {
        $settings = data_get($this->question_bank, '__meta__', []);

        return is_array($settings) ? $settings : [];
    }

    public function isWalkInForm(): bool
    {
        return (bool) ($this->settings()['is_walkin_form'] ?? false);
    }

    public function requiresVideoInterview(): bool
    {
        return ! $this->isWalkInForm();
    }

    public function positionOptions(): array
    {
        $positions = collect($this->question_bank ?? [])
            ->keys()
            ->filter(function ($value): bool {
                $position = trim((string) $value);
                $normalizedPosition = strtolower($position);

                return $position !== ''
                    && ! str_starts_with($position, '__')
                    && ! in_array($normalizedPosition, ['other', 'others'], true);
            })
            ->values()
            ->all();

        $hasAssistantBranchManager = collect($positions)
            ->contains(fn (string $position): bool => strcasecmp($position, 'Assistant Branch Manager') === 0);

        if ($hasAssistantBranchManager) {
            return $positions;
        }

        $resolvedPositions = [];

        foreach ($positions as $position) {
            $resolvedPositions[] = $position;

            if (strcasecmp($position, 'Branch Manager') === 0) {
                $resolvedPositions[] = 'Assistant Branch Manager';
            }
        }

        return $resolvedPositions;
    }
}
