<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\DriverLog;
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
        $type = in_array($type, ['driver-summary', 'driver-activity'], true) ? $type : 'driver-summary';
        $fileName = $type . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($type) {
            $handle = fopen('php://output', 'w');

            if ($type === 'driver-activity') {
                $this->writeDriverActivityCsv($handle);
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
                'title' => 'Driver Activity Report - ' . now()->format('F j, Y'),
                'type' => 'CSV',
                'export_type' => 'driver-activity',
                'count' => $driverLogs->count(),
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
        fputcsv($handle, ['Log ID', 'Driver', 'Vehicle No.', 'Organization', 'Transaction', 'Terminal', 'Scanned At']);

        DriverLog::latest()->chunk(100, function ($logs) use ($handle) {
            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->driver?->name ?: 'Unknown driver',
                    $log->driver?->vehicle_number ?: 'Unassigned',
                    $log->driver?->organization?->name ?: 'Not assigned',
                    $log->attendance_type ?: 'UNKNOWN',
                    $log->terminal_id ?: 'N/A',
                    optional($log->scanned_at ?: $log->created_at)->format('Y-m-d H:i:s'),
                ]);
            }
        });
    }
}
