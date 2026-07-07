<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table): void {
            if (! Schema::hasColumn('recruitment_candidates', 'hiring_admin_name')) {
                $table->string('hiring_admin_name')->nullable()->after('created_by_admin_id');
            }

            if (! Schema::hasColumn('recruitment_candidates', 'joining_admin_name')) {
                $table->string('joining_admin_name')->nullable()->after('joining_admin_id');
            }

            if (! Schema::hasColumn('recruitment_candidates', 'hr_admin_name')) {
                $table->string('hr_admin_name')->nullable()->after('fixed_salary');
            }
        });

        Schema::dropIfExists('recruitment_candidate_activity_logs');

        Schema::create('recruitment_candidate_activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('recruitment_candidate_id');
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('actor_role')->nullable();
            $table->string('action', 100);
            $table->text('remarks')->nullable();
            $table->json('changed_fields')->nullable();
            $table->timestamps();

            $table->foreign('recruitment_candidate_id', 'rcal_candidate_fk')
                ->references('id')
                ->on('recruitment_candidates')
                ->cascadeOnDelete();
            $table->foreign('admin_id', 'rcal_admin_fk')
                ->references('id')
                ->on('admins')
                ->nullOnDelete();
        });

        $adminNames = DB::table('admins')
            ->select(['id', 'name', 'email'])
            ->get()
            ->mapWithKeys(function ($admin): array {
                $name = trim((string) ($admin->name ?? ''));
                $email = trim((string) ($admin->email ?? ''));

                return [$admin->id => $name !== '' ? $name : $email];
            });

        DB::table('recruitment_candidates')
            ->select(['id', 'created_by_admin_id', 'joining_admin_id'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($adminNames): void {
                foreach ($rows as $row) {
                    $updates = [];

                    if (! empty($row->created_by_admin_id) && isset($adminNames[$row->created_by_admin_id])) {
                        $updates['hiring_admin_name'] = $adminNames[$row->created_by_admin_id];
                    }

                    if (! empty($row->joining_admin_id) && isset($adminNames[$row->joining_admin_id])) {
                        $updates['joining_admin_name'] = $adminNames[$row->joining_admin_id];
                    }

                    if ($updates === []) {
                        continue;
                    }

                    DB::table('recruitment_candidates')
                        ->where('id', $row->id)
                        ->update($updates);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_candidate_activity_logs');

        Schema::table('recruitment_candidates', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('recruitment_candidates', 'hiring_admin_name') ? 'hiring_admin_name' : null,
                Schema::hasColumn('recruitment_candidates', 'joining_admin_name') ? 'joining_admin_name' : null,
                Schema::hasColumn('recruitment_candidates', 'hr_admin_name') ? 'hr_admin_name' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
