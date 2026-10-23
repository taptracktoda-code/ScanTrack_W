<?php

namespace Tests\Feature;

use App\Mail\DriverAccountCreatedMail;
use App\Models\Driver;
use App\Models\TodaOrganization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ManagerPortalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_portal_pages_render_for_the_assigned_organization(): void
    {
        $organization = TodaOrganization::create([
            'name' => 'North TODA',
            'barangay_travel_area' => 'North Area',
            'status' => 'Active',
        ]);
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'status' => 'Active',
            'organization_id' => $organization->id,
            'must_change_password' => false,
        ]);

        $this->actingAs($manager);

        foreach ([
            'manager.drivers' => 'Drivers',
            'manager.vehicles' => 'Vehicles',
            'manager.qr.management' => 'QR Management',
            'manager.attendance' => 'Attendance',
            'manager.reports' => 'Reports',
            'manager.organization' => 'North TODA',
            'manager.profile' => 'Manager Profile',
        ] as $route => $content) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee($content);
        }
    }

    public function test_manager_web_creation_activates_driver_and_emails_credentials_without_super_admin_review(): void
    {
        Mail::fake();
        $organization = TodaOrganization::create([
            'name' => 'North TODA',
            'barangay_travel_area' => 'North Area',
            'status' => 'Active',
        ]);
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'status' => 'Active',
            'organization_id' => $organization->id,
            'must_change_password' => false,
        ]);

        $this->actingAs($manager)
            ->post(route('manager.drivers.store'), [
                'first_name' => 'Juan',
                'last_name' => 'Driver',
                'email' => 'juan.driver@example.com',
                'contact_number' => '09171112222',
                'address' => 'Main Street',
                'license_number' => 'LIC-WEB-001',
                'license_expiration_date' => now()->addYear()->toDateString(),
                'plate_number' => 'WEB-1001',
                'vehicle_type' => 'Tricycle',
            ])
            ->assertRedirect(route('manager.drivers'))
            ->assertSessionHas('status', 'Driver account created and activated. Login credentials were sent to the driver email.');

        $driver = Driver::where('license_number', 'LIC-WEB-001')->firstOrFail();
        $this->assertSame('Active', $driver->status);
        $this->assertNull($driver->approved_at);
        $this->assertDatabaseHas('admins', [
            'email' => 'juan.driver@example.com',
            'status' => 'Active',
            'must_change_password' => true,
        ]);
        $this->assertDatabaseHas('vehicles', ['plate_number' => 'WEB-1001', 'status' => 'Active']);
        $this->assertDatabaseHas('qr_codes', ['driver_id' => $driver->id, 'status' => 'Active']);
        Mail::assertSent(DriverAccountCreatedMail::class, fn (DriverAccountCreatedMail $mail) => $mail->driver->is($driver));
    }

    public function test_manager_can_download_csv_report_for_only_their_organization(): void
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
        Driver::create([
            'name' => 'North Driver',
            'license_number' => 'LIC-CSV-NORTH',
            'contact_number' => '09170000001',
            'vehicle_number' => 'NORTH-CSV',
            'organization_id' => $organization->id,
            'status' => 'Active',
        ]);
        Driver::create([
            'name' => 'South Driver Secret',
            'license_number' => 'LIC-CSV-SOUTH',
            'contact_number' => '09170000002',
            'vehicle_number' => 'SOUTH-CSV',
            'organization_id' => $otherOrganization->id,
            'status' => 'Active',
        ]);

        $this->actingAs($manager)
            ->get(route('manager.reports'))
            ->assertOk()
            ->assertSee(route('manager.reports.export'));

        $response = $this->actingAs($manager)->get(route('manager.reports.export'));
        $response->assertOk()->assertDownload('organization-report-'.$organization->id.'-'.now()->format('Y-m-d').'.csv');
        $csv = $response->streamedContent();

        $this->assertStringContainsString('North TODA', $csv);
        $this->assertStringContainsString('North Driver', $csv);
        $this->assertStringNotContainsString('South Driver Secret', $csv);
        $this->assertStringNotContainsString('South TODA', $csv);
    }
}
