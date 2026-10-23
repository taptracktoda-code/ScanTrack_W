<?php

namespace Tests\Feature;

use App\Mail\ManagerPasswordOtpMail;
use App\Models\TodaOrganization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ManagerPasswordOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_request_verify_and_use_an_otp_to_reset_password(): void
    {
        Mail::fake();
        $organization = TodaOrganization::create([
            'name' => 'North TODA',
            'barangay_travel_area' => 'North Area',
            'status' => 'Active',
        ]);
        $manager = User::factory()->create([
            'email' => 'manager@example.com',
            'role' => 'Manager',
            'status' => 'Active',
            'organization_id' => $organization->id,
            'must_change_password' => true,
        ]);

        $this->postJson('/api/manager/password/otp/request', ['email' => $manager->email])
            ->assertOk()
            ->assertJsonPath('success', true);

        Mail::assertSent(ManagerPasswordOtpMail::class);
        $mail = Mail::sent(ManagerPasswordOtpMail::class)->first();
        $otp = $mail->otp;
        $this->assertStringContainsString($otp, $mail->render());

        $this->postJson('/api/manager/password/otp/verify', [
            'email' => $manager->email,
            'otp' => $otp,
        ])->assertOk()->assertJsonPath('success', true);

        $this->postJson('/api/manager/password/otp/reset', [
            'email' => $manager->email,
            'otp' => $otp,
            'password' => 'NewSecurePassword123',
            'password_confirmation' => 'NewSecurePassword123',
        ])->assertOk()->assertJsonPath('success', true);

        $manager->refresh();
        $this->assertTrue(Hash::check('NewSecurePassword123', $manager->password));
        $this->assertFalse($manager->must_change_password);
        $this->assertNull(Cache::get('manager-password-otp:'.hash('sha256', $manager->email)));

        $this->postJson('/api/manager/password/otp/reset', [
            'email' => $manager->email,
            'otp' => $otp,
            'password' => 'AnotherSecurePassword123',
            'password_confirmation' => 'AnotherSecurePassword123',
        ])->assertUnprocessable();
    }

    public function test_otp_request_does_not_disclose_non_manager_accounts(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/manager/password/otp/request', [
            'email' => 'not-a-manager@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'If the email belongs to an active manager account, a verification code will be sent.');
        Mail::assertNothingSent();
    }
}
