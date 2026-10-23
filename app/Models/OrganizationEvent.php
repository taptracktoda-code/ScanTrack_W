<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationEvent extends Model
{
    protected $fillable = [
        'organization_id',
        'created_by',
        'title',
        'description',
        'event_date',
        'start_time',
        'end_time',
        'location',
    ];

    protected $casts = [
        'event_date' => 'date',
    ];

    public function organization()
    {
        return $this->belongsTo(TodaOrganization::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
