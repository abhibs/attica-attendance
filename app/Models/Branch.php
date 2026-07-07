<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $table = 'wp_branches_database';

    public $timestamps = false;

    protected $fillable = [
        'branchId',
        'branchName',
        'addressline',
        'area',
        'city',
        'state',
        'pincode',
        'timings',
        'latitude',
        'longitude',
        'url',
        'status',
    ];
}
