<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationQueueEntry extends Model
{
    protected $fillable = [
        'organization_id',
        'driver_id',
        'queue_number',
        'status',
        'queued_by',
        'served_by',
        'served_at',
        'completed_at',
    ];

    protected $casts = [
        'served_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(TodaOrganization::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'queued_by');
    }

    public function server()
    {
        return $this->belongsTo(User::class, 'served_by');
    }
}