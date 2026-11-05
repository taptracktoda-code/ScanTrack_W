<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\ExpoPushToken;
use App\Models\TodaOrganization;
use App\Models\User;
use App\Notifications\ScanTrackNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileNotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_and_manager_can_read_and_mark_only_their_own_notifications(): void
    {
        [$driver, $driverAccount, $driverToken] = $this->driverFixture();
        $organization = TodaOrganization::create([
            'name' => 'Manager TODA',
            'barangay_travel_area' => 'Manager Area',
            'status' => 'Active',
        ]);
        $manager = User::factory()->create([
            'role' => 'Manager',
            'organization_id' => $organization->id,
            'must_change_password' => false,
        ]);
        $driverNotification = $this->createNotification($driverAccount, 'Terminal entry recorded');
        $managerNotification = $this->createNotification($manager, 'Driver entered terminal');

        $this->withToken($driverToken)
            ->getJson('/api/driver/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.id', $driverNotification->id)
            ->assertJsonPath('data.0.title', 'Terminal entry recorded')
            ->assertJsonPath('data.0.is_read', false);

        $this->withToken($driverToken)
            ->putJson("/api/driver/notifications/{$driverNotification->id}/read")
            ->assertOk();

        $this->assertNotNull($driverNotification->fresh()->read_at);

        $this->withToken($driverToken)
            ->putJson("/api/driver/notifications/{$managerNotification->id}/read")
            ->assertNotFound();

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/manager/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.id', $managerNotification->id)
            ->assertJsonPath('data.0.title', 'Driver entered terminal');
    }

    public function test_push_token_is_registered_for_the_signed_in_account_and_removed_on_logout(): void
    {
        [, , $driverToken] = $this->driverFixture();
        $pushToken = 'ExpoPushToken[abc123xyz]';

        $this->withToken($driverToken)
            ->postJson('/api/driver/notifications/push-token', ['expo_push_token' => $pushToken])
            ->assertCreated();

        $this->assertDatabaseHas('expo_push_tokens', [
            'token' => $pushToken,
        ]);

        $this->withToken($driverToken)
            ->deleteJson('/api/driver/notifications/push-token', ['expo_push_token' => $pushToken])
            ->assertOk();

        $this->assertDatabaseMissing('expo_push_tokens', ['token' => $pushToken]);
    }

    public function test_notification_sends_a_push_to_registered_devices(): void
    {
        Http::fake([
            'https://exp.host/--/api/v2/push/send' => Http::response([
                'data' => [['status' => 'ok', 'id' => 'push-ticket-id']],
            ]),
        ]);
        [, $driverAccount] = $this->driverFixture();
        $token = 'ExpoPushToken[abc123xyz]';
        ExpoPushToken::create(['user_id' => $driverAccount->id, 'token' => $token]);

        $driverAccount->notify(new ScanTrackNotification(
            'Terminal entry recorded',
            'Your QR scan was recorded successfully.',
            'attendance',
            ['screen' => 'attendance'],
        ));

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $driverAccount->id,
            'data' => json_encode([
                'title' => 'Terminal entry recorded',
                'message' => 'Your QR scan was recorded successfully.',
                'type' => 'attendance',
                'data' => ['screen' => 'attendance'],
            ]),
        ]);

        Http::assertSent(function ($request) use ($token): bool {
            $message = $request->data()[0] ?? [];

            return $request->url() === 'https://exp.host/--/api/v2/push/send'
                && ($message['to'] ?? null) === $token
                && ($message['title'] ?? null) === 'Terminal entry recorded'
                && ($message['body'] ?? null) === 'Your QR scan was recorded successfully.'
                && ($message['data']['notification_type'] ?? null) === 'attendance';
        });
    }

    private function driverFixture(): array
    {
        $organization = TodaOrganization::create([
            'name' => 'Driver TODA',
            'barangay_travel_area' => 'Driver Area',
            'status' => 'Active',
        ]);
        $driver = Driver::create([
            'name' => 'Test Driver',
            'license_number' => 'LIC-'.fake()->unique()->numerify('#####'),
            'contact_number' => '09170000000',
            'vehicle_number' => 'TEST-'.fake()->unique()->numerify('####'),
            'status' => 'Active',
            'organization_id' => $organization->id,
        ]);
        $driverAccount = User::factory()->create([
            'role' => User::ROLE_DRIVER,
            'user_id' => 'DRIVER-'.$driver->id,
            'must_change_password' => false,
        ]);

        return [$driver, $driverAccount, $driverAccount->createToken('notification-test')->plainTextToken];
    }

    private function createNotification(User $user, string $title)
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => ScanTrackNotification::class,
            'data' => [
                'title' => $title,
                'message' => 'A test notification.',
                'type' => 'system',
                'data' => [],
            ],
        ]);
    }
}
