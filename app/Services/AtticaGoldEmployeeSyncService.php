<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Employee;
use Illuminate\Support\Facades\Http;

class AtticaGoldEmployeeSyncService
{
    public function sync(Employee $employee, ?Branch $branch = null): array
    {
        $url = trim((string) config('services.atticagold_employee_sync.url'));

        if ($url === '') {
            return [
                'enabled' => false,
                'synced' => false,
            ];
        }

        $request = Http::acceptJson()->timeout((int) config('services.atticagold_employee_sync.timeout', 15));
        $token = trim((string) config('services.atticagold_employee_sync.token'));

        if ($token !== '') {
            $request = $request
                ->withToken($token)
                ->withHeaders([
                    'X-Attica-Sync-Token' => $token,
                ]);
        }

        $response = $request->post($url, $this->payload($employee, $branch));
        $response->throw();

        return [
            'enabled' => true,
            'synced' => true,
            'status' => $response->status(),
        ];
    }

    private function payload(Employee $employee, ?Branch $branch = null): array
    {
        return [
            'empId' => trim((string) $employee->empId),
            'name' => trim((string) $employee->name),
            'contact' => trim((string) $employee->contact),
            'branchId' => trim((string) ($branch?->branchId ?? '')),
            'mailId' => trim((string) $employee->mailId),
            'address' => trim((string) $employee->address),
            'location' => trim((string) $employee->location),
            'designation' => trim((string) $employee->designation),
            'photo' => trim((string) $employee->photo),
            'rating' => (int) ($employee->rating ?? 0),
            'status' => trim((string) ($employee->status ?: 'Active')),
            'salary' => $employee->salary !== null ? (int) $employee->salary : null,
            'advance' => $employee->advance !== null ? (int) $employee->advance : 0,
        ];
    }
}
