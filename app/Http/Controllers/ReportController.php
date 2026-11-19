<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\DriverLog;
use App\Models\TerminalActivity;
use App\Models\TodaOrganization;
use App\Services\OperationsReport;
use App\Services\OperationsReportPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        $drivers = Driver::with('organization')->latest()->get();
        $driverLogCount = DriverLog::count();
        $monthlyPerformance = $this->monthlyDriverPerformance($drivers);

        return view('reports.index', [
            'drivers' => $drivers,
            'monthlyPerformance' => $monthlyPerformance,
            'reportCards' => $this->reportCards($drivers, $driverLogCount),
            'reportTypes' => OperationsReport::TYPES,
        ]);
    }

    public function export(Request $request, OperationsReport $reports, OperationsReportPdf $pdf): Response
    {
        $filters = $reports->filters($request);
        $type = $request->validate(['type' => ['sometimes', Rule::in(array_keys(OperationsReport::TYPES))]])['type'] ?? 'combined';
        $bytes = $pdf->render($type, $filters, $reports->snapshot($filters, includeAssignments: $type === 'combined'));
        $filename = $type.'-'.now(OperationsReport::ZONE)->toDateString().'.pdf';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
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

    private function reportCards(Collection $drivers, int $driverLogCount): array
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
                'trend' => $driverLogCount . ' driver log records',
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
}
