<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TodaOrganization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'barangay_travel_area',
        'address',
        'contact_number',
        'email',
        'status',
    ];

    public function managers()
    {
        return $this->hasMany(User::class, 'organization_id')->where('role', User::ROLE_MANAGER);
    }

    public function drivers()
    {
        return $this->hasMany(Driver::class, 'organization_id');
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class, 'organization_id');
    }

    public function queueEntries()
    {
        return $this->hasMany(OrganizationQueueEntry::class, 'organization_id');
    }

    public function tripDestinations()
    {
        return $this->hasMany(OrganizationDestination::class, 'organization_id');
    }
}
