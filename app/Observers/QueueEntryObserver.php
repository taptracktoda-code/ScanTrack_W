<?php

namespace App\Observers;

use App\Models\OrganizationQueueEntry;
use App\Services\NextInLineService;

class QueueEntryObserver
{
    public function saved(OrganizationQueueEntry $entry): void
    {
        if ($entry->wasRecentlyCreated || $entry->wasChanged('status')) {
            // Called inside the same organization transaction as queue changes.
            // The notifier locks the organization again and atomically claims the new head.
            app(NextInLineService::class)->notify((int) $entry->organization_id);
        }
    }
}
