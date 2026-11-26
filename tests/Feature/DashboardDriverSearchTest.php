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

    public function test_admin_driver_directory_shows_the_managers_for_each_drivers_toda(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $north = TodaOrganization::create(['name' => 'North TODA', 'barangay_travel_area' => 'North Area']);
        $south = TodaOrganization::create(['name' => 'South TODA', 'barangay_travel_area' => 'South Area']);
        $west = TodaOrganization::create(['name' => 'West TODA', 'barangay_travel_area' => 'West Area']);

        User::factory()->create(['role' => 'Manager', 'organization_id' => $north->id, 'name' => 'Rosa Cruz']);
        User::factory()->create(['role' => User::ROLE_MANAGER, 'organization_id' => $south->id, 'name' => 'Elena Reyes']);
        User::factory()->create(['role' => 'Manager', 'organization_id' => $south->id, 'name' => 'Ana Flores']);
        User::factory()->create(['role' => 'Administrator', 'organization_id' => $west->id, 'name' => 'Pedro Garcia']);

        $driver = Driver::create([
            'name' => 'Maria Santos',
            'license_number' => 'LIC-DIRECTORY-1',
            'vehicle_number' => 'NORTH-101',
            'organization_id' => $north->id,
        ]);
        $driver->qrCode()->create(['qr_token' => 'directory-test-token']);
        User::factory()->create([
            'role' => User::ROLE_DRIVER,
            'user_id' => 'DRIVER-' . $driver->id,
        ]);
        Driver::create(['name' => 'Jose Reyes', 'license_number' => 'LIC-DIRECTORY-2', 'organization_id' => $south->id]);
        Driver::create(['name' => 'Luz Ramos', 'license_number' => 'LIC-DIRECTORY-3', 'organization_id' => $west->id]);
        Driver::create(['name' => 'Eva Lopez', 'license_number' => 'LIC-DIRECTORY-4']);

        $this->actingAs($admin)
            ->get(route('drivers', ['search' => 'NORTH-101']))
            ->assertOk()
            ->assertSeeInOrder(['NORTH-101', 'North TODA', 'Manager: Rosa Cruz'])
            ->assertDontSee('Manager: Ana Flores')
            ->assertSee('Edit Maria Santos')
            ->assertSee('View QR code for Maria Santos')
            ->assertSee('Resend credentials to Maria Santos')
            ->assertSee('Delete Maria Santos');

        $this->get(route('drivers', ['search' => 'South TODA']))
            ->assertOk()
            ->assertSee('Manager: Ana Flores, Elena Reyes')
            ->assertDontSee('Manager: Rosa Cruz');

        $this->get(route('drivers', ['search' => 'West TODA']))
            ->assertOk()
            ->assertSee('Manager: Unassigned')
            ->assertDontSee('Pedro Garcia');

        $this->get(route('drivers', ['search' => 'Eva Lopez']))
            ->assertOk()
            ->assertSeeInOrder(['No TODA assigned', 'Manager: Unassigned']);
    }
}
