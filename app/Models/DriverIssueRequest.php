<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverIssueRequest extends Model
{
    protected $guarded = [];
    protected $casts = ['decided_at' => 'datetime'];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function queueEntry()
    {
        return $this->belongsTo(OrganizationQueueEntry::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
