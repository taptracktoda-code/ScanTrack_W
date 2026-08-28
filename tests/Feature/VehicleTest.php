<?php

namespace Tests\Feature;

use App\Models\Driver;
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

    public function test_driver_registration_creates_a_vehicle_record_when_vehicle_number_is_provided(): void
    {
        $user = User::factory()->create([
            'user_id' => 'USER-200',
            'license_number' => 'LIC-200',
            'contact' => '09170000001',
            'address' => 'Main Street',
            'status' => 'Active',
        ]);

        $response = $this->actingAs($user)->post(route('drivers.store'), [
            'name' => 'Rico Santos',
            'license_number' => 'LIC-200-1',
            'contact_number' => '09170000001',
            'vehicle_number' => 'XYZ-789',
            'status' => 'Active',
        ]);

        $response->assertRedirect(route('drivers'));
        $this->assertDatabaseHas('vehicles', [
            'plate_number' => 'XYZ-789',
            'status' => 'Active',
        ]);
        $this->assertDatabaseHas('drivers', [
            'name' => 'Rico Santos',
            'vehicle_number' => 'XYZ-789',
        ]);
    }
}
