<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\DriverLog;
use App\Models\QrCode;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role' => 'SUPER_ADMIN', 'status' => 'Active']));
    }

    public function test_empty_system_is_healthy_without_inventing_a_scan(): void
    {
        $this->getJson(route('system-health.api'))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('overall_status', 'Healthy')
            ->assertJsonPath('database', 'Connected')
            ->assertJsonPath('storage', 'Writable')
            ->assertJsonPath('today_attendance', 0)
            ->assertJsonPath('total_logs', 0)
            ->assertJsonPath('last_scan', null)
            ->assertJsonPath('reasons', [])
            ->assertJsonStructure(['checked_at', 'response_time_ms']);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_metrics_refresh_and_last_qr_scan_uses_actual_driver_and_scan_time(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(12));
        $driver = Driver::create(['name' => 'Maria Santos', 'license_number' => 'HEALTH-1', 'status' => 'Active']);
        Vehicle::create(['name' => 'Maria Santos', 'plate_number' => 'HEALTH-1', 'vehicle_type' => 'Tricycle', 'status' => 'Active']);
        QrCode::create(['driver_id' => $driver->id, 'qr_token' => 'HEALTH-QR', 'status' => 'Active', 'generated_at' => now()]);
        for ($i = 0; $i < 12; $i++) {
            DriverLog::create(['driver_id' => $driver->id, 'scan_method' => 'QR', 'attendance_type' => 'ENTRY', 'scanned_at' => now()->subDay()]);
        }
        $this->getJson(route('system-health.api'))->assertOk()
            ->assertJsonPath('overall_status', 'Healthy')
            ->assertJsonPath('total_logs', 12)
            ->assertJsonPath('today_attendance', 0);

        $scanTime = now()->subMinute();
        DriverLog::create(['driver_id' => $driver->id, 'scan_method' => 'QR', 'attendance_type' => 'EXIT', 'scanned_at' => $scanTime]);
        DriverLog::create(['driver_id' => $driver->id, 'scan_method' => 'MANUAL', 'attendance_type' => 'ENTRY', 'scanned_at' => now()]);
        $this->getJson(route('system-health.api'))->assertOk()
            ->assertJsonPath('drivers', 1)->assertJsonPath('vehicles', 1)->assertJsonPath('qr_codes', 1)
            ->assertJsonPath('total_logs', 14)->assertJsonPath('today_attendance', 2)
            ->assertJsonPath('last_scan.driver', 'Maria Santos')
            ->assertJsonPath('last_scan.type', 'EXIT')
            ->assertJsonPath('last_scan.time', $scanTime->toIso8601String());
    }

    public function test_database_failure_still_returns_storage_health_and_unknown_metrics(): void
    {
        DB::shouldReceive('select')->once()->with('SELECT 1')->andThrow(new \RuntimeException('Private database details'));
        $this->getJson(route('system-health.api'))->assertOk()
            ->assertJsonPath('overall_status', 'Critical')
            ->assertJsonPath('database', 'Unavailable')
            ->assertJsonPath('storage', 'Writable')
            ->assertJsonPath('drivers', null)->assertJsonPath('total_logs', null)
            ->assertJsonPath('today_attendance', null)->assertJsonPath('last_scan', null)
            ->assertDontSee('Private database details');
    }

    public function test_a_failed_metrics_query_is_contained(): void
    {
        // The connection succeeds, but a later application query fails.
        Schema::drop('vehicles');
        $this->getJson(route('system-health.api'))->assertOk()
            ->assertJsonPath('overall_status', 'Critical')
            ->assertJsonPath('database', 'Unavailable')
            ->assertJsonPath('vehicles', null);
    }

    public function test_silent_storage_write_failure_is_detected_and_cleaned_up(): void
    {
        $disk = Mockery::mock();
        Storage::shouldReceive('disk')->with('local')->once()->andReturn($disk);
        $disk->shouldReceive('put')->once()->andReturn(false);
        $disk->shouldReceive('delete')->once()->andReturn(true);
        $this->getJson(route('system-health.api'))->assertOk()
            ->assertJsonPath('overall_status', 'Critical')->assertJsonPath('storage', 'Unavailable')
            ->assertJsonPath('database', 'Connected')->assertJsonPath('drivers', 0);
    }

    public function test_storage_read_and_cleanup_failures_are_detected(): void
    {
        $disk = Mockery::mock();
        Storage::shouldReceive('disk')->with('local')->once()->andReturn($disk);
        $disk->shouldReceive('put')->once()->andReturn(true);
        $disk->shouldReceive('get')->once()->andReturn('corrupted');
        $disk->shouldReceive('delete')->once()->andReturn(false);
        $this->getJson(route('system-health.api'))->assertOk()
            ->assertJsonPath('overall_status', 'Critical')->assertJsonPath('storage', 'Unavailable')
            ->assertJsonCount(2, 'errors');
    }

    public function test_page_includes_live_monitor_and_both_routes_require_authentication(): void
    {
        $this->get(route('system-health'))->assertOk()->assertSee('js/system-health.js')
            ->assertSee('system-health-freshness')->assertDontSee('Pending Logs Count');
        auth()->logout();
        $this->getJson(route('system-health.api'))->assertUnauthorized();
        $this->get(route('system-health'))->assertRedirect(route('login'));
    }

    public function test_driver_cannot_access_admin_health_data(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'DRIVER', 'status' => 'Active']))
            ->getJson(route('system-health.api'))->assertForbidden();
    }
}
