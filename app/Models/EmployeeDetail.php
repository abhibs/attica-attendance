<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDetail extends Model
{
    protected $table = 'employeeDetails';

    public $timestamps = false;

    protected $fillable = [
        'empName',
        'employeeId',
        'designation',
        'bankName',
        'bankAcNo',
        'ifscCode',
        'passbookDoc',
        'salary',
        'branchId',
        'status',
        'accountVerified',
        'date',
        'time',
        'totalWorkingDays',
        'absentDays',
        'presentDays',
        'penalty',
        'advanceSalary',
        'finalSalary',
        'salaryPaymentStatus',
        'salaryVerifiedDate',
        'salaryVerifiedTime',
        'salaryPaidDate',
        'salaryPaidTime',
        'salaryPaidBy',
        'salaryBankName',
        'salaryProcessingBy',
        'salaryProcessingUser',
        'salaryProcessingTime',
        'aadhaarNo',
        'panNo',
        'uanNumber',
        'pfAmount',
        'remarks',
        'salaryDate',
    ];

    protected $casts = [
        'salary' => 'decimal:2',
        'pfAmount' => 'decimal:2',
        'totalWorkingDays' => 'decimal:2',
        'absentDays' => 'decimal:2',
        'presentDays' => 'decimal:2',
        'penalty' => 'decimal:2',
        'advanceSalary' => 'decimal:2',
        'finalSalary' => 'decimal:2',
        'salaryProcessingTime' => 'datetime',
        'salaryVerifiedDate' => 'date',
        'salaryPaidDate' => 'date',
        'salaryDate' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employeeId', 'empId');
    }

    public function getPassbookDocUrlAttribute(): string
    {
        $path = trim((string) $this->passbookDoc);

        if ($path === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $path) === 1) {
            return $path;
        }

        $normalizedPath = ltrim($path, '/');

        if (str_starts_with($normalizedPath, 'public/')) {
            $normalizedPath = substr($normalizedPath, 7);
        }

        if (! str_contains($normalizedPath, '/')) {
            $normalizedPath = 'EmployeeDocuments/'.$normalizedPath;
        }

        return function_exists('project_asset')
            ? \project_asset($normalizedPath)
            : asset($normalizedPath);
    }
}
