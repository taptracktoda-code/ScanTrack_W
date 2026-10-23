<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\OrganizationEvent;
use App\Models\TodaOrganization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ScheduleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_schedule_for_assigned_organization_and_its_drivers_can_see_it(): void
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
        $driver = $this->createDriver($organization, 'North Driver', 'NORTH-1');
        $token = $manager->createToken('schedule-test')->plainTextToken;
        $eventDate = now()->addDays(2)->toDateString();
        $this->assertTrue($driver->isDriver());
        $this->assertSame('Active', $driver->status);
        $this->assertNotNull(Driver::find((int) substr((string) $driver->user_id, 7)));

        $response = $this->withToken($token)->postJson('/api/manager/schedules', [
            'title' => 'TODA General Assembly',
            'description' => 'Monthly meeting for all drivers.',
            'event_date' => $eventDate,
            'start_time' => '09:00',
            'end_time' => '11:30',
            'location' => 'North Terminal',
        ]);

        $response->assertCreated()
            ->assertJsonPath('schedule.title', 'TODA General Assembly')
            ->assertJsonPath('schedule.organization_id', $organization->id);
        $event = OrganizationEvent::firstOrFail();

        Auth::forgetGuards();
        $this->withToken($driver->createToken('driver-schedule-test')->plainTextToken)
            ->getJson('/api/driver/schedule/upcoming')
            ->assertOk()
            ->assertJsonPath('data.0.id', $event->id)
            ->assertJsonPath('data.0.location', 'North Terminal');

        $this->withToken($driver->createToken('driver-schedule-date-test')->plainTextToken)
            ->getJson('/api/driver/schedule/'.$eventDate)
            ->assertOk()
            ->assertJsonPath('data.0.title', 'TODA General Assembly');
    }

    public function test_manager_cannot_manage_or_view_events_from_another_organization(): void
    {
        $managerOrganization = $this->createOrganization('North TODA');
        $otherOrganization = $this->createOrganization('South TODA');
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'status' => 'Active',
            'organization_id' => $managerOrganization->id,
            'must_change_password' => false,
        ]);
        $event = OrganizationEvent::create([
            'organization_id' => $otherOrganization->id,
            'title' => 'South TODA Event',
            'event_date' => now()->addDays(1)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'location' => 'South Terminal',
        ]);
        $token = $manager->createToken('schedule-scope-test')->plainTextToken;

        $this->withToken($token)->getJson('/api/manager/schedules')
            ->assertOk()
            ->assertJsonCount(0, 'schedules');
        $this->withToken($token)->putJson('/api/manager/schedules/'.$event->id, ['title' => 'Changed'])
            ->assertNotFound();
        $this->withToken($token)->deleteJson('/api/manager/schedules/'.$event->id)
            ->assertNotFound();
        $this->assertSame('South TODA Event', $event->fresh()->title);
    }

    public function test_manager_can_update_and_delete_an_event_in_their_organization(): void
    {
        $organization = $this->createOrganization('North TODA');
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'status' => 'Active',
            'organization_id' => $organization->id,
            'must_change_password' => false,
        ]);
        $event = $this->createEvent($organization, 'Original Event', now()->addDays(1)->toDateString());
        $token = $manager->createToken('schedule-update-test')->plainTextToken;

        $this->withToken($token)->putJson('/api/manager/schedules/'.$event->id, [
            'title' => 'Updated Event',
            'start_time' => '10:00',
            'end_time' => '09:30',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('end_time');
        $this->withToken($token)->putJson('/api/manager/schedules/'.$event->id, [
            'title' => 'Updated Event',
        ])->assertOk()
            ->assertJsonPath('schedule.title', 'Updated Event');
        $this->withToken($token)->deleteJson('/api/manager/schedules/'.$event->id)
            ->assertOk();

        $this->assertDatabaseMissing('organization_events', ['id' => $event->id]);
    }

    public function test_driver_schedule_only_includes_events_for_the_drivers_organization(): void
    {
        $driverOrganization = $this->createOrganization('North TODA');
        $otherOrganization = $this->createOrganization('South TODA');
        $driver = $this->createDriver($driverOrganization, 'North Driver', 'NORTH-2');
        $date = now()->addDays(1)->toDateString();
        $visibleEvent = $this->createEvent($driverOrganization, 'North Event', $date);
        $this->createEvent($otherOrganization, 'South Event', $date);

        $this->withToken($driver->createToken('driver-schedule-scope-test')->plainTextToken)
            ->getJson('/api/driver/schedule/'.$date)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visibleEvent->id);
    }

    public function test_schedule_rejects_end_time_before_start_time(): void
    {
        $organization = $this->createOrganization('North TODA');
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'status' => 'Active',
            'organization_id' => $organization->id,
            'must_change_password' => false,
        ]);

        $this->withToken($manager->createToken('invalid-schedule-test')->plainTextToken)
            ->postJson('/api/manager/schedules', [
                'title' => 'Invalid Event',
                'event_date' => now()->addDay()->toDateString(),
                'start_time' => '11:00',
                'end_time' => '10:00',
                'location' => 'North Terminal',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_time');
    }

    public function test_manager_dashboard_can_create_events_for_its_assigned_organization(): void
    {
        $organization = $this->createOrganization('North TODA');
        $otherOrganization = $this->createOrganization('South TODA');
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'status' => 'Active',
            'organization_id' => $organization->id,
            'must_change_password' => false,
        ]);
        $this->actingAs($manager)->get(route('manager.dashboard'))
            ->assertOk()
            ->assertSee('Add an event')
            ->assertSee('Scheduled events')
            ->assertSee('North TODA');

        $this->actingAs($manager)->post(route('manager.schedules.store'), [
            'title' => 'TODA Meeting',
            'description' => 'Monthly driver meeting',
            'event_date' => now()->addDays(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'location' => 'North Terminal',
        ])->assertRedirect(route('manager.dashboard'));

        $event = OrganizationEvent::firstOrFail();
        $this->assertSame($organization->id, $event->organization_id);
        $this->assertNotSame($otherOrganization->id, $event->organization_id);

        $this->actingAs($manager)->get(route('manager.dashboard'))
            ->assertOk()
            ->assertSee('TODA Meeting');
    }

    public function test_manager_web_login_requires_temporary_password_change_before_dashboard_access(): void
    {
        $organization = $this->createOrganization('North TODA');
        $manager = User::factory()->create([
            'email' => 'manager.web@example.com',
            'password' => bcrypt('temporary-password'),
            'role' => User::ROLE_MANAGER,
            'status' => 'Active',
            'organization_id' => $organization->id,
            'must_change_password' => true,
        ]);

        $this->post(route('manager.login.store'), [
            'email' => $manager->email,
            'password' => 'temporary-password',
        ])->assertRedirect(route('manager.password.change'));
        $this->get(route('manager.dashboard'))->assertRedirect(route('manager.password.change'));

        $this->post(route('manager.password.update'), [
            'current_password' => 'temporary-password',
            'password' => 'a-new-secure-password',
            'password_confirmation' => 'a-new-secure-password',
        ])->assertRedirect(route('manager.dashboard'));

        $this->assertFalse($manager->fresh()->must_change_password);
        $this->get(route('manager.dashboard'))->assertOk();
    }

    private function createOrganization(string $name): TodaOrganization
    {
        return TodaOrganization::create([
            'name' => $name,
            'barangay_travel_area' => $name.' Area',
            'status' => 'Active',
        ]);
    }

    private function createDriver(TodaOrganization $organization, string $name, string $plateNumber): User
    {
        $driver = Driver::create([
            'name' => $name,
            'license_number' => 'LIC-'.$plateNumber,
            'contact_number' => '09170000000',
            'address' => 'Main Street',
            'vehicle_number' => $plateNumber,
            'organization_id' => $organization->id,
            'status' => 'Active',
        ]);

        return User::factory()->create([
            'role' => User::ROLE_DRIVER,
            'user_id' => 'DRIVER-'.strtoupper((string) $driver->id),
            'status' => 'Active',
            'must_change_password' => false,
        ]);
    }

    private function createEvent(TodaOrganization $organization, string $title, string $date): OrganizationEvent
    {
        return OrganizationEvent::create([
            'organization_id' => $organization->id,
            'title' => $title,
            'event_date' => $date,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'location' => $organization->name.' Terminal',
        ]);
    }
}
