<?php

namespace Tests\Feature;

use App\Mail\DriverAccountCreatedMail;
use App\Models\Driver;
use App\Models\DriverLog;
use App\Models\TodaOrganization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
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

    public function test_manager_can_download_pdf_report_for_only_their_organization(): void
    {
        $this->travelTo(now('UTC')->setDate(2026, 11, 18)->setTime(16, 35));
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
        $driver = Driver::create([
            'name' => 'North Driver',
            'license_number' => 'LIC-PDF-NORTH',
            'contact_number' => '09170000001',
            'vehicle_number' => 'NORTH-PDF',
            'organization_id' => $organization->id,
            'status' => 'Active',
        ]);
        Driver::create([
            'name' => 'South Driver Secret',
            'license_number' => 'LIC-PDF-SOUTH',
            'contact_number' => '09170000002',
            'vehicle_number' => 'SOUTH-PDF',
            'organization_id' => $otherOrganization->id,
            'status' => 'Active',
        ]);

        $driver->update(['organization_id' => $otherOrganization->id]);
        DriverLog::create(['driver_id' => $driver->id, 'attendance_type' => 'EXIT', 'terminal_id' => 'Private Terminal',
            'scanned_at' => now()]);
        $driver->update(['organization_id' => $organization->id]);
        DriverLog::create(['driver_id' => $driver->id, 'attendance_type' => 'ENTRY', 'terminal_id' => 'North Terminal',
            'scanned_at' => now()->subMinutes(25)]);
        DriverLog::create(['driver_id' => $driver->id, 'attendance_type' => 'EXIT', 'scanned_at' => now()->subHour()]);

        $this->actingAs($manager)
            ->get(route('manager.reports'))
            ->assertOk()
            ->assertSee('Download PDF')
            ->assertSee(route('manager.reports.export'));

        $checked = false;
        View::composer('reports.operations-pdf', function ($view) use (&$checked): void {
            $data = $view->getData();
            $this->assertSame('North TODA', $data['organization']);
            $this->assertSame([1, 1, 0, 1, 0], $data['sections'][0]['rows']->sole());
            $row = $data['sections'][1]['rows']->sole();
            $this->assertSame('North Driver', $row[0][0]);
            $this->assertSame(['Entries' => 1, 'Exits' => 1], $row[5]);
            $this->assertSame('19 Nov 2026, 00:10:00', $row[6]);
            $this->assertStringNotContainsString('Terminal', json_encode($data['sections']));
            $checked = true;
        });
        $response = $this->get(route('manager.reports.export', ['organization_id' => $otherOrganization->id]));
        $response->assertOk()->assertDownload('organization-report-'.$organization->id.'-2026-11-19.pdf')
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertTrue($checked);
    }
}
