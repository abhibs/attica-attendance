<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'site_visit_review_filter_order_idx';

    public function up(): void
    {
        if (! Schema::hasTable('site_visit_requests')) {
            return;
        }

        if (! $this->indexExists()) {
            Schema::table('site_visit_requests', function (Blueprint $table): void {
                $table->index(['status', 'visit_date', 'employee_id', 'created_at'], self::INDEX_NAME);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('site_visit_requests')) {
            return;
        }

        if ($this->indexExists()) {
            Schema::table('site_visit_requests', function (Blueprint $table): void {
                $table->dropIndex(self::INDEX_NAME);
            });
        }
    }

    private function indexExists(): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', 'site_visit_requests')
            ->where('index_name', self::INDEX_NAME)
            ->exists();
    }
};
