<?php

namespace Tests\Feature;

use App\Mail\DriverAccountCreatedMail;
use App\Mail\DriverRegistrationRejectedMail;
use App\Models\Driver;
use App\Models\QrCode;
use App\Models\TodaOrganization;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\QrScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DriverVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_issues_hashed_temporary_password_and_forces_password_change(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'Administrator']);
        [$driver, $account] = $this->pendingDriver();

        $response = $this->actingAs($admin)->post(route('drivers.approve', $driver));

        $response->assertRedirect(route('drivers.verification'));
        $driver->refresh();
        $account->refresh();
        $this->assertSame('Active', $driver->status);
        $this->assertSame($admin->id, $driver->approved_by);
        $this->assertNotNull($driver->approved_at);
        $this->assertSame('Active', $account->status);
        $this->assertTrue($account->must_change_password);
        $this->assertDatabaseHas('vehicles', ['plate_number' => $driver->vehicle_number, 'status' => 'Active']);
        $this->assertDatabaseHas('qr_codes', ['driver_id' => $driver->id, 'status' => 'Active']);

        $temporaryPassword = null;
        Mail::assertSent(DriverAccountCreatedMail::class, function (DriverAccountCreatedMail $mail) use (&$temporaryPassword, $account): bool {
            $temporaryPassword = $mail->temporaryPassword;

            return $mail->hasTo($account->email);
        });
        $this->assertNotEmpty($temporaryPassword);
        $this->assertTrue(Hash::check($temporaryPassword, $account->password));

        $passwordHash = $account->password;
        $this->post(route('drivers.approve', $driver))
            ->assertRedirect(route('drivers.verification'))
            ->assertSessionHasErrors('driver');
        $account->refresh();
        $this->assertSame($passwordHash, $account->password);
        Mail::assertSentCount(1);

        $this->app['auth']->guard('web')->logout();
        $login = $this->postJson('/api/login', ['email' => $account->email, 'password' => $temporaryPassword]);
        $login->assertOk()
            ->assertJsonPath('data.driver.must_change_password', true)
            ->assertJsonMissingPath('data.temporary_password');
        $this->postJson('/api/login', ['email' => $account->user_id, 'password' => $temporaryPassword])
            ->assertOk()
            ->assertJsonPath('data.driver.email', $account->email);
        $token = $login->json('data.token');
        $this->withToken($token)->getJson('/api/driver/profile')->assertForbidden();
        $this->withToken($token)->postJson('/api/driver/change-password', [
            'current_password' => $temporaryPassword,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertOk()->assertJsonPath('data.must_change_password', false);
        $this->withToken($token)->getJson('/api/driver/profile')->assertOk();
    }

    public function test_invalid_driver_password_returns_unauthorized_instead_of_validation_error(): void
    {
        $organization = TodaOrganization::create([
            'name' => 'North TODA',
            'barangay_travel_area' => 'North Area',
            'status' => 'Active',
        ]);
        $driver = Driver::create([
            'name' => 'Juan Dela Cruz',
            'license_number' => 'LIC-LOGIN-1',
            'contact_number' => '09170000000',
            'vehicle_number' => 'ABC-1234',
            'organization_id' => $organization->id,
            'status' => 'Active',
        ]);
        $account = User::factory()->create([
            'email' => 'juan.login@example.com',
            'password' => Hash::make('correct-password'),
            'role' => 'Driver',
            'user_id' => 'DRIVER-'.strtoupper((string) $driver->id),
            'status' => 'Active',
        ]);

        $this->postJson('/api/login', [
            'email' => $account->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid credentials. If you recently applied, wait for Driver Verification approval and check your email for login credentials.');
    }

    public function test_driver_profile_includes_the_toda_assigned_on_the_web(): void
    {
        $organization = TodaOrganization::create([
            'name' => 'North TODA',
            'barangay_travel_area' => 'North Area',
            'status' => 'Active',
        ]);
        $driver = Driver::create([
            'name' => 'Juan Dela Cruz',
            'license_number' => 'LIC-PROFILE-TODA',
            'contact_number' => '09170000000',
            'organization_id' => $organization->id,
            'status' => 'Active',
        ]);
        $account = User::factory()->create([
            'role' => 'Driver',
            'user_id' => 'DRIVER-'.strtoupper((string) $driver->id),
            'status' => 'Active',
            'must_change_password' => false,
        ]);

        $this->withToken($account->createToken('driver-profile-test')->plainTextToken)
            ->getJson('/api/driver/profile')
            ->assertOk()
            ->assertJsonPath('data.organization_id', $organization->id)
            ->assertJsonPath('data.organization_name', 'North TODA')
            ->assertJsonPath('data.toda_name', 'North TODA')
            ->assertJsonPath('data.organization.id', $organization->id)
            ->assertJsonPath('data.organization.name', 'North TODA');
    }

    public function test_driver_can_upload_and_remove_profile_avatar(): void
    {
        Storage::fake('public');
        $driver = Driver::create([
            'name' => 'Juan Dela Cruz',
            'license_number' => 'LIC-DRIVER-AVATAR',
            'status' => 'Active',
        ]);
        $account = User::factory()->create([
            'role' => 'Driver',
            'user_id' => 'DRIVER-'.strtoupper((string) $driver->id),
            'status' => 'Active',
            'must_change_password' => false,
        ]);
        $token = $account->createToken('driver-avatar-test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/driver/profile/avatar', ['avatar' => UploadedFile::fake()->image('driver.png')])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.avatar_url', fn ($url) => is_string($url) && str_contains($url, '/storage/profile-pictures/'));

        $avatarPath = $account->fresh()->profile_picture;
        $this->assertNotEmpty($avatarPath);
        Storage::disk('public')->assertExists($avatarPath);

        $this->withToken($token)
            ->getJson('/api/driver/profile')
            ->assertOk()
            ->assertJsonPath('data.profile_picture', $avatarPath)
            ->assertJsonPath('data.avatar', Storage::disk('public')->url($avatarPath));

        $this->withToken($token)
            ->deleteJson('/api/driver/profile/avatar')
            ->assertOk()
            ->assertJsonPath('data.avatar', null);

        $this->assertNull($account->fresh()->profile_picture);
        Storage::disk('public')->assertMissing($avatarPath);
    }

    public function test_rejection_requires_a_reason_and_records_the_reviewer(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'Administrator']);
        [$driver, $account] = $this->pendingDriver();

        $this->actingAs($admin)->post(route('drivers.reject', $driver), [])->assertSessionHasErrors('rejection_reason');
        $this->assertSame('Pending', $driver->fresh()->status);

        $this->actingAs($admin)->post(route('drivers.reject', $driver), [
            'rejection_reason' => 'License details could not be verified.',
        ])->assertRedirect(route('drivers.verification'));

        $driver->refresh();
        $account->refresh();
        $this->assertSame('Rejected', $driver->status);
        $this->assertSame($admin->id, $driver->rejected_by);
        $this->assertNotNull($driver->rejected_at);
        $this->assertSame('License details could not be verified.', $driver->rejection_reason);
        $this->assertSame('Rejected', $account->status);
        Mail::assertSent(DriverRegistrationRejectedMail::class, fn (DriverRegistrationRejectedMail $mail): bool => $mail->hasTo($account->email));
    }

    public function test_inactive_organization_blocks_existing_driver_tokens_and_qr_scans(): void
    {
        $organization = TodaOrganization::create([
            'name' => 'North TODA',
            'barangay_travel_area' => 'North Area',
            'status' => 'Active',
        ]);
        $driver = Driver::create([
            'name' => 'Juan Santos Dela Cruz',
            'license_number' => 'LIC-INACTIVE-ORG',
            'contact_number' => '09170000000',
            'vehicle_number' => 'ORG-100',
            'organization_id' => $organization->id,
            'status' => 'Active',
        ]);
        $account = User::factory()->create([
            'email' => 'juan.inactive@example.com',
            'role' => 'Driver',
            'user_id' => 'DRIVER-'.strtoupper((string) $driver->id),
            'status' => 'Active',
        ]);
        $qrCode = QrCode::create([
            'driver_id' => $driver->id,
            'qr_token' => str_repeat('a', 32),
            'status' => 'Active',
            'generated_at' => now(),
        ]);
        $organization->update(['status' => 'Inactive']);

        $this->postJson('/api/login', [
            'email' => $account->email,
            'password' => 'password',
        ])->assertForbidden();
        $this->withToken($account->createToken('test')->plainTextToken)
            ->getJson('/api/driver/profile')->assertForbidden();

        $scan = app(QrScanService::class)->handle(['qr_identifier' => $qrCode->qr_token]);
        $this->assertFalse($scan['success']);
        $this->assertSame('The driver organization is inactive.', $scan['message']);
    }

    private function pendingDriver(): array
    {
        $organization = TodaOrganization::create([
            'name' => 'North TODA',
            'barangay_travel_area' => 'North Area',
        ]);
        $driver = Driver::create([
            'name' => 'Juan Santos Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'license_number' => 'LIC-VERIFY-1',
            'license_expiration_date' => now()->addYear()->toDateString(),
            'contact_number' => '09170000000',
            'address' => 'Main Street',
            'vehicle_number' => 'ABC-1234',
            'organization_id' => $organization->id,
            'status' => 'Pending',
        ]);
        Vehicle::create([
            'name' => $driver->name,
            'plate_number' => $driver->vehicle_number,
            'vehicle_type' => 'Tricycle',
            'organization_id' => $organization->id,
            'status' => 'Pending',
        ]);
        $account = User::factory()->create([
            'name' => $driver->name,
            'email' => 'juan.verify@example.com',
            'password' => Hash::make('unusable-random-password'),
            'role' => 'Driver',
            'user_id' => 'DRIVER-'.strtoupper((string) $driver->id),
            'license_number' => $driver->license_number,
            'contact' => $driver->contact_number,
            'address' => $driver->address,
            'status' => 'Pending',
            'must_change_password' => true,
        ]);

        return [$driver, $account];
    }
}
