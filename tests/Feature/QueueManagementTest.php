<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\OrganizationQueueEntry;
use App\Models\TodaOrganization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_manages_fifo_queue_and_super_admin_can_monitor_it(): void
    {
        $organization = TodaOrganization::create([
            'name' => 'North TODA',
            'barangay_travel_area' => 'North Area',
        ]);
        $manager = User::factory()->create([
            'role' => 'Manager',
            'status' => 'Active',
            'organization_id' => $organization->id,
            'must_change_password' => false,
        ]);
        $firstDriver = $this->activeDriver($organization, 'North Driver One', 'NORTH-1');
        $secondDriver = $this->activeDriver($organization, 'North Driver Two', 'NORTH-2');
        $token = $manager->createToken('test')->plainTextToken;

        $first = $this->withToken($token)->postJson('/api/manager/queue', ['driver_id' => $firstDriver->id]);
        $first->assertCreated()->assertJsonPath('entry.queue_number', 1)->assertJsonPath('entry.status', 'WAITING');
        $second = $this->withToken($token)->postJson('/api/manager/queue', ['driver_id' => $secondDriver->id]);
        $second->assertCreated()->assertJsonPath('entry.queue_number', 2);

        $this->withToken($token)->getJson('/api/manager/queue')
            ->assertOk()
            ->assertJsonPath('waiting_count', 2)
            ->assertJsonPath('waiting.0.driver.name', 'North Driver One');

        $serving = $this->withToken($token)->postJson('/api/manager/queue/serve-next');
        $serving->assertOk()->assertJsonPath('entry.driver.name', 'North Driver One')->assertJsonPath('entry.status', 'SERVING');
        $entryId = $serving->json('entry.id');
        $this->withToken($token)->postJson('/api/manager/queue/serve-next')->assertStatus(409);
        $this->withToken($token)->postJson('/api/manager/queue/' . $entryId . '/complete')->assertOk();

        $this->assertDatabaseHas('organization_queue_entries', [
            'id' => $entryId,
            'status' => 'COMPLETED',
        ]);
        $this->assertDatabaseCount('terminal_activities', 4);

        $admin = User::factory()->create(['role' => 'SUPER_ADMIN']);
        $this->actingAs($admin)->get(route('queues.monitoring'))
            ->assertOk()
            ->assertViewHas('organizations', function ($queues): bool {
                return $queues->first()['waiting'] === 1
                    && $queues->first()['current'] === null
                    && $queues->first()['last_served']?->driver?->name === 'North Driver One';
            })
            ->assertSee('North Driver One');
    }

    public function test_manager_cannot_queue_a_driver_from_another_organization(): void
    {
        $organization = TodaOrganization::create(['name' => 'North TODA', 'barangay_travel_area' => 'North Area']);
        $otherOrganization = TodaOrganization::create(['name' => 'South TODA', 'barangay_travel_area' => 'South Area']);
        $manager = User::factory()->create([
            'role' => 'Manager',
            'status' => 'Active',
            'organization_id' => $organization->id,
            'must_change_password' => false,
        ]);
        $driver = $this->activeDriver($otherOrganization, 'South Driver', 'SOUTH-1');

        $this->withToken($manager->createToken('test')->plainTextToken)
            ->postJson('/api/manager/queue', ['driver_id' => $driver->id])
            ->assertUnprocessable();
        $this->assertSame(0, OrganizationQueueEntry::count());
    }

    public function test_manager_must_change_temporary_password_before_queue_actions(): void
    {
        $organization = TodaOrganization::create(['name' => 'North TODA', 'barangay_travel_area' => 'North Area']);
        $manager = User::factory()->create([
            'role' => 'Manager',
            'status' => 'Active',
            'organization_id' => $organization->id,
            'must_change_password' => true,
        ]);

        $this->withToken($manager->createToken('test')->plainTextToken)
            ->getJson('/api/manager/queue')
            ->assertForbidden()
            ->assertJsonPath('must_change_password', true);
    }

    private function activeDriver(TodaOrganization $organization, string $name, string $plate): Driver
    {
        return Driver::create([
            'name' => $name,
            'license_number' => 'LIC-' . $plate,
            'contact_number' => '09170000000',
            'vehicle_number' => $plate,
            'organization_id' => $organization->id,
            'status' => 'Active',
        ]);
    }
}
