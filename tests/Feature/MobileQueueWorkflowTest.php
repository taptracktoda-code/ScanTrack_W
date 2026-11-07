<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\DriverLog;
use App\Models\OrganizationDestination;
use App\Models\OrganizationQueueEntry;
use App\Models\QrCode;
use App\Models\TodaOrganization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileQueueWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_trip_and_completion_share_one_fifo_queue(): void
    {
        [$org, $manager, $first, $firstAccount, $firstQr] = $this->fixture('First');
        [, , $second, $secondAccount, $secondQr] = $this->fixture('Second', $org, $manager);
        $destination = OrganizationDestination::create(['organization_id' => $org->id, 'name' => 'Downtown', 'status' => 'Active']);
        $this->actingAs($manager, 'sanctum')->postJson('/api/manager/qr/scan', ['qr_identifier' => $firstQr->qr_token])
            ->assertOk()->assertJsonPath('queue.status', 'WAITING')->assertJsonPath('queue.position', 1);
        $this->actingAs($manager, 'sanctum')->postJson('/api/manager/qr/scan', ['qr_identifier' => $secondQr->qr_token])
            ->assertOk()->assertJsonPath('queue.position', 2)->assertJsonPath('queue.waiting_count', 2);
        $this->actingAs($secondAccount, 'sanctum')->getJson('/api/driver/dashboard')
            ->assertOk()->assertJsonPath('data.queue.position', 2)->assertJsonPath('data.queue.is_next', false);
        $this->postJson('/api/driver/trips', ['destination_id' => $destination->id, 'passenger_count' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('queue');

        $trip = $this->actingAs($firstAccount, 'sanctum')->postJson('/api/driver/trips', ['destination_id' => $destination->id, 'passenger_count' => 2])
            ->assertCreated()->json('data.trip_id');
        $this->getJson('/api/driver/dashboard')->assertOk()
            ->assertJsonPath('data.queue.status', 'SERVING')->assertJsonPath('data.queue.position', null)->assertJsonPath('data.queue.waiting_count', 1);
        $this->actingAs($secondAccount, 'sanctum')->getJson('/api/driver/dashboard')->assertOk()
            ->assertJsonPath('data.queue.position', 1)->assertJsonPath('data.queue.is_next', true);
        $this->actingAs($manager, 'sanctum')->getJson('/api/manager/dashboard')->assertOk()
            ->assertJsonPath('data.summary.waiting_drivers', 1)->assertJsonPath('data.summary.on_trip', 1);
        $drivers = $this->getJson('/api/manager/drivers')->assertOk()->json('drivers');
        $this->assertSame('SERVING', collect($drivers)->firstWhere('id', $first->id)['queue_status']);
        $this->assertSame(1, collect($drivers)->firstWhere('id', $second->id)['queue_position']);
        $entry = OrganizationQueueEntry::where('driver_id', $first->id)->firstOrFail();
        $this->postJson('/api/manager/queue/'.$entry->id.'/complete')->assertUnprocessable();

        $this->actingAs($firstAccount, 'sanctum')->postJson('/api/driver/trips/'.$trip.'/complete')->assertOk();
        $this->getJson('/api/driver/dashboard')->assertOk()
            ->assertJsonPath('data.queue.status', 'COMPLETED')->assertJsonPath('data.queue.position', null);
        $this->assertSame(1, OrganizationQueueEntry::where('status', 'WAITING')->count());
        $this->assertSame(0, OrganizationQueueEntry::where('status', 'SERVING')->count());
        // A fresh dashboard request retains state without refreshing authentication.
        $this->getJson('/api/driver/dashboard')->assertOk()->assertJsonPath('data.queue.status', 'COMPLETED');
        $this->travel(11)->seconds();
        $this->actingAs($manager, 'sanctum')->postJson('/api/manager/qr/scan', ['qr_identifier' => $firstQr->qr_token])
            ->assertOk()->assertJsonPath('queue.position', 2);
    }

    public function test_repeated_scans_do_not_toggle_attendance_or_duplicate_queue_entries(): void
    {
        [, $manager, $driver, , $qr] = $this->fixture('Repeated');
        $this->actingAs($manager, 'sanctum')->postJson('/api/manager/qr/scan', ['qr_identifier' => $qr->qr_token])->assertOk();
        $this->postJson('/api/manager/qr/scan', ['qr_identifier' => $qr->qr_token])->assertUnprocessable();
        $this->travel(11)->seconds();
        $this->postJson('/api/manager/qr/scan', ['qr_identifier' => $qr->qr_token])->assertUnprocessable();
        $this->assertSame(1, OrganizationQueueEntry::where('driver_id', $driver->id)->count());
        $this->assertSame(1, DriverLog::where('driver_id', $driver->id)->count());
        $this->assertDatabaseHas('organization_queue_entries', ['driver_id' => $driver->id, 'status' => 'WAITING']);
    }

    public function test_explicit_exit_removes_waiting_entry_and_advances_next_driver(): void
    {
        [$org, $manager, $driver, , $qr] = $this->fixture('Leaving');
        [, , , $secondAccount, $secondQr] = $this->fixture('Remaining', $org, $manager);
        $this->actingAs($manager, 'sanctum')->postJson('/api/manager/qr/scan', ['qr_identifier' => $qr->qr_token])->assertOk();
        $this->postJson('/api/manager/qr/scan', ['qr_identifier' => $secondQr->qr_token])->assertOk();
        $this->travel(11)->seconds();
        $this->postJson('/api/manager/qr/scan', ['qr_identifier' => $qr->qr_token, 'queue_action' => 'exit'])
            ->assertOk()->assertJsonPath('transaction_type', 'EXIT')->assertJsonPath('queue.status', 'CANCELLED');
        $this->actingAs($secondAccount, 'sanctum')->getJson('/api/driver/dashboard')->assertOk()->assertJsonPath('data.queue.position', 1);
        $this->assertDatabaseHas('organization_queue_entries', ['driver_id' => $driver->id, 'status' => 'CANCELLED']);
    }

    public function test_invalid_inactive_and_foreign_qr_codes_cannot_join_queue(): void
    {
        [, $manager, $driver, $account, $qr] = $this->fixture('Protected');
        [, , , , $foreignQr] = $this->fixture('Foreign');
        $this->actingAs($manager, 'sanctum')->postJson('/api/manager/qr/scan', ['qr_identifier' => 'UNKNOWN'])->assertUnprocessable();
        $this->postJson('/api/manager/qr/scan', ['qr_identifier' => $foreignQr->qr_token])->assertUnprocessable();
        $qr->update(['status' => 'Inactive']);
        $this->postJson('/api/manager/qr/scan', ['qr_identifier' => $qr->qr_token])->assertUnprocessable();
        $qr->update(['status' => 'Active']);
        $driver->update(['status' => 'Inactive']);
        $this->postJson('/api/manager/qr/scan', ['qr_identifier' => $qr->qr_token])->assertUnprocessable();
        $this->actingAs($account, 'sanctum')->postJson('/api/manager/qr/scan', ['qr_identifier' => $qr->qr_token])->assertForbidden();
        $this->assertDatabaseCount('organization_queue_entries', 0);
        $this->assertDatabaseCount('driver_logs', 0);
    }

    private function fixture(string $name, ?TodaOrganization $org = null, ?User $manager = null): array
    {
        $org ??= TodaOrganization::create(['name' => $name.' TODA', 'barangay_travel_area' => $name, 'status' => 'Active']);
        $manager ??= User::factory()->create(['role' => 'Manager', 'status' => 'Active', 'organization_id' => $org->id, 'must_change_password' => false]);
        $driver = Driver::create(['name' => $name, 'license_number' => 'LIC-'.$name, 'contact_number' => '09170000000', 'vehicle_number' => 'PLATE-'.$name, 'status' => 'Active', 'organization_id' => $org->id]);
        $account = User::factory()->create(['role' => 'Driver', 'user_id' => 'DRIVER-'.$driver->id, 'status' => 'Active', 'must_change_password' => false]);
        $qr = QrCode::create(['driver_id' => $driver->id, 'qr_token' => 'QUEUE-'.$name, 'status' => 'Active', 'generated_at' => now()]);

        return [$org, $manager, $driver, $account, $qr];
    }
}
