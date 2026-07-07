<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_candidates', function (Blueprint $table): void {
            $table->id();
            $table->string('public_token')->unique();
            $table->string('status')->default('draft')->index();
            $table->string('position_applied_for')->nullable();
            $table->string('candidate_name')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('email')->nullable();
            $table->string('candidate_photo_path')->nullable();
            $table->string('employee_photo_path')->nullable();
            $table->json('document_photo_paths')->nullable();
            $table->json('hiring_payload')->nullable();
            $table->json('onboarding_payload')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('joining_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('verification_shared_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('verified_ip', 64)->nullable();
            $table->timestamp('joining_started_at')->nullable();
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->string('generated_emp_id', 7)->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_candidates');
    }
};
