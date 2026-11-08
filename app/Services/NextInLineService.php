<?php

namespace App\Services;

use App\Models\OrganizationQueueEntry;
use App\Models\TodaOrganization;
use Illuminate\Support\Facades\DB;

class NextInLineService
{
    public function notify(int $organizationId): void
    {
        DB::transaction(function () use ($organizationId): void {
            if (! TodaOrganization::whereKey($organizationId)->lockForUpdate()->first()) return;
            $next = OrganizationQueueEntry::where('organization_id', $organizationId)
                ->where('status', 'WAITING')->orderBy('queue_number')->orderBy('id')->lockForUpdate()->first();
            if (! $next || $next->next_notified_at || ! $next->driver) return;
            // The claim and database notification commit together under the FIFO organization lock.
            $next->forceFill(['next_notified_at' => now()])->save();
            app(MobileNotificationService::class)->notifyDriver($next->driver,
                'You are next in line', 'You are now position 1. Open your queue to see its current status.',
                'system', ['screen' => 'dashboard', 'event' => 'next_in_line', 'entry_id' => $next->id]);
        });
    }
}
