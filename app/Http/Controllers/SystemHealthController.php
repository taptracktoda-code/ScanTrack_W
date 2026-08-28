<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\DriverLog;
use App\Models\NfcCard;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SystemHealthController extends Controller
{
    public function index(): View
    {
        return view('system-health.index');
    }

    public function api(): JsonResponse
    {
        $health = $this->buildHealthPayload();

        return response()->json($health);
    }

    private function buildHealthPayload(): array
    {
        $errors = [];

        $server = 'Running';
        $database = 'Connected';
        $api = 'Online';
        $storage = 'Writable';

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $exception) {
            $database = 'Disconnected';
            $errors[] = 'The database connection is currently unavailable.';
        }

        try {
            Storage::disk('local')->put('system-health-test.tmp', 'ok');
            Storage::disk('local')->delete('system-health-test.tmp');
        } catch (\Throwable $exception) {
            $storage = 'Not Writable';
            $errors[] = 'Storage is not writable at the moment.';
        }

        $drivers = Driver::count();
        $vehicles = Vehicle::count();
        $nfcCards = NfcCard::count();
        $todayAttendance = DriverLog::whereDate('created_at', now()->toDateString())->count();
        $pendingLogs = DriverLog::count();

        // Configurable behavior
        $pendingThreshold = (int) env('SYSTEM_HEALTH_PENDING_THRESHOLD', 10);
        $considerPending = filter_var(env('SYSTEM_HEALTH_CONSIDER_PENDING', 'true'), FILTER_VALIDATE_BOOLEAN);

        $reasons = [];

        $lastScan = DriverLog::query()
            ->latest('created_at')
            ->first();

        $lastScanData = [
            'driver' => $lastScan?->driver_name ?? 'Juan Dela Cruz',
            'type' => $lastScan?->attendance_type ?? 'ENTRY',
            'time' => $lastScan?->created_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
        ];

        // Collect reasons
        if ($database !== 'Connected') {
            $reasons[] = 'Database connection lost';
        }

        if ($storage !== 'Writable') {
            $reasons[] = 'Storage not writable';
        }

        if ($considerPending) {
            if ($pendingLogs > $pendingThreshold) {
                $reasons[] = "High pending logs ({$pendingLogs})";
            }
        } else {
            // pending logs are intentionally ignored by config
        }

        if ($todayAttendance === 0) {
            $reasons[] = 'No attendance recorded today';
        }

        // Determine overall status
        $overallStatus = 'Healthy';
        $hasCritical = ($server !== 'Running' || $database !== 'Connected' || $api !== 'Online' || $storage !== 'Writable');

        if ($hasCritical) {
            $overallStatus = 'Critical';
        } elseif (count($reasons) > 0) {
            $overallStatus = 'Warning';
        }

        return [
            'server' => $server,
            'database' => $database,
            'api' => $api,
            'storage' => $storage,
            'drivers' => $drivers,
            'vehicles' => $vehicles,
            'nfc_cards' => $nfcCards,
            'today_attendance' => $todayAttendance,
            'pending_logs' => $pendingLogs,
            'last_scan' => $lastScanData,
            'overall_status' => $overallStatus,
            'reasons' => $reasons,
            'config' => [
                'pending_threshold' => $pendingThreshold,
                'consider_pending' => $considerPending,
            ],
            'errors' => $errors,
        ];
    }
}
