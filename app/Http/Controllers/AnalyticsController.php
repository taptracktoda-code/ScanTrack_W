<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\DriverLog;
use App\Models\TodaOrganization;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    private const SCAN_TIME = 'COALESCE(driver_logs.scanned_at, driver_logs.created_at)';

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $start = CarbonImmutable::parse($filters['start_date'])->startOfDay();
        $end = CarbonImmutable::parse($filters['end_date'])->endOfDay();
        $organizationId = $filters['organization_id'];
        $logs = $this->filteredLogs($start, $end, $organizationId);
        $today = now()->toImmutable();
        $todayLogs = $this->filteredLogs($today->startOfDay(), $today->endOfDay(), $organizationId);

        $entryExit = (clone $logs)->selectRaw("SUM(CASE WHEN driver_logs.attendance_type = 'ENTRY' THEN 1 ELSE 0 END) as entries")
            ->selectRaw("SUM(CASE WHEN driver_logs.attendance_type = 'EXIT' THEN 1 ELSE 0 END) as exits")
            ->first();
        $todayEntryExit = (clone $todayLogs)->selectRaw("SUM(CASE WHEN driver_logs.attendance_type = 'ENTRY' THEN 1 ELSE 0 END) as entries")
            ->selectRaw("SUM(CASE WHEN driver_logs.attendance_type = 'EXIT' THEN 1 ELSE 0 END) as exits")
            ->first();

        $activityByDate = (clone $logs)
            ->selectRaw('DATE(' . self::SCAN_TIME . ') as activity_date')
            ->selectRaw("SUM(CASE WHEN driver_logs.attendance_type = 'ENTRY' THEN 1 ELSE 0 END) as entries")
            ->selectRaw("SUM(CASE WHEN driver_logs.attendance_type = 'EXIT' THEN 1 ELSE 0 END) as exits")
            ->groupByRaw('DATE(' . self::SCAN_TIME . ')')
            ->orderByDesc('activity_date')
            ->get()
            ->keyBy('activity_date');

        $trend = collect();
        for ($date = $start->startOfDay(); $date->lessThanOrEqualTo($end->startOfDay()); $date = $date->addDay()) {
            $day = $activityByDate->get($date->toDateString());
            $trend->push([
                'date' => $date->toDateString(),
                'label' => $date->format('M j'),
                'entries' => (int) ($day->entries ?? 0),
                'exits' => (int) ($day->exits ?? 0),
            ]);
        }

        $dailyActivity = $trend->reverse()->values();
        $dailyPage = max(1, (int) $request->query('daily_page', 1));
        $dailyActivity = new LengthAwarePaginator(
            $dailyActivity->forPage($dailyPage, 15)->values(),
            $dailyActivity->count(),
            15,
            $dailyPage,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => 'daily_page']
        );
        $hourExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', " . self::SCAN_TIME . ') AS INTEGER)'
            : 'HOUR(' . self::SCAN_TIME . ')';
        $logsByHour = (clone $logs)
            ->selectRaw($hourExpression . ' as activity_hour')
            ->selectRaw('COUNT(*) as scans')
            ->groupByRaw($hourExpression)
            ->pluck('scans', 'activity_hour');
        $peakHours = collect(range(0, 23))->map(fn (int $hour) => [
            'label' => CarbonImmutable::createFromTime($hour)->format('g A'),
            'scans' => (int) $logsByHour->get($hour, 0),
        ]);

        $driverCounts = (clone $logs)
            ->select('driver_logs.driver_id')
            ->selectRaw("SUM(CASE WHEN driver_logs.attendance_type = 'ENTRY' THEN 1 ELSE 0 END) as entries")
            ->selectRaw("SUM(CASE WHEN driver_logs.attendance_type = 'EXIT' THEN 1 ELSE 0 END) as exits")
            ->selectRaw('COUNT(*) as total_scans')
            ->groupBy('driver_logs.driver_id');

        $latestType = DriverLog::query()
            ->select('attendance_type')
            ->whereColumn('driver_logs.driver_id', 'drivers.id')
            ->whereIn('attendance_type', ['ENTRY', 'EXIT'])
            ->orderByRaw('COALESCE(scanned_at, created_at) DESC')
            ->orderByDesc('id')
            ->limit(1);
        $latestTime = DriverLog::query()
            ->selectRaw(self::SCAN_TIME)
            ->whereColumn('driver_logs.driver_id', 'drivers.id')
            ->whereIn('attendance_type', ['ENTRY', 'EXIT'])
            ->orderByRaw('COALESCE(scanned_at, created_at) DESC')
            ->orderByDesc('id')
            ->limit(1);

        $driverQuery = Driver::query()
            ->select('drivers.*')
            ->selectSub($latestType, 'latest_attendance_type')
            ->selectSub($latestTime, 'last_scanned_at')
            ->leftJoinSub($driverCounts, 'scan_counts', 'scan_counts.driver_id', '=', 'drivers.id')
            ->addSelect(DB::raw('COALESCE(scan_counts.entries, 0) as analytics_entries'))
            ->addSelect(DB::raw('COALESCE(scan_counts.exits, 0) as analytics_exits'))
            ->addSelect(DB::raw('COALESCE(scan_counts.total_scans, 0) as analytics_total_scans'))
            ->with(['organization', 'vehicle'])
            ->when($organizationId, fn ($query) => $query->where('drivers.organization_id', $organizationId))
            ->orderBy('drivers.name');
        $drivers = $driverQuery->paginate(15)->withQueryString();

        $currentStateQuery = Driver::query()
            ->select('drivers.id', 'drivers.organization_id')
            ->selectSub($latestType, 'latest_attendance_type')
            ->when($organizationId, fn ($query) => $query->where('drivers.organization_id', $organizationId));
        $currentState = DB::query()->fromSub($currentStateQuery, 'driver_states');
        $inside = (clone $currentState)->where('latest_attendance_type', 'ENTRY')->count();
        $outside = (clone $currentState)->where('latest_attendance_type', 'EXIT')->count();

        $organizationDriverCounts = Driver::query()
            ->select('organization_id')
            ->selectRaw('COUNT(*) as drivers_count')
            ->groupBy('organization_id');
        $organizationVehicleCounts = Driver::query()
            ->join('vehicles', 'vehicles.plate_number', '=', 'drivers.vehicle_number')
            ->select('drivers.organization_id')
            ->selectRaw('COUNT(DISTINCT vehicles.id) as vehicles_count')
            ->groupBy('drivers.organization_id');
        $organizationActivity = (clone $logs)
            ->join('drivers as activity_drivers', 'activity_drivers.id', '=', 'driver_logs.driver_id')
            ->select('activity_drivers.organization_id')
            ->selectRaw("SUM(CASE WHEN driver_logs.attendance_type = 'ENTRY' THEN 1 ELSE 0 END) as entries")
            ->selectRaw("SUM(CASE WHEN driver_logs.attendance_type = 'EXIT' THEN 1 ELSE 0 END) as exits")
            ->selectRaw('COUNT(*) as total_scans')
            ->groupBy('activity_drivers.organization_id');
        $organizations = TodaOrganization::query()
            ->leftJoinSub($organizationDriverCounts, 'driver_totals', 'driver_totals.organization_id', '=', 'toda_organizations.id')
            ->leftJoinSub($organizationVehicleCounts, 'vehicle_totals', 'vehicle_totals.organization_id', '=', 'toda_organizations.id')
            ->leftJoinSub($organizationActivity, 'activity_totals', 'activity_totals.organization_id', '=', 'toda_organizations.id')
            ->select('toda_organizations.id', 'toda_organizations.name')
            ->selectRaw('COALESCE(driver_totals.drivers_count, 0) as drivers_count')
            ->selectRaw('COALESCE(vehicle_totals.vehicles_count, 0) as vehicles_count')
            ->selectRaw('COALESCE(activity_totals.entries, 0) as entries')
            ->selectRaw('COALESCE(activity_totals.exits, 0) as exits')
            ->selectRaw('COALESCE(activity_totals.total_scans, 0) as total_scans')
            ->when($organizationId, fn ($query) => $query->where('toda_organizations.id', $organizationId))
            ->orderByDesc('total_scans')->orderBy('toda_organizations.name')->get();

        $totalVehicles = $organizationId
            ? Vehicle::query()->whereIn('plate_number', Driver::query()->select('vehicle_number')->where('organization_id', $organizationId)->whereNotNull('vehicle_number'))->count()
            : Vehicle::count();

        return view('analytics.index', [
            'filters' => $filters,
            'organizationsList' => TodaOrganization::query()->orderBy('name')->get(['id', 'name']),
            'cards' => [
                'drivers' => (clone $currentState)->count(),
                'active_drivers' => Driver::query()->whereRaw('LOWER(status) = ?', ['active'])->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))->count(),
                'organizations' => $organizationId ? TodaOrganization::whereKey($organizationId)->count() : TodaOrganization::count(),
                'vehicles' => $totalVehicles,
                'scans' => (clone $logs)->count(),
                'today_entries' => (int) ($todayEntryExit->entries ?? 0),
                'today_exits' => (int) ($todayEntryExit->exits ?? 0),
                'inside' => $inside,
            ],
            'outside' => $outside,
            'entryExit' => [
                'entries' => (int) ($entryExit->entries ?? 0),
                'exits' => (int) ($entryExit->exits ?? 0),
            ],
            'trend' => $trend,
            'dailyActivity' => $dailyActivity,
            'peakHours' => $peakHours,
            'organizations' => $organizations,
            'drivers' => $drivers,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $start = CarbonImmutable::parse($filters['start_date'])->startOfDay();
        $end = CarbonImmutable::parse($filters['end_date'])->endOfDay();
        $fileName = 'scantrack-analytics-' . $start->format('Ymd') . '-to-' . $end->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($start, $end, $filters): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Scan ID', 'Scanned At', 'Driver', 'Organization', 'Vehicle Plate', 'Attendance Type', 'Terminal']);

            $query = $this->filteredLogs($start, $end, $filters['organization_id'])
                ->leftJoin('toda_organizations', 'toda_organizations.id', '=', 'drivers.organization_id')
                ->select('driver_logs.id as log_id', 'driver_logs.attendance_type', 'driver_logs.terminal_id', 'drivers.name as driver_name', 'drivers.vehicle_number', 'toda_organizations.name as organization_name')
                ->selectRaw(self::SCAN_TIME . ' as scanned_time')
                ->orderBy('driver_logs.id');

            $query->chunkById(500, function ($rows) use ($handle): void {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->log_id,
                        $row->scanned_time,
                        $row->driver_name,
                        $row->organization_name,
                        $row->vehicle_number,
                        $row->attendance_type,
                        $row->terminal_id,
                    ]);
                }
            }, 'driver_logs.id', 'log_id');

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'period' => ['nullable', 'in:today,7,30,month'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'organization_id' => ['nullable', 'integer', 'exists:toda_organizations,id'],
        ]);

        $hasCustomRange = ! empty($validated['start_date']) || ! empty($validated['end_date']);
        if ($hasCustomRange) {
            $start = CarbonImmutable::parse($validated['start_date'] ?? $validated['end_date']);
            $end = CarbonImmutable::parse($validated['end_date'] ?? $validated['start_date']);
            if ($end->lessThan($start)) {
                throw ValidationException::withMessages(['end_date' => 'The end date must be on or after the start date.']);
            }
        } else {
            $period = $validated['period'] ?? '7';
            $end = now()->toImmutable()->startOfDay();
            $start = match ($period) {
                'today' => $end,
                '30' => $end->subDays(29),
                'month' => $end->startOfMonth(),
                default => $end->subDays(6),
            };
        }

        if ($start->diffInDays($end) > 365) {
            throw ValidationException::withMessages(['end_date' => 'The selected date range cannot exceed 366 days.']);
        }

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'organization_id' => isset($validated['organization_id']) ? (int) $validated['organization_id'] : null,
            'period' => $hasCustomRange ? null : ($validated['period'] ?? '7'),
        ];
    }

    private function filteredLogs(CarbonImmutable $start, CarbonImmutable $end, ?int $organizationId): Builder
    {
        return DB::table('driver_logs')
            ->join('drivers', 'drivers.id', '=', 'driver_logs.driver_id')
            ->whereBetween(DB::raw(self::SCAN_TIME), [$start, $end])
            ->when($organizationId, fn (Builder $query) => $query->where('drivers.organization_id', $organizationId));
    }
}