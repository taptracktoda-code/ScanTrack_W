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
    }

    public function test_dashboard_can_update_and_persist_shift_status(): void
    {
        $user = User::factory()->create([
            'user_id' => 'USER-001',
            'license_number' => 'LIC-001',
            'contact' => '09170000001',
            'address' => 'Main Street',
            'status' => 'Active',
        ]);

        $response = $this->actingAs($user)
            ->post(route('dashboard.shift_status'), ['shift_status' => 'On Break']);

        $response->assertOk();
        $this->assertDatabaseHas('admins', [
            'id' => $user->id,
            'shift_status' => 'On Break',
        ]);

        $dashboard = $this->actingAs($user)->get('/dashboard');
        $dashboard->assertOk();
        $dashboard->assertSee('On Break');
        $dashboard->assertSee('Duty shift paused');
    }
}
