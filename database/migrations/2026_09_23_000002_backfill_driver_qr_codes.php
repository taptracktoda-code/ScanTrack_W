<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('drivers')
            ->leftJoin('qr_codes', 'drivers.id', '=', 'qr_codes.driver_id')
            ->whereNull('qr_codes.id')
            ->select('drivers.id as driver_id')
            ->get()
            ->each(function (object $driver): void {
                do {
                    $token = 'TT-DRV-' . strtoupper(bin2hex(random_bytes(6)));
                } while (DB::table('qr_codes')->where('qr_token', $token)->exists());

                DB::table('qr_codes')->insert([
                    'driver_id' => $driver->driver_id,
                    'qr_token' => $token,
                    'status' => 'Active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Existing driver QR records are retained when rolling back this data migration.
    }
};