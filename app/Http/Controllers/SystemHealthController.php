<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\DriverLog;
use App\Models\QrCode;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SystemHealthController extends Controller
{
    public function index(): View
    {
        return view('system-health.index');
    }

    public function api(): JsonResponse
    {
        $started = microtime(true);
        $errors = [];
        $database = 'Connected';
        $storage = 'Writable';
        $metrics = [
            'drivers' => null,
            'vehicles' => null,
            'qr_codes' => null,
            'today_attendance' => null,
            'total_logs' => null,
            'last_scan' => null,
        ];

        try {
            // Execute a query, rather than just obtaining a potentially stale PDO connection.
            DB::select('SELECT 1');
            $today = now()->startOfDay();
            $lastScan = DriverLog::with('driver')->where('scan_method', 'QR')
                ->orderByRaw('COALESCE(scanned_at, created_at) DESC')->orderByDesc('id')->first();

            // Only publish metrics if every query succeeds; unavailable counts are never zero.
            $metrics = [
                'drivers' => Driver::count(),
                'vehicles' => Vehicle::count(),
                'qr_codes' => QrCode::count(),
                'today_attendance' => DriverLog::whereRaw('COALESCE(scanned_at, created_at) >= ?', [$today])
                    ->whereRaw('COALESCE(scanned_at, created_at) < ?', [$today->copy()->addDay()])->count(),
                'total_logs' => DriverLog::count(),
                'last_scan' => $lastScan ? [
                    'driver' => $lastScan->driver?->name ?? 'Unknown driver',
                    'type' => $lastScan->attendance_type,
                    'time' => ($lastScan->scanned_at ?? $lastScan->created_at)?->toIso8601String(),
                ] : null,
            ];
        } catch (\Throwable $exception) {
            $database = 'Unavailable';
            $errors[] = 'Database checks failed. Attendance and registration data are unavailable.';
            report($exception);
        }

        $probePath = 'health-checks/'.Str::uuid().'.tmp';
        $disk = null;
        try {
            $disk = Storage::disk('local');
            // The local disk normally returns false on failure instead of throwing.
            if (! $disk->put($probePath, 'health-check') || $disk->get($probePath) !== 'health-check') {
                throw new \RuntimeException('Storage write/read probe failed.');
            }
        } catch (\Throwable $exception) {
            $storage = 'Unavailable';
            $errors[] = 'Local storage write/read check failed.';
            report($exception);
        } finally {
            try {
                if ($disk && ! $disk->delete($probePath)) {
                    throw new \RuntimeException('Storage cleanup probe failed.');
                }
            } catch (\Throwable $exception) {
                $storage = 'Unavailable';
                $errors[] = 'Local storage cleanup check failed.';
                report($exception);
            }
        }

        return response()->json([
            // A successful response demonstrates this app and endpoint are responding only.
            'server' => 'Responding',
            'api' => 'Responding',
            'database' => $database,
            'storage' => $storage,
            ...$metrics,
            'overall_status' => $errors === [] ? 'Healthy' : 'Critical',
            'reasons' => $errors,
            'errors' => $errors,
            'checked_at' => now()->toIso8601String(),
            'response_time_ms' => (int) round((microtime(true) - $started) * 1000),
        ])->header('Cache-Control', 'no-store, private');
    }
}
