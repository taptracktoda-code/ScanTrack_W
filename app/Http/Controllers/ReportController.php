<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\DriverLog;
use App\Models\OrganizationQueueEntry;
use App\Models\TerminalActivity;
use App\Models\TodaOrganization;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        $drivers = Driver::with('organization')->latest()->get();
        $driverLogs = DriverLog::with('driver.organization')->latest()->get();
        $monthlyPerformance = $this->monthlyDriverPerformance($drivers);

        return view('reports.index', [
            'drivers' => $drivers,
            'driverLogs' => $driverLogs,
            'monthlyPerformance' => $monthlyPerformance,
            'reportCards' => $this->reportCards($drivers, $driverLogs),
            'summaryReports' => $this->summaryReports($drivers, $driverLogs),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $type = $request->query('type', 'driver-summary');
        $types = ['driver-summary', 'driver-activity', 'driver-verification', 'vehicle-register', 'organization-activity', 'terminal-activity', 'queue-activity'];
        $type = in_array($type, $types, true) ? $type : 'driver-summary';
        $fileName = $type . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($type) {
            $handle = fopen('php://output', 'w');

            if ($type === 'driver-activity') {
                $this->writeDriverActivityCsv($handle);
            } elseif ($type === 'driver-verification') {
                $this->writeDriverVerificationCsv($handle);
            } elseif ($type === 'vehicle-register') {
                $this->writeVehicleCsv($handle);
            } elseif ($type === 'organization-activity') {
                $this->writeOrganizationCsv($handle);
            } elseif ($type === 'terminal-activity') {
                $this->writeTerminalActivityCsv($handle);
            } elseif ($type === 'queue-activity') {
                $this->writeQueueActivityCsv($handle);
            } else {
                $this->writeDriverSummaryCsv($handle);
            }

            if (is_resource($handle)) {
                fclose($handle);
            }
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function pendingLogs(): View
    {
        $pendingLogs = DriverLog::latest()->get();

        return view('reports.pending', [
            'pendingLogs' => $pendingLogs,
            'count' => $pendingLogs->count(),
        ]);
    }

    public function attendance(Request $request): View
    {
        $filters = $request->validate([
            'organization_id' => ['nullable', 'integer', 'exists:toda_organizations,id'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'attendance_type' => ['nullable', 'in:ENTRY,EXIT'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);
        $records = DriverLog::query()->with('driver.organization')
            ->when(isset($filters['organization_id']), fn ($query) => $query->whereHas('driver', fn ($driverQuery) => $driverQuery->where('organization_id', $filters['organization_id'])))
            ->when(isset($filters['driver_id']), fn ($query) => $query->where('driver_id', $filters['driver_id']))
            ->when(isset($filters['attendance_type']), fn ($query) => $query->where('attendance_type', $filters['attendance_type']))
            ->when(isset($filters['start_date']), fn ($query) => $query->whereRaw('COALESCE(scanned_at, driver_logs.created_at) >= ?', [$filters['start_date'] . ' 00:00:00']))
            ->when(isset($filters['end_date']), fn ($query) => $query->whereRaw('COALESCE(scanned_at, driver_logs.created_at) <= ?', [$filters['end_date'] . ' 23:59:59']))
            ->latest('scanned_at')
            ->latest('id')
            ->limit(250)
            ->get();

        return view('reports.activity', [
            'title' => 'Attendance',
            'description' => 'Driver entry and exit records from terminal QR scans.',
            'mode' => 'attendance',
            'records' => $records,
            'filters' => $filters,
            'organizations' => TodaOrganization::orderBy('name')->get(['id', 'name']),
            'drivers' => Driver::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function terminalActivities(): View
    {
        $records = TerminalActivity::with(['driver.organization', 'admin'])
            ->latest()
            ->limit(250)
            ->get();

        return view('reports.activity', [
            'title' => 'Terminal Activities',
            'description' => 'Recent QR scan, attendance, and driver management activity.',
            'mode' => 'activities',
            'records' => $records,
        ]);
    }

    public function transactionsApi(): JsonResponse
    {
        $transactions = DriverLog::with(['driver.organization'])
            ->latest()
            ->take(50)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'driver_id' => $log->driver_id,
                    'driver_name' => $log->driver?->name,
                    'organization' => $log->driver?->organization?->name,
                    'attendance_type' => $log->attendance_type,
                    'scan_method' => $log->scan_method,
                    'terminal_id' => $log->terminal_id,
                    'status' => $log->status ?? 'completed',
                    'scanned_at' => optional($log->scanned_at ?? $log->created_at)->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'count' => $transactions->count(),
            'transactions' => $transactions,
        ]);
    }

    private function reportCards(Collection $drivers, Collection $driverLogs): array
    {
        $activeDrivers = $drivers->where('status', 'Active')->count();
        $assignedDrivers = $drivers->whereNotNull('vehicle_number')->filter(fn ($driver) => filled($driver->vehicle_number))->count();
        $driverGrowth = $this->monthlyGrowthText($drivers);

        return [
            [
                'label' => 'Total Drivers',
                'value' => number_format($drivers->count()),
                'trend' => $driverGrowth,
            ],
            [
                'label' => 'Active Drivers',
                'value' => number_format($activeDrivers),
                'trend' => $drivers->count() > 0 ? round(($activeDrivers / $drivers->count()) * 100) . '% of drivers' : 'No drivers yet',
            ],
            [
                'label' => 'Assigned Vehicles',
                'value' => number_format($assignedDrivers),
                'trend' => $driverLogs->count() . ' driver log records',
            ],
        ];
    }

    private function summaryReports(Collection $drivers, Collection $driverLogs): array
    {
        return [
            [
                'title' => 'Driver Summary Report - ' . now()->format('F j, Y'),
                'type' => 'CSV',
                'export_type' => 'driver-summary',
                'count' => $drivers->count(),
            ],
            [
                'title' => 'QR Scan and Attendance Report - ' . now()->format('F j, Y'),
                'type' => 'CSV',
                'export_type' => 'driver-activity',
                'count' => $driverLogs->count(),
            ],
            [
                'title' => 'Driver Verification Report - ' . now()->format('F j, Y'),
                'type' => 'CSV',
                'export_type' => 'driver-verification',
                'count' => Driver::whereIn('status', ['Pending', 'Active', 'Rejected', 'Inactive'])->count(),
            ],
            [
                'title' => 'Vehicle Register - ' . now()->format('F j, Y'),
                'type' => 'CSV',
                'export_type' => 'vehicle-register',
                'count' => Vehicle::count(),
            ],
            [
                'title' => 'Organization Activity - ' . now()->format('F j, Y'),
                'type' => 'CSV',
                'export_type' => 'organization-activity',
                'count' => TodaOrganization::count(),
            ],
            [
                'title' => 'Terminal Activity - ' . now()->format('F j, Y'),
                'type' => 'CSV',
                'export_type' => 'terminal-activity',
                'count' => TerminalActivity::count(),
            ],
            [
                'title' => 'Queue Activity - ' . now()->format('F j, Y'),
                'type' => 'CSV',
                'export_type' => 'queue-activity',
                'count' => OrganizationQueueEntry::count(),
            ],
        ];
    }

    private function monthlyDriverPerformance(Collection $drivers): array
    {
        $months = collect(range(4, 0))->map(fn ($offset) => now()->startOfMonth()->subMonths($offset));
        $maxCount = max(1, $months->map(fn ($month) => $drivers->filter(function ($driver) use ($month) {
            return $driver->created_at && $driver->created_at->isSameMonth($month);
        })->count())->max());

        return $months->map(function ($month) use ($drivers, $maxCount) {
            $count = $drivers->filter(function ($driver) use ($month) {
                return $driver->created_at && $driver->created_at->isSameMonth($month);
            })->count();

            return [
                'label' => $month->format('M'),
                'count' => $count,
                'height' => max(8, round(($count / $maxCount) * 100)),
            ];
        })->all();
    }

    private function monthlyGrowthText(Collection $drivers): string
    {
        $thisMonth = $drivers->filter(fn ($driver) => $driver->created_at && $driver->created_at->isCurrentMonth())->count();
        $lastMonth = $drivers->filter(fn ($driver) => $driver->created_at && $driver->created_at->isSameMonth(now()->subMonth()))->count();

        if ($lastMonth === 0) {
            return $thisMonth . ' added this month';
        }

        $growth = round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1);

        return ($growth >= 0 ? '+' : '') . $growth . '% from last month';
    }

    private function writeDriverSummaryCsv($handle): void
    {
        fputcsv($handle, ['Driver name', 'License No.', 'Contact', 'Vehicle No.', 'Organization', 'Status', 'Created At', 'Updated At']);

        Driver::latest()->chunk(100, function ($drivers) use ($handle) {
            foreach ($drivers as $driver) {
                fputcsv($handle, [
                    $driver->name,
                    $driver->license_number,
                    $driver->contact_number,
                    $driver->vehicle_number ?: 'Unassigned',
                    $driver->organization?->name ?: 'Not assigned',
                    $driver->status,
                    optional($driver->created_at)->format('Y-m-d H:i:s'),
                    optional($driver->updated_at)->format('Y-m-d H:i:s'),
                ]);
            }
        });
    }

    private function writeDriverActivityCsv($handle): void
    {
        fputcsv($handle, ['Log ID', 'Driver', 'Vehicle No.', 'Organization', 'Scan Type', 'Scan Method', 'Terminal', 'Scanned At']);

        DriverLog::latest()->chunk(100, function ($logs) use ($handle) {
            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->driver?->name ?: 'Unknown driver',
                    $log->driver?->vehicle_number ?: 'Unassigned',
                    $log->driver?->organization?->name ?: 'Not assigned',
                    $log->attendance_type ?: 'UNKNOWN',
                    $log->scan_method ?: 'Recorded',
                    $log->terminal_id ?: 'N/A',
                    optional($log->scanned_at ?: $log->created_at)->format('Y-m-d H:i:s'),
                ]);
            }
        });
    }

    private function writeDriverVerificationCsv($handle): void
    {
        fputcsv($handle, ['Driver', 'Organization', 'Status', 'Registration Date', 'Approved At', 'Approved By', 'Rejected At', 'Rejected By', 'Rejection Reason']);

        Driver::with(['organization', 'approver', 'rejector'])->latest()->chunk(100, function ($drivers) use ($handle): void {
            foreach ($drivers as $driver) {
                fputcsv($handle, [
                    $driver->name,
                    $driver->organization?->name,
                    $driver->status,
                    optional($driver->created_at)->format('Y-m-d H:i:s'),
                    optional($driver->approved_at)->format('Y-m-d H:i:s'),
                    $driver->approver?->name,
                    optional($driver->rejected_at)->format('Y-m-d H:i:s'),
                    $driver->rejector?->name,
                    $driver->rejection_reason,
                ]);
            }
        });
    }

    private function writeVehicleCsv($handle): void
    {
        fputcsv($handle, ['Vehicle ID', 'Plate Number', 'Driver', 'Organization', 'Type', 'Model', 'Status', 'Registration Date']);

        Vehicle::with(['driver', 'organization'])->latest()->chunk(100, function ($vehicles) use ($handle): void {
            foreach ($vehicles as $vehicle) {
                fputcsv($handle, [
                    $vehicle->id,
                    $vehicle->plate_number,
                    $vehicle->driver?->name,
                    $vehicle->organization?->name,
                    $vehicle->vehicle_type,
                    $vehicle->name,
                    $vehicle->status,
                    optional($vehicle->created_at)->format('Y-m-d H:i:s'),
                ]);
            }
        });
    }

    private function writeOrganizationCsv($handle): void
    {
        fputcsv($handle, ['Organization', 'Address', 'Contact Number', 'Email', 'Status', 'Managers', 'Drivers', 'Vehicles', 'Created At']);

        TodaOrganization::withCount(['managers', 'drivers', 'vehicles'])->latest()->chunk(100, function ($organizations) use ($handle): void {
            foreach ($organizations as $organization) {
                fputcsv($handle, [
                    $organization->name,
                    $organization->address,
                    $organization->contact_number,
                    $organization->email,
                    $organization->status,
                    $organization->managers_count,
                    $organization->drivers_count,
                    $organization->vehicles_count,
                    optional($organization->created_at)->format('Y-m-d H:i:s'),
                ]);
            }
        });
    }

    private function writeTerminalActivityCsv($handle): void
    {
        fputcsv($handle, ['Activity ID', 'Activity', 'Actor', 'Driver', 'Organization', 'Details', 'Recorded At']);

        TerminalActivity::with(['admin', 'driver.organization'])->latest()->chunk(100, function ($activities) use ($handle): void {
            foreach ($activities as $activity) {
                fputcsv($handle, [
                    $activity->id,
                    $activity->activity,
                    $activity->admin?->name,
                    $activity->driver?->name,
                    $activity->driver?->organization?->name,
                    $activity->description,
                    optional($activity->created_at)->format('Y-m-d H:i:s'),
                ]);
            }
        });
    }

    private function writeQueueActivityCsv($handle): void
    {
        fputcsv($handle, ['Queue ID', 'Organization', 'Queue Number', 'Driver', 'Status', 'Queued By', 'Served By', 'Served At', 'Completed At']);

        OrganizationQueueEntry::with(['organization', 'driver', 'manager', 'server'])->latest()->chunk(100, function ($entries) use ($handle): void {
            foreach ($entries as $entry) {
                fputcsv($handle, [
                    $entry->id,
                    $entry->organization?->name,
                    $entry->queue_number,
                    $entry->driver?->name,
                    $entry->status,
                    $entry->manager?->name,
                    $entry->server?->name,
                    optional($entry->served_at)->format('Y-m-d H:i:s'),
                    optional($entry->completed_at)->format('Y-m-d H:i:s'),
                ]);
            }
        });
    }
}
