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
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
        'registered_by',
    ];

    protected $casts = [
        'license_expiration_date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

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

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
