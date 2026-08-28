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
    ];
}
