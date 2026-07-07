<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OutsourceLocation extends Model
{
    protected $table = 'outsource_locations';

    protected $fillable = [
        'location_code',
        'name',
        'addressline',
        'area',
        'city',
        'state',
        'pincode',
        'url',
        'latitude',
        'longitude',
        'status',
    ];

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(
            Employee::class,
            'outsource_employee_locations',
            'outsource_location_id',
            'employee_id'
        );
    }
}
