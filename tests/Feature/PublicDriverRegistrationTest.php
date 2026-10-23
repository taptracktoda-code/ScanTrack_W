<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\TodaOrganization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicDriverRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_application_appears_in_driver_verification_and_can_be_assigned(): void
    {
        $response = $this->postJson('/api/drivers/registration', [
            'full_name' => 'Juan Santos Dela Cruz',
            'email' => 'juan@example.com',
            'phone_number' => '09170000000',
            'license_number' => 'LIC-PUBLIC-1',
            'unit' => 'Unit 12',
            'address' => 'Main Street',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('registration.full_name', 'Juan Santos Dela Cruz')
            ->assertJsonPath('registration.status', 'pending_verification');

        $driver = Driver::query()->where('license_number', 'LIC-PUBLIC-1')->firstOrFail();
        $account = User::query()->where('email', 'juan@example.com')->firstOrFail();

        $this->assertSame('Pending', $driver->status);
        $this->assertNull($driver->organization_id);
        $this->assertSame('Cruz', $driver->last_name);
        $this->assertSame('Santos Dela', $driver->middle_name);
        $this->assertSame('DRIVER-'.strtoupper((string) $driver->id), $account->user_id);
        $this->assertSame('Pending', $account->status);
        $this->assertTrue($account->must_change_password);
        $this->assertSame('Unit 12', $account->unit);
        $this->assertSame(0, Vehicle::count());

        $admin = User::factory()->create(['role' => 'Administrator']);
        $this->actingAs($admin)->get(route('drivers.verification'))
            ->assertOk()
            ->assertSee('Juan Santos Dela Cruz')
            ->assertSee('juan@example.com')
            ->assertSee('Unit: Unit 12')
            ->assertSee('Assign organization');

        $organization = TodaOrganization::create([
            'name' => 'North TODA',
            'barangay_travel_area' => 'North Area',
            'status' => 'Active',
        ]);

        $this->post(route('drivers.assign', $driver), [
            'organization_id' => $organization->id,
            'plate_number' => 'ABC-1234',
            'vehicle_type' => 'Tricycle',
        ])->assertRedirect(route('drivers.verification'));

        $this->assertSame($organization->id, $driver->fresh()->organization_id);
        $this->assertSame('ABC-1234', $driver->fresh()->vehicle_number);
        $this->assertDatabaseHas('vehicles', [
            'plate_number' => 'ABC-1234',
            'organization_id' => $organization->id,
            'status' => 'Pending',
        ]);

        $this->get(route('drivers.verification'))
            ->assertOk()
            ->assertSee('ABC-1234')
            ->assertSee('Approve driver');
    }

    public function test_public_registration_rejects_duplicate_email_and_license(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $this->postJson('/api/drivers/registration', [
            'full_name' => 'Juan Dela Cruz',
            'email' => 'existing@example.com',
            'phone_number' => '09170000000',
            'license_number' => 'LIC-PUBLIC-2',
            'unit' => 'Unit 12',
            'address' => 'Main Street',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertSame(0, Driver::count());

        $this->postJson('/api/drivers/registration', [
            'full_name' => 'Juan Dela Cruz',
            'email' => 'new@example.com',
            'phone_number' => '09170000000',
            'license_number' => 'LIC-PUBLIC-2',
            'unit' => 'Unit 12',
            'address' => 'Main Street',
        ])->assertCreated();

        $this->postJson('/api/drivers/registration', [
            'full_name' => 'Maria Santos',
            'email' => 'another@example.com',
            'phone_number' => '09170000001',
            'license_number' => 'LIC-PUBLIC-2',
            'unit' => 'Unit 13',
            'address' => 'Other Street',
        ])->assertUnprocessable()->assertJsonValidationErrors('license_number');
    }
}
