<?php

use App\Support\MayPfSecurityDeposit;
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

        if (! Schema::hasColumn('employee', 'pfsecuritydeposit')) {
            Schema::table('employee', function (Blueprint $table): void {
                $table->decimal('pfsecuritydeposit', 12, 2)->nullable()->after('pf');
            });
        }

        $latestEmployeeDetails = collect();

        if (Schema::hasTable('employeeDetails')) {
            $latestEmployeeDetails = DB::table('employeeDetails')
                ->select('employeeId', 'designation', 'uanNumber')
                ->whereIn('id', function ($query): void {
                    $query
                        ->selectRaw('MAX(id)')
                        ->from('employeeDetails')
                        ->groupBy('employeeId');
                })
                ->get()
                ->keyBy(fn ($detail): string => trim((string) $detail->employeeId));
        }

        $employeeColumns = ['id', 'empId', 'designation', 'pfsecuritydeposit'];

        if (Schema::hasColumn('employee', 'pf_eligible')) {
            $employeeColumns[] = 'pf_eligible';
        }

        DB::table('employee')
            ->select($employeeColumns)
            ->orderBy('id')
            ->chunkById(500, function ($employees) use ($latestEmployeeDetails): void {
                foreach ($employees as $employee) {
                    if ($employee->pfsecuritydeposit !== null) {
                        continue;
                    }

                    $empId = trim((string) $employee->empId);
                    $detail = $latestEmployeeDetails->get($empId);
                    $amount = MayPfSecurityDeposit::amountFor(
                        Carbon::parse('2026-05-01', config('app.timezone', 'Asia/Kolkata')),
                        $empId,
                        $detail?->uanNumber,
                        trim((string) ($employee->designation ?: $detail?->designation)),
                        $employee->pf_eligible ?? null
                    );

                    if ($amount <= 0) {
                        continue;
                    }

                    DB::table('employee')
                        ->where('id', $employee->id)
                        ->update(['pfsecuritydeposit' => $amount]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employee') || ! Schema::hasColumn('employee', 'pfsecuritydeposit')) {
            return;
        }

        DB::table('employee')
            ->where('pfsecuritydeposit', 5000)
            ->update(['pfsecuritydeposit' => null]);
    }
};
