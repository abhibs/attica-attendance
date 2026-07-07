<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class EmployeeImportService
{
    public function import(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        $headerMap = null;
        $parsedRows = [];
        $result = [
            'processed' => 0,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        foreach ($rows as $row) {
            $normalizedRow = $this->normalizeRow($row);

            if (! $this->rowHasContent($normalizedRow)) {
                continue;
            }

            if ($headerMap === null) {
                $headerMap = $this->detectHeaderMap($normalizedRow);

                if ($headerMap === null) {
                    throw new \RuntimeException('The file must include at least Employee ID and Employee Name columns.');
                }

                continue;
            }

            $payload = $this->extractEmployeePayload($row, $headerMap);

            if ($payload['empId'] === '') {
                $result['skipped']++;
                continue;
            }

            $parsedRows[$payload['empId']] = $payload;
            $result['processed']++;
        }

        if ($headerMap === null) {
            throw new \RuntimeException('No header row was found in the uploaded file.');
        }

        if ($parsedRows === []) {
            return $result;
        }

        $existingEmployees = $this->existingEmployeesByEmpId(array_keys($parsedRows));

        DB::transaction(function () use ($parsedRows, $existingEmployees, &$result): void {
            foreach ($parsedRows as $empId => $payload) {
                /** @var Employee|null $employee */
                $employee = $existingEmployees->get($empId);

                if ($employee instanceof Employee) {
                    $updates = $this->buildUpdatePayload(
                        $employee,
                        $payload,
                        $payload['__mapped_fields'] ?? []
                    );

                    if ($updates !== []) {
                        $employee->fill($updates);
                        $employee->save();
                        $result['updated']++;
                    } else {
                        $result['skipped']++;
                    }

                    continue;
                }

                $employee = new Employee();
                $employee->empId = $payload['empId'];
                $employee->name = $payload['name'] ?: $payload['empId'];
                $employee->contact = $payload['contact'];
                $employee->address = $payload['address'];
                $employee->location = $payload['location'];
                $employee->designation = $payload['designation'];
                $employee->status = $payload['status'] ?: 'Active';
                $employee->doj = $payload['doj'];
                $employee->mailId = $payload['mailId'] ?? '';
                $employee->shift_timing = $payload['shift_timing'];
                $employee->gender = $payload['gender'];
                $employee->marital_status = $payload['marital_status'];
                $employee->remark = $payload['remark'];
                $employee->salary = $payload['salary'];
                $employee->advance = 0;
                $employee->pf = 0;
                $employee->date_of_birth = $payload['date_of_birth'];
                $employee->save();
                $result['inserted']++;
            }
        });

        return $result;
    }

    private function existingEmployeesByEmpId(array $empIds): Collection
    {
        $empIds = collect($empIds)
            ->map(fn ($empId): string => $this->clean($empId))
            ->filter()
            ->unique()
            ->values();

        if ($empIds->isEmpty()) {
            return collect();
        }

        $query = Employee::query();

        foreach ($empIds as $empId) {
            $query->orWhereRaw('TRIM(empId) = ?', [$empId]);
        }

        return $query
            ->get()
            ->keyBy(fn (Employee $employee): string => $this->clean($employee->empId));
    }

    private function buildUpdatePayload(Employee $employee, array $payload, array $mappedFields): array
    {
        $updates = [];

        foreach ($mappedFields as $field) {
            $value = $payload[$field] ?? null;

            $current = $employee->{$field};

            if ($this->valuesMatch($current, $value)) {
                continue;
            }

            $updates[$field] = $value;
        }

        return $updates;
    }

    private function extractEmployeePayload(array $row, array $headerMap): array
    {
        $mappedFields = collect($headerMap)
            ->filter(fn (array $indexes, string $field): bool => $field !== 'empId' && $indexes !== [])
            ->keys()
            ->values()
            ->all();

        return [
            'empId' => $this->stringValue($this->firstMappedValue($row, $headerMap['empId'] ?? [])),
            'name' => $this->stringValue($this->firstMappedValue($row, $headerMap['name'] ?? [])),
            'contact' => $this->nullableStringValue($this->firstMappedValue($row, $headerMap['contact'] ?? [])),
            'address' => $this->nullableStringValue($this->firstMappedValue($row, $headerMap['address'] ?? [])),
            'location' => $this->nullableStringValue($this->firstMappedValue($row, $headerMap['location'] ?? [])),
            'designation' => $this->nullableStringValue($this->firstMappedValue($row, $headerMap['designation'] ?? [])),
            'status' => $this->nullableStringValue($this->firstMappedValue($row, $headerMap['status'] ?? [])),
            'doj' => $this->parseDate($this->firstMappedValue($row, $headerMap['doj'] ?? [])),
            'mailId' => $this->nullableStringValue($this->firstMappedValue($row, $headerMap['mailId'] ?? [])),
            'shift_timing' => $this->nullableStringValue($this->firstMappedValue($row, $headerMap['shift_timing'] ?? [])),
            'gender' => $this->nullableStringValue($this->firstMappedValue($row, $headerMap['gender'] ?? [])),
            'marital_status' => $this->nullableStringValue($this->firstMappedValue($row, $headerMap['marital_status'] ?? [])),
            'remark' => $this->nullableStringValue($this->firstMappedValue($row, $headerMap['remark'] ?? [])),
            'salary' => $this->parseInteger($this->firstMappedValue($row, $headerMap['salary'] ?? [])),
            'date_of_birth' => $this->parseDate($this->firstMappedValue($row, $headerMap['date_of_birth'] ?? [])),
            '__mapped_fields' => $mappedFields,
        ];
    }

    private function detectHeaderMap(array $row): ?array
    {
        $aliases = [
            'empId' => ['empid', 'employeeid', 'newecod', 'newecode', 'ecode', 'employeecode', 'code', 'id'],
            'name' => ['name', 'employeename', 'employeenames', 'employee', 'staffname'],
            'contact' => ['contact', 'contactnumber', 'mobile', 'mobileno', 'phone', 'phonenumber'],
            'address' => ['address', 'employeeaddress'],
            'location' => ['city', 'location', 'branch', 'branchname'],
            'designation' => ['designation', 'position', 'role'],
            'status' => ['status'],
            'doj' => ['doj', 'dojdate', 'dateofjoining', 'dateofjoin', 'joiningdate', 'datejoining', 'joining'],
            'mailId' => ['mailid', 'email', 'emailid', 'emailaddress'],
            'shift_timing' => ['shifttiming', 'shift', 'timing'],
            'gender' => ['gender', 'sex'],
            'marital_status' => ['maritalstatus', 'marital'],
            'remark' => ['remark', 'remarks', 'note', 'notes'],
            'salary' => ['salary', 'permonth', 'permonthctc', 'permonthsalary', 'permonthamount', 'permonthrs', 'monthlysalary', 'monthlypay', 'grosssalary', 'ctc'],
            'date_of_birth' => ['dateofbirth', 'dob', 'birthdate', 'birth'],
        ];

        $map = [];

        foreach ($aliases as $field => $candidateHeaders) {
            $map[$field] = [];

            foreach ($row as $index => $value) {
                if (in_array($this->normalizeHeader($value), $candidateHeaders, true)) {
                    $map[$field][] = $index;
                }
            }
        }

        if ($map['empId'] === [] || $map['name'] === []) {
            return null;
        }

        return $map;
    }

    private function normalizeRow(array $row): array
    {
        return array_map(fn ($value): string => $this->clean($value), $row);
    }

    private function rowHasContent(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->clean($value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function normalizeHeader($value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower($this->clean($value))) ?? '';
    }

    private function firstMappedValue(array $row, array $indexes)
    {
        foreach ($indexes as $index) {
            if (! array_key_exists($index, $row)) {
                continue;
            }

            $value = $row[$index];

            if ($value === null) {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            return $value;
        }

        return null;
    }

    private function stringValue($value): string
    {
        return $this->clean($value);
    }

    private function nullableStringValue($value): ?string
    {
        $trimmed = $this->clean($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function parseInteger($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (int) round((float) $value);
        }

        $sanitized = preg_replace('/[^0-9.\-]+/', '', $this->clean($value)) ?? '';

        if ($sanitized === '' || ! is_numeric($sanitized)) {
            return null;
        }

        return (int) round((float) $sanitized);
    }

    private function parseDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^\d+(?:\.\d+)?$/', trim($value)) === 1)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        $trimmed = $this->clean($value);

        if ($trimmed === '') {
            return null;
        }

        try {
            return Carbon::parse($trimmed, config('app.timezone', 'Asia/Kolkata'))->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function valuesMatch($left, $right): bool
    {
        if ($left === null && $right === null) {
            return true;
        }

        if (is_numeric($left) && is_numeric($right)) {
            return (string) ((float) $left) === (string) ((float) $right);
        }

        return (string) $left === (string) $right;
    }

    private function clean($value): string
    {
        return trim((string) $value);
    }
}
