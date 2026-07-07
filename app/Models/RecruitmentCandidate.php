<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class RecruitmentCandidate extends Model
{
    protected $guarded = [];

    public const STATUS_HIRING_FORM_SHARED = 'hiring_form_shared';
    public const STATUS_HIRING_SUBMITTED = 'hiring_submitted';
    public const STATUS_HIRING_SELECTED = 'hiring_selected';
    public const STATUS_HIRING_HOLD = 'hiring_hold';
    public const STATUS_HIRING_REJECTED = 'hiring_rejected';
    public const STATUS_HIRING_UPDATE_REQUESTED = 'hiring_update_requested';
    public const STATUS_JOINING_FORM_SHARED = 'joining_form_shared';
    public const STATUS_JOINING_SUBMITTED = 'joining_submitted';
    public const STATUS_JOINING_HOLD = 'joining_hold';
    public const STATUS_JOINING_REJECTED = 'joining_rejected';
    public const STATUS_JOINING_UPDATE_REQUESTED = 'joining_update_requested';
    public const STATUS_ONBOARDED = 'onboarded';
    public const STATUS_DRAFT = self::STATUS_HIRING_FORM_SHARED;
    public const STATUS_SHARED = self::STATUS_HIRING_FORM_SHARED;
    public const STATUS_VERIFIED = self::STATUS_HIRING_SUBMITTED;
    public const STATUS_JOINING_IN_PROGRESS = self::STATUS_JOINING_FORM_SHARED;
    public const STATUS_ONBOARDING_COMPLETED = self::STATUS_ONBOARDED;
    public const STATUS_JOINED = 'joined';

    protected $casts = [
        'hiring_payload' => 'array',
        'onboarding_payload' => 'array',
        'interview_payload' => 'array',
        'document_photo_paths' => 'array',
        'is_whatsapp_same_as_contact' => 'boolean',
        'fixed_salary' => 'decimal:2',
        'verification_shared_at' => 'datetime',
        'submitted_at' => 'datetime',
        'verified_at' => 'datetime',
        'joining_started_at' => 'datetime',
        'onboarding_completed_at' => 'datetime',
        'joined_at' => 'datetime',
    ];

    public function formLink(): BelongsTo
    {
        return $this->belongsTo(RecruitmentFormLink::class, 'recruitment_form_link_id');
    }

    public function hiringAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function joiningAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'joining_admin_id');
    }

    public function resubmissionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'resubmission_of_candidate_id');
    }

    public function isReadyForJoining(): bool
    {
        return in_array($this->status, [
            self::STATUS_HIRING_SELECTED,
            self::STATUS_JOINING_FORM_SHARED,
            self::STATUS_JOINING_SUBMITTED,
            self::STATUS_JOINING_HOLD,
            self::STATUS_JOINING_REJECTED,
            self::STATUS_JOINING_UPDATE_REQUESTED,
            self::STATUS_ONBOARDED,
            self::STATUS_JOINED,
        ], true);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(RecruitmentCandidateActivityLog::class, 'recruitment_candidate_id')
            ->latest('created_at')
            ->latest('id');
    }

    public function preferredWhatsappNumber(): ?string
    {
        $number = trim((string) ($this->whatsapp_number ?: $this->contact_number));

        return $number === '' ? null : $number;
    }

    public function whatsappTarget(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->preferredWhatsappNumber());

        if ($digits === null || $digits === '') {
            return null;
        }

        if (Str::startsWith($digits, '91') && strlen($digits) === 12) {
            return $digits;
        }

        if (strlen($digits) === 10) {
            return '91'.$digits;
        }

        return $digits;
    }
}
