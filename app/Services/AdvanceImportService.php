<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeAdvanceTransaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class AdvanceImportService
{
    public function prepareImport(UploadedFile $file): array
    {
        $token = (string) Str::uuid();
        $originalName = trim((string) $file->getClientOriginalName()) ?: 'advance-import.xlsx';
        $extension = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
        $fileName = $token.'.'.$extension;
        $storedPath = $file->storeAs($this->pendingDirectory(), $fileName, 'local');

        if (! is_string($storedPath) || $storedPath === '') {
            throw new \RuntimeException('Unable to stage the import file.');
        }

        $metadata = [
            'token' => $token,
            'original_name' => $originalName,
            'stored_path' => $storedPath,
        ];

        Storage::disk('local')->put(
            $this->pendingMetadataPath($token),
            json_encode($metadata, JSON_THROW_ON_ERROR)
        );

        try {
            $analysis = $this->analyzeStoredImport($metadata);
        } catch (\Throwable $exception) {
            $this->discardPreparedImport($token);

            throw $exception;
        }

        return [
            'token' => $token,
            'original_name' => $originalName,
            'rows' => $analysis['rows'],
            'skipped_rows' => $analysis['skipped_rows'],
            'skipped_details' => $analysis['skipped_details'],
            'missing_ids' => $analysis['missing_ids'],
            'conflicts' => $analysis['conflicts'],
        ];
    }

    public function importPrepared(string $token, bool $allowManualConflictMerge = false): array
    {
        $metadata = $this->readPendingMetadata($token);
        $analysis = $this->analyzeStoredImport($metadata);

        if ($analysis['conflicts'] !== [] && ! $allowManualConflictMerge) {
            throw new \RuntimeException('This import contains manual advance conflicts and needs confirmation before proceeding.');
        }

        $result = [
            'rows' => $analysis['rows'],
            'inserted' => 0,
            'duplicates' => $analysis['duplicates'],
            'skipped_rows' => $analysis['skipped_rows'],
            'skipped_details' => $analysis['skipped_details'],
            'missing_ids' => $analysis['missing_ids'],
            'confirmed_conflicts' => $allowManualConflictMerge ? count($analysis['conflicts']) : 0,
        ];
        $touchedEmployeeIds = [];
        $timestamp = now();

        DB::transaction(function () use ($analysis, $metadata, &$result, &$touchedEmployeeIds, $timestamp): void {
            foreach ($analysis['import_rows'] as $row) {
                if ($row['is_duplicate']) {
                    continue;
                }

                $rowHash = hash('sha256', implode('|', [
                    $row['emp_id'],
                    $row['advance_date'],
                    number_format($row['amount'], 2, '.', ''),
                    $row['row_no'],
                ]));

                EmployeeAdvanceTransaction::query()->create([
                    'employee_id' => $row['employee_id'],
                    'emp_id' => $row['emp_id'],
                    'advance_date' => $row['advance_date'],
                    'amount' => $row['amount'],
                    'source_type' => 'import',
                    'source_file' => $metadata['original_name'],
                    'source_row_no' => $row['row_no'],
                    'row_hash' => $rowHash,
                    'remarks' => 'Imported from salary advance file',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                $result['inserted']++;
                $touchedEmployeeIds[] = $row['employee_id'];
            }
        });

        $this->refreshAdvanceTotals($touchedEmployeeIds);
        $this->discardPreparedImport($token);

        return $result;
    }

    public function discardPreparedImport(?string $token): void
    {
        $token = trim((string) $token);

        if ($token === '') {
            return;
        }

        $metadataPath = $this->pendingMetadataPath($token);

        if (! Storage::disk('local')->exists($metadataPath)) {
            return;
        }

        $metadata = json_decode((string) Storage::disk('local')->get($metadataPath), true);
        $storedPath = trim((string) ($metadata['stored_path'] ?? ''));

        if ($storedPath !== '' && Storage::disk('local')->exists($storedPath)) {
            Storage::disk('local')->delete($storedPath);
        }

        Storage::disk('local')->delete($metadataPath);
    }

    private function analyzeStoredImport(array $metadata): array
    {
        $storedPath = trim((string) ($metadata['stored_path'] ?? ''));
        $originalName = trim((string) ($metadata['original_name'] ?? 'advance-import.xlsx'));

        if ($storedPath === '' || ! Storage::disk('local')->exists($storedPath)) {
            throw new \RuntimeException('The staged import file could not be found. Please upload it again.');
        }

        $absolutePath = Storage::disk('local')->path($storedPath);

        return $this->analyzeFile($absolutePath, $originalName);
    }

    private function analyzeFile(string $path, string $originalName): array
    {
        $spreadsheet = IOFactory::load($path);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        $headerMap = null;
        $result = [
            'rows' => 0,
            'duplicates' => 0,
            'skipped_rows' => 0,
            'skipped_details' => [],
            'missing_ids' => [],
            'conflicts' => [],
            'import_rows' => [],
        ];
        $employeeCache = [];

        foreach ($rows as $rowIndex => $row) {
            if ($headerMap === null) {
                $normalizedHeaderRow = $this->normalizeRow($row);

                if (! $this->rowHasContent($normalizedHeaderRow)) {
                    continue;
                }

                $headerMap = $this->detectHeaderMap($normalizedHeaderRow);

                if ($headerMap === null) {
                    throw new \RuntimeException('The file must include ID, DATE and AMOUNT columns in the header row.');
                }

                continue;
            }

            if (! $this->rowHasContent($row)) {
                continue;
            }

            $result['rows']++;
            $employeeId = $this->clean($this->firstMappedValue($row, $headerMap['id']));
            $advanceDate = $this->parseDate($this->firstMappedValue($row, $headerMap['date']));
            $amount = $this->parseAmount($this->firstMappedValue($row, $headerMap['amount']));

            if ($employeeId === '' || $advanceDate === null || $amount === null || $amount <= 0) {
                $result['skipped_rows']++;
                $result['skipped_details'][] = [
                    'row_no' => $rowIndex + 1,
                    'emp_id' => $employeeId,
                    'advance_date' => $advanceDate,
                    'amount' => $amount,
                    'reason' => $this->invalidRowReason($employeeId, $advanceDate, $amount),
                ];
                continue;
            }

            $employee = $employeeCache[$employeeId] ??= Employee::query()
                ->select(['id', 'empId', 'name'])
                ->whereRaw('TRIM(empId) = ?', [$employeeId])
                ->first();

            if (! $employee) {
                $result['missing_ids'][] = $employeeId;
                $result['skipped_details'][] = [
                    'row_no' => $rowIndex + 1,
                    'emp_id' => $employeeId,
                    'advance_date' => $advanceDate,
                    'amount' => $amount,
                    'reason' => 'Employee ID not found in employee master.',
                ];
                continue;
            }

            $existingTransactions = EmployeeAdvanceTransaction::query()
                ->where('employee_id', $employee->id)
                ->where('advance_date', $advanceDate)
                ->orderBy('id')
                ->get(['id', 'amount', 'source_type']);

            $isDuplicate = $existingTransactions->contains(
                fn (EmployeeAdvanceTransaction $transaction): bool => $this->amountsMatch(
                    (float) $transaction->amount,
                    $amount
                )
            );

            if ($isDuplicate) {
                $result['duplicates']++;
                $result['skipped_details'][] = [
                    'row_no' => $rowIndex + 1,
                    'emp_id' => $this->clean($employee->empId),
                    'advance_date' => $advanceDate,
                    'amount' => $amount,
                    'reason' => 'Duplicate advance already exists for this employee, date and amount.',
                ];
                continue;
            }

            $manualConflicts = $existingTransactions
                ->filter(fn (EmployeeAdvanceTransaction $transaction): bool => $transaction->source_type === 'manual')
                ->filter(fn (EmployeeAdvanceTransaction $transaction): bool => ! $this->amountsMatch((float) $transaction->amount, $amount))
                ->values();

            if ($manualConflicts->isNotEmpty()) {
                $result['conflicts'][] = [
                    'row_no' => $rowIndex + 1,
                    'emp_id' => $this->clean($employee->empId),
                    'employee_name' => $this->clean($employee->name),
                    'advance_date' => $advanceDate,
                    'import_amount' => $amount,
                    'manual_amounts' => $manualConflicts
                        ->map(fn (EmployeeAdvanceTransaction $transaction): float => round((float) $transaction->amount, 2))
                        ->values()
                        ->all(),
                    'combined_total' => round(
                        $amount + (float) $manualConflicts->sum(fn (EmployeeAdvanceTransaction $transaction): float => (float) $transaction->amount),
                        2
                    ),
                ];
            }

            $result['import_rows'][] = [
                'row_no' => $rowIndex + 1,
                'employee_id' => (int) $employee->id,
                'emp_id' => $this->clean($employee->empId),
                'advance_date' => $advanceDate,
                'amount' => $amount,
                'is_duplicate' => false,
                'source_file' => $originalName,
            ];
        }

        if ($headerMap === null) {
            throw new \RuntimeException('No import header row was found in the uploaded file.');
        }

        $result['missing_ids'] = array_values(array_unique($result['missing_ids']));

        return $result;
    }

    private function readPendingMetadata(string $token): array
    {
        $token = trim($token);

        if ($token === '') {
            throw new \RuntimeException('The import token is missing.');
        }

        $metadataPath = $this->pendingMetadataPath($token);

        if (! Storage::disk('local')->exists($metadataPath)) {
            throw new \RuntimeException('The staged import was not found. Please upload the file again.');
        }

        $metadata = json_decode((string) Storage::disk('local')->get($metadataPath), true);

        if (! is_array($metadata)) {
            throw new \RuntimeException('The staged import metadata is invalid. Please upload the file again.');
        }

        return $metadata;
    }

    private function pendingDirectory(): string
    {
        return 'advance-imports/pending';
    }

    private function pendingMetadataPath(string $token): string
    {
        return $this->pendingDirectory().'/'.$token.'.json';
    }

    private function detectHeaderMap(array $row): ?array
    {
        $map = [
            'id' => [],
            'date' => [],
            'amount' => [],
        ];

        foreach ($row as $index => $value) {
            $header = $this->normalizeHeader($value);

            if (in_array($header, ['id', 'employeeid', 'empid'], true)) {
                $map['id'][] = $index;
            }

            if (in_array($header, ['date', 'advdate', 'advancedate'], true)) {
                $map['date'][] = $index;
            }

            if (in_array($header, ['amount', 'advance', 'advanceamount'], true)) {
                $map['amount'][] = $index;
            }
        }

        if ($map['id'] === [] || $map['date'] === [] || $map['amount'] === []) {
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

        return '';
    }

    private function parseAmount($value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return round((float) $value, 2);
        }

        $sanitized = preg_replace('/[^0-9.\-]+/', '', $this->clean($value)) ?? '';

        if ($sanitized === '' || ! is_numeric($sanitized)) {
            return null;
        }

        return round((float) $sanitized, 2);
    }

    private function parseDate($value): ?string
    {
        if ($value === null) {
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

    private function invalidRowReason(string $employeeId, ?string $advanceDate, ?float $amount): string
    {
        $reasons = [];

        if ($employeeId === '') {
            $reasons[] = 'Employee ID is blank';
        }

        if ($advanceDate === null) {
            $reasons[] = 'date is missing or invalid';
        }

        if ($amount === null) {
            $reasons[] = 'amount is missing or invalid';
        } elseif ($amount <= 0) {
            $reasons[] = 'amount must be greater than zero';
        }

        return $reasons !== []
            ? ucfirst(implode(', ', $reasons)).'.'
            : 'Required row data is invalid.';
    }

    private function refreshAdvanceTotals(array $employeeIds): void
    {
        $employeeIds = collect($employeeIds)->filter()->unique()->values();

        if ($employeeIds->isEmpty()) {
            return;
        }

        $totals = EmployeeAdvanceTransaction::query()
            ->selectRaw('employee_id, COALESCE(SUM(amount), 0) as total_advance')
            ->whereIn('employee_id', $employeeIds->all())
            ->groupBy('employee_id')
            ->pluck('total_advance', 'employee_id');

        $employees = Employee::query()
            ->whereIn('id', $employeeIds->all())
            ->get();

        foreach ($employees as $employee) {
            $employee->advance = (float) ($totals[$employee->id] ?? 0);
            $employee->save();
        }
    }

    private function amountsMatch(float $left, float $right): bool
    {
        return abs(round($left, 2) - round($right, 2)) < 0.00001;
    }

    private function clean($value): string
    {
        return trim((string) $value);
    }
}
