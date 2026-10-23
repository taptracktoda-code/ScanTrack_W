<?php

namespace Tests\Feature;

use App\Mail\DriverPasswordOtpMail;
use App\Models\Driver;
use App\Models\TodaOrganization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DriverPasswordOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_can_request_verify_and_use_an_otp_to_reset_password(): void
    {
        Mail::fake();
        [$driver, $account] = $this->activeDriver();

        $this->postJson('/api/driver/password/otp/request', ['email' => $account->email])
            ->assertOk()
            ->assertJsonPath('success', true);

        Mail::assertSent(DriverPasswordOtpMail::class);
        $mail = Mail::sent(DriverPasswordOtpMail::class)->first();
        $otp = $mail->otp;
        $this->assertStringContainsString($otp, $mail->render());

        $this->postJson('/api/driver/password/otp/verify', [
            'email' => $account->email,
            'otp' => $otp,
        ])->assertOk()->assertJsonPath('success', true);

        $this->postJson('/api/driver/password/otp/reset', [
            'email' => $account->email,
            'otp' => $otp,
            'password' => 'NewSecurePassword123',
            'password_confirmation' => 'NewSecurePassword123',
        ])->assertOk()->assertJsonPath('success', true);

        $account->refresh();
        $this->assertTrue(Hash::check('NewSecurePassword123', $account->password));
        $this->assertFalse($account->must_change_password);
        $this->assertNull(Cache::get('driver-password-otp:'.hash('sha256', $account->email)));

        $this->postJson('/api/driver/password/otp/reset', [
            'email' => $account->email,
            'otp' => $otp,
            'password' => 'AnotherSecurePassword123',
            'password_confirmation' => 'AnotherSecurePassword123',
        ])->assertUnprocessable();
    }

    public function test_otp_request_does_not_disclose_non_driver_accounts(): void
    {
        Mail::fake();

        $this->postJson('/api/driver/password/otp/request', [
            'email' => 'not-a-driver@example.com',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'If the email belongs to an active driver account, a verification code will be sent.');

        Mail::assertNothingSent();
    }

    private function activeDriver(): array
    {
        $organization = TodaOrganization::create([
            'name' => 'North TODA',
            'barangay_travel_area' => 'North Area',
            'status' => 'Active',
        ]);
        $driver = Driver::create([
            'name' => 'Juan Dela Cruz',
            'license_number' => 'LIC-OTP-1',
            'contact_number' => '09170000000',
            'vehicle_number' => 'ABC-1234',
            'organization_id' => $organization->id,
            'status' => 'Active',
        ]);
        $account = User::factory()->create([
            'email' => 'juan.otp@example.com',
            'role' => 'Driver',
            'user_id' => 'DRIVER-'.$driver->id,
            'status' => 'Active',
            'must_change_password' => true,
        ]);

        return [$driver, $account];
    }
}
