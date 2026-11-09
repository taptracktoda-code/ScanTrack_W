<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\DriverIssueRequest;
use App\Models\OrganizationQueueEntry;
use App\Models\TodaOrganization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DriverReportReviewService
{
    public function review(User $manager, DriverIssueRequest $issue, array $attributes): void
    {
        abort_unless($manager->isManager() && (int) $issue->organization_id === (int) $manager->organization_id, 404);
        $removal = app(QueueRemovalService::class);
        $notifications = app(MobileNotificationService::class);
        DB::transaction(function () use ($manager, $issue, $attributes, $removal, $notifications): void {
            TodaOrganization::whereKey($issue->organization_id)->lockForUpdate()->firstOrFail();
            $driver = Driver::whereKey($issue->driver_id)->lockForUpdate()->firstOrFail();
            $entry = $issue->queue_entry_id !== null
                ? OrganizationQueueEntry::whereKey($issue->queue_entry_id)->lockForUpdate()->firstOrFail() : null;
            $locked = DriverIssueRequest::whereKey($issue->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') throw ValidationException::withMessages(['request' => ['This request has already been reviewed. Refresh to see the decision.']]);
            if ($attributes['decision'] === 'approved') {
                if ((int) $driver->organization_id !== (int) $issue->organization_id || ($entry !== null && $entry->status !== 'WAITING')) {
                    throw ValidationException::withMessages(['queue' => ['This request is no longer eligible for approval in your TODA. Refresh the request; it can be rejected with an explanation.']]);
                }
                if ($entry !== null) $removal->remove($manager, $entry, 'other', $attributes['decision_reason']);
            }
            $locked->update(['status' => $attributes['decision'], 'decision_reason' => $attributes['decision_reason'],
                'reviewed_by' => $manager->id, 'decided_at' => now(), 'pending_entry_id' => null]);
            $notifications->notifyDriver($driver, 'Issue request '.$attributes['decision'], $attributes['decision_reason'],
                'system', ['screen' => 'issues', 'issue_id' => $issue->id]);
        });
    }
}
