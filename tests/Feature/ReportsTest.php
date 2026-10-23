<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\DriverLog;
use App\Models\TodaOrganization;
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

    public function test_admin_can_download_verification_vehicle_organization_and_activity_exports(): void
    {
        $admin = User::factory()->create(['role' => 'SUPER_ADMIN']);
        $headers = [
            'driver-verification' => ['Driver', 'Organization', 'Status', 'Registration Date', 'Approved At', 'Approved By', 'Rejected At', 'Rejected By', 'Rejection Reason'],
            'vehicle-register' => ['Vehicle ID', 'Plate Number', 'Driver', 'Organization', 'Type', 'Model', 'Status', 'Registration Date'],
            'organization-activity' => ['Organization', 'Address', 'Contact Number', 'Email', 'Status', 'Managers', 'Drivers', 'Vehicles', 'Created At'],
            'terminal-activity' => ['Activity ID', 'Activity', 'Actor', 'Driver', 'Organization', 'Details', 'Recorded At'],
            'queue-activity' => ['Queue ID', 'Organization', 'Queue Number', 'Driver', 'Status', 'Queued By', 'Served By', 'Served At', 'Completed At'],
        ];

        foreach ($headers as $type => $header) {
            $response = $this->actingAs($admin)->get(route('reports.export', ['type' => $type]));
            $response->assertOk()->assertDownload();
            $firstLine = strtok($response->streamedContent(), "\r\n");
            $this->assertSame($header, str_getcsv($firstLine));
        }
    }
}
