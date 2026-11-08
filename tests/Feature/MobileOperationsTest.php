<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\DriverIssueRequest;
use App\Models\DriverLog;
use App\Models\OrganizationQueueEntry;
use App\Models\TodaOrganization;
use App\Models\Trip;
use App\Models\User;
use App\Services\NextInLineService;
use App\Services\PhilippineDay;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MobileOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_counts_use_completion_time_and_philippine_midnight_without_resetting_history(): void
    {
        [, , $driver, $account] = $this->fixture('Daily');
        $this->travelTo(CarbonImmutable::parse('2026-11-08 15:59:59', 'UTC'));
        $this->trip($driver, 'before', '2026-11-07 15:59:59');
        $this->trip($driver, 'start', '2026-11-07 16:00:00');
        $this->trip($driver, 'end', '2026-11-08 15:59:59');
        $this->trip($driver, 'tomorrow', '2026-11-08 16:00:00');
        $this->trip($driver, 'cancelled', '2026-11-08 12:00:00', 'cancelled');
        $this->trip($driver, 'ongoing', null, 'in_progress');
        $this->actingAs($account, 'sanctum')->getJson('/api/driver/dashboard')->assertOk()
            ->assertJsonPath('data.today_completed_trips', 2)->assertJsonPath('data.total_trips', 6);
        $this->getJson('/api/driver/trips/summary')->assertOk()->assertJsonPath('data.today_completed_trips', 2);
        $this->travelTo(CarbonImmutable::parse('2026-11-08 16:00:00', 'UTC'));
        $this->getJson('/api/driver/dashboard')->assertOk()->assertJsonPath('data.today_completed_trips', 1)->assertJsonPath('data.total_trips', 6);
        $this->assertSame('2026-11-09', PhilippineDay::bounds()[2]);
    }

    public function test_reports_count_unique_attendance_and_preserve_organization_snapshots(): void
    {
        [$org, $manager, $driver, $account] = $this->fixture('Report');
        [$foreign, , $foreignDriver] = $this->fixture('Foreign');
        $this->trip($driver, 'included', '2026-11-07 16:00:00', 'completed', 4);
        $this->trip($driver, 'also-included', '2026-11-08 15:59:59', 'completed', 3);
        $this->trip($driver, 'outside', '2026-11-08 16:00:00', 'completed', 9);
        $this->trip($driver, 'cancelled', '2026-11-08 10:00:00', 'cancelled', 8);
        $this->trip($foreignDriver, 'foreign', '2026-11-08 10:00:00', 'completed', 100);
        foreach (['ENTRY', 'ENTRY', 'EXIT'] as $index => $type) {
            DriverLog::create(['driver_id' => $driver->id, 'attendance_type' => $type, 'created_at' => '2026-11-08 01:00:0'.$index]);
        }
        $entry = OrganizationQueueEntry::create(['organization_id' => $org->id, 'driver_id' => $driver->id, 'queue_number' => 1, 'status' => 'CANCELLED']);
        $entry->forceFill(['removed_at' => '2026-11-08 10:00:00'])->save();
        $driver->update(['organization_id' => $foreign->id]);
        $this->actingAs($manager, 'sanctum')->getJson('/api/manager/reports/daily?date=2026-11-08&organization_id='.$foreign->id)->assertOk()
            ->assertJsonPath('data.completed_trips', 2)->assertJsonPath('data.passenger_total', 7)
            ->assertJsonPath('data.unique_drivers_checked_in', 1)->assertJsonPath('data.entry_events', 2)
            ->assertJsonPath('data.exit_events', 1)->assertJsonPath('data.queue_removals', 1);
        $this->getJson('/api/manager/reports/daily?date=2026-02-30')->assertUnprocessable();
        $this->getJson('/api/manager/reports/daily?date=2026-11-10')->assertOk()->assertJsonPath('data.completed_trips', 0);
        $this->actingAs($account, 'sanctum')->getJson('/api/manager/reports/daily')->assertForbidden();
    }

    public function test_next_in_line_is_once_per_entry_and_advances_on_removal(): void
    {
        [$org, $manager, $first, $firstAccount] = $this->fixture('First');
        [, , $second, $secondAccount] = $this->fixture('Second', $org, $manager);
        $entry = $this->enqueue($manager, $first);
        $this->enqueue($manager, $second);
        for ($i = 0; $i < 3; $i++) app(NextInLineService::class)->notify($org->id);
        $this->assertSame(1, $this->nextCount($firstAccount));
        $this->assertSame(0, $this->nextCount($secondAccount));
        $this->actingAs($secondAccount, 'sanctum')->getJson('/api/driver/dashboard')->assertOk()->assertJsonPath('data.queue.drivers_ahead', 1);
        $this->actingAs($manager, 'sanctum')->postJson('/api/manager/queue/'.$entry.'/remove', ['reason' => 'left_terminal'])->assertOk();
        $this->assertSame(1, $this->nextCount($secondAccount));
        app(NextInLineService::class)->notify($org->id);
        $this->assertSame(1, $this->nextCount($secondAccount));
        $this->actingAs($secondAccount, 'sanctum')->getJson('/api/driver/dashboard')->assertOk()->assertJsonPath('data.queue.drivers_ahead', 0);
    }

    public function test_next_in_line_advances_when_first_driver_is_called_and_rolls_back_with_queue_changes(): void
    {
        [$org, $manager, $first, $firstAccount] = $this->fixture('CallFirst');
        [, , $second, $secondAccount] = $this->fixture('CallSecond', $org, $manager);
        $firstEntry = $this->enqueue($manager, $first);
        $this->enqueue($manager, $second);
        $this->actingAs($manager, 'sanctum')->postJson('/api/manager/queue/serve-next')->assertOk();
        $this->assertSame(1, $this->nextCount($secondAccount));
        $this->assertSame(1, $this->nextCount($firstAccount));
        $this->assertDatabaseHas('organization_queue_entries', ['id' => $firstEntry, 'status' => 'SERVING']);
        [, , $third, $thirdAccount] = $this->fixture('Rollback', $org, $manager);
        \Illuminate\Support\Facades\Http::fake();
        \App\Models\ExpoPushToken::create(['user_id' => $thirdAccount->id, 'token' => 'ExpoPushToken[rollback123]']);
        try {
            DB::transaction(function () use ($org, $third): void {
                OrganizationQueueEntry::where('organization_id', $org->id)->update(['status' => 'COMPLETED']);
                OrganizationQueueEntry::create(['organization_id' => $org->id, 'driver_id' => $third->id, 'queue_number' => 3, 'status' => 'WAITING']);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {}
        $this->assertSame(0, $this->nextCount($thirdAccount));
        \Illuminate\Support\Facades\Http::assertNothingSent();
        OrganizationQueueEntry::where('organization_id', $org->id)->update(['status' => 'COMPLETED']);
        $this->enqueue($manager, $third);
        $this->assertSame(1, $this->nextCount($thirdAccount));
    }

    public function test_issue_submission_is_idempotent_and_approval_atomically_removes_original_entry(): void
    {
        [$org, $manager, $driver, $account] = $this->fixture('Issue');
        $entry = $this->enqueue($manager, $driver);
        DriverLog::create(['driver_id' => $driver->id, 'attendance_type' => 'ENTRY', 'scan_method' => 'QR']);
        $payload = ['type' => 'vehicle_breakdown', 'explanation' => ' Flat tire ', 'queue_entry_id' => $entry, 'client_request_id' => 'request-one'];
        $issue = $this->actingAs($account, 'sanctum')->postJson('/api/driver/issues', $payload)->assertCreated()->json('data.id');
        $this->postJson('/api/driver/issues', $payload)->assertCreated()->assertJsonPath('data.id', $issue);
        $this->postJson('/api/driver/issues', [...$payload, 'client_request_id' => 'request-two'])->assertUnprocessable();
        $this->assertDatabaseHas('organization_queue_entries', ['id' => $entry, 'status' => 'WAITING']);
        $this->assertDatabaseCount('driver_issue_requests', 1);
        $this->actingAs($manager, 'sanctum')->getJson('/api/manager/issues')->assertOk()->assertJsonPath('pending_count', 1);
        $decision = ['decision' => 'approved', 'decision_reason' => 'Repair the tire'];
        $this->postJson('/api/manager/issues/'.$issue.'/decision', $decision)->assertUnprocessable();
        $this->postJson('/api/manager/issues/'.$issue.'/decision', [...$decision, 'confirm_removal' => true])->assertOk();
        $this->postJson('/api/manager/issues/'.$issue.'/decision', [...$decision, 'confirm_removal' => true])->assertUnprocessable();
        $this->assertDatabaseHas('driver_issue_requests', ['id' => $issue, 'status' => 'approved', 'pending_entry_id' => null]);
        $this->assertDatabaseHas('organization_queue_entries', ['id' => $entry, 'status' => 'CANCELLED', 'removed_by' => $manager->id, 'removal_details' => 'Repair the tire']);
        $this->assertDatabaseHas('driver_logs', ['driver_id' => $driver->id, 'attendance_type' => 'EXIT', 'scan_method' => 'MANUAL']);
        $this->getJson('/api/manager/issues')->assertOk()->assertJsonPath('pending_count', 0);
        $this->actingAs($account, 'sanctum')->getJson('/api/driver/issues')->assertOk()->assertJsonPath('data.0.status', 'approved')->assertJsonPath('data.0.decision_reason', 'Repair the tire');
        $this->assertTrue($account->notifications()->get()->contains(fn ($n) => $n->data['title'] === 'Issue request approved'));
        $this->assertTrue($manager->notifications()->get()->contains(fn ($n) => $n->data['title'] === 'Driver issue request'));
    }

    public function test_stale_request_cannot_remove_a_new_checkin_and_rejection_keeps_queue_unchanged(): void
    {
        [$org, $manager, $driver, $account] = $this->fixture('Stale');
        $entry = $this->enqueue($manager, $driver);
        $issue = $this->actingAs($account, 'sanctum')->postJson('/api/driver/issues', ['type' => 'leave_queue', 'explanation' => 'Need to leave', 'queue_entry_id' => $entry, 'client_request_id' => 'stale'])->assertCreated()->json('data.id');
        $this->actingAs($manager, 'sanctum')->postJson('/api/manager/queue/'.$entry.'/remove', ['reason' => 'left_terminal'])->assertOk();
        $new = OrganizationQueueEntry::create(['organization_id' => $org->id, 'driver_id' => $driver->id, 'queue_number' => 2, 'status' => 'WAITING']);
        $this->postJson('/api/manager/issues/'.$issue.'/decision', ['decision' => 'approved', 'decision_reason' => 'Okay', 'confirm_removal' => true])->assertUnprocessable();
        $this->getJson('/api/manager/issues')->assertOk()->assertJsonPath('data.0.can_approve', false);
        $this->postJson('/api/manager/issues/'.$issue.'/decision', ['decision' => 'rejected', 'decision_reason' => 'Original entry already closed'])->assertOk();
        $this->assertDatabaseHas('organization_queue_entries', ['id' => $new->id, 'status' => 'WAITING']);
    }

    public function test_issues_enforce_ownership_toda_roles_and_required_explanations(): void
    {
        [, $manager, $driver, $account] = $this->fixture('Protected');
        [, $foreignManager, $foreignDriver, $foreignAccount] = $this->fixture('ForeignProtected');
        $entry = $this->enqueue($manager, $driver);
        $this->actingAs($account, 'sanctum')->postJson('/api/driver/issues', ['type' => 'leave_queue', 'explanation' => ' ', 'queue_entry_id' => $entry, 'client_request_id' => 'invalid'])->assertUnprocessable();
        $payload = ['type' => 'leave_queue', 'explanation' => 'Reason', 'queue_entry_id' => $entry, 'client_request_id' => 'protected'];
        $issue = $this->postJson('/api/driver/issues', $payload)->assertCreated()->json('data.id');
        $this->actingAs($foreignAccount, 'sanctum')->getJson('/api/driver/issues')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/driver/issues', [...$payload, 'client_request_id' => 'foreign'])->assertNotFound();
        $this->postJson('/api/driver/issues', $payload)->assertNotFound();
        $this->postJson('/api/manager/issues/'.$issue.'/decision', ['decision' => 'rejected', 'decision_reason' => 'No'])->assertForbidden();
        $this->actingAs($foreignManager, 'sanctum')->getJson('/api/manager/issues')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/manager/issues/'.$issue.'/decision', ['decision' => 'approved', 'decision_reason' => 'No', 'confirm_removal' => true])->assertNotFound();
        $this->actingAs($manager, 'sanctum')->postJson('/api/manager/issues/'.$issue.'/decision', ['decision' => 'rejected', 'decision_reason' => ' '])->assertUnprocessable();
        $this->getJson('/api/driver/issues')->assertForbidden();
    }

    private function nextCount(User $account): int
    {
        return $account->notifications()->get()->filter(fn ($n) => ($n->data['data']['event'] ?? null) === 'next_in_line')->count();
    }

    private function enqueue(User $manager, Driver $driver): int
    {
        return $this->actingAs($manager, 'sanctum')->postJson('/api/manager/queue', ['driver_id' => $driver->id])->assertCreated()->json('entry.id');
    }

    private function trip(Driver $driver, string $id, ?string $arrival, string $status = 'completed', int $passengers = 1): void
    {
        Trip::create(['trip_id' => $id, 'driver_id' => $driver->id, 'destination' => 'Market', 'passenger_count' => $passengers,
            'status' => $status, 'departure_time' => '2026-11-07 00:00:00', 'arrival_time' => $arrival]);
    }

    private function fixture(string $name, ?TodaOrganization $org = null, ?User $manager = null): array
    {
        $org ??= TodaOrganization::create(['name' => $name.' TODA', 'barangay_travel_area' => $name, 'status' => 'Active']);
        $manager ??= User::factory()->create(['role' => 'Manager', 'status' => 'Active', 'organization_id' => $org->id, 'must_change_password' => false]);
        $driver = Driver::create(['name' => $name, 'license_number' => 'LIC-'.$name, 'contact_number' => '09170000000', 'vehicle_number' => 'PLATE-'.$name, 'status' => 'Active', 'organization_id' => $org->id]);
        $account = User::factory()->create(['role' => 'Driver', 'user_id' => 'DRIVER-'.$driver->id, 'status' => 'Active', 'must_change_password' => false]);
        return [$org, $manager, $driver, $account];
    }
}
