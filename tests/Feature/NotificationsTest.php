<?php

namespace Tests\Feature;

use App\Models\TerminalActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_notification_bell_opens_a_dropdown_with_recent_activity(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        TerminalActivity::create([
            'admin_id' => $admin->id,
            'activity' => 'Driver entered terminal',
            'description' => 'QR scan accepted',
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('data-bs-toggle="dropdown"', false)
            ->assertSee('navbar-notification-list', false)
            ->assertDontSee('href="http://localhost/notifications"', false);

        $this->actingAs($admin)
            ->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJsonPath('notifications.0.title', 'Driver entered terminal')
            ->assertJsonPath('notifications.0.description', 'QR scan accepted');
    }

    public function test_notification_feed_requires_an_authenticated_administrator(): void
    {
        $this->get(route('notifications.feed'))->assertRedirect(route('login'));

        $driver = User::factory()->create(['role' => 'Driver']);
        $this->actingAs($driver)
            ->getJson(route('notifications.feed'))
            ->assertForbidden();
    }
}