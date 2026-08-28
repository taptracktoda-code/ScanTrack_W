<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\DriverLog;
use App\Models\Setting;
use App\Models\TodaOrganization;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $organizations = TodaOrganization::orderBy('created_at', 'desc')->get();
        $shiftStatus = auth()->user()->shift_status ?? 'Available';

        return view('dashboard.index', [
            'stats' => $this->stats($organizations),
            'organizations' => $organizations,
            'shiftStatus' => $shiftStatus,
        ]);
    }

    public function updateShiftStatus(Request $request): JsonResponse
    {
        $request->validate([
            'shift_status' => 'required|string|in:Available,On Break,Off Duty',
        ]);

        $user = auth()->user();
        $user->update(['shift_status' => $request->input('shift_status')]);

        return response()->json([
            'message' => 'Shift status updated.',
            'shift_status' => $user->shift_status,
        ]);
    }

    public function statsJson(): JsonResponse
    {
        return response()->json([
            'stats' => $this->stats(),
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
                'label' => 'Drivers',
                'value' => Driver::count(),
                'icon' => 'bi-person-badge',
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
}
