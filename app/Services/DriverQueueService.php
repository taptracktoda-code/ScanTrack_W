<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\OrganizationQueueEntry;

class DriverQueueService
{
    public function snapshot(Driver $driver): array
    {
        // One read keeps the position and waiting count on the same snapshot.
        $entries = OrganizationQueueEntry::query()
            ->where('organization_id', $driver->organization_id)
            ->whereIn('status', ['WAITING', 'SERVING'])
            ->orderBy('queue_number')->orderBy('id')->get();
        $waiting = $entries->where('status', 'WAITING')->unique('driver_id')->values();
        $own = $entries->firstWhere('driver_id', $driver->id);
        $position = $own?->status === 'WAITING'
            ? $waiting->search(fn ($entry) => $entry->driver_id === $driver->id) + 1
            : null;
        if (! $own) {
            $own = OrganizationQueueEntry::query()
                ->where('organization_id', $driver->organization_id)
                ->where('driver_id', $driver->id)
                ->whereNotIn('status', ['WAITING', 'SERVING'])
                ->latest('queue_number')->first();
        }

        return [
            'entry_id' => $own?->id,
            'status' => $own?->status,
            'position' => $position,
            'waiting_count' => $waiting->count(),
            'queue_number' => $own?->queue_number,
            'is_next' => $position === 1,
            'drivers_ahead' => $position === null ? null : $position - 1,
            'refreshed_at' => now()->toIso8601String(),
        ];
    }
}
