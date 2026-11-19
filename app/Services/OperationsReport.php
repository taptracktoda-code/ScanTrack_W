<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\DriverLog;
use App\Models\OrganizationQueueEntry;
use App\Models\TodaOrganization;
use App\Models\Trip;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OperationsReport
{
    public const ZONE = 'Asia/Manila';

    public const TYPES = [
        'entry-exit' => 'Driver Entry and Exit Report',
        'queue-monitoring' => 'Queue Monitoring Report',
        'trip-history' => 'Driver Trip History Report',
        'terminal-activity' => 'Terminal Activity Report',
        'combined' => 'Combined Entry, Exit, and Queue Monitoring Report',
    ];

    public const STATUSES = [
        'WAITING' => 'Waiting',
        'SERVING' => 'On Trip',
        'COMPLETED' => 'Completed',
        'CANCELLED' => 'Removed',
    ];

    public const UNKNOWN = 'Not Available';

    public function filters(Request $request, ?int $managedOrganizationId = null): array
    {
        $driverRule = Rule::exists('drivers', 'id')->where(
            fn ($query) => $query->when($managedOrganizationId !== null, fn ($query) => $query->where('organization_id', $managedOrganizationId))
        );

        return array_filter($request->validate([
            'terminal' => ['nullable', 'string', 'max:255'],
            'organization_id' => $managedOrganizationId === null ? ['nullable', 'integer', 'exists:toda_organizations,id'] : ['prohibited'],
            'driver_id' => ['nullable', 'integer', $driverRule],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('start_date'), 'after_or_equal:start_date')],
            'status' => ['nullable', Rule::in(array_keys(self::STATUSES))],
        ]), fn ($value) => $value !== null);
    }

    public function snapshot(array $filters, bool $includeAssignments = false, ?int $managedOrganizationId = null): array
    {
        $connection = DB::connection();
        $outermost = $connection->transactionLevel() === 0;
        $driver = $connection->getDriverName();
        if ($outermost && in_array($driver, ['mysql', 'mariadb'], true)) {
            $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }

        [$logs, $entries, $waiting, $trips, $drivers, $organizations] = $connection->transaction(function () use ($connection, $outermost, $driver, $filters, $managedOrganizationId) {
            if ($outermost && $driver === 'pgsql') {
                $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
            }
            $driverId = $filters['driver_id'] ?? null;

            $logs = DriverLog::when($driverId, fn ($query) => $query->where('driver_id', $driverId))
                ->when($managedOrganizationId !== null, fn ($query) => $query->where('organization_id', $managedOrganizationId))
                ->whereIn('attendance_type', ['ENTRY', 'EXIT'])
                ->orderByRaw('COALESCE(scanned_at, created_at)')->orderBy('id')->get();
            $queue = OrganizationQueueEntry::when($managedOrganizationId !== null, fn ($query) => $query->where('organization_id', $managedOrganizationId));
            $entries = (clone $queue)->when($driverId, fn ($query) => $query->where('driver_id', $driverId))->get();
            $waiting = $queue->where('status', 'WAITING')->orderBy('queue_number')->orderBy('id')
                ->get(['id', 'driver_id', 'organization_id', 'status'])->groupBy('organization_id');
            $trips = Trip::with('destinationRecord:id,organization_id')
                ->when($driverId, fn ($query) => $query->where('driver_id', $driverId))
                ->when($managedOrganizationId !== null, fn ($query) => $this->limitTripsToOrganization($query, $managedOrganizationId))
                ->get();
            $drivers = Driver::when($driverId, fn ($query) => $query->whereKey($driverId))
                ->when($managedOrganizationId !== null, fn ($query) => $query->whereIntegerInRaw('id',
                    $logs->pluck('driver_id')->merge($entries->pluck('driver_id'))->merge($trips->pluck('driver_id'))->unique()->all()
                ))
                ->orderBy('name')->orderBy('id')->get(['id', 'name', 'organization_id', 'assigned_terminal'])->keyBy('id');
            $organizations = TodaOrganization::when($managedOrganizationId !== null, fn ($query) => $query->whereKey($managedOrganizationId))
                ->pluck('name', 'id');

            return [$logs, $entries, $waiting, $trips, $drivers, $organizations];
        });

        $now = now()->toImmutable();
        $start = isset($filters['start_date']) ? PhilippineDay::bounds($filters['start_date'])[0] : null;
        $end = isset($filters['end_date']) ? PhilippineDay::bounds($filters['end_date'])[1] : null;
        $periodIncludes = function (?CarbonInterface ...$times) use ($start, $end): bool {
            if (! $start && ! $end) {
                return true;
            }

            return collect($times)->contains(fn ($time) => $time && (! $start || $time >= $start) && (! $end || $time < $end));
        };

        $entryLinks = $this->queueEntryLinks($entries, $logs);
        $exitLinks = $this->exitLinks($logs);
        $tripLinks = $this->tripLinks($entries, $trips);
        $queueByLog = $entries->filter(fn ($entry) => isset($entryLinks[$entry->id]))
            ->groupBy(fn ($entry) => $entryLinks[$entry->id]->id);
        $logByExit = $exitLinks->flip();
        $tripByQueue = $tripLinks->flip();
        $trips = $trips->keyBy('id');
        $logsById = $logs->keyBy('id');
        $positions = collect();
        foreach ($waiting as $line) {
            foreach (DriverQueueService::waitingLine($line) as $index => $entry) {
                $positions[$entry->id] = $index + 1;
            }
        }

        $completedByDriver = $trips->where('status', 'completed')->countBy('driver_id');
        $attendanceRows = collect();
        $queueRows = collect();
        $tripRows = collect();
        $standaloneRows = collect();
        $latestScans = $logs->keyBy('driver_id');
        $out = $entries->where('status', 'SERVING')->pluck('driver_id')
            ->merge($trips->where('status', 'in_progress')->pluck('driver_id'))->flip();
        $inside = collect();

        $identity = fn ($driverId, $organizationId, $terminal) => [
            'driver_id' => $driverId,
            'driver' => $drivers->get($driverId)?->name ?? self::UNKNOWN,
            'organization_id' => $organizationId,
            'organization' => $organizations[$organizationId] ?? self::UNKNOWN,
            'terminal' => $terminal,
            'assigned_terminal' => $managedOrganizationId === null || (int) $drivers->get($driverId)?->organization_id === $managedOrganizationId
                ? $drivers->get($driverId)?->assigned_terminal : null,
            'total_trips' => $completedByDriver[$driverId] ?? 0,
        ];

        $scopeIncludes = fn ($row) => (! isset($filters['driver_id']) || $row['driver_id'] == $filters['driver_id'])
            && ($managedOrganizationId === null || (int) $row['organization_id'] === $managedOrganizationId)
            && (! isset($filters['organization_id']) || $row['organization_id'] == $filters['organization_id'])
            && (! isset($filters['terminal']) || $row['terminal'] === $filters['terminal']);
        $statusIncludes = fn ($queue) => ! isset($filters['status']) || $queue?->status === $filters['status'];

        $makeRow = fn ($context, $entry, $exit, $queue, $trip) => $context + [
            'entry' => $entry,
            'exit' => $exit,
            'queue' => $queue,
            'trip' => $trip,
            'position' => $positions[$queue?->id] ?? null,
            'waiting' => $this->waitingSeconds($queue, $trip, $now),
        ];

        foreach ($entries as $queue) {
            $entry = $entryLinks[$queue->id] ?? null;
            $exit = $logsById[$logByExit[$entry?->id] ?? null] ?? null;
            $trip = $trips[$tripByQueue[$queue->id] ?? null] ?? null;
            // A known terminal belongs to the scan that opened the visit.
            $row = $makeRow($identity($queue->driver_id, $queue->organization_id, $entry?->terminal_id), $entry, $exit, $queue, $trip);
            if (! $scopeIncludes($row) || ! $statusIncludes($queue)) {
                continue;
            }

            if ($periodIncludes($queue->created_at, $queue->served_at, $queue->completed_at, $queue->removed_at,
                $trip?->departure_time, $trip?->arrival_time, self::scanTime($entry), self::scanTime($exit))) {
                $queueRows->push($row);
            }
            if ($trip && $periodIncludes($trip->departure_time, $trip->arrival_time)) {
                $tripRows->push($row);
            }
        }

        $displayedVisits = $queueRows->map(fn ($row) => $row['entry']?->id)->filter()->flip();
        foreach ($logs as $log) {
            if ($exitLinks->has($log->id)) {
                continue;
            }
            $entry = $log->attendance_type === 'ENTRY' ? $log : null;
            $exit = $entry ? ($logsById[$logByExit[$log->id] ?? null] ?? null) : $log;
            $queues = $queueByLog[$log->id] ?? collect();
            $context = $identity($log->driver_id, $log->organization_id ?? $queues->first()?->organization_id, $log->terminal_id);
            $row = $makeRow($context, $entry, $exit, null, null);

            if (! $scopeIncludes($row) || ! $periodIncludes(self::scanTime($entry), self::scanTime($exit))
                || (isset($filters['status']) && ! $queues->contains($statusIncludes))) {
                continue;
            }
            $attendanceRows->push($row);
            if (! $displayedVisits->has($log->id)) {
                $standaloneRows->push($row);
            }
            if ($entry && $latestScans[$log->driver_id]->id === $entry->id && ! $out->has($log->driver_id)
                && ($managedOrganizationId === null || (int) $drivers->get($log->driver_id)?->organization_id === $managedOrganizationId)) {
                $inside->push($log->driver_id);
            }
        }

        foreach ($trips as $trip) {
            if ($tripLinks->has($trip->id) || isset($filters['status']) || ! $periodIncludes($trip->departure_time, $trip->arrival_time)) {
                continue;
            }
            $row = $makeRow($identity($trip->driver_id, $trip->destinationRecord?->organization_id ?? $trip->organization_id, null), null, null, null, $trip);
            if ($scopeIncludes($row)) {
                $tripRows->push($row);
                $standaloneRows->push($row);
            }
        }

        $countDrivers = fn ($status) => $queueRows->filter(fn ($row) => $row['queue']->status === $status)->unique('driver_id')->count();
        $assignments = collect();
        if ($includeAssignments) {
            if (isset($filters['terminal']) || isset($filters['start_date']) || isset($filters['end_date']) || isset($filters['status'])) {
                $matchingDrivers = $queueRows->concat($attendanceRows)->concat($tripRows)->pluck('driver_id')->flip();
                $drivers = $drivers->intersectByKeys($matchingDrivers);
            } elseif (isset($filters['organization_id'])) {
                $drivers = $drivers->where('organization_id', $filters['organization_id']);
            }
            $assignments = $drivers->map(fn (Driver $driver) => [
                'driver_id' => $driver->id,
                'driver' => $driver->name,
                'organization' => $organizations[$driver->organization_id] ?? self::UNKNOWN,
                'assigned_terminal' => $driver->assigned_terminal,
            ]);
        }

        return [
            'summary' => [
                'Total Drivers Inside Terminals' => $inside->unique()->count(),
                'Total Drivers Waiting' => $countDrivers('WAITING'),
                'Total Drivers On Trip' => $queueRows->filter(fn ($row) => $row['queue']->status === 'SERVING')->pluck('driver_id')
                    ->merge($tripRows->filter(fn ($row) => $row['trip']->status === 'in_progress')->pluck('driver_id'))->unique()->count(),
                'Total Completed Trips' => $tripRows->filter(fn ($row) => $row['trip']->status === 'completed'
                    && $periodIncludes($row['trip']->arrival_time))->count(),
                'Total Drivers Removed from Queue' => $countDrivers('CANCELLED'),
            ],
            'attendance' => $this->latestFirst($attendanceRows),
            'queue' => $this->latestFirst($queueRows),
            'trips' => $tripRows->sortByDesc(fn ($row) => $row['trip']->departure_time)->values(),
            'records' => $this->latestFirst($queueRows->concat($standaloneRows)),
            'assignments' => $assignments,
        ];
    }

    private function limitTripsToOrganization(Builder $query, int $organizationId): Builder
    {
        $destinationInOrganization = fn ($destination) => $destination->where('organization_id', $organizationId);

        return $query->where(function ($query) use ($organizationId, $destinationInOrganization) {
            $query->where('organization_id', $organizationId)
                ->orWhere(fn ($query) => $query->whereNull('organization_id')->whereHas('destinationRecord', $destinationInOrganization));
        })->whereDoesntHave('destinationRecord', fn ($query) => $query->where('organization_id', '<>', $organizationId))
            ->where(fn ($query) => $query->whereNull('queue_entry_id')->orWhereIn('queue_entry_id',
                OrganizationQueueEntry::where('organization_id', $organizationId)->select('id')
            ));
    }

    private function queueEntryLinks(Collection $entries, Collection $logs): Collection
    {
        $byId = $logs->where('attendance_type', 'ENTRY')->keyBy('id');
        $linkedLogs = $entries->pluck('entry_log_id')->filter()->flip();
        $legacyLogs = $byId->where('scan_method', 'QR')->reject(fn ($log) => $linkedLogs->has($log->id))
            ->groupBy(fn ($log) => $log->driver_id.'|'.self::scanTime($log)?->getTimestamp());
        $legacyQueues = $entries->whereNull('entry_log_id')->whereNotNull('queued_by')
            ->groupBy(fn ($entry) => $entry->driver_id.'|'.$entry->created_at?->getTimestamp());

        return $entries->mapWithKeys(function ($entry) use ($byId, $legacyLogs, $legacyQueues) {
            $log = $byId[$entry->entry_log_id] ?? null;
            if ($log && $log->driver_id == $entry->driver_id && $log->organization_id == $entry->organization_id) {
                return [$entry->id => $log];
            }
            // Older manager check-ins inserted the queue row and QR log in the same scan.
            $key = $entry->driver_id.'|'.$entry->created_at?->getTimestamp();
            $logsAtJoin = $legacyLogs[$key] ?? null;
            $legacyLog = $logsAtJoin?->count() === 1 ? $logsAtJoin->first() : null;
            if (! $entry->entry_log_id && $entry->queued_by && ($legacyQueues[$key] ?? null)?->count() === 1
                && $legacyLog && ($legacyLog->organization_id === null || $legacyLog->organization_id == $entry->organization_id)) {
                return [$entry->id => $legacyLog];
            }

            return [];
        });
    }

    private function exitLinks(Collection $logs): Collection
    {
        $entries = $logs->where('attendance_type', 'ENTRY')->keyBy('id');
        $last = [];
        $links = collect();
        $closed = [];
        foreach ($logs as $log) {
            if ($log->attendance_type === 'ENTRY') {
                $last[$log->driver_id] = $log;
                continue;
            }
            $entry = $log->entry_log_id ? ($entries[$log->entry_log_id] ?? null) : ($last[$log->driver_id] ?? null);
            $sameContext = $entry && ($log->entry_log_id
                ? ($entry->organization_id === null || $entry->organization_id == $log->organization_id)
                    && ($log->terminal_id === null || $entry->terminal_id === $log->terminal_id)
                : $log->organization_id == $entry->organization_id && $entry->terminal_id === $log->terminal_id);
            if ($sameContext && $entry->driver_id == $log->driver_id && ! isset($closed[$entry->id])
                && self::scanTime($entry) <= self::scanTime($log)) {
                $links[$log->id] = $entry->id;
                $closed[$entry->id] = true;
            }
            unset($last[$log->driver_id]);
        }

        return $links;
    }

    private function tripLinks(Collection $entries, Collection $trips): Collection
    {
        $byId = $entries->keyBy('id');
        $claimed = $trips->pluck('queue_entry_id')->filter()->flip();
        $legacy = $entries->whereNotNull('served_at')->groupBy(fn ($entry) => $entry->driver_id.'|'.$entry->served_at->getTimestamp());

        return $trips->mapWithKeys(function ($trip) use ($byId, $claimed, $legacy) {
            if ($trip->queue_entry_id) {
                $queue = $byId[$trip->queue_entry_id] ?? null;

                return $queue && $queue->driver_id == $trip->driver_id ? [$trip->id => $queue->id] : [];
            }

            $organizationId = $trip->destinationRecord?->organization_id;
            $key = $trip->driver_id.'|'.$trip->departure_time?->getTimestamp();
            $candidates = ($legacy[$key] ?? collect())->filter(fn ($queue) => $organizationId && $queue->organization_id == $organizationId
                && ! $claimed->has($queue->id)
                && (($trip->status === 'in_progress' && $queue->status === 'SERVING')
                    || ($trip->status === 'completed' && $queue->status === 'COMPLETED' && $trip->arrival_time && $queue->completed_at
                        && abs($trip->arrival_time->diffInSeconds($queue->completed_at)) <= 1)));
            if ($candidates->count() !== 1) {
                return [];
            }
            $queueId = $candidates->first()->id;
            $claimed[$queueId] = true;

            return [$trip->id => $queueId];
        });
    }

    private function waitingSeconds($queue, $trip, CarbonInterface $now): ?int
    {
        $joined = $queue?->created_at;
        $end = $queue?->served_at ?? $trip?->departure_time ?? $queue?->removed_at ?? $queue?->completed_at;
        $end ??= $queue?->status === 'WAITING' ? $now : null;

        return $joined && $end && $end >= $joined ? (int) $joined->diffInSeconds($end) : null;
    }

    private function latestFirst(Collection $rows): Collection
    {
        return $rows->sortByDesc(fn ($row) => ($row['queue']?->created_at ?? $row['trip']?->departure_time
            ?? self::scanTime($row['exit'] ?? $row['entry']))?->getTimestamp())->values();
    }

    public static function scanTime(?DriverLog $log): ?CarbonInterface
    {
        return $log?->scanned_at ?? $log?->created_at;
    }

    public static function timestamp(?CarbonInterface $value): string
    {
        return $value?->copy()->timezone(self::ZONE)->format('M j, Y g:i:s A') ?? self::UNKNOWN;
    }

    public static function duration(?int $seconds): string
    {
        return $seconds === null ? self::UNKNOWN : sprintf('%dh %02dm %02ds', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }
}
