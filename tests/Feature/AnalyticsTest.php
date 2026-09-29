<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\DriverLog;
use App\Models\TodaOrganization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_database_driven_activity_and_current_driver_states(): void
    {
        $this->travelTo(Carbon::parse('2026-09-26 12:00:00'));
        $admin = User::factory()->create(['role' => 'administrator']);
        $organization = TodaOrganization::create(['name' => 'Harbor TODA', 'barangay_travel_area' => 'Harbor']);
        $vehicle = Vehicle::create(['name' => 'Unit 1', 'plate_number' => 'ABC-123', 'status' => 'Active']);
        $inside = Driver::create(['name' => 'Ava Santos', 'status' => 'Active', 'organization_id' => $organization->id, 'vehicle_number' => $vehicle->plate_number]);
        $outside = Driver::create(['name' => 'Ben Cruz', 'status' => 'Active', 'organization_id' => $organization->id]);
        $inactive = Driver::create(['name' => 'No Scan Driver', 'status' => 'Inactive', 'organization_id' => $organization->id]);

        DriverLog::create(['driver_id' => $inside->id, 'attendance_type' => 'EXIT', 'scanned_at' => now()->subDay()]);
        DriverLog::create(['driver_id' => $inside->id, 'attendance_type' => 'ENTRY', 'scanned_at' => now()->subHours(2)]);
        DriverLog::create(['driver_id' => $outside->id, 'attendance_type' => 'EXIT', 'scanned_at' => now()->subHour()]);

        $response = $this->actingAs($admin)->get(route('analytics', [
            'start_date' => '2026-09-26',
            'end_date' => '2026-09-26',
            'organization_id' => $organization->id,
        ]));

        $response->assertOk()
            ->assertSee('Ava Santos')
            ->assertSee('Ben Cruz')
            ->assertSee('No Scan Driver')
            ->assertSee('Inside')
            ->assertSee('Outside')
            ->assertSee('No Activity')
            ->assertSee('Harbor TODA')
            ->assertSee('ABC-123')
            ->assertSee('Currently Inside');
        $response->assertViewHas('cards', fn (array $cards) => $cards['drivers'] === 3
            && $cards['active_drivers'] === 2
            && $cards['vehicles'] === 1
            && $cards['scans'] === 2
            && $cards['today_entries'] === 1
            && $cards['today_exits'] === 1
            && $cards['inside'] === 1);
        $response->assertViewHas('outside', 1);
        $response->assertViewHas('entryExit', ['entries' => 1, 'exits' => 1]);
    }

    public function test_analytics_and_export_are_for_administrators_only(): void
    {
        $user = User::factory()->create(['role' => 'User']);

        $this->actingAs($user)->get(route('analytics'))->assertForbidden();
        $this->actingAs($user)->get(route('analytics.export'))->assertForbidden();
    }

    public function test_empty_attendance_data_renders_a_clear_empty_state(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $response = $this->actingAs($admin)->get(route('analytics'));

        $response->assertOk()
            ->assertSee('No attendance data available for the selected period.')
            ->assertViewHas('cards', fn (array $cards) => $cards['scans'] === 0
                && $cards['inside'] === 0
                && $cards['today_entries'] === 0
                && $cards['today_exits'] === 0);
    }

    public function test_export_uses_the_selected_organization_and_date_range(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $organization = TodaOrganization::create(['name' => 'North TODA', 'barangay_travel_area' => 'North']);
        $driver = Driver::create(['name' => 'Real Driver', 'status' => 'Active', 'organization_id' => $organization->id]);
        DriverLog::create(['driver_id' => $driver->id, 'attendance_type' => 'ENTRY', 'terminal_id' => 'GATE-1', 'scanned_at' => '2026-09-26 09:00:00']);

        $response = $this->actingAs($admin)->get(route('analytics.export', [
            'start_date' => '2026-09-26',
            'end_date' => '2026-09-26',
            'organization_id' => $organization->id,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('Real Driver', $response->streamedContent());
        $this->assertStringContainsString('GATE-1', $response->streamedContent());
    }
}