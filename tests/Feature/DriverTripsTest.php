<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\DriverLog;
use App\Models\OrganizationDestination;
use App\Models\TodaOrganization;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverTripsTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_can_start_complete_and_view_trips_and_summary(): void
    {
        [$driver, $account, $token, $destination] = $this->driverFixture();
        $this->recordQrEntry($driver);

        $start = $this->withToken($token)->postJson('/api/driver/trips', [
            'destination_id' => $destination->id,
            'passenger_count' => 3,
        ]);

        $start->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.destination', 'Downtown')
            ->assertJsonPath('data.passenger_count', 3)
            ->assertJsonPath('data.status', 'in_progress');

        $tripId = $start->json('data.trip_id');
        $this->assertDatabaseHas('trips', [
            'trip_id' => $tripId,
            'driver_id' => $driver->id,
            'status' => 'in_progress',
        ]);

        $this->withToken($token)->getJson('/api/driver/trips/active')
            ->assertOk()
            ->assertJsonPath('data.trip_id', $tripId);
        $this->withToken($token)->getJson('/api/driver/trips/today')
            ->assertOk()
            ->assertJsonPath('data.trips.0.trip_id', $tripId);
        $this->withToken($token)->getJson('/api/driver/trips/summary')
            ->assertOk()
            ->assertJsonPath('data.total_trips', 1)
            ->assertJsonPath('data.in_progress_trips', 1);

        $this->withToken($token)->postJson("/api/driver/trips/{$tripId}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonStructure(['data' => ['arrival_time']]);

        $this->assertNotNull(Trip::where('trip_id', $tripId)->firstOrFail()->arrival_time);
        $this->withToken($token)->getJson('/api/driver/trips/active')
            ->assertOk()
            ->assertJsonPath('data', null);
        $this->withToken($token)->getJson('/api/driver/trips/summary')
            ->assertOk()
            ->assertJsonPath('data.total_trips', 1)
            ->assertJsonPath('data.completed_trips', 1)
            ->assertJsonPath('data.in_progress_trips', 0);
    }

    public function test_driver_cannot_start_a_second_trip_or_complete_another_drivers_trip(): void
    {
        [$firstDriver, , $firstToken, $destination] = $this->driverFixture();
        [$secondDriver, , $secondToken] = $this->driverFixture('Other Driver');
        $this->recordQrEntry($firstDriver);

        $tripId = $this->withToken($firstToken)->postJson('/api/driver/trips', [
            'destination_id' => $destination->id,
            'passenger_count' => 1,
        ])->assertCreated()->json('data.trip_id');

        $this->withToken($firstToken)->postJson('/api/driver/trips', [
            'destination_id' => $destination->id,
            'passenger_count' => 2,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('trip');

        auth()->forgetGuards();
        $this->withToken($secondToken)->getJson('/api/driver/profile')
            ->assertOk()
            ->assertJsonPath('data.id', (string) $secondDriver->id);
        $this->withToken($secondToken)->postJson("/api/driver/trips/{$tripId}/complete")
            ->assertNotFound();

        $this->assertDatabaseHas('trips', ['trip_id' => $tripId, 'status' => 'in_progress']);
    }

    public function test_start_trip_requires_a_destination_and_positive_passenger_count(): void
    {
        [$driver, , $token] = $this->driverFixture();
        $this->recordQrEntry($driver);

        $this->withToken($token)->postJson('/api/driver/trips', [
            'passenger_count' => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['destination_id', 'passenger_count']);
    }

    public function test_driver_cannot_start_a_trip_without_a_manager_qr_entry_scan(): void
    {
        [$driver, , $token, $destination] = $this->driverFixture();

        $this->withToken($token)->postJson('/api/driver/trips', [
            'destination_id' => $destination->id,
            'passenger_count' => 2,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('qr_scan');

        $this->assertDatabaseCount('trips', 0);

        DriverLog::create([
            'driver_id' => $driver->id,
            'attendance_type' => 'EXIT',
            'scan_method' => 'QR',
            'scanned_at' => now(),
        ]);

        $this->withToken($token)->postJson('/api/driver/trips', [
            'destination_id' => $destination->id,
            'passenger_count' => 2,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('qr_scan');

        $this->assertDatabaseCount('trips', 0);
    }

    public function test_driver_can_only_use_active_destinations_from_their_own_organization(): void
    {
        [$driver, , $token] = $this->driverFixture();
        [, , , $otherDestination] = $this->driverFixture('Other Driver');
        $this->recordQrEntry($driver);

        $this->withToken($token)->getJson('/api/driver/trip-destinations')
            ->assertOk()
            ->assertJsonCount(1, 'data.destinations')
            ->assertJsonPath('data.destinations.0.name', 'Downtown');

        $this->withToken($token)->postJson('/api/driver/trips', [
            'destination_id' => $otherDestination->id,
            'passenger_count' => 1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('destination_id');

        $this->assertDatabaseCount('trips', 0);
    }

    private function driverFixture(string $name = 'Test Driver'): array
    {
        $organization = TodaOrganization::create([
            'name' => $name.' TODA',
            'barangay_travel_area' => $name.' Area',
            'status' => 'Active',
        ]);
        $driver = Driver::create([
            'name' => $name,
            'license_number' => 'LIC-'.fake()->unique()->numerify('#####'),
            'contact_number' => '09170000000',
            'vehicle_number' => 'TEST-'.fake()->unique()->numerify('####'),
            'status' => 'Active',
            'organization_id' => $organization->id,
        ]);
        $destination = OrganizationDestination::create([
            'organization_id' => $organization->id,
            'name' => 'Downtown',
            'status' => 'Active',
        ]);
        $account = User::factory()->create([
            'role' => 'Driver',
            'user_id' => 'DRIVER-'.$driver->id,
            'must_change_password' => false,
        ]);

        return [$driver, $account, $account->createToken('driver-trips-test')->plainTextToken, $destination];
    }

    private function recordQrEntry(Driver $driver): void
    {
        DriverLog::create([
            'driver_id' => $driver->id,
            'attendance_type' => 'ENTRY',
            'scan_method' => 'QR',
            'scanned_at' => now(),
        ]);
    }
}
