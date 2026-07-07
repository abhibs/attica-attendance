<?php

namespace App\Services;

use App\Models\HoAttendanceImport;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class HoAttendanceImportService
{
    public function import(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        $batchId = Str::lower((string) Str::uuid());
        $mapping = $this->defaultMapping();
        $started = false;
        $summaryMode = false;
        $summaryRange = null;
        $summaryDateMap = [];
        $summaryEmployee = ['emp_id' => '', 'employee_name' => ''];
        $summaryBuffer = [
            'status' => [],
            'login' => [],
            'logout' => [],
            'duration' => [],
        ];
        $result = [
            'inserted' => 0,
            'duplicates' => 0,
            'skipped' => 0,
            'batch_id' => $batchId,
            'source_file' => $file->getClientOriginalName(),
        ];

        DB::beginTransaction();

        try {
            foreach ($rows as $rowIndex => $row) {
                $normalizedRow = $this->normalizeRow($row);

                if (! $this->rowHasContent($normalizedRow)) {
                    continue;
                }

                if (! $started) {
                    $summaryMode = $this->isSummaryAttendanceReportRow($normalizedRow);

                    if (! $summaryMode) {
                        $detectedMapping = $this->mapAttendanceImportHeaders($normalizedRow);
                        if ($detectedMapping['detected']) {
                            $mapping = $detectedMapping;
                            $started = true;
                            continue;
                        }
                    }

                    $started = true;
                }

                if ($summaryMode) {
                    $this->consumeSummaryRow(
                        $normalizedRow,
                        $rowIndex + 1,
                        $result,
                        $summaryRange,
                        $summaryDateMap,
                        $summaryEmployee,
                        $summaryBuffer,
                        $batchId,
                        $file->getClientOriginalName()
                    );
                    continue;
                }

                $record = $this->extractAttendanceImportRecord($normalizedRow, $mapping);
                $this->persistRecord($record, $normalizedRow, $rowIndex + 1, $result, $batchId, $file->getClientOriginalName());
            }

            if ($summaryMode) {
                $this->flushSummaryEmployee(
                    $summaryEmployee,
                    $summaryDateMap,
                    $summaryBuffer,
                    $result,
                    $batchId,
                    $file->getClientOriginalName()
                );
            }

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        return $result;
    }

    private function consumeSummaryRow(
        array $row,
        int $rowNumber,
        array &$result,
        ?array &$summaryRange,
        array &$summaryDateMap,
        array &$summaryEmployee,
        array &$summaryBuffer,
        string $batchId,
        string $sourceFile
    ): void {
        if ($summaryRange === null) {
            $summaryRange = $this->parseSummaryReportDateRange($row);
        }

        if ($summaryDateMap === []) {
            $summaryDateMap = $this->buildSummaryDateMap($row, $summaryRange['start'] ?? null);
            if ($summaryDateMap !== []) {
                return;
            }
        }

        $firstLabel = $this->normalizeHeader($row[0] ?? '');

        if ($firstLabel === 'employee') {
            $this->flushSummaryEmployee($summaryEmployee, $summaryDateMap, $summaryBuffer, $result, $batchId, $sourceFile);
            $summaryEmployee = $this->extractSummaryEmployeeDetails($row);
            $summaryBuffer = [
                'status' => [],
                'login' => [],
                'logout' => [],
                'duration' => [],
            ];
            return;
        }

        if (($summaryEmployee['emp_id'] ?? '') === '' || $summaryDateMap === []) {
            return;
        }

        if ($firstLabel === 'status') {
            $summaryBuffer['status'] = $this->buildSummaryDailyValueMap($row, $summaryDateMap);
            return;
        }

        if (in_array($firstLabel, ['intime', 'time', 'logintime'], true)) {
            $summaryBuffer['login'] = $this->buildSummaryDailyValueMap($row, $summaryDateMap);
            return;
        }

        if (in_array($firstLabel, ['outtime', 'logouttime'], true)) {
            $summaryBuffer['logout'] = $this->buildSummaryDailyValueMap($row, $summaryDateMap);
            return;
        }

        if ($firstLabel === 'duration') {
            $summaryBuffer['duration'] = $this->buildSummaryDailyValueMap($row, $summaryDateMap);
            $this->flushSummaryEmployee($summaryEmployee, $summaryDateMap, $summaryBuffer, $result, $batchId, $sourceFile);
            $summaryEmployee = ['emp_id' => '', 'employee_name' => ''];
        }
    }

    private function flushSummaryEmployee(
        array $summaryEmployee,
        array $summaryDateMap,
        array &$summaryBuffer,
        array &$result,
        string $batchId,
        string $sourceFile
    ): void {
        $empId = trim((string) ($summaryEmployee['emp_id'] ?? ''));

        if ($empId === '' || $summaryDateMap === []) {
            return;
        }

        $dates = array_values(array_unique(array_merge(
            array_keys($summaryBuffer['status']),
            array_keys($summaryBuffer['login']),
            array_keys($summaryBuffer['logout']),
            array_keys($summaryBuffer['duration'])
        )));

        sort($dates);

        foreach ($dates as $attendanceDate) {
            $record = [
                'emp_id' => $empId,
                'employee_name' => trim((string) ($summaryEmployee['employee_name'] ?? '')),
                'branch_name' => 'HO',
                'attendance_date' => $attendanceDate,
                'login_time' => $this->parseAttendanceTimeValue($summaryBuffer['login'][$attendanceDate] ?? ''),
                'logout_time' => $this->parseAttendanceTimeValue($summaryBuffer['logout'][$attendanceDate] ?? ''),
                'attendance_status' => trim((string) ($summaryBuffer['status'][$attendanceDate] ?? '')),
                'work_duration' => $this->normalizeDuration($summaryBuffer['duration'][$attendanceDate] ?? ''),
            ];

            $this->persistRecord($record, $record, 0, $result, $batchId, $sourceFile);
        }

        $summaryBuffer = [
            'status' => [],
            'login' => [],
            'logout' => [],
            'duration' => [],
        ];
    }

    private function persistRecord(
        array $record,
        array $rawRow,
        int $rowNumber,
        array &$result,
        string $batchId,
        string $sourceFile
    ): void {
        if ($record === []) {
            $result['skipped']++;
            return;
        }

        $empId = trim((string) ($record['emp_id'] ?? ''));
        $attendanceDate = trim((string) ($record['attendance_date'] ?? ''));

        if ($empId === '' || $attendanceDate === '') {
            $result['skipped']++;
            return;
        }

        $loginTime = $this->parseAttendanceTimeValue($record['login_time'] ?? '');
        $logoutTime = $this->parseAttendanceTimeValue($record['logout_time'] ?? '');
        $workDuration = $this->normalizeDuration($record['work_duration'] ?? '');

        if ($workDuration === '' && $loginTime !== '' && $logoutTime !== '') {
            $workDuration = $this->durationFromTimes($attendanceDate, $loginTime, $logoutTime);
        }

        $payload = [
            'source_file' => $sourceFile,
            'import_batch' => $batchId,
            'source_row_no' => max(1, $rowNumber),
            'emp_id' => $empId,
            'employee_name' => trim((string) ($record['employee_name'] ?? '')),
            'branch_name' => trim((string) ($record['branch_name'] ?? '')) ?: 'HO',
            'attendance_date' => $attendanceDate,
            'login_time' => $loginTime !== '' ? $loginTime : null,
            'logout_time' => $logoutTime !== '' ? $logoutTime : null,
            'attendance_status' => trim((string) ($record['attendance_status'] ?? '')),
            'work_duration' => $workDuration,
            'late_bucket' => $this->getLateBucketLabel($loginTime),
            'raw_row' => json_encode($rawRow, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'row_hash' => md5(json_encode([
                $empId,
                $attendanceDate,
                $loginTime,
                $logoutTime,
                trim((string) ($record['attendance_status'] ?? '')),
                $workDuration,
                trim((string) ($record['branch_name'] ?? '')) ?: 'HO',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $inserted = HoAttendanceImport::query()->insertOrIgnore($payload);

        if ($inserted === 1) {
            $result['inserted']++;
            return;
        }

        $result['duplicates']++;
    }

    private function normalizeRow(array $row): array
    {
        return array_map(function ($value): string {
            if ($value instanceof DateTimeInterface) {
                return $value->format('Y-m-d H:i:s');
            }

            return trim((string) $value);
        }, $row);
    }

    private function rowHasContent(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function defaultMapping(): array
    {
        return [
            'detected' => false,
            'emp_id' => [0],
            'employee_name' => [1],
            'branch_name' => [2],
            'attendance_date' => [3],
            'login_time' => [4],
            'logout_time' => [5],
            'attendance_status' => [6],
            'work_duration' => [7],
            'datetime' => [],
        ];
    }

    private function mapAttendanceImportHeaders(array $headers): array
    {
        $aliases = [
            'emp_id' => ['empid', 'employeeid', 'employeecode', 'empcode', 'staffid', 'staffcode', 'employeeno', 'empno', 'code', 'userid'],
            'employee_name' => ['name', 'employeename', 'staffname', 'username', 'employee'],
            'branch_name' => ['branch', 'branchname', 'location', 'unit', 'department', 'branchcode'],
            'attendance_date' => ['date', 'attendancedate', 'logdate', 'workdate', 'punchdate', 'indate'],
            'login_time' => ['time', 'logintime', 'intime', 'checkin', 'checkintime', 'signin', 'signintime', 'punchtime', 'firstin', 'in'],
            'logout_time' => ['logouttime', 'outtime', 'checkout', 'checkouttime', 'signout', 'signouttime', 'lastout', 'punchout', 'out'],
            'attendance_status' => ['status', 'attendancestatus', 'remark', 'remarks', 'state'],
            'work_duration' => ['workduration', 'workinghours', 'workedhours', 'hoursworked', 'totalhours', 'duration', 'workhrs', 'hours'],
            'datetime' => ['datetime', 'punchdatetime', 'logdatetime', 'checkindatetime', 'transactiontime', 'timestamp'],
        ];

        $mapped = ['detected' => false];
        $normalizedHeaders = array_map(fn ($header): string => $this->normalizeHeader($header), $headers);

        foreach ($aliases as $key => $candidates) {
            $mapped[$key] = [];
            foreach ($normalizedHeaders as $index => $header) {
                if (in_array($header, $candidates, true)) {
                    $mapped[$key][] = $index;
                }
            }
        }

        $mapped['detected'] = ! empty($mapped['emp_id']) || ! empty($mapped['attendance_date']) || ! empty($mapped['datetime']) || ! empty($mapped['login_time']) || ! empty($mapped['employee_name']);

        return $mapped;
    }

    private function normalizeHeader($value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower(trim((string) $value))) ?? '';
    }

    private function firstMappedValue(array $row, array $indexes): string
    {
        foreach ($indexes as $index) {
            if (! array_key_exists($index, $row)) {
                continue;
            }

            $value = trim((string) $row[$index]);

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function extractAttendanceImportRecord(array $row, array $mapping): array
    {
        $dateValue = $this->firstMappedValue($row, $mapping['attendance_date'] ?? []);
        $dateTimeValue = $this->firstMappedValue($row, $mapping['datetime'] ?? []);
        $loginValue = $this->firstMappedValue($row, $mapping['login_time'] ?? []);
        $logoutValue = $this->firstMappedValue($row, $mapping['logout_time'] ?? []);

        return [
            'emp_id' => trim($this->firstMappedValue($row, $mapping['emp_id'] ?? [])),
            'employee_name' => trim($this->firstMappedValue($row, $mapping['employee_name'] ?? [])),
            'branch_name' => trim($this->firstMappedValue($row, $mapping['branch_name'] ?? [])) ?: 'HO',
            'attendance_status' => trim($this->firstMappedValue($row, $mapping['attendance_status'] ?? [])),
            'work_duration' => trim($this->firstMappedValue($row, $mapping['work_duration'] ?? [])),
            'attendance_date' => $dateValue !== '' ? $this->parseAttendanceDateValue($dateValue) : $this->parseAttendanceDateValue($dateTimeValue),
            'login_time' => $loginValue !== '' ? $this->parseAttendanceTimeValue($loginValue) : $this->parseAttendanceTimeValue($dateTimeValue),
            'logout_time' => $logoutValue !== '' ? $this->parseAttendanceTimeValue($logoutValue) : '',
        ];
    }

    private function parseAttendanceDateValue($value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        $formats = [
            'Y-m-d', 'd-m-Y', 'd/m/Y', 'm/d/Y', 'Y/m/d', 'd.m.Y', 'd-M-Y', 'd M Y', 'M d Y',
            'd/m/y', 'd-m-y', 'Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y H:i:s', 'd/m/Y H:i',
            'd-m-Y H:i:s', 'd-m-Y H:i', 'm/d/Y H:i:s', 'm/d/Y H:i', 'd/m/Y h:i:s A',
            'd/m/Y h:i A', 'd-m-Y h:i:s A', 'd-m-Y h:i A', 'm/d/Y h:i:s A', 'm/d/Y h:i A',
        ];

        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);

            if ($date instanceof DateTimeImmutable) {
                return $date->format('Y-m-d');
            }
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? date('Y-m-d', $timestamp) : '';
    }

    private function parseAttendanceTimeValue($value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        if (preg_match('/^\d{1,2}:\d{2}$/', $value) === 1) {
            return $value.':00';
        }

        $formats = [
            'H:i:s', 'H:i', 'h:i:s A', 'h:i A', 'g:i:s A', 'g:i A', 'Y-m-d H:i:s', 'Y-m-d H:i',
            'd/m/Y H:i:s', 'd/m/Y H:i', 'd-m-Y H:i:s', 'd-m-Y H:i', 'm/d/Y H:i:s', 'm/d/Y H:i',
            'd/m/Y h:i:s A', 'd/m/Y h:i A', 'd-m-Y h:i:s A', 'd-m-Y h:i A', 'm/d/Y h:i:s A', 'm/d/Y h:i A',
        ];

        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);

            if ($date instanceof DateTimeImmutable) {
                return $date->format('H:i:s');
            }
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? date('H:i:s', $timestamp) : '';
    }

    private function normalizeDuration($value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/^(\d{1,3}):(\d{2})(?::(\d{2}))?$/', $value, $matches) === 1) {
            $seconds = isset($matches[3]) ? (int) $matches[3] : 0;

            return sprintf('%02d:%02d:%02d', (int) $matches[1], (int) $matches[2], $seconds);
        }

        if (preg_match('/(\d{1,3}):(\d{2})/', $value, $matches) === 1) {
            return sprintf('%02d:%02d:00', (int) $matches[1], (int) $matches[2]);
        }

        return $value;
    }

    private function durationFromTimes(string $attendanceDate, string $loginTime, string $logoutTime): string
    {
        $checkIn = Carbon::parse($attendanceDate.' '.$loginTime);
        $checkOut = Carbon::parse($attendanceDate.' '.$logoutTime);

        if ($checkOut->lte($checkIn)) {
            return '';
        }

        $seconds = $checkIn->diffInSeconds($checkOut);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
    }

    private function getLateBucketLabel(string $loginTime): string
    {
        if ($loginTime === '') {
            return 'Unknown';
        }

        if ($loginTime <= '09:30:59') {
            return 'Before 9:30';
        }

        if ($loginTime <= '09:40:59') {
            return '09:30 - 09:40';
        }

        if ($loginTime <= '10:00:59') {
            return '09:40 - 10:00';
        }

        if ($loginTime <= '10:30:59') {
            return '10:00 - 10:30';
        }

        return 'After 10:30';
    }

    private function isSummaryAttendanceReportRow(array $row): bool
    {
        foreach ($row as $value) {
            $text = trim((string) $value);

            if ($text !== '' && stripos($text, 'Monthly Status Report') !== false) {
                return true;
            }
        }

        foreach ($row as $value) {
            $label = $this->normalizeHeader($value);

            if ($label !== '') {
                return in_array($label, ['days', 'employee'], true);
            }
        }

        return false;
    }

    private function parseSummaryReportDateRange(array $row): ?array
    {
        foreach ($row as $value) {
            $text = trim((string) $value);

            if ($text === '') {
                continue;
            }

            if (preg_match('/([A-Za-z]{3,9}\s+\d{1,2}\s+\d{4})\s+To\s+([A-Za-z]{3,9}\s+\d{1,2}\s+\d{4})/i', $text, $matches) !== 1) {
                continue;
            }

            $startTimestamp = strtotime($matches[1]);
            $endTimestamp = strtotime($matches[2]);

            if ($startTimestamp === false || $endTimestamp === false) {
                continue;
            }

            return [
                'start' => new DateTimeImmutable(date('Y-m-d', $startTimestamp)),
                'end' => new DateTimeImmutable(date('Y-m-d', $endTimestamp)),
            ];
        }

        return null;
    }

    private function buildSummaryDateMap(array $row, ?DateTimeInterface $rangeStart = null): array
    {
        $dateMap = [];
        $lastDay = null;
        $cursor = $rangeStart ? new DateTimeImmutable($rangeStart->format('Y-m-01')) : null;

        foreach ($row as $index => $value) {
            $text = trim((string) $value);

            if ($text === '' || preg_match('/^(\d{1,2})\b/', $text, $matches) !== 1) {
                continue;
            }

            $day = (int) $matches[1];

            if ($day < 1 || $day > 31) {
                continue;
            }

            if (! $cursor instanceof DateTimeImmutable) {
                $cursor = new DateTimeImmutable(date('Y-m-01'));
            }

            if ($lastDay !== null && $day < $lastDay) {
                $cursor = $cursor->modify('first day of next month');
            }

            $dateMap[$index] = $cursor->setDate((int) $cursor->format('Y'), (int) $cursor->format('m'), $day)->format('Y-m-d');
            $lastDay = $day;
        }

        return $dateMap;
    }

    private function extractSummaryEmployeeDetails(array $row): array
    {
        foreach ($row as $value) {
            $text = trim((string) $value);

            if ($text === '') {
                continue;
            }

            if (preg_match('/\b(\d{1,10})\s*:\s*(.+)$/', $text, $matches) !== 1) {
                continue;
            }

            $employeeName = preg_replace('/\s+/', ' ', str_replace('.', ' ', trim((string) $matches[2]))) ?? trim((string) $matches[2]);

            return [
                'emp_id' => trim((string) $matches[1]),
                'employee_name' => trim((string) $employeeName),
            ];
        }

        return ['emp_id' => '', 'employee_name' => ''];
    }

    private function buildSummaryDailyValueMap(array $row, array $dateMap): array
    {
        $dailyValues = [];

        foreach ($dateMap as $columnIndex => $attendanceDate) {
            if (! array_key_exists($columnIndex, $row)) {
                continue;
            }

            $value = trim((string) $row[$columnIndex]);

            if ($value !== '') {
                $dailyValues[$attendanceDate] = $value;
            }
        }

        return $dailyValues;
    }
}
