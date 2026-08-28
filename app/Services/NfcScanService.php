<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\DriverLog;
use App\Models\NfcCard;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NfcScanService
{
    /**
     * Process an NFC scan and create the corresponding attendance log.
     */
    public function handle(array $data): array
    {
        $uid = trim((string) ($data['uid'] ?? ''));
        $terminalId = $data['terminal_id'] ?? null;

        if (! Schema::hasTable('nfc_cards')) {
            return [
                'success' => false,
                'message' => 'NFC card is not registered.',
            ];
        }

        try {
            $card = Schema::hasColumn('nfc_cards', 'uid')
                ? NfcCard::where('uid', $uid)->first()
                : null;
        } catch (QueryException $exception) {
            return [
                'success' => false,
                'message' => 'NFC card is not registered.',
            ];
        }

        if (! $card) {
            return [
                'success' => false,
                'message' => 'NFC card is not registered.',
            ];
        }

        $driver = Driver::find($card->driver_id ?? null);

        if (! $driver) {
            return [
                'success' => false,
                'message' => 'Associated driver was not found.',
            ];
        }

        try {
            $latestLog = null;
            $attendanceType = 'ENTRY';
            $recentDuplicate = null;

            if (Schema::hasTable('driver_logs') && Schema::hasColumn('driver_logs', 'driver_id') && Schema::hasColumn('driver_logs', 'attendance_type')) {
                $latestLog = DriverLog::query()
                    ->whereDate('created_at', now()->toDateString())
                    ->where('driver_id', $driver->id)
                    ->latest('created_at')
                    ->first();

                $attendanceType = $latestLog && $latestLog->attendance_type === 'ENTRY' ? 'EXIT' : 'ENTRY';
            }

            if (Schema::hasTable('driver_logs') && Schema::hasColumn('driver_logs', 'driver_id')) {
                $duplicateWindow = now()->subSeconds(10);
                $recentDuplicate = DriverLog::query()
                    ->where('driver_id', $driver->id)
                    ->where('created_at', '>=', $duplicateWindow)
                    ->latest('created_at')
                    ->first();
            }
        } catch (QueryException $exception) {
            $attendanceType = 'ENTRY';
            $recentDuplicate = null;
        }

        if ($recentDuplicate) {
            return [
                'success' => false,
                'message' => 'Duplicate scan within 10 seconds.',
            ];
        }

        try {
            $log = DB::transaction(function () use ($driver, $attendanceType, $terminalId): array {
                $payload = [
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (Schema::hasTable('driver_logs') && Schema::hasColumn('driver_logs', 'driver_id')) {
                    $payload['driver_id'] = $driver->id;
                }

                if (Schema::hasTable('driver_logs') && Schema::hasColumn('driver_logs', 'attendance_type')) {
                    $payload['attendance_type'] = $attendanceType;
                }

                if (Schema::hasTable('driver_logs') && Schema::hasColumn('driver_logs', 'terminal_id')) {
                    $payload['terminal_id'] = $terminalId;
                }

                if (Schema::hasTable('driver_logs') && Schema::hasColumn('driver_logs', 'scanned_at')) {
                    $payload['scanned_at'] = now();
                }

                DB::table('driver_logs')->insert($payload);

                return $payload;
            });
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'message' => 'Database error.',
            ];
        }

        return [
            'success' => true,
            'driver_id' => $driver->id,
            'driver_name' => $driver->name ?? 'Unknown Driver',
            'organization' => $this->organizationName($driver),
            'attendance_type' => $attendanceType,
            'scanned_at' => isset($log['scanned_at']) && $log['scanned_at'] instanceof \DateTimeInterface
                ? $log['scanned_at']->format('Y-m-d H:i:s')
                : now()->format('Y-m-d H:i:s'),
            'message' => 'Attendance recorded successfully.',
        ];
    }

    protected function organizationName(Driver $driver): string
    {
        if (! isset($driver->organization_id) || ! $driver->organization_id) {
            return 'N/A';
        }

        return \App\Models\TodaOrganization::find($driver->organization_id)?->name ?? 'N/A';
    }
}
