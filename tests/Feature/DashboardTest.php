<?php

namespace Tests\Feature;

use App\Models\TodaOrganization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_displays_registered_toda_organizations(): void
    {
        $user = User::factory()->create([
            'role' => 'SUPER_ADMIN',
            'user_id' => 'USER-001',
            'license_number' => 'LIC-001',
            'contact' => '09170000001',
            'address' => 'Main Street',
            'status' => 'Active',
        ]);
        $organization = TodaOrganization::create([
            'name' => 'Bayanihan TODA',
            'barangay_travel_area' => 'San Isidro',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee($organization->name);
        $response->assertSee($organization->barangay_travel_area);
        $response->assertSee('profile-chip');
        $response->assertDontSee('aria-label="Send SMS"');
        $response->assertDontSee('sendSmsModal');
    }

    public function test_dashboard_does_not_display_daily_shift_status(): void
    {
        $user = User::factory()->create([
            'role' => 'SUPER_ADMIN',
            'user_id' => 'USER-001',
            'license_number' => 'LIC-001',
            'contact' => '09170000001',
            'address' => 'Main Street',
            'status' => 'Active',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertDontSee('Duty Status')
            ->assertDontSee('data-duty-status')
            ->assertDontSee('dashboard-shift-badge');
        $this->assertDatabaseHas('admins', [
            'id' => $user->id,
            'shift_status' => 'Available',
        ]);
        $this->assertSame('Available', $user->fresh()->shift_status);
        $this->post('/dashboard/shift-status', ['shift_status' => 'Off Duty'])
            ->assertNotFound();
    }

    public function test_dashboard_does_not_show_driver_verification_or_pending_driver_stats(): void
    {
        $user = User::factory()->create([
            'role' => 'SUPER_ADMIN',
            'status' => 'Active',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertDontSee('Pending Driver Verification')
            ->assertDontSee('Driver Verification')
            ->assertViewHas('stats', fn (array $stats): bool => ! array_key_exists('pending_drivers', $stats));
    }
}
