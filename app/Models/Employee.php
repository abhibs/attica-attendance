<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Employee extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'employee';

    public $timestamps = false;

    protected $fillable = [
        'empId',
        'name',
        'contact',
        'mailId',
        'address',
        'location',
        'designation',
        'photo',
        'rating',
        'status',
        'attendance_blocked_on',
        'attendance_unblocked_on',
        'inactive_reason',
        'last_working_date',
        'last_login_branch_id',
        'last_login_at',
        'salary',
        'advance',
        'pf',
        'pf_eligible',
        'pfsecuritydeposit',
        'doj',
        'date_of_birth',
        'shift_timing',
        'is_night_shift',
        'is_outsourced',
        'gender',
        'marital_status',
        'remark',
    ];

    protected $hidden = [];

    protected $casts = [
        'is_night_shift' => 'boolean',
        'is_outsourced' => 'boolean',
        'pf_eligible' => 'boolean',
        'pfsecuritydeposit' => 'decimal:2',
    ];

    public function isInactive(): bool
    {
        return strcasecmp(trim((string) $this->status), 'Inactive') === 0;
    }

    public function advanceTransactions(): HasMany
    {
        return $this->hasMany(EmployeeAdvanceTransaction::class, 'employee_id');
    }

    public function advanceRequests(): HasMany
    {
        return $this->hasMany(EmployeeAdvanceRequest::class, 'employee_id');
    }

    public function detail(): HasOne
    {
        return $this->hasOne(EmployeeDetail::class, 'employeeId', 'empId')->latestOfMany();
    }

    public function latestBankDetailRequest(): HasOne
    {
        return $this->hasOne(EmployeeBankDetailRequest::class, 'employee_id')->latestOfMany();
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'employee_id');
    }

    public function siteVisitRequests(): HasMany
    {
        return $this->hasMany(SiteVisitRequest::class, 'employee_id');
    }

    public function teTrackerVisits(): HasMany
    {
        return $this->hasMany(TeTrackerVisit::class, 'employee_id');
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(EmployeeDeviceToken::class, 'employee_id');
    }

    public function locationPings(): HasMany
    {
        return $this->hasMany(EmployeeLocationPing::class, 'employee_id');
    }

    public function outsourceLocations(): BelongsToMany
    {
        return $this->belongsToMany(
            OutsourceLocation::class,
            'outsource_employee_locations',
            'employee_id',
            'outsource_location_id'
        );
    }
}
