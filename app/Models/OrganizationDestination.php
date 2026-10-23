<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationDestination extends Model
{
    protected $fillable = [
        'organization_id',
        'created_by',
        'name',
        'status',
    ];

    public function organization()
    {
        return $this->belongsTo(TodaOrganization::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function trips()
    {
        return $this->hasMany(Trip::class, 'destination_id');
    }
}
