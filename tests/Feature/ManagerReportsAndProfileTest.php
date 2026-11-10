<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\DriverIssueRequest;
use App\Models\OrganizationQueueEntry;
use App\Models\TodaOrganization;
use App\Models\User;
use App\Services\MobileNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManagerReportsAndProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_photos_are_previewed_inline_and_signed_links_still_expire(): void
    {
        Storage::fake('local');
        [$manager, , $account] = $this->fixture('Photo');
        $response = $this->actingAs($account, 'sanctum')->postJson('/api/driver/issues', [
            'type' => 'other', 'explanation' => 'Broken light', 'client_request_id' => 'photo-preview',
            'attachments' => [UploadedFile::fake()->image('evidence.jpg')],
        ])->assertCreated();
        $url = $response->json('data.attachments.0.url');
        $id = $response->json('data.id');
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Content-Disposition', 'inline; filename=evidence.jpg');
        $this->actingAs($manager, 'web')->get('/manager/reports/'.$id)->assertOk()
            ->assertSee('alt="Report photo: evidence.jpg"', false)->assertDontSee('Download evidence.jpg');
        $this->get(route('reports.attachments.show', [$id, 0]))->assertForbidden();
        $this->travel(16)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_mobile_report_with_attachment_is_received_reviewed_on_web_and_synced_to_driver(): void
    {
        Storage::fake('local');
        [$manager, $driver, $account] = $this->fixture('North');
        $payload = ['type' => 'other', 'explanation' => 'Broken terminal light', 'client_request_id' => 'light-report',
            'attachments' => [UploadedFile::fake()->create('evidence.pdf', 20, 'application/pdf')]];
        $response = $this->actingAs($account, 'sanctum')->postJson('/api/driver/issues', $payload)->assertCreated();
        $id = $response->json('data.id');
        $url = $response->json('data.attachments.0.url');
        $this->postJson('/api/driver/issues', $payload)->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertCount(1, Storage::disk('local')->allFiles('report-attachments'));
        $this->assertDatabaseCount('driver_issue_requests', 1);
        $this->get($url)->assertOk()->assertDownload('evidence.pdf');
        $this->get(route('reports.attachments.show', [$id, 0]))->assertForbidden();

        $this->actingAs($manager, 'web')->get('/manager/reports')->assertOk()->assertSee('North Driver')->assertSee('Broken terminal light');
        $this->getJson('/manager/notifications/feed')->assertOk()->assertJsonPath('notifications.0.title', 'Driver report submitted')
            ->assertJsonPath('notifications.0.url', route('manager.reports.show', $id));
        $this->get('/manager/reports/'.$id)->assertOk()->assertSee('evidence.pdf')->assertSee('Review report');
        $this->post('/manager/reports/'.$id.'/review', ['decision' => 'approved', 'decision_reason' => 'Repair scheduled'])
            ->assertRedirect('/manager/reports/'.$id)->assertSessionHas('status');
        $this->get('/manager/reports/'.$id)->assertOk()->assertSee('Repair scheduled')->assertSee($manager->name)->assertDontSee('Save review');
        $this->actingAs($account, 'sanctum')->getJson('/api/driver/issues')->assertOk()
            ->assertJsonPath('data.0.status', 'approved')->assertJsonPath('data.0.decision_reason', 'Repair scheduled')
            ->assertJsonPath('data.0.manager_name', $manager->name)->assertJsonPath('data.0.attachments.0.name', 'evidence.pdf');
        $this->assertTrue($manager->notifications()->exists());
        $this->assertTrue($account->notifications()->exists());
        $this->travel(16)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_web_reports_are_scoped_searchable_filtered_and_paginated(): void
    {
        [$manager, $driver] = $this->fixture('North');
        [$foreign, $foreignDriver] = $this->fixture('South');
        $issue = $this->report($driver, 'Broken lamp');
        $this->report($driver, 'Resolved concern', 'approved');
        $secret = $this->report($foreignDriver, 'South secret');
        $this->actingAs($manager)->get('/manager/reports?status=pending&search=lamp')->assertOk()
            ->assertSee('Broken lamp')->assertDontSee('Resolved concern')->assertDontSee('South secret');
        $this->get('/manager/reports?search=North')->assertOk()->assertSee('Broken lamp')->assertSee('Resolved concern');
        $this->get('/manager/reports?status=rejected')->assertOk()->assertSee('No submitted reports match your filters.');
        $this->get('/manager/reports?date='.now('Asia/Manila')->toDateString())->assertOk()->assertSee('Broken lamp');
        $this->get('/manager/reports?date=2000-01-01')->assertOk()->assertDontSee('Broken lamp');
        $this->get('/manager/reports?status=invalid')->assertSessionHasErrors('status');
        $this->get('/manager/reports/'.$secret->id)->assertNotFound();
        $this->getJson('/manager/notifications/feed')->assertOk()->assertDontSee('South secret');
        $this->post('/manager/reports/'.$secret->id.'/review', ['decision' => 'rejected', 'decision_reason' => 'No'])->assertNotFound();
        $this->actingAs($foreign)->get('/manager/reports/'.$issue->id)->assertNotFound();
        for ($i = 0; $i < 16; $i++) $this->report($driver, 'More report '.$i);
        $this->actingAs($manager)->get('/manager/reports')->assertOk()->assertViewHas('reports', fn ($reports) => $reports->count() === 15 && $reports->total() === 18);
        $this->get('/manager/reports?page=2')->assertOk()->assertViewHas('reports', fn ($reports) => $reports->count() === 3);
    }

    public function test_web_review_requires_reason_and_queue_removal_confirmation_and_rejects_duplicate_decisions(): void
    {
        [$manager, $driver] = $this->fixture('Queue');
        $entry = OrganizationQueueEntry::create(['organization_id' => $driver->organization_id, 'driver_id' => $driver->id, 'queue_number' => 1, 'status' => 'WAITING']);
        $issue = $this->report($driver, 'Flat tire');
        $issue->update(['queue_entry_id' => $entry->id, 'pending_entry_id' => $entry->id]);
        $url = '/manager/reports/'.$issue->id.'/review';
        $this->actingAs($manager)->post($url, ['decision' => 'approved', 'decision_reason' => ' '])->assertSessionHasErrors(['decision_reason', 'confirm_removal']);
        $this->post($url, ['decision' => 'approved', 'decision_reason' => 'Repair tire', 'confirm_removal' => 1])->assertRedirect();
        $this->assertDatabaseHas('organization_queue_entries', ['id' => $entry->id, 'status' => 'CANCELLED', 'removed_by' => $manager->id]);
        $this->post($url, ['decision' => 'rejected', 'decision_reason' => 'Changed mind'])->assertSessionHasErrors('request');
        $this->assertSame('approved', $issue->fresh()->status);
    }

    public function test_attachment_validation_and_storage_failure_leave_no_partial_reports(): void
    {
        Storage::fake('local');
        [, , $account] = $this->fixture('Invalid');
        $payload = ['type' => 'other', 'explanation' => 'Concern', 'client_request_id' => 'invalid'];
        $this->actingAs($account, 'sanctum')->postJson('/api/driver/issues', [...$payload,
            'attachments' => [UploadedFile::fake()->create('script.html', 1, 'text/html')]])->assertUnprocessable();
        $this->postJson('/api/driver/issues', [...$payload,
            'attachments' => [UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf')]])->assertUnprocessable();
        $this->postJson('/api/driver/issues', [...$payload, 'attachments' => array_map(
            fn ($i) => UploadedFile::fake()->create('file'.$i.'.pdf', 1, 'application/pdf'), range(1, 4)
        )])->assertUnprocessable();
        $this->mock(MobileNotificationService::class)->shouldReceive('notifyOrganizationManagers')->andThrow(new \RuntimeException('Notification failed'));
        $this->postJson('/api/driver/issues', [...$payload,
            'attachments' => [UploadedFile::fake()->create('evidence.pdf', 1, 'application/pdf')]])->assertStatus(500);
        $this->assertDatabaseCount('driver_issue_requests', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles('report-attachments'));
    }

    public function test_manager_can_edit_only_own_profile_and_replace_and_remove_photo(): void
    {
        Storage::fake('public');
        [$manager] = $this->fixture('Profile');
        [$other] = $this->fixture('Other');
        $payload = ['first_name' => 'Updated', 'middle_name' => 'M', 'last_name' => 'Manager',
            'email' => 'updated@example.com', 'contact' => '09171234567', 'address' => 'New address',
            'id' => $other->id, 'role' => 'SUPER_ADMIN', 'organization_id' => $other->organization_id];
        $this->actingAs($manager)->get('/manager/profile')->assertOk()->assertSee('Edit Profile');
        $this->get('/manager/profile?edit=1')->assertOk()->assertSee('Save profile');
        $this->put('/manager/profile', [...$payload, 'profile_picture' => UploadedFile::fake()->image('avatar.jpg')])->assertRedirect('/manager/profile');
        $manager->refresh();
        $first = $manager->profile_picture;
        Storage::disk('public')->assertExists($first);
        $this->assertSame('Updated M Manager', $manager->name);
        $this->assertSame('MANAGER', $manager->role);
        $this->assertNotSame($other->organization_id, $manager->organization_id);
        $this->assertSame('Other Manager', $other->fresh()->name);
        $this->get('/manager/profile')->assertOk()->assertSee('Updated M Manager')->assertSee('09171234567')->assertSee($first);
        $this->actingAs($manager, 'sanctum')->getJson('/api/user')->assertOk()->assertJsonPath('data.name', 'Updated M Manager');
        $this->actingAs($manager, 'web')->put('/manager/profile', [...$payload, 'profile_picture' => UploadedFile::fake()->image('next.png')])->assertRedirect();
        Storage::disk('public')->assertMissing($first);
        $second = $manager->fresh()->profile_picture;
        Storage::disk('public')->assertExists($second);
        $this->put('/manager/profile', [...$payload, 'remove_picture' => 1])->assertRedirect();
        $this->assertNull($manager->fresh()->profile_picture);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_invalid_profile_changes_are_not_saved_and_non_managers_cannot_edit(): void
    {
        [$manager, , $driverAccount] = $this->fixture('Validate');
        [$other] = $this->fixture('Taken');
        $this->actingAs($manager)->put('/manager/profile', [
            'first_name' => ' ', 'last_name' => 'Manager', 'email' => $other->email, 'contact' => str_repeat('1', 41),
            'profile_picture' => UploadedFile::fake()->create('bad.txt', 1, 'text/plain'),
        ])->assertSessionHasErrors(['first_name', 'email', 'contact', 'profile_picture']);
        $this->assertSame('Validate Manager', $manager->fresh()->name);
        $this->actingAs($driverAccount, 'web')->put('/manager/profile', [])->assertForbidden();
        $this->get('/manager/reports')->assertForbidden();
    }

    private function fixture(string $name): array
    {
        $org = TodaOrganization::create(['name' => $name.' TODA', 'barangay_travel_area' => $name, 'status' => 'Active']);
        $manager = User::factory()->create(['name' => $name.' Manager', 'role' => 'MANAGER', 'status' => 'Active', 'organization_id' => $org->id, 'must_change_password' => false]);
        $driver = Driver::create(['name' => $name.' Driver', 'license_number' => 'LIC-'.$name, 'contact_number' => '09170000000', 'status' => 'Active', 'organization_id' => $org->id]);
        $account = User::factory()->create(['role' => 'DRIVER', 'user_id' => 'DRIVER-'.$driver->id, 'status' => 'Active', 'must_change_password' => false]);
        return [$manager, $driver, $account];
    }

    private function report(Driver $driver, string $details, string $status = 'pending'): DriverIssueRequest
    {
        return DriverIssueRequest::create(['organization_id' => $driver->organization_id, 'driver_id' => $driver->id,
            'type' => 'other', 'explanation' => $details, 'status' => $status, 'client_request_id' => fake()->uuid()]);
    }
}
