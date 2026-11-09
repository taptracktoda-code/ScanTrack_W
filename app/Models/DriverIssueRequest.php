<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverIssueRequest extends Model
{
    protected $guarded = [];
    protected $casts = ['decided_at' => 'datetime', 'attachments' => 'array'];

    public function attachmentData(): array
    {
        return collect($this->attachments ?? [])->map(fn ($file, $index) => [
            'name' => $file['name'],
            'size' => $file['size'],
            'url' => \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'reports.attachments.show', now()->addMinutes(15), ['issue' => $this->id, 'attachment' => $index]
            ),
        ])->all();
    }

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
