<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    public function user()
    {
        return $this->hasOne(User::class);
    }

    protected $fillable = [
        'name',
        'license_number',
        'contact_number',
        'vehicle_number',
        'status',
        'first_name',
        'middle_name',
        'last_name',
        'address',
        'license_expiration_date',
        'organization_id',
    ];

    protected $casts = ['license_expiration_date' => 'date'];

    public function organization()
    {
        return $this->belongsTo(TodaOrganization::class, 'organization_id');
    }

    public function vehicle()
    {
        return $this->hasOne(Vehicle::class, 'plate_number', 'vehicle_number');
    }

    public function qrCode()
    {
        return $this->hasOne(QrCode::class);
    }

    public function logs()
    {
        return $this->hasMany(DriverLog::class);
    }
}
