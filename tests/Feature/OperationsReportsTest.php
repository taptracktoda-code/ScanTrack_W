<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\DriverLog;
use App\Models\OrganizationDestination;
use App\Models\OrganizationQueueEntry;
use App\Models\QrCode;
use App\Models\TodaOrganization;
use App\Models\Trip;
use App\Models\User;
use App\Services\OperationsReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OperationsReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_reports_follow_scan_dispatch_return_exit_and_removal_across_terminals(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 18)->setTime(16, 5));
        $admin = User::factory()->create(['role' => 'SUPER_ADMIN']);
        [$north, $manager, $first, $firstAccount, $firstQr] = $this->queueFixture('North');
        [, , $second, , $secondQr] = $this->queueFixture('Second', $north, $manager);
        [$south, $southManager, , , $thirdQr] = $this->queueFixture('South');
        $destination = OrganizationDestination::create(['organization_id' => $north->id, 'name' => 'Downtown', 'status' => 'Active']);

        $this->scan($manager, $firstQr, 'North Terminal')->assertJsonPath('queue.position', 1);
        $this->scan($manager, $secondQr, 'North Terminal')->assertJsonPath('queue.position', 2);
        $this->scan($southManager, $thirdQr, 'South Terminal')->assertJsonPath('queue.position', 1);
        $this->actingAs($manager, 'sanctum')->postJson('/api/manager/qr/scan', ['qr_identifier' => $firstQr->qr_token])
            ->assertUnprocessable();
        $this->assertDatabaseCount('organization_queue_entries', 3);

        $filters = ['organization_id' => $north->id, 'terminal' => 'North Terminal', 'status' => 'WAITING',
            'start_date' => '2026-11-19', 'end_date' => '2026-11-19'];
        $feed = $this->actingAs($admin, 'web')->get(route('queues.feed', $filters))->assertOk();
        $feed->assertViewHas('summary', fn ($summary) => $summary['Total Drivers Inside Terminals'] === 2)
            ->assertViewHas('records', fn ($records) => $records->total() === 2
                && $records->firstWhere('driver_id', $second->id)['position'] === 2)
            ->assertDontSee('South Driver')
            ->assertSee('Nov 19, 2026 12:05:00 AM');
        $this->get(route('queues.feed', $filters), ['If-None-Match' => $feed->headers->get('ETag')])->assertStatus(304);
        $this->get(route('queues.feed', $filters + ['driver_id' => $second->id]))
            ->assertViewHas('records', fn ($records) => $records->total() === 1 && $records->first()['position'] === 2);

        $this->travel(4)->minutes();
        $this->actingAs($manager, 'sanctum')->postJson('/api/manager/queue/serve-next')->assertOk();
        $this->travel(2)->minutes();
        $tripId = $this->actingAs($firstAccount, 'sanctum')
            ->postJson('/api/driver/trips', ['destination_id' => $destination->id, 'passenger_count' => 1])
            ->assertCreated()->json('data.trip_id');
        $onTrip = app(OperationsReport::class)->snapshot([]);
        $this->assertSame(1, $onTrip['summary']['Total Drivers On Trip']);
        $this->assertSame(2, $onTrip['summary']['Total Drivers Inside Terminals']);
        $this->assertSame(1, $onTrip['queue']->firstWhere('driver_id', $second->id)['position']);
        $this->assertSame(240, $onTrip['queue']->firstWhere('driver_id', $first->id)['waiting']);
        $this->actingAs($admin, 'web')->get(route('queues.feed', $filters), ['If-None-Match' => $feed->headers->get('ETag')])
            ->assertOk()
            ->assertViewHas('records', fn ($records) => $records->total() === 1 && $records->first()['position'] === 1);

        $this->travel(7)->minutes();
        $this->actingAs($firstAccount, 'sanctum')->postJson('/api/driver/trips/'.$tripId.'/complete')->assertOk();
        $this->scan($manager, $firstQr, 'North Terminal')->assertJsonPath('queue.position', 2);
        $this->travel(20)->seconds();
        $this->scan($manager, $firstQr, 'North Terminal', 'exit')->assertJsonPath('queue.status', 'CANCELLED');
        $secondEntry = OrganizationQueueEntry::where('driver_id', $second->id)->sole();
        $this->postJson('/api/manager/queue/'.$secondEntry->id.'/remove', ['reason' => 'left_terminal'])->assertOk();

        $history = app(OperationsReport::class)->snapshot(['terminal' => 'North Terminal']);
        $this->assertSame(1, $history['summary']['Total Completed Trips']);
        $this->assertSame(2, $history['summary']['Total Drivers Removed from Queue']);
        $this->assertSame(0, $history['summary']['Total Drivers Inside Terminals']);
        $sessions = $history['queue']->where('driver_id', $first->id)->values();
        $this->assertCount(2, $sessions);
        $this->assertNotSame($sessions[0]['entry']->id, $sessions[1]['entry']->id);
        $this->assertSame('EXIT', $sessions[0]['exit']->attendance_type);
        $this->assertNull($sessions[0]['trip']);
        $this->assertNull($sessions[0]['position']);
        $this->assertSame($tripId, $sessions[1]['trip']->trip_id);
        $this->assertNull($sessions[1]['exit']);
        $this->assertNull($sessions[1]['position']);
        $this->assertSame('MANUAL', $history['queue']->firstWhere('driver_id', $second->id)['exit']->scan_method);

        $southOnly = app(OperationsReport::class)->snapshot(['organization_id' => $south->id, 'terminal' => 'South Terminal']);
        $this->assertSame(1, $southOnly['summary']['Total Drivers Inside Terminals']);
        $this->assertNull($southOnly['queue']->sole()['exit']);
    }

    public function test_pdf_uses_captured_queue_data_when_the_queue_changes_during_rendering(): void
    {
        $admin = User::factory()->create(['role' => 'SUPER_ADMIN']);
        [, $manager, $driver, , $qr] = $this->queueFixture('Snapshot');
        $this->scan($manager, $qr, 'Snapshot Terminal');
        $rendered = false;
        View::composer('reports.operations-pdf', function ($view) use ($driver, &$rendered): void {
            $entry = OrganizationQueueEntry::where('driver_id', $driver->id)->sole();
            $entry->update(['status' => 'SERVING', 'served_at' => now()]);
            $row = $view->getData()['sections'][0]['rows']->sole();
            $this->assertSame('Waiting', $row[2][0]);
            $this->assertStringNotContainsString('Snapshot Terminal', json_encode($row));
            $rendered = true;
        });

        $this->actingAs($admin, 'web')->get(route('reports.export', ['type' => 'queue-monitoring', 'terminal' => 'Snapshot Terminal']))
            ->assertOk()->assertDownload();
        $this->assertTrue($rendered);
        $this->assertDatabaseHas('organization_queue_entries', ['driver_id' => $driver->id, 'status' => 'SERVING']);
        $this->assertDatabaseCount('driver_logs', 1);
        $this->assertDatabaseCount('trips', 0);
    }

    public function test_legacy_scans_are_included_and_different_terminals_are_not_paired(): void
    {
        [$organization, $manager, $driver] = $this->queueFixture('Legacy');
        $entry = DriverLog::create(['driver_id' => $driver->id, 'organization_id' => $organization->id,
            'terminal_id' => 'Old Terminal', 'attendance_type' => 'ENTRY', 'scan_method' => 'QR', 'scanned_at' => now()]);
        $queue = OrganizationQueueEntry::create(['organization_id' => $organization->id, 'driver_id' => $driver->id,
            'queue_number' => 1, 'status' => 'COMPLETED', 'queued_by' => $manager->id]);
        DriverLog::create(['driver_id' => $driver->id, 'organization_id' => $organization->id,
            'terminal_id' => 'Other Terminal', 'attendance_type' => 'EXIT', 'scan_method' => 'QR', 'scanned_at' => now()]);

        $oldTerminal = app(OperationsReport::class)->snapshot(['terminal' => 'Old Terminal']);
        $this->assertSame($queue->id, $oldTerminal['queue']->sole()['queue']->id);
        $this->assertSame($entry->id, $oldTerminal['queue']->sole()['entry']->id);
        $this->assertNull($oldTerminal['queue']->sole()['exit']);
        $this->assertNull($oldTerminal['queue']->sole()['position']);
        $this->assertSame(0, $oldTerminal['summary']['Total Completed Trips']);
        $otherTerminal = app(OperationsReport::class)->snapshot(['terminal' => 'Other Terminal']);
        $this->assertTrue($otherTerminal['queue']->isEmpty());
        $this->assertSame('EXIT', $otherTerminal['attendance']->sole()['exit']->attendance_type);
    }

    public function test_web_reports_show_current_terminal_assignments_but_the_pdf_omits_them(): void
    {
        $admin = User::factory()->create(['role' => 'SUPER_ADMIN']);
        [$organization, $manager, $driver, , $qr] = $this->queueFixture('Assigned');
        [, , $noScans] = $this->queueFixture('Unassigned', $organization, $manager);
        $this->scan($manager, $qr, 'Market Terminal');

        $this->actingAs($admin, 'web')->put(route('drivers.update', $driver), [
            ...$driver->only('name', 'license_number', 'contact_number', 'vehicle_number', 'status'),
            'assigned_terminal' => 'Harbor Terminal',
        ])->assertRedirect(route('drivers'));
        $this->assertSame('Harbor Terminal', $driver->refresh()->assigned_terminal);
        $this->get(route('reports'))->assertOk()->assertSee('Assigned terminal')->assertSee('Harbor Terminal');
        $this->get(route('queues.monitoring', ['terminal' => 'Market Terminal']))->assertOk()
            ->assertSee('Assigned · Harbor Terminal')
            ->assertSee('Recorded · Market Terminal')
            ->assertSee('queue-monitoring.css');

        View::composer('reports.operations-pdf', function ($view) use ($driver, $noScans): void {
            $sections = collect($view->getData()['sections'])->keyBy('title');
            $data = json_encode($sections->all());
            $this->assertStringNotContainsString('Harbor Terminal', $data);
            $this->assertStringNotContainsString('Market Terminal', $data);
            $roster = $sections['Driver roster']['rows'];
            $this->assertSame($driver->name, $roster[$driver->id][0][0]);
            $this->assertSame($noScans->name, $roster[$noScans->id][0][0]);
        });
        $this->get(route('reports.export', ['type' => 'combined']))->assertOk()->assertDownload();
        $filtered = app(OperationsReport::class)->snapshot(['terminal' => 'Market Terminal'], includeRoster: true);
        $this->assertSame([$driver->id], $filtered['roster']->keys()->all());

        $this->put(route('drivers.update', $driver), [
            ...$driver->only('name', 'license_number', 'contact_number', 'vehicle_number', 'status'),
            'assigned_terminal' => '',
        ])->assertRedirect(route('drivers'));
        $this->assertNull($driver->refresh()->assigned_terminal);
    }

    public function test_manager_web_queue_and_live_feed_are_scoped_to_the_assigned_toda(): void
    {
        $this->freezeTime();
        [$north, $manager, $driver, , $qr] = $this->queueFixture('North');
        [$south, $otherManager, $otherDriver, , $otherQr] = $this->queueFixture('South');
        $driver->update(['organization_id' => $south->id]);
        Trip::create([
            'trip_id' => 'TRIP-PRIOR-TODA', 'driver_id' => $driver->id,
            'destination' => 'Prior destination', 'passenger_count' => 1, 'status' => 'completed',
            'departure_time' => now()->subHour(), 'arrival_time' => now()->subMinutes(30),
        ]);
        $driver->update(['organization_id' => $north->id]);
        $this->scan($manager, $qr, 'North Terminal');
        $this->scan($otherManager, $otherQr, 'South Terminal');

        $url = route('manager.queues.monitoring');
        $feedUrl = route('manager.queues.feed', ['driver_id' => $driver->id]);
        $this->actingAs($manager, 'web')->get(route('manager.dashboard'))->assertOk()->assertSee($url);
        $this->get($url)->assertOk()
            ->assertSee('North Terminal')
            ->assertSee(route('manager.reports'))
            ->assertSee(route('manager.queues.feed', ['page' => 1]))
            ->assertDontSee('South Terminal')
            ->assertDontSee('South Driver')
            ->assertDontSee('Download PDF')
            ->assertViewHas('summary', fn ($summary) => $summary['Total Drivers Waiting'] === 1);
        $feed = $this->get($feedUrl)->assertOk();
        $records = $feed->viewData('records');
        $this->assertSame(1, $records->total());
        $this->assertSame($driver->id, $records->first()['driver_id']);
        $this->assertSame(0, $records->first()['total_trips']);
        $this->assertStringStartsWith($url, $records->url(2));
        $this->get($feedUrl, ['If-None-Match' => $feed->headers->get('ETag')])->assertStatus(304);
        foreach (['manager.queues.monitoring', 'manager.queues.feed'] as $route) {
            $this->getJson(route($route, ['driver_id' => $otherDriver->id]))->assertUnprocessable()->assertJsonValidationErrors('driver_id');
            $this->getJson(route($route, ['organization_id' => $south->id]))->assertUnprocessable()->assertJsonValidationErrors('organization_id');
        }

        $this->actingAs($manager, 'sanctum')->postJson('/api/manager/queue/serve-next')->assertOk();
        $this->actingAs($manager, 'web')->get($feedUrl, ['If-None-Match' => $feed->headers->get('ETag')])
            ->assertOk()->assertViewHas('summary', fn ($summary) => $summary['Total Drivers On Trip'] === 1);

        $driver->update(['organization_id' => $south->id, 'assigned_terminal' => 'Private reassignment']);
        $this->get($url)->assertOk()->assertSee('North Driver')->assertDontSee('Private reassignment');
    }

    public function test_manager_queue_routes_require_manager_access_and_a_changed_temporary_password(): void
    {
        [, $manager] = $this->queueFixture('Access');
        $manager->update(['must_change_password' => true]);

        foreach (['manager.queues.monitoring', 'manager.queues.feed'] as $route) {
            $this->actingAs($manager, 'web')->get(route($route))->assertRedirect(route('manager.password.change'));
        }
        $admin = User::factory()->create(['role' => 'SUPER_ADMIN']);
        $this->actingAs($admin, 'web')->get(route('manager.queues.feed'))->assertForbidden();
    }

    private function scan(User $manager, QrCode $qr, string $terminal, string $action = 'join'): TestResponse
    {
        return $this->actingAs($manager, 'sanctum')->postJson('/api/manager/qr/scan', [
            'qr_identifier' => $qr->qr_token, 'terminal_id' => $terminal, 'queue_action' => $action,
        ])->assertOk();
    }

    private function queueFixture(string $label, ?TodaOrganization $organization = null, ?User $manager = null): array
    {
        $organization ??= TodaOrganization::create(['name' => $label.' TODA', 'barangay_travel_area' => $label]);
        $manager ??= User::factory()->create(['role' => 'Manager', 'organization_id' => $organization->id, 'must_change_password' => false]);
        $driver = Driver::create([
            'name' => $label.' Driver', 'organization_id' => $organization->id, 'status' => 'Active',
            'license_number' => 'LIC-'.$label, 'contact_number' => '09170000000', 'vehicle_number' => 'PLATE-'.$label,
        ]);
        $account = User::factory()->create(['role' => 'Driver', 'user_id' => 'DRIVER-'.$driver->id, 'must_change_password' => false]);
        $qr = QrCode::create(['driver_id' => $driver->id, 'qr_token' => 'REPORT-'.$label, 'status' => 'Active', 'generated_at' => now()]);

        return [$organization, $manager, $driver, $account, $qr];
    }
}
