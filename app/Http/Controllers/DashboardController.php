<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\DriverLog;
use App\Models\User;
use App\Models\Setting;
use App\Models\TerminalActivity;
use App\Models\TodaOrganization;
use App\Models\Vehicle;
use App\Models\QrCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $organizations = TodaOrganization::orderBy('created_at', 'desc')->get();
        return view('dashboard.index', [
            'stats' => $this->stats($organizations),
            'organizations' => $organizations,
            'recentScans' => DriverLog::with(['driver.organization'])->latest('created_at')->limit(8)->get(),
            'recentActivities' => TerminalActivity::with(['admin', 'driver'])->latest('id')->limit(8)->get(),
        ]);
    }

    public function statsJson(): JsonResponse
    {
        return response()->json([
            'stats' => $this->stats(),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function notificationFeed(Request $request): JsonResponse
    {
        $notifications = TerminalActivity::with('driver')
            ->when(
                $request->user()->isManager(),
                fn ($query) => $query->whereHas('driver', fn ($driverQuery) => $driverQuery->where('organization_id', $request->user()->organization_id))
            )
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (TerminalActivity $activity): array => [
                'id' => $activity->id,
                'title' => $activity->activity,
                'description' => collect([$activity->driver?->name, $activity->description])
                    ->filter()
                    ->implode(' · '),
                'created_at' => $activity->created_at?->diffForHumans(),
            ])
            ->values();

        return response()->json(['notifications' => $notifications]);
    }

    public function apiDashboard(): JsonResponse
    {
        $today = now()->startOfDay();
        $recentActivity = DriverLog::with(['driver.organization'])
            ->latest('created_at')
            ->take(10)
            ->get()
            ->map(fn (DriverLog $log): array => [
                'id' => $log->id,
                'driver_id' => $log->driver_id,
                'driver_name' => $log->driver?->name,
                'organization' => $log->driver?->organization?->name,
                'attendance_type' => $log->attendance_type,
                'scan_method' => $log->scan_method,
                'terminal_id' => $log->terminal_id,
                'scanned_at' => optional($log->scanned_at ?? $log->created_at)->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'total_registered_drivers' => Driver::count(),
                'active_drivers' => Driver::where('status', 'Active')->count(),
                'scanned_entries' => DriverLog::where('created_at', '>=', $today)
                    ->where('attendance_type', 'ENTRY')
                    ->count(),
                'scanned_exits' => DriverLog::where('created_at', '>=', $today)
                    ->where('attendance_type', 'EXIT')
                    ->count(),
                'drivers_currently_inside' => $this->driversInside(),
                'active_qr_codes' => QrCode::where('status', 'Active')->count(),
                'inactive_qr_codes' => QrCode::where('status', 'Inactive')->count(),
                'recent_activity' => $recentActivity,
            ],
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    private function stats(?iterable $organizations = null): array
    {
        $today = now()->startOfDay();
        $organizationsCount = $organizations ? count($organizations) : TodaOrganization::count();

        return [
            'organizations' => [
                'label' => 'TODA Organizations',
                'value' => (int) $organizationsCount,
                'icon' => 'bi-buildings',
            ],
            'vehicles' => [
                'label' => 'Vehicles',
                'value' => Vehicle::count(),
                'icon' => 'bi-truck-front',
            ],
            'drivers' => [
                'label' => 'Registered Drivers',
                'value' => Driver::count(),
                'icon' => 'bi-person-badge',
            ],
            'active_drivers' => [
                'label' => 'Active Drivers',
                'value' => Driver::whereRaw('LOWER(status) = ?', ['active'])->count(),
                'icon' => 'bi-person-check',
            ],
            'inactive_drivers' => [
                'label' => 'Inactive Drivers',
                'value' => Driver::whereIn('status', ['Inactive', 'Suspended', 'inactive', 'suspended'])->count(),
                'icon' => 'bi-person-slash',
            ],
            'managers' => [
                'label' => 'Managers',
                'value' => User::where('role', User::ROLE_MANAGER)->count(),
                'icon' => 'bi-people',
            ],
            'active_qr_codes' => [
                'label' => 'Active QR Codes',
                'value' => QrCode::where('status', 'Active')->count(),
                'icon' => 'bi-qr-code',
            ],
            'inactive_qr_codes' => [
                'label' => 'Inactive QR Codes',
                'value' => QrCode::where('status', 'Inactive')->count(),
                'icon' => 'bi-qr-code',
            ],
            'today_entries' => [
                'label' => "Today's Entries",
                'value' => DriverLog::whereDate('created_at', today())->where('attendance_type', 'ENTRY')->count(),
                'icon' => 'bi-box-arrow-in-right',
            ],
            'today_exits' => [
                'label' => "Today's Exits",
                'value' => DriverLog::whereDate('created_at', today())->where('attendance_type', 'EXIT')->count(),
                'icon' => 'bi-box-arrow-right',
            ],
            'drivers_inside' => [
                'label' => 'Drivers Currently Inside',
                'value' => $this->driversInside(),
                'icon' => 'bi-people',
            ],
            'driver_logs_today' => [
                'label' => "Today's Driver Log",
                'value' => DriverLog::where('created_at', '>=', $today)->count(),
                'icon' => 'bi-journal-check',
            ],
            'pending_logs' => [
                'label' => 'Pending Logs',
                'value' => DriverLog::count(),
                'icon' => 'bi-hourglass-split',
            ],
        ];
    }

    private function driversInside(): int
    {
        return Driver::with('logs')
            ->get()
            ->filter(fn (Driver $driver): bool => $driver->logs->sortByDesc('created_at')->first()?->attendance_type === 'ENTRY')
            ->count();
    }
}
