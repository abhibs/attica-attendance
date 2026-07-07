<?php

use App\Models\RecruitmentCandidate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_form_links', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('public_token')->unique();
            $table->date('hiring_date')->nullable();
            $table->json('question_bank')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('recruitment_candidates', function (Blueprint $table): void {
            $table->foreignId('recruitment_form_link_id')
                ->nullable()
                ->after('id')
                ->constrained('recruitment_form_links')
                ->nullOnDelete();
            $table->foreignId('resubmission_of_candidate_id')
                ->nullable()
                ->after('recruitment_form_link_id')
                ->constrained('recruitment_candidates')
                ->nullOnDelete();
            $table->string('submission_code')->nullable()->unique()->after('public_token');
            $table->string('whatsapp_number')->nullable()->after('contact_number');
            $table->boolean('is_whatsapp_same_as_contact')->default(true)->after('whatsapp_number');
            $table->string('resume_file_path')->nullable()->after('candidate_photo_path');
            $table->string('resume_original_name')->nullable()->after('resume_file_path');
            $table->string('interview_video_path')->nullable()->after('resume_original_name');
            $table->string('interview_video_original_name')->nullable()->after('interview_video_path');
            $table->json('interview_payload')->nullable()->after('onboarding_payload');
            $table->timestamp('submitted_at')->nullable()->after('verification_shared_at');
        });

        $this->migrateExistingSharedLinks();
        $this->backfillSubmissionColumns();
    }

    public function down(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('recruitment_form_link_id');
            $table->dropConstrainedForeignId('resubmission_of_candidate_id');
            $table->dropUnique(['submission_code']);
            $table->dropColumn([
                'submission_code',
                'whatsapp_number',
                'is_whatsapp_same_as_contact',
                'resume_file_path',
                'resume_original_name',
                'interview_video_path',
                'interview_video_original_name',
                'interview_payload',
                'submitted_at',
            ]);
        });

        Schema::dropIfExists('recruitment_form_links');
    }

    private function migrateExistingSharedLinks(): void
    {
        $rows = DB::table('recruitment_candidates')
            ->where('status', RecruitmentCandidate::STATUS_HIRING_FORM_SHARED)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $candidateName = trim((string) ($row->candidate_name ?? ''));
            $contactNumber = trim((string) ($row->contact_number ?? ''));
            $email = trim((string) ($row->email ?? ''));

            if ($candidateName !== '' || $contactNumber !== '' || $email !== '') {
                continue;
            }

            $payload = json_decode((string) ($row->hiring_payload ?? '[]'), true);

            DB::table('recruitment_form_links')->updateOrInsert(
                ['public_token' => $row->public_token],
                [
                    'title' => 'Hiring Form #'.$row->id,
                    'hiring_date' => data_get($payload, 'hiring_date'),
                    'question_bank' => json_encode([]),
                    'is_active' => true,
                    'created_by_admin_id' => $row->created_by_admin_id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]
            );
        }
    }

    private function backfillSubmissionColumns(): void
    {
        $formLinkIdsByToken = DB::table('recruitment_form_links')
            ->pluck('id', 'public_token');

        $rows = DB::table('recruitment_candidates')
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $candidateName = trim((string) ($row->candidate_name ?? ''));
            $contactNumber = trim((string) ($row->contact_number ?? ''));
            $email = trim((string) ($row->email ?? ''));
            $status = trim((string) ($row->status ?? ''));
            $isPlaceholder = $status === RecruitmentCandidate::STATUS_HIRING_FORM_SHARED
                && $candidateName === ''
                && $contactNumber === ''
                && $email === '';

            $updates = [];

            if ($isPlaceholder) {
                $updates['recruitment_form_link_id'] = $formLinkIdsByToken[$row->public_token] ?? null;
            } else {
                $updates['submission_code'] = $this->generateUniqueSubmissionCode();
                $updates['whatsapp_number'] = $contactNumber !== '' ? $contactNumber : null;
                $updates['is_whatsapp_same_as_contact'] = true;
                $updates['submitted_at'] = $row->verified_at ?: $row->updated_at ?: $row->created_at;
            }

            DB::table('recruitment_candidates')
                ->where('id', $row->id)
                ->update($updates);
        }
    }

    private function generateUniqueSubmissionCode(): string
    {
        do {
            $code = 'SUB-'.strtoupper(Str::random(8));
        } while (DB::table('recruitment_candidates')->where('submission_code', $code)->exists());

        return $code;
    }
};
