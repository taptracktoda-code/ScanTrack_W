<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_driver_reports_and_exports(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        Driver::create([
            'name' => 'Maria Santos',
            'license_number' => 'LIC-REPORT-1',
            'contact_number' => '09170000000',
            'vehicle_number' => 'ABC-1234',
            'status' => 'Active',
        ]);

        $this->actingAs($admin)
            ->get(route('reports'))
            ->assertOk()
            ->assertSee('Reports')
            ->assertSee('Driver registrations')
            ->assertSee('Available exports')
            ->assertSee('Maria Santos')
            ->assertSee('QR Scan and Attendance Report');
    }
}
