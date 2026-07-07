<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\EmployeeBankDetailRequest;
use App\Models\EmployeeDetail;
use App\Support\MayPfEligibility;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use RuntimeException;

class ResolveMayPfEmployeeIds extends Command
{
    protected $signature = 'payroll:resolve-may-pf-employee-ids';

    protected $description = 'Resolve employee IDs for the PF UAN allowlist effective from May 2026';

    public function handle(): int
    {
        $path = MayPfEligibility::dataPath();
        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $existingEmployees = collect($data['employees'] ?? []);
        $existingEmpIdsByUan = $existingEmployees
            ->mapWithKeys(fn (array $employee): array => [
                $this->uanKey($employee['uan'] ?? null) => trim((string) ($employee['empId'] ?? '')),
            ]);
        $activeMembersPath = base_path((string) ($data['activeMembersFile'] ?? 'ActiveMembers_10062026114723.csv'));
        $activeMembers = $this->activeMembers($activeMembersPath);
        $employees = Employee::query()
            ->with('detail')
            ->get(['id', 'empId', 'name', 'contact', 'mailId']);
        $detailEmpIdsByUan = EmployeeDetail::query()
            ->whereNotNull('uanNumber')
            ->where('uanNumber', '<>', '')
            ->get(['employeeId', 'uanNumber'])
            ->mapWithKeys(function (EmployeeDetail $detail): array {
                $uan = $this->normalizeUan($detail->uanNumber);

                return $uan !== '' ? [$this->uanKey($uan) => trim((string) $detail->employeeId)] : [];
            });
        $requestEmpIdsByUan = EmployeeBankDetailRequest::query()
            ->whereIn('status', [
                EmployeeBankDetailRequest::STATUS_SUBMITTED,
                EmployeeBankDetailRequest::STATUS_VERIFIED,
            ])
            ->whereNotNull('requested_uan_number')
            ->where('requested_uan_number', '<>', '')
            ->orderBy('id')
            ->get(['emp_id', 'requested_uan_number'])
            ->mapWithKeys(function (EmployeeBankDetailRequest $request): array {
                $uan = $this->normalizeUan($request->requested_uan_number);

                return $uan !== '' ? [$this->uanKey($uan) => trim((string) $request->emp_id)] : [];
            });
        $empIdsByUan = $requestEmpIdsByUan->merge($detailEmpIdsByUan);
        $empIdsByMobile = $this->uniqueEmployeeMap(
            $employees,
            fn (Employee $employee): array => [$this->normalizePhone($employee->contact)]
        );
        $empIdsByEmail = $this->uniqueEmployeeMap(
            $employees,
            fn (Employee $employee): array => [$this->normalizeEmail($employee->mailId)]
        );
        $empIdsByName = $this->uniqueEmployeeMap(
            $employees,
            fn (Employee $employee): array => [
                $this->normalizeName($employee->name),
                $this->normalizeName($employee->detail?->empName),
            ]
        );

        $resolved = 0;
        $unresolvedUans = [];
        $sourceCounts = [
            'uan' => 0,
            'mobile' => 0,
            'email' => 0,
            'name' => 0,
            'existing_json' => 0,
        ];
        $resolvedEmployees = $activeMembers
            ->map(function (array $member) use (
                $empIdsByUan,
                $empIdsByMobile,
                $empIdsByEmail,
                $empIdsByName,
                $existingEmpIdsByUan,
                &$resolved,
                &$unresolvedUans,
                &$sourceCounts
            ): array {
                $uan = $this->normalizeUan($member['UAN'] ?? null);
                $uanKey = $this->uanKey($uan);
                $matches = [
                    'uan' => $empIdsByUan->get($uanKey),
                    'mobile' => $empIdsByMobile->get($this->normalizePhone($member['Mobile'] ?? null)),
                    'email' => $empIdsByEmail->get($this->normalizeEmail($member['Email ID'] ?? null)),
                    'name' => $empIdsByName->get($this->normalizeName($member['Name'] ?? null)),
                    'existing_json' => $existingEmpIdsByUan->get($uanKey),
                ];
                $source = collect($matches)->search(fn ($empId): bool => trim((string) $empId) !== '');
                $empId = $source !== false ? trim((string) $matches[$source]) : '';

                if ($empId !== '') {
                    $resolved++;
                    $sourceCounts[$source]++;
                } else {
                    $unresolvedUans[] = $uan;
                }

                return [
                    'uan' => $uan,
                    'empId' => $empId,
                ];
            })
            ->values();
        $data['employees'] = $resolvedEmployees
            ->values()
            ->all();

        file_put_contents(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
        );

        MayPfEligibility::reset();
        $this->info(sprintf(
            'Resolved %d of %d active PF employee IDs.',
            $resolved,
            count($data['employees'])
        ));
        $this->line(sprintf(
            'UAN records available: employee details %d, submitted/verified requests %d.',
            $detailEmpIdsByUan->count(),
            $requestEmpIdsByUan->count()
        ));
        $this->line(sprintf(
            'Resolved by source: UAN %d, mobile %d, email %d, name %d, existing JSON %d.',
            $sourceCounts['uan'],
            $sourceCounts['mobile'],
            $sourceCounts['email'],
            $sourceCounts['name'],
            $sourceCounts['existing_json']
        ));

        if ($unresolvedUans !== []) {
            $this->warn(sprintf('Unresolved UANs (%d):', count($unresolvedUans)));

            foreach (array_chunk($unresolvedUans, 10) as $uanChunk) {
                $this->line(implode(', ', $uanChunk));
            }
        }

        return self::SUCCESS;
    }

    private function normalizeUan(?string $uanNumber): string
    {
        return preg_replace('/\D+/', '', (string) $uanNumber) ?? '';
    }

    private function uanKey(?string $uanNumber): string
    {
        return 'uan:'.$this->normalizeUan($uanNumber);
    }

    private function activeMembers(string $path): Collection
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException('Unable to read ActiveMembers CSV: '.$path);
        }

        $headers = fgetcsv($handle);

        if (! is_array($headers)) {
            fclose($handle);

            throw new RuntimeException('ActiveMembers CSV has no header row.');
        }

        $headers = array_map(
            fn ($header): string => trim((string) $header, "\xEF\xBB\xBF \t\n\r\0\x0B"),
            $headers
        );
        $rows = collect();
        $invalidUans = [];
        $rowNumber = 1;

        while (($values = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (count($values) < count($headers)) {
                $values = array_pad($values, count($headers), '');
            } elseif (count($values) > count($headers)) {
                $values = array_slice($values, 0, count($headers));
            }

            $row = array_combine($headers, $values);
            $rawUan = trim((string) ($row['UAN'] ?? ''));
            $uan = $this->normalizeUan($rawUan);

            if ($rawUan !== '' && (preg_match('/e\s*\+?\s*\d+/i', $rawUan) === 1 || strlen($uan) !== 12)) {
                $invalidUans[] = sprintf('row %d: %s', $rowNumber, $rawUan);

                continue;
            }

            if ($uan !== '') {
                $row['UAN'] = $uan;
                $rows->push($row);
            }
        }

        fclose($handle);

        if ($invalidUans !== []) {
            throw new RuntimeException(sprintf(
                "ActiveMembers CSV has invalid UAN values. Upload/export the file with the UAN column formatted as Text, not Number/Scientific. Invalid examples: %s",
                implode('; ', array_slice($invalidUans, 0, 10))
            ));
        }

        return $rows->unique('UAN')->values();
    }

    private function uniqueEmployeeMap(Collection $employees, callable $keysForEmployee): Collection
    {
        $candidates = [];

        foreach ($employees as $employee) {
            foreach (array_unique($keysForEmployee($employee)) as $key) {
                $key = trim((string) $key);

                if ($key !== '') {
                    $candidates[$key][] = trim((string) $employee->empId);
                }
            }
        }

        return collect($candidates)
            ->filter(fn (array $empIds): bool => count(array_unique($empIds)) === 1)
            ->map(fn (array $empIds): string => (string) $empIds[0]);
    }

    private function normalizePhone(?string $phone): string
    {
        $phone = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return strlen($phone) > 10 ? substr($phone, -10) : $phone;
    }

    private function normalizeEmail(?string $email): string
    {
        $email = strtolower(trim((string) $email));

        return $email === 'not available' ? '' : $email;
    }

    private function normalizeName(?string $name): string
    {
        $name = preg_replace('/^(mr|ms|mrs|miss)\.?\s+/i', '', trim((string) $name)) ?? '';

        return preg_replace('/[^a-z0-9]+/', '', strtolower($name)) ?? '';
    }
}
