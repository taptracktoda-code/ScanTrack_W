<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\DriverLog;
use App\Models\OrganizationQueueEntry;
use App\Models\TerminalActivity;
use App\Models\TodaOrganization;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QueueRemovalService
{
    public function remove(User $manager, OrganizationQueueEntry $entry, string $reason, ?string $details): void
    {
        abort_unless($manager->isManager() && (int) $entry->organization_id === (int) $manager->organization_id, 404);
        DB::transaction(function () use ($manager, $entry, $reason, $details): void {
            $organization = TodaOrganization::whereKey($manager->organization_id)->lockForUpdate()->firstOrFail();
            $driver = Driver::whereKey($entry->driver_id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $driver->organization_id === (int) $organization->id, 404);
            $locked = OrganizationQueueEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'WAITING' || Trip::where('driver_id', $driver->id)->where('status', 'in_progress')->exists()) {
                throw ValidationException::withMessages(['queue' => ['This driver is no longer waiting. Refresh the request before reviewing it.']]);
            }
            $text = $reason === 'left_terminal' ? 'Left terminal' : $details;
            $timestamp = now();
            $locked->forceFill(['status' => 'CANCELLED', 'completed_at' => $timestamp, 'removed_at' => $timestamp,
                'removed_by' => $manager->id, 'removal_reason' => $reason, 'removal_details' => $reason === 'other' ? $details : null])->save();
            $latest = DriverLog::where('driver_id', $driver->id)->latest('created_at')->latest('id')->first();
            if ($latest?->attendance_type === 'ENTRY') {
                $legacyCheckIn = $locked->entry_log_id === null && $locked->queued_by && $latest->scan_method === 'QR'
                    && ($latest->scanned_at ?? $latest->created_at)?->equalTo($locked->created_at);
                $entryLog = (int) $latest->organization_id === (int) $organization->id
                    && ($locked->entry_log_id === $latest->id || $legacyCheckIn) ? $latest : null;
                DriverLog::create([
                    'driver_id' => $driver->id, 'organization_id' => $organization->id,
                    'entry_log_id' => $entryLog?->id, 'terminal_id' => $entryLog?->terminal_id,
                    'attendance_type' => 'EXIT', 'scanned_at' => $timestamp, 'scan_method' => 'MANUAL',
                ]);
            }
            TerminalActivity::create(['admin_id' => $manager->id, 'driver_id' => $driver->id,
                'activity' => 'Driver removed from queue', 'description' => 'Queue #'.$locked->queue_number.' at '.$organization->name.': '.$text]);
            app(MobileNotificationService::class)->notifyDriver($driver, 'Removed from queue',
                'Your manager removed you from the queue: '.$text.'. Check in again to join the end of the queue.',
                'system', ['screen' => 'dashboard', 'entry_id' => $locked->id, 'reason' => $text]);
        });
    }
}
