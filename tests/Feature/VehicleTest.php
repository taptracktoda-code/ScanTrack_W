<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\TodaOrganization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_page_lists_registered_vehicles(): void
    {
        $user = User::factory()->create([
            'role' => 'Administrator',
            'user_id' => 'USER-100',
            'license_number' => 'LIC-100',
            'contact' => '09170000000',
            'address' => 'Main Street',
            'status' => 'Active',
        ]);

        $vehicle = Vehicle::create([
            'name' => 'Van 101',
            'plate_number' => 'ABC-123',
            'status' => 'Active',
        ]);

        $response = $this->actingAs($user)->get(route('vehicles'));

        $response->assertOk();
        $response->assertSee($vehicle->name);
        $response->assertSee($vehicle->plate_number);
    }

    public function test_legacy_direct_driver_creation_route_is_not_exposed(): void
    {
        $user = User::factory()->create([
            'role' => 'Administrator',
            'user_id' => 'USER-200',
            'license_number' => 'LIC-200',
            'contact' => '09170000001',
            'address' => 'Main Street',
            'status' => 'Active',
        ]);

        $this->actingAs($user)->post('/drivers', [
            'name' => 'Rico Santos',
            'license_number' => 'LIC-200-1',
            'contact_number' => '09170000001',
            'vehicle_number' => 'XYZ-789',
            'status' => 'Active',
        ])->assertMethodNotAllowed();
        $this->assertDatabaseMissing('drivers', ['name' => 'Rico Santos']);
    }

    public function test_vehicle_edit_keeps_driver_assignment_and_organization_in_sync(): void
    {
        $admin = User::factory()->create(['role' => 'SUPER_ADMIN']);
        $organization = TodaOrganization::create([
            'name' => 'Central TODA',
            'barangay_travel_area' => 'Central Area',
        ]);
        $driver = Driver::create([
            'name' => 'Rico Santos',
            'license_number' => 'LIC-VEHICLE-1',
            'contact_number' => '09170000001',
            'vehicle_number' => 'XYZ-789',
            'status' => 'Active',
        ]);
        $vehicle = Vehicle::create([
            'name' => 'Tricycle 1',
            'plate_number' => 'XYZ-789',
            'status' => 'Active',
        ]);

        $this->actingAs($admin)->put(route('vehicles.update', $vehicle), [
            'name' => 'Tricycle 1 Updated',
            'plate_number' => 'XYZ-790',
            'vehicle_type' => 'Tricycle',
            'organization_id' => $organization->id,
            'status' => 'Active',
        ])->assertRedirect(route('vehicles'));

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'plate_number' => 'XYZ-790',
            'vehicle_type' => 'Tricycle',
            'organization_id' => $organization->id,
        ]);
        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'vehicle_number' => 'XYZ-790',
            'organization_id' => $organization->id,
        ]);
    }
}
