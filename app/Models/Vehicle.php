<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = [
        'name',
        'plate_number',
        'vehicle_type',
        'status',
        'organization_id',
    ];

    public function organization()
    {
        return $this->belongsTo(TodaOrganization::class, 'organization_id');
    }

    public function driver()
    {
        return $this->hasOne(Driver::class, 'vehicle_number', 'plate_number');
    }
}
