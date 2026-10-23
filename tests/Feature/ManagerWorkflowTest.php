<?php

namespace Tests\Feature;

use App\Mail\ManagerAccountCreatedMail;
use App\Mail\DriverAccountCreatedMail;
use App\Models\TodaOrganization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ManagerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_a_manager_and_email_temporary_credentials(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'Administrator']);
        $organization = TodaOrganization::create([
            'name' => 'North TODA',
            'barangay_travel_area' => 'North Area',
        ]);

        $response = $this->actingAs($admin)->post(route('managers.store'), [
            'first_name' => 'Maya',
            'middle_name' => 'Rose',
            'last_name' => 'Manager',
            'email' => 'maya@example.com',
            'contact' => '09170000000',
            'organization_id' => $organization->id,
        ]);

        $response->assertRedirect(route('managers'));
        $manager = User::where('email', 'maya@example.com')->firstOrFail();
        $this->assertSame('Manager', $manager->role);
        $this->assertSame('Active', $manager->status);
        $this->assertSame($organization->id, $manager->organization_id);
        $this->assertSame('Maya', $manager->first_name);
        $this->assertSame('Rose', $manager->middle_name);
        $this->assertSame('Manager', $manager->last_name);
        $this->assertTrue($manager->must_change_password);
        $this->assertNotSame('Maya Manager', $manager->password);
        Mail::assertSent(ManagerAccountCreatedMail::class, fn (ManagerAccountCreatedMail $mail) => $mail->manager->is($manager));
        $this->actingAs($admin)->get(route('managers'))
            ->assertOk()
            ->assertSee('maya@example.com');
    }

    public function test_admin_mobile_api_can_assign_a_manager_to_an_active_organization(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $manager = User::factory()->create(['role' => 'Manager']);
        $organization = TodaOrganization::create([
            'name' => 'North TODA',
            'barangay_travel_area' => 'North Area',
        ]);
        $token = $admin->createToken('mobile-admin')->plainTextToken;

        $this->withToken($token)->patchJson('/api/admin/managers/' . $manager->id . '/organization', [
            'organization_id' => $organization->id,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('manager.organization_id', $organization->id)
            ->assertJsonPath('manager.organization', 'North TODA');

        $this->assertDatabaseHas('admins', [
            'id' => $manager->id,
            'organization_id' => $organization->id,
        ]);
    }

    public function test_admin_mobile_api_rejects_assigning_a_manager_to_an_inactive_organization(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $manager = User::factory()->create(['role' => 'Manager']);
        $organization = TodaOrganization::create([
            'name' => 'Inactive TODA',
            'barangay_travel_area' => 'North Area',
            'status' => 'Inactive',
        ]);
        $token = $admin->createToken('mobile-admin')->plainTextToken;

        $this->withToken($token)->patchJson('/api/admin/managers/' . $manager->id . '/organization', [
            'organization_id' => $organization->id,
        ])->assertUnprocessable();

        $this->assertNull($manager->fresh()->organization_id);
    }

    public function test_manager_can_create_an_active_driver_account_for_assigned_organization(): void
    {
        Mail::fake();
        $organization = TodaOrganization::create([
            'name' => 'North TODA',
            'barangay_travel_area' => 'North Area',
        ]);
        $manager = User::factory()->create([
            'role' => 'Manager',
            'status' => 'Active',
            'organization_id' => $organization->id,
            'must_change_password' => false,
        ]);

        $response = $this->withToken($manager->createToken('test')->plainTextToken)->postJson('/api/manager/drivers', [
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@example.com',
            'contact_number' => '09171112222',
            'address' => 'Main Street',
            'license_number' => 'LIC-0001',
            'license_expiration_date' => now()->addYear()->toDateString(),
            'plate_number' => 'ABC-1234',
            'vehicle_type' => 'Tricycle',
        ]);

        $response->assertCreated()
            ->assertJsonPath('driver.status', 'Active')
            ->assertJsonPath('success', true);
        $this->assertDatabaseHas('drivers', [
            'name' => 'Juan Santos Dela Cruz',
            'organization_id' => $organization->id,
            'registered_by' => $manager->id,
            'status' => 'Active',
            'approved_at' => null,
        ]);
        $this->assertDatabaseHas('admins', [
            'email' => 'juan@example.com',
            'role' => User::ROLE_DRIVER,
            'status' => 'Active',
            'must_change_password' => true,
        ]);
        $this->assertDatabaseHas('vehicles', [
            'plate_number' => 'ABC-1234',
            'organization_id' => $organization->id,
            'status' => 'Active',
        ]);
        $driver = \App\Models\Driver::where('license_number', 'LIC-0001')->firstOrFail();
        $this->assertDatabaseHas('qr_codes', [
            'driver_id' => $driver->id,
            'status' => 'Active',
        ]);
        $temporaryPassword = null;
        Mail::assertSent(DriverAccountCreatedMail::class, function (DriverAccountCreatedMail $mail) use ($driver, &$temporaryPassword): bool {
            $temporaryPassword = $mail->temporaryPassword;

            return $mail->driver->is($driver);
        });
        $this->assertFalse(Hash::check('password', User::where('email', 'juan@example.com')->firstOrFail()->password));
        $this->assertDatabaseCount('qr_codes', 1);
        $this->postJson('/api/login', [
            'email' => 'juan@example.com',
            'password' => $temporaryPassword,
        ])->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_manager_cannot_access_admin_web_routes(): void
    {
        $manager = User::factory()->create(['role' => 'Manager']);

        foreach ([route('dashboard'), route('managers'), route('settings.profile'), route('vehicles')] as $url) {
            $this->actingAs($manager)->get($url)->assertForbidden();
        }
    }

    public function test_manager_login_is_denied_when_organization_is_inactive(): void
    {
        $organization = TodaOrganization::create([
            'name' => 'North TODA',
            'barangay_travel_area' => 'North Area',
            'status' => 'Inactive',
        ]);
        User::factory()->create([
            'email' => 'inactive.manager@example.com',
            'password' => Hash::make('manager-password'),
            'role' => 'Manager',
            'status' => 'Active',
            'organization_id' => $organization->id,
        ]);

        $this->postJson('/api/manager/login', [
            'email' => 'inactive.manager@example.com',
            'password' => 'manager-password',
        ])->assertForbidden()->assertJsonPath('success', false);
    }
}
