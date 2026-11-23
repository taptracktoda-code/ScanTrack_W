<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\DriverLog;
use App\Models\TodaOrganization;
use App\Models\User;
use App\Services\OperationsReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_driver_and_manager_records_and_exports(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        Driver::create([
            'name' => 'Maria Santos',
            'license_number' => 'LIC-REPORT-1',
            'contact_number' => '09170000000',
            'vehicle_number' => 'ABC-1234',
            'status' => 'Active',
        ]);
        $organization = TodaOrganization::create(['name' => 'Central TODA', 'barangay_travel_area' => 'Central Area']);
        $manager = User::factory()->create([
            'name' => 'Rico Santos',
            'role' => 'Manager',
            'organization_id' => $organization->id,
            'contact' => '09170000001',
        ]);
        $unassignedManager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $driverAccount = User::factory()->create(['role' => User::ROLE_DRIVER]);

        $this->actingAs($admin)
            ->get(route('reports'))
            ->assertOk()
            ->assertSee('Reports')
            ->assertSee('Driver registrations')
            ->assertSee('Available exports')
            ->assertSee('Maria Santos')
            ->assertSee('Manager records')
            ->assertSeeInOrder(['Rico Santos', 'Central TODA', $manager->email, '09170000001'])
            ->assertSee($unassignedManager->email)
            ->assertDontSee($driverAccount->email)
            ->assertSee('Driver Entry and Exit Report')
            ->assertSee('Queue Monitoring');
    }

    public function test_attendance_filters_by_organization_driver_dates_and_entry_exit(): void
    {
        $admin = User::factory()->create(['role' => 'SUPER_ADMIN']);
        $north = TodaOrganization::create(['name' => 'North TODA', 'barangay_travel_area' => 'North Area']);
        $south = TodaOrganization::create(['name' => 'South TODA', 'barangay_travel_area' => 'South Area']);
        $northDriver = Driver::create([
            'name' => 'North Driver',
            'license_number' => 'LIC-NORTH-1',
            'contact_number' => '09170000001',
            'vehicle_number' => 'NORTH-1',
            'organization_id' => $north->id,
            'status' => 'Active',
        ]);
        $southDriver = Driver::create([
            'name' => 'South Driver',
            'license_number' => 'LIC-SOUTH-1',
            'contact_number' => '09170000002',
            'vehicle_number' => 'SOUTH-1',
            'organization_id' => $south->id,
            'status' => 'Active',
        ]);
        DriverLog::create(['driver_id' => $northDriver->id, 'attendance_type' => 'ENTRY', 'scanned_at' => now()]);
        DriverLog::create(['driver_id' => $southDriver->id, 'attendance_type' => 'EXIT', 'scanned_at' => now()]);

        $response = $this->actingAs($admin)->get(route('attendance', [
            'organization_id' => $north->id,
            'driver_id' => $northDriver->id,
            'attendance_type' => 'ENTRY',
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
        ]));

        $response->assertOk()->assertViewHas('records', function ($records): bool {
            return $records->count() === 1 && $records->first()->driver?->name === 'North Driver';
        });
    }

    public function test_admin_can_download_each_pdf_report_and_managers_cannot_access_system_wide_reports(): void
    {
        $admin = User::factory()->create(['role' => 'SUPER_ADMIN']);
        foreach (array_keys(OperationsReport::TYPES) as $type) {
            $response = $this->actingAs($admin)->get(route('reports.export', ['type' => $type]));
            $response->assertOk()->assertDownload($type.'-'.now(OperationsReport::ZONE)->toDateString().'.pdf')
                ->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $response->getContent());
            $this->assertMatchesRegularExpression('/\/MediaBox\s*\[0(?:\.0+)?\s+0(?:\.0+)?\s+841\.\d+\s+595\.\d+\s*\]/', $response->getContent());
        }

        $this->getJson(route('reports.export', ['type' => 'driver-summary']))->assertUnprocessable();
        $manager = User::factory()->create(['role' => 'Manager']);
        foreach (['reports', 'reports.export', 'queues.monitoring', 'queues.feed'] as $route) {
            $this->actingAs($manager)->get(route($route))->assertForbidden();
        }
    }

    public function test_combined_pdf_paginates_many_drivers(): void
    {
        $admin = User::factory()->create(['role' => 'SUPER_ADMIN']);
        foreach (range(1, 24) as $number) {
            Driver::create([
                'name' => 'Maria Josefina Dela Cruz Santos '.$number,
                'license_number' => 'PDF-LAYOUT-'.$number,
                'status' => 'Active',
            ]);
        }

        $pdf = $this->actingAs($admin)->get(route('reports.export', ['type' => 'combined']))
            ->assertOk()->assertDownload()->getContent();
        $this->assertGreaterThan(1, preg_match_all('/\/Type\s*\/Page\b/', $pdf));
    }
}
