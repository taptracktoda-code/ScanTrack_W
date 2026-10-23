<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    protected $fillable = [
        'trip_id',
        'driver_id',
        'destination_id',
        'destination',
        'passenger_count',
        'status',
        'departure_time',
        'arrival_time',
    ];

    protected $casts = [
        'passenger_count' => 'integer',
        'departure_time' => 'datetime',
        'arrival_time' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function destinationRecord()
    {
        return $this->belongsTo(OrganizationDestination::class, 'destination_id');
    }
}
