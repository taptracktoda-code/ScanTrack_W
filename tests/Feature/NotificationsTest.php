<?php

namespace Tests\Feature;

use App\Models\TerminalActivity;
use App\Models\Driver;
use App\Models\TodaOrganization;
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

    public function test_super_admin_notification_bell_is_visible(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('navbar-notifications-toggle', false)
            ->assertSee('navbar-notification-list', false);
    }

    public function test_manager_notification_bell_and_feed_are_limited_to_assigned_organization(): void
    {
        $organization = TodaOrganization::create([
            'name' => 'North TODA',
            'barangay_travel_area' => 'North Area',
            'status' => 'Active',
        ]);
        $otherOrganization = TodaOrganization::create([
            'name' => 'South TODA',
            'barangay_travel_area' => 'South Area',
            'status' => 'Active',
        ]);
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'status' => 'Active',
            'organization_id' => $organization->id,
            'must_change_password' => false,
        ]);
        $northDriver = Driver::create([
            'name' => 'North Driver',
            'license_number' => 'LIC-NOTIFY-NORTH',
            'contact_number' => '09170000001',
            'vehicle_number' => 'NORTH-1',
            'organization_id' => $organization->id,
            'status' => 'Active',
        ]);
        $southDriver = Driver::create([
            'name' => 'South Driver',
            'license_number' => 'LIC-NOTIFY-SOUTH',
            'contact_number' => '09170000002',
            'vehicle_number' => 'SOUTH-1',
            'organization_id' => $otherOrganization->id,
            'status' => 'Active',
        ]);
        TerminalActivity::create([
            'admin_id' => $manager->id,
            'driver_id' => $northDriver->id,
            'activity' => 'North driver entered',
            'description' => 'North organization activity',
        ]);
        TerminalActivity::create([
            'driver_id' => $southDriver->id,
            'activity' => 'South driver entered',
            'description' => 'Other organization activity',
        ]);

        $this->actingAs($manager)
            ->get(route('manager.dashboard'))
            ->assertOk()
            ->assertSee('navbar-notifications-toggle', false)
            ->assertSee('navbar-notification-list', false);

        $this->actingAs($manager)
            ->getJson(route('manager.notifications.feed'))
            ->assertOk()
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.title', 'North driver entered')
            ->assertDontSee('South driver entered');
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