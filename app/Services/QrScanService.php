<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\DriverLog;
use App\Models\OrganizationQueueEntry;
use App\Models\QrCode;
use App\Models\TerminalActivity;
use App\Models\TodaOrganization;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Services\MobileNotificationService;

class QrScanService
{
    public function __construct(private readonly MobileNotificationService $notifications)
    {
    }

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

        if (isset($data['organization_id']) && (int) $driver->organization_id !== (int) $data['organization_id']) {
            return $this->failure('QR code is not assigned to your organization.');
        }

        if (strcasecmp((string) $driver->status, 'Active') !== 0) {
            return $this->failure('Driver account is inactive.');
        }
        if ($driver->organization && strcasecmp((string) $driver->organization->status, 'Active') !== 0) {
            return $this->failure('The driver organization is inactive.');
        }

        $driverAccount = User::where('user_id', 'DRIVER-'.strtoupper((string) $driver->id))->first();
        if ($driverAccount && strcasecmp((string) $driverAccount->status, 'Active') !== 0) {
            return $this->failure('Driver account is inactive.');
        }

        $result = DB::transaction(function () use ($driver, $data, $qrCode): array {
            $managerScan = isset($data['organization_id']);
            if ($managerScan) {
                TodaOrganization::whereKey($driver->organization_id)->lockForUpdate()->firstOrFail();
                Driver::whereKey($driver->id)->lockForUpdate()->firstOrFail();
                $activeEntry = OrganizationQueueEntry::where('organization_id', $driver->organization_id)
                    ->where('driver_id', $driver->id)->whereIn('status', ['WAITING', 'SERVING'])
                    ->lockForUpdate()->first();
                if (Trip::where('driver_id', $driver->id)->where('status', 'in_progress')->exists()
                    || $activeEntry?->status === 'SERVING') {
                    return ['failure' => 'This driver is on a trip. Complete the trip before scanning again.'];
                }
                if (($data['queue_action'] ?? 'join') === 'join' && $activeEntry) {
                    return ['failure' => 'Duplicate scan: this driver is already in the waiting queue.'];
                }
            }
            $lockedQrCode = QrCode::query()->whereKey($qrCode->id)->lockForUpdate()->first();
            if (! $lockedQrCode || $lockedQrCode->status !== 'Active') {
                return ['inactive' => true];
            }

            $today = now();
            $latestLog = DriverLog::where('driver_id', $driver->id)
                ->whereBetween('created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
                ->latest('created_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($latestLog && $latestLog->created_at?->greaterThanOrEqualTo(now()->subSeconds(10))) {
                return ['duplicate' => true];
            }

            $transactionType = $managerScan
                ? (($data['queue_action'] ?? 'join') === 'exit' ? 'EXIT' : 'ENTRY')
                : ($latestLog?->attendance_type === 'ENTRY' ? 'EXIT' : 'ENTRY');
            if ($managerScan && $transactionType === 'EXIT' && $latestLog?->attendance_type !== 'ENTRY') {
                return ['failure' => 'Record a terminal entry before recording an exit.'];
            }
            if ($managerScan && $transactionType === 'ENTRY') {
                $number = (int) OrganizationQueueEntry::where('organization_id', $driver->organization_id)->max('queue_number') + 1;
                OrganizationQueueEntry::create([
                    'organization_id' => $driver->organization_id,
                    'driver_id' => $driver->id,
                    'queue_number' => $number,
                    'status' => 'WAITING',
                    'queued_by' => $data['admin_id'] ?? null,
                ]);
            } elseif ($managerScan && $activeEntry) {
                $activeEntry->update(['status' => 'CANCELLED', 'completed_at' => now()]);
            }
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
                'description' => 'QR scan accepted'.(filled($data['terminal_id'] ?? null) ? ' at terminal '.$data['terminal_id'] : ''),
            ]);

            return ['log' => $log, 'transaction_type' => $transactionType];
        });

        if (isset($result['failure'])) {
            return $this->failure($result['failure']);
        }

        if ($result['duplicate'] ?? false) {
            return $this->failure('Duplicate scan detected.');
        }

        if ($result['inactive'] ?? false) {
            return $this->failure('QR code is inactive.');
        }

        $transactionType = $result['transaction_type'];
        $timestamp = $result['log']->scanned_at ?? $result['log']->created_at;
        $isEntry = $transactionType === 'ENTRY';
        $activity = $isEntry ? 'entered' : 'exited';
        $notificationData = [
            'screen' => 'attendance',
            'driver_id' => $driver->id,
        ];

        $this->notifications->notifyDriver(
            $driver,
            $isEntry ? 'Terminal entry recorded' : 'Terminal exit recorded',
            'Your QR scan was recorded successfully.',
            'attendance',
            $notificationData,
        );

        if (isset($data['organization_id'])) {
            $this->notifications->notifyOrganizationManagers(
                (int) $data['organization_id'],
                $isEntry ? 'Driver entered terminal' : 'Driver exited terminal',
                "{$driver->name} {$activity} the terminal.",
                'attendance',
                $notificationData,
            );
        }

        return [
            'queue' => isset($data['organization_id']) ? app(DriverQueueService::class)->snapshot($driver) : null,
            'success' => true,
            'message' => ucfirst(strtolower($transactionType)).' recorded successfully.',
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
