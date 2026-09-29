<?php

namespace App\Services;

use App\Models\DriverLog;
use App\Models\QrCode;
use App\Models\TerminalActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class QrScanService
{
    public function handle(array $data): array
    {
        $identifier = trim((string) ($data['qr_identifier'] ?? ''));
        $qrCode = QrCode::with(['driver.organization', 'driver.vehicle'])
            ->where('qr_token', $identifier)
            ->first();

        if (! $qrCode) {
            return $this->failure('QR code not found.');
        }

        if ($qrCode->status !== 'Active') {
            return $this->failure('QR code is inactive.');
        }

        $driver = $qrCode->driver;
        if (! $driver) {
            return $this->failure('The QR code is not assigned to a driver.');
        }

        if (strcasecmp((string) $driver->status, 'Active') !== 0) {
            return $this->failure('Driver account is inactive.');
        }

        $driverAccount = User::where('user_id', 'DRIVER-' . strtoupper((string) $driver->id))->first();
        if ($driverAccount && strcasecmp((string) $driverAccount->status, 'Active') !== 0) {
            return $this->failure('Driver account is inactive.');
        }

        $result = DB::transaction(function () use ($driver, $data, $qrCode): array {
            $lockedQrCode = QrCode::query()->whereKey($qrCode->id)->lockForUpdate()->first();
            if (! $lockedQrCode || $lockedQrCode->status !== 'Active') {
                return ['inactive' => true];
            }

            $latestLog = DriverLog::where('driver_id', $driver->id)
                ->latest('created_at')
                ->lockForUpdate()
                ->first();

            if ($latestLog && $latestLog->created_at?->greaterThanOrEqualTo(now()->subSeconds(10))) {
                return ['duplicate' => true];
            }

            $transactionType = $latestLog?->attendance_type === 'ENTRY' ? 'EXIT' : 'ENTRY';
            $timestamp = now();
            $log = DriverLog::create([
                'driver_id' => $driver->id,
                'attendance_type' => $transactionType,
                'terminal_id' => $data['terminal_id'] ?? null,
                'scanned_at' => $timestamp,
                'scan_method' => 'QR',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            TerminalActivity::create([
                'admin_id' => $data['admin_id'] ?? null,
                'driver_id' => $driver->id,
                'activity' => $transactionType === 'ENTRY' ? 'Driver entered terminal' : 'Driver exited terminal',
                'description' => 'QR scan accepted' . (filled($data['terminal_id'] ?? null) ? ' at terminal ' . $data['terminal_id'] : ''),
            ]);

            return ['log' => $log, 'transaction_type' => $transactionType];
        });

        if ($result['duplicate'] ?? false) {
            return $this->failure('Duplicate scan detected.');
        }

        if ($result['inactive'] ?? false) {
            return $this->failure('QR code is inactive.');
        }

        $transactionType = $result['transaction_type'];
        $timestamp = $result['log']->scanned_at ?? $result['log']->created_at;

        return [
            'success' => true,
            'message' => ucfirst(strtolower($transactionType)) . ' recorded successfully.',
            'scan_type' => $transactionType,
            'scanned_at' => $timestamp?->toIso8601String(),
            'transaction_type' => $transactionType,
            'driver' => ['id' => $driver->id, 'name' => $driver->name],
            'vehicle' => ['plate_number' => $driver->vehicle?->plate_number ?? $driver->vehicle_number],
            'organization' => ['name' => $driver->organization?->name],
            'timestamp' => $timestamp?->format('Y-m-d H:i:s'),
        ];
    }

    private function failure(string $message): array
    {
        return ['success' => false, 'message' => $message];
    }
}