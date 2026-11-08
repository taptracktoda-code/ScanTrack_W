<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\OrganizationDestination;
use App\Models\OrganizationQueueEntry;
use App\Models\QrCode;
use App\Models\TodaOrganization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TodaQueueRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_removal_advances_fifo_notifies_driver_and_rejoining_requires_new_checkin(): void
    {
        [$org, $manager, $driver, $account, $qr] = $this->fixture('Leaving');
        [, , , $remainingAccount, $remainingQr] = $this->fixture('Remaining', $org, $manager);
        $entry = $this->join($manager, $qr);
        $this->join($manager, $remainingQr);
        $this->actingAs($manager, 'sanctum')->postJson('/api/manager/queue/'.$entry.'/remove', ['reason' => 'left_terminal'])
            ->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('organization_queue_entries', ['id' => $entry, 'status' => 'CANCELLED', 'removed_by' => $manager->id, 'removal_reason' => 'left_terminal']);
        $this->assertDatabaseHas('driver_logs', ['driver_id' => $driver->id, 'attendance_type' => 'EXIT', 'scan_method' => 'MANUAL']);
        $this->getJson('/api/manager/queue/removals')->assertOk()->assertJsonPath('data.0.driver_name', 'Leaving')
            ->assertJsonPath('data.0.manager_name', $manager->name)->assertJsonPath('data.0.reason', 'Left terminal');
        $this->actingAs($account, 'sanctum')->getJson('/api/driver/dashboard')->assertOk()
            ->assertJsonPath('data.queue.status', 'CANCELLED')->assertJsonPath('data.queue.position', null);
        $notifications = $this->getJson('/api/driver/notifications')->assertOk()->json('data');
        $this->assertContains('Removed from queue', array_column($notifications, 'title'));
        $this->actingAs($remainingAccount, 'sanctum')->getJson('/api/driver/dashboard')->assertOk()->assertJsonPath('data.queue.position', 1);
        $destination = OrganizationDestination::create(['organization_id' => $org->id, 'name' => 'Downtown', 'status' => 'Active']);
        $this->actingAs($account, 'sanctum')->postJson('/api/driver/trips', ['destination_id' => $destination->id, 'passenger_count' => 1])->assertUnprocessable();
        $this->travel(11)->seconds();
        $newEntry = $this->actingAs($manager, 'sanctum')->postJson('/api/manager/qr/scan', ['qr_identifier' => $qr->qr_token])
            ->assertOk()->assertJsonPath('queue.position', 2)->json('queue.entry_id');
        $this->assertNotEquals($entry, $newEntry);
        $this->postJson('/api/manager/queue/'.$entry.'/remove', ['reason' => 'left_terminal'])->assertUnprocessable();
        $this->postJson('/api/manager/qr/scan', ['qr_identifier' => $qr->qr_token])->assertUnprocessable();
        $this->assertSame(1, OrganizationQueueEntry::where('driver_id', $driver->id)->where('status', 'WAITING')->count());
        $this->getJson('/api/manager/drivers')->assertOk()->assertJsonFragment(['queue_entry_id' => $newEntry]);
    }

    public function test_removal_validates_reason_and_cannot_remove_a_serving_or_completed_entry(): void
    {
        [, $manager, , , $qr] = $this->fixture('Validation');
        $entry = $this->join($manager, $qr);
        foreach ([[], ['reason' => 'invalid'], ['reason' => 'other'], ['reason' => 'other', 'details' => '   '], ['reason' => 'other', 'details' => str_repeat('x', 501)]] as $invalid) {
            $this->postJson('/api/manager/queue/'.$entry.'/remove', $invalid)->assertUnprocessable();
        }
        foreach (['SERVING', 'COMPLETED', 'CANCELLED'] as $status) {
            OrganizationQueueEntry::whereKey($entry)->update(['status' => $status]);
            $this->postJson('/api/manager/queue/'.$entry.'/remove', ['reason' => 'left_terminal'])->assertUnprocessable();
        }
        OrganizationQueueEntry::whereKey($entry)->update(['status' => 'WAITING']);
        $this->postJson('/api/manager/queue/'.$entry.'/remove', ['reason' => 'other', 'details' => '  Vehicle breakdown  '])->assertOk();
        $this->assertDatabaseHas('organization_queue_entries', ['id' => $entry, 'removal_details' => 'Vehicle breakdown']);
        $this->assertDatabaseCount('terminal_activities', 2);
    }

    public function test_removal_and_audit_enforce_roles_and_toda_boundaries(): void
    {
        [, $manager, , $account, $qr] = $this->fixture('Own');
        [, $foreignManager, , , $foreignQr] = $this->fixture('Foreign');
        $entry = $this->join($manager, $qr);
        $foreignEntry = $this->join($foreignManager, $foreignQr);
        $this->actingAs($manager, 'sanctum')->postJson('/api/manager/queue/'.$foreignEntry.'/remove', ['reason' => 'left_terminal'])->assertNotFound();
        $this->actingAs($foreignManager, 'sanctum')->postJson('/api/manager/queue/'.$foreignEntry.'/remove', ['reason' => 'left_terminal'])->assertOk();
        $this->actingAs($manager, 'sanctum')->getJson('/api/manager/queue/removals')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($account, 'sanctum')->postJson('/api/manager/queue/'.$entry.'/remove', ['reason' => 'left_terminal'])->assertForbidden();
        $this->getJson('/api/manager/queue/removals')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'SUPER_ADMIN']), 'sanctum')
            ->postJson('/api/manager/queue/'.$entry.'/remove', ['reason' => 'left_terminal'])->assertForbidden();
    }

    public function test_my_toda_exposes_only_assigned_active_managers_and_official_contact_and_refreshes_assignment(): void
    {
        [$org, $manager, $driver, $account] = $this->fixture('MyToda');
        [$foreign, $foreignManager] = $this->fixture('Different');
        $org->update(['contact_number' => 'OFFICIAL-123']);
        $manager->update(['name' => 'Assigned Manager', 'contact' => 'PRIVATE-123']);
        $manager->forceFill(['profile_picture' => 'profile-pictures/manager.jpg'])->save();
        $this->actingAs($account, 'sanctum')->getJson('/api/driver/toda?organization_id='.$foreign->id)->assertOk()
            ->assertJsonPath('data.name', $org->name)->assertJsonPath('data.managers.0.name', 'Assigned Manager')
            ->assertJsonPath('data.official_contact_number', 'OFFICIAL-123')
            ->assertJsonCount(1, 'data.managers')->assertJsonMissing(['contact' => 'PRIVATE-123'])
            ->assertJsonMissingPath('data.managers.0.email')->assertJsonMissingPath('data.managers.0.password')
            ->assertJsonMissingPath('data.managers.0.contact')->assertJsonMissingPath('data.managers.0.user_id');
        $manager->update(['status' => 'Inactive']);
        $this->getJson('/api/driver/toda')->assertOk()->assertJsonCount(0, 'data.managers');
        $driver->update(['organization_id' => $foreign->id]);
        $this->getJson('/api/driver/toda')->assertOk()->assertJsonPath('data.managers.0.id', $foreignManager->id);
        $driver->update(['organization_id' => null]);
        $this->getJson('/api/driver/toda')->assertOk()->assertJsonPath('data.id', null)->assertJsonCount(0, 'data.managers');
        $this->actingAs($manager, 'sanctum')->getJson('/api/driver/toda')->assertForbidden();
    }

    private function join(User $manager, QrCode $qr): int
    {
        return $this->actingAs($manager, 'sanctum')->postJson('/api/manager/qr/scan', ['qr_identifier' => $qr->qr_token])
            ->assertOk()->json('queue.entry_id');
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
