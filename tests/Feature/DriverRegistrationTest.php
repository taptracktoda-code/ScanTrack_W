<?php

namespace Tests\Feature;

use App\Models\TodaOrganization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DriverRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_register_driver_account_and_send_credentials(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'Administrator']);
        $organization = TodaOrganization::create([
            'name' => 'North TODA',
            'barangay_travel_area' => 'North Area',
        ]);

        $response = $this->actingAs($admin)->post(route('drivers.register'), [
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'email' => 'juan.driver@gmail.com',
            'contact_number' => '09170000000',
            'address' => 'Main Street',
            'license_number' => 'LIC-DRIVER-1',
            'license_expiration_date' => now()->addYear()->toDateString(),
            'plate_number' => 'ABC-1234',
            'vehicle_type' => 'Tricycle',
            'organization_id' => $organization->id,
        ]);

        $response->assertRedirect(route('drivers.verification'));
        $response->assertSessionHas('status', 'Driver registration submitted for Super Admin verification. Credentials will be sent after approval.');
        $this->assertDatabaseHas('drivers', [
            'name' => 'Juan Santos Dela Cruz',
            'organization_id' => $organization->id,
            'vehicle_number' => 'ABC-1234',
            'status' => 'Pending',
        ]);
        $this->assertDatabaseHas('vehicles', ['plate_number' => 'ABC-1234', 'vehicle_type' => 'Tricycle', 'status' => 'Pending']);
        $this->assertDatabaseCount('qr_codes', 0);
        $this->assertDatabaseHas('terminal_activities', [
            'driver_id' => \App\Models\Driver::firstOrFail()->id,
            'admin_id' => $admin->id,
            'activity' => 'Driver registered',
        ]);
        $driverAccount = User::where('email', 'juan.driver@gmail.com')->firstOrFail();
        $this->assertSame(User::ROLE_DRIVER, $driverAccount->role);
        $this->assertSame('Pending', $driverAccount->status);
        $this->assertTrue($driverAccount->must_change_password);
        $this->assertFalse(Hash::check('Juan1234', $driverAccount->password));
        Mail::assertNothingSent();
    }

    public function test_admin_can_regenerate_a_driver_qr_code(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $organization = TodaOrganization::create(['name' => 'North TODA', 'barangay_travel_area' => 'North Area']);

        Mail::fake();
        $this->actingAs($admin)->post(route('drivers.register'), [
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => 'juan2.driver@gmail.com',
            'contact_number' => '09170000000', 'address' => 'Main Street', 'license_number' => 'LIC-DRIVER-2',
            'license_expiration_date' => now()->addYear()->toDateString(), 'plate_number' => 'ABC-1235',
            'vehicle_type' => 'Tricycle', 'organization_id' => $organization->id,
        ]);

        $driver = \App\Models\Driver::firstOrFail();
        $this->actingAs($admin)->post(route('drivers.approve', $driver))->assertRedirect(route('drivers.verification'));
        $driver->refresh();
        $oldToken = $driver->qrCode->qr_token;
        $this->actingAs($admin)->post(route('drivers.qr.regenerate', $driver))->assertRedirect(route('drivers'));

        $this->assertDatabaseCount('qr_codes', 1);
        $this->assertNotSame($oldToken, $driver->fresh()->qrCode->qr_token);
        $this->assertDatabaseMissing('qr_codes', ['qr_token' => $oldToken]);
    }

    public function test_non_admin_cannot_register_driver_account(): void
    {
        $user = User::factory()->create(['role' => 'Driver']);

        $this->actingAs($user)
            ->post(route('drivers.register'), [])
            ->assertForbidden();
    }

    public function test_admin_can_log_in_through_api_endpoint(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'Administrator',
            'status' => 'Active',
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $admin->id)
            ->assertJsonPath('user.name', 'Admin User')
            ->assertJsonPath('user.role', User::ROLE_SUPER_ADMIN)
            ->assertJsonPath('user.email', 'admin@example.com')
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_admin_can_fetch_transactions_via_api_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator', 'status' => 'Active']);
        $driver = \App\Models\Driver::create([
            'name' => 'Juan Dela Cruz',
            'license_number' => 'LIC-123',
            'contact_number' => '09170000000',
            'vehicle_number' => 'ABC-1234',
            'status' => 'Active',
        ]);

        \App\Models\DriverLog::create([
            'driver_id' => $driver->id,
            'attendance_type' => 'ENTRY',
            'terminal_id' => 'T-100',
            'scanned_at' => now(),
        ]);

        $response = $this->withToken($admin->createToken('test')->plainTextToken)
            ->getJson('/api/admin/transactions');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('count', 1)
            ->assertJsonStructure([
                'success',
                'count',
                'transactions' => [[
                    'id',
                    'driver_id',
                    'driver_name',
                    'attendance_type',
                    'terminal_id',
                    'status',
                    'scanned_at',
                ]],
            ]);
    }
}
