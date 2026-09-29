<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\QrCode;
use App\Models\TodaOrganization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_qr_scan_records_entry_then_exit(): void
    {
        [$admin, $qrCode] = $this->driverFixture();

        $response = $this->withToken($admin->createToken('admin')->plainTextToken)
            ->postJson('/api/admin/qr/scan', ['qr_token' => $qrCode->qr_token]);

        $response->assertOk()
            ->assertJsonPath('transaction_type', 'ENTRY')
            ->assertJsonPath('scan_type', 'ENTRY')
            ->assertJsonStructure(['scanned_at']);
        $this->assertDatabaseHas('driver_logs', ['driver_id' => $qrCode->driver_id, 'attendance_type' => 'ENTRY', 'scan_method' => 'QR']);
        $this->assertDatabaseHas('terminal_activities', [
            'driver_id' => $qrCode->driver_id,
            'admin_id' => $admin->id,
            'activity' => 'Driver entered terminal',
        ]);

        $this->travel(11)->seconds();
        $response = $this->withToken($admin->createToken('admin-2')->plainTextToken)
            ->postJson('/api/admin/qr/scan', ['qr_identifier' => $qrCode->qr_token]);

        $response->assertOk()->assertJsonPath('transaction_type', 'EXIT');
    }

    public function test_duplicate_inactive_and_unknown_qr_scans_are_rejected(): void
    {
        [$admin, $qrCode] = $this->driverFixture();
        $token = $admin->createToken('admin')->plainTextToken;

        $this->withToken($token)->postJson('/api/admin/qr/scan', ['qr_identifier' => 'TT-QR-UNKNOWN'])
            ->assertStatus(422)->assertJsonPath('message', 'QR code not found.');

        $qrCode->update(['status' => 'Inactive']);
        $this->withToken($token)->postJson('/api/admin/qr/scan', ['qr_identifier' => $qrCode->qr_token])
            ->assertStatus(422)->assertJsonPath('message', 'QR code is inactive.');

        $qrCode->update(['status' => 'Active']);
        $this->withToken($token)->postJson('/api/admin/qr/scan', ['qr_identifier' => $qrCode->qr_token])->assertOk();
        $this->withToken($token)->postJson('/api/admin/qr/scan', ['qr_identifier' => $qrCode->qr_token])
            ->assertStatus(422)->assertJsonPath('message', 'Duplicate scan detected.');
    }

    public function test_qr_scan_requires_an_authenticated_administrator(): void
    {
        [, $qrCode] = $this->driverFixture();
        $driver = User::factory()->create(['role' => 'Driver']);

        $this->postJson('/api/qr/scan', ['qr_token' => $qrCode->qr_token])
            ->assertUnauthorized();

        $this->withToken($driver->createToken('driver')->plainTextToken)
            ->postJson('/api/admin/qr/scan', ['qr_identifier' => $qrCode->qr_token])
            ->assertForbidden();

        $this->withToken($driver->createToken('driver-qr-scan')->plainTextToken)
            ->postJson('/api/qr/scan', ['qr_token' => $qrCode->qr_token])
            ->assertForbidden();
    }

    public function test_admin_can_view_attendance_and_terminal_activities(): void
    {
        [$admin, $qrCode] = $this->driverFixture();
        $this->withToken($admin->createToken('admin-activity')->plainTextToken)
            ->postJson('/api/qr/scan', ['qr_token' => $qrCode->qr_token])
            ->assertOk();

        $this->actingAs($admin)
            ->get('/attendance')
            ->assertOk()
            ->assertSee('Attendance')
            ->assertSee('Juan Dela Cruz')
            ->assertSee('QR');

        $this->actingAs($admin)
            ->get('/terminal-activities')
            ->assertOk()
            ->assertSee('Terminal Activities')
            ->assertSee('Driver entered terminal')
            ->assertSee('QR scan accepted');
    }

    private function driverFixture(): array
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $organization = TodaOrganization::create(['name' => 'North TODA', 'barangay_travel_area' => 'North Area']);
        $driver = Driver::create(['name' => 'Juan Dela Cruz', 'license_number' => 'LIC-QR-1', 'contact_number' => '09170000000', 'vehicle_number' => 'ABC-1234', 'status' => 'Active', 'organization_id' => $organization->id]);
        Vehicle::create(['name' => $driver->name, 'plate_number' => 'ABC-1234', 'vehicle_type' => 'Tricycle', 'status' => 'Active']);
        $qrCode = QrCode::create(['driver_id' => $driver->id, 'qr_token' => 'TT-QR-TEST-001', 'status' => 'Active', 'generated_at' => now()]);

        return [$admin, $qrCode];
    }
}