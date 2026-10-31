<?php

namespace Tests\Feature;

use App\Models\Driver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyMigrationSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_administrator_named_driver_is_preserved(): void
    {
        $driver = Driver::create([
            'name' => 'Administrator',
            'license_number' => 'LIC-LEGACY-ADMIN',
            'status' => 'Active',
        ]);

        $migration = require database_path('migrations/2026_06_29_000002_delete_admin_driver_records.php');
        $migration->up();

        $this->assertDatabaseHas('drivers', ['id' => $driver->id, 'name' => 'Administrator']);
    }

    public function test_retiring_nfc_cards_preserves_legacy_cards_while_backfilling_qr_codes(): void
    {
        $driver = Driver::create([
            'name' => 'Legacy NFC Driver',
            'license_number' => 'LIC-LEGACY-NFC',
            'status' => 'Active',
        ]);
        DB::table('nfc_cards')->insert([
            'uid' => 'NFC-LEGACY-001',
            'driver_id' => $driver->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_09_26_000001_retire_nfc_cards_and_mark_qr_scans.php');
        $migration->up();

        $this->assertTrue(Schema::hasTable('nfc_cards'));
        $this->assertDatabaseHas('nfc_cards', ['uid' => 'NFC-LEGACY-001', 'driver_id' => $driver->id]);
        $this->assertDatabaseHas('qr_codes', ['driver_id' => $driver->id]);
    }
}
