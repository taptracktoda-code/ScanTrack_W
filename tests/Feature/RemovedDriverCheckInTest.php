<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\OrganizationQueueEntry;
use App\Models\TodaOrganization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemovedDriverCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_enqueue_cannot_bypass_returning_driver_checkin(): void
    {
        $org = TodaOrganization::create(['name' => 'Returning TODA', 'barangay_travel_area' => 'Area', 'status' => 'Active']);
        $manager = User::factory()->create(['role' => 'Manager', 'status' => 'Active', 'organization_id' => $org->id, 'must_change_password' => false]);
        $driver = Driver::create(['name' => 'Returning Driver', 'license_number' => 'RETURN-1', 'vehicle_number' => 'RETURN-1', 'contact_number' => '09170000000', 'status' => 'Active', 'organization_id' => $org->id]);
        $entry = $this->actingAs($manager, 'sanctum')->postJson('/api/manager/queue', ['driver_id' => $driver->id])->assertCreated()->json('entry.id');
        $this->postJson('/api/manager/queue/'.$entry.'/remove', ['reason' => 'left_terminal'])->assertOk();
        $this->postJson('/api/manager/queue', ['driver_id' => $driver->id])->assertUnprocessable()->assertJsonValidationErrors('driver_id');
        $this->assertSame(0, OrganizationQueueEntry::where('driver_id', $driver->id)->where('status', 'WAITING')->count());
    }
}
