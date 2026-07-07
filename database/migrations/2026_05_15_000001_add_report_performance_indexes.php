<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * These indexes target the slow admin/report paths: attendance range reports,
     * salary reports, dashboard counts, branch opening checks, and review queues.
     */
    private array $indexes = [
        ['attendance', ['check_in_date', 'empId', 'id'], 'attendance_check_date_emp_id_idx'],
        ['attendance', ['empId', 'check_in_date', 'id'], 'attendance_emp_date_id_idx'],
        ['attendance', ['check_in_date', 'check_in_branch_id', 'empId'], 'attendance_in_date_branch_emp_idx'],
        ['attendance', ['check_out_date', 'check_out_branch_id', 'empId'], 'attendance_out_date_branch_emp_idx'],
        ['attendance', ['check_in_date', 'attendance_status_override', 'empId'], 'attendance_review_date_status_emp_idx'],

        ['ho_attendance_imports', ['attendance_date', 'emp_id'], 'ho_att_import_date_emp_idx'],
        ['ho_attendance_imports', ['emp_id', 'attendance_date', 'attendance_status'], 'ho_att_import_emp_date_status_idx'],
        ['ho_attendance_imports', ['attendance_date', 'login_time'], 'ho_att_import_date_login_idx'],
        ['ho_attendance_import_overrides', ['attendance_date', 'emp_id'], 'ho_att_override_date_emp_idx'],

        ['employee', ['status'], 'employee_status_idx'],
        ['employee', ['status', 'name'], 'employee_status_name_idx'],
        ['employee', ['name', 'empId'], 'employee_name_emp_idx'],
        ['employee', ['is_night_shift', 'empId'], 'employee_night_shift_emp_idx'],
        ['employee', ['last_login_branch_id'], 'employee_last_login_branch_idx'],

        ['employeeDetails', ['employeeId', 'id'], 'employee_details_emp_id_latest_idx'],
        ['employeeDetails', ['branchId', 'status'], 'employee_details_branch_status_idx'],
        ['employeeDetails', ['accountVerified', 'employeeId'], 'employee_details_verified_emp_idx'],

        ['employee_advance_transactions', ['employee_id', 'advance_date', 'id'], 'emp_adv_employee_date_id_idx'],
        ['employee_advance_transactions', ['advance_date', 'employee_id'], 'emp_adv_date_employee_idx'],

        ['employee_bank_detail_requests', ['status', 'created_at'], 'bank_detail_status_created_idx'],
        ['employee_bank_detail_requests', ['employee_id', 'status'], 'bank_detail_employee_status_idx'],
        ['employee_bank_detail_requests', ['emp_id', 'status'], 'bank_detail_emp_status_idx'],

        ['wp_branches_database', ['status'], 'branches_status_idx'],
        ['wp_branches_database', ['status', 'branchName'], 'branches_status_name_idx'],
        ['wp_branches_database', ['branchId', 'status'], 'branches_branch_id_status_idx'],
        ['wp_branches_database', ['state', 'city', 'status'], 'branches_state_city_status_idx'],

        ['branch_opening_daily_summaries', ['opening_status', 'attendance_date'], 'branch_open_status_date_idx'],
        ['branch_opening_daily_summaries', ['attendance_date', 'branch_name'], 'branch_open_date_name_idx'],
        ['branch_opening_admin_alerts', ['status', 'opening_date', 'branch_id'], 'branch_alert_status_date_branch_idx'],
        ['branch_opening_notification_logs', ['branch_id', 'employee_id', 'created_at'], 'branch_open_log_branch_emp_created_idx'],

        ['employee_notification_deliveries', ['employee_id', 'created_at'], 'emp_notify_employee_created_idx'],
        ['employee_notification_deliveries', ['read_at', 'employee_id'], 'emp_notify_read_employee_idx'],

        ['recruitment_candidates', ['status', 'created_at'], 'recruitment_status_created_idx'],
        ['recruitment_candidates', ['position_applied_for', 'status'], 'recruitment_position_status_idx'],
        ['recruitment_candidates', ['recruitment_form_link_id', 'status'], 'recruitment_form_status_idx'],
        ['recruitment_form_links', ['is_active', 'hiring_date'], 'recruitment_links_active_date_idx'],

        ['leave_requests', ['status', 'created_at'], 'leave_status_created_idx'],
        ['site_visit_requests', ['status', 'created_at'], 'site_visit_status_created_idx'],
        ['te_tracker_visits', ['visit_date', 'branch_id'], 'te_tracker_date_branch_idx'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as [$table, $columns, $name]) {
            $this->addIndexIfMissing($table, $columns, $name);
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->indexes) as [$table, $columns, $name]) {
            $this->dropIndexIfExists($table, $name);
        }
    }

    private function addIndexIfMissing(string $tableName, array $columns, string $indexName): void
    {
        if (
            ! Schema::hasTable($tableName)
            || ! $this->hasColumns($tableName, $columns)
            || $this->indexExists($tableName, $indexName)
        ) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName): void {
            $table->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        if (! Schema::hasTable($tableName) || ! $this->indexExists($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
            $table->dropIndex($indexName);
        });
    }

    private function hasColumns(string $tableName, array $columns): bool
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($tableName, $column)) {
                return false;
            }
        }

        return true;
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => $this->mysqlIndexExists($tableName, $indexName),
            'sqlite' => $this->sqliteIndexExists($tableName, $indexName),
            'pgsql' => $this->pgsqlIndexExists($indexName),
            'sqlsrv' => $this->sqlsrvIndexExists($tableName, $indexName),
            default => false,
        };
    }

    private function mysqlIndexExists(string $tableName, string $indexName): bool
    {
        $safeTableName = str_replace('`', '``', $tableName);

        return DB::select("SHOW INDEX FROM `{$safeTableName}` WHERE Key_name = ?", [$indexName]) !== [];
    }

    private function sqliteIndexExists(string $tableName, string $indexName): bool
    {
        $safeTableName = str_replace("'", "''", $tableName);
        $indexes = DB::select("PRAGMA index_list('{$safeTableName}')");

        foreach ($indexes as $index) {
            if (($index->name ?? null) === $indexName) {
                return true;
            }
        }

        return false;
    }

    private function pgsqlIndexExists(string $indexName): bool
    {
        return DB::table('pg_indexes')
            ->where('indexname', $indexName)
            ->exists();
    }

    private function sqlsrvIndexExists(string $tableName, string $indexName): bool
    {
        return DB::table('sys.indexes')
            ->join('sys.objects', 'sys.indexes.object_id', '=', 'sys.objects.object_id')
            ->where('sys.objects.name', $tableName)
            ->where('sys.indexes.name', $indexName)
            ->exists();
    }
};
