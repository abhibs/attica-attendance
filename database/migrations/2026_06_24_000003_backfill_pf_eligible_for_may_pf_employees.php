<?php

use App\Support\MayPfEligibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee')) {
            return;
        }

        if (! Schema::hasColumn('employee', 'pf_eligible')) {
            Schema::table('employee', function (Blueprint $table): void {
                $table->boolean('pf_eligible')->nullable()->after('pf');
            });
        }

        $latestEmployeeDetails = collect();

        if (Schema::hasTable('employeeDetails')) {
            $latestEmployeeDetails = DB::table('employeeDetails')
                ->select('employeeId', 'uanNumber')
                ->whereIn('id', function ($query): void {
                    $query
                        ->selectRaw('MAX(id)')
                        ->from('employeeDetails')
                        ->groupBy('employeeId');
                })
                ->get()
                ->keyBy(fn ($detail): string => trim((string) $detail->employeeId));
        }

        DB::table('employee')
            ->select(['id', 'empId', 'pf_eligible'])
            ->whereNull('pf_eligible')
            ->orderBy('id')
            ->chunkById(500, function ($employees) use ($latestEmployeeDetails): void {
                foreach ($employees as $employee) {
                    $empId = trim((string) $employee->empId);
                    $detail = $latestEmployeeDetails->get($empId);

                    if (! MayPfEligibility::isEligible(
                        Carbon::parse('2026-05-01', config('app.timezone', 'Asia/Kolkata')),
                        $empId,
                        $detail?->uanNumber
                    )) {
                        continue;
                    }

                    DB::table('employee')
                        ->where('id', $employee->id)
                        ->update(['pf_eligible' => true]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employee') || ! Schema::hasColumn('employee', 'pf_eligible')) {
            return;
        }

        DB::table('employee')
            ->where('pf_eligible', true)
            ->update(['pf_eligible' => null]);
    }
};
