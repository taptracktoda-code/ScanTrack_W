<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\TodaOrganization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardDriverSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_drivers_by_name_or_driver_id(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $organization = TodaOrganization::create([
            'name' => 'North TODA',
            'barangay_travel_area' => 'North Area',
        ]);
        $driver = Driver::create([
            'name' => 'Maria Santos',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'license_number' => 'LIC-SEARCH-1',
            'vehicle_number' => 'ABC-1234',
            'organization_id' => $organization->id,
            'status' => 'Active',
        ]);
        $otherDriver = Driver::create([
            'name' => 'Jose Reyes',
            'license_number' => 'LIC-SEARCH-2',
            'vehicle_number' => 'XYZ-4321',
            'status' => 'Active',
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('dashboard-driver-search', false)
            ->assertSee('dashboard-driver-search-results', false);

        $this->actingAs($admin)
            ->getJson(route('drivers.search', ['q' => 'Santos']))
            ->assertOk()
            ->assertJsonPath('drivers.0.id', $driver->id)
            ->assertJsonPath('drivers.0.driver_id', 'DRIVER-' . $driver->id)
            ->assertJsonPath('drivers.0.vehicle_number', 'ABC-1234')
            ->assertJsonPath('drivers.0.organization', 'North TODA')
            ->assertJsonPath('drivers.0.url', route('drivers', ['search' => 'DRIVER-' . $driver->id]));

        $this->actingAs($admin)
            ->getJson(route('drivers.search', ['q' => 'DRIVER-' . $driver->id]))
            ->assertOk()
            ->assertJsonPath('drivers.0.id', $driver->id);

        $this->actingAs($admin)
            ->get(route('drivers', ['search' => 'DRIVER-' . $driver->id]))
            ->assertOk()
            ->assertSee('Maria Santos')
            ->assertDontSee('Jose Reyes')
            ->assertSee('Clear filter');

        $this->assertNotSame($driver->id, $otherDriver->id);
    }

    public function test_driver_search_is_restricted_to_administrators(): void
    {
        $driver = User::factory()->create(['role' => 'Driver']);

        $this->actingAs($driver)
            ->getJson(route('drivers.search', ['q' => 'Maria']))
            ->assertForbidden();
    }
}