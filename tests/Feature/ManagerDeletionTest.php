<?php

namespace Tests\Feature;

use App\Models\TerminalActivity;
use App\Models\TodaOrganization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_delete_manager_and_revoke_tokens_while_preserving_history(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator', 'status' => 'Active']);
        $organization = TodaOrganization::create(['name' => 'North TODA', 'barangay_travel_area' => 'North Area']);
        $manager = User::factory()->create(['role' => 'Manager', 'organization_id' => $organization->id]);
        $token = $manager->createToken('mobile')->accessToken;
        $pushToken = $manager->expoPushTokens()->create(['token' => 'ExponentPushToken[test-manager]']);
        $activity = TerminalActivity::create(['admin_id' => $manager->id, 'activity' => 'Previous manager activity']);

        $this->actingAs($admin)->get(route('managers'))->assertOk()
            ->assertSee(route('managers.destroy', $manager), false)
            ->assertSee('data-swal-delete', false);

        $this->actingAs($admin)->delete(route('managers.destroy', $manager))
            ->assertRedirect(route('managers'))->assertSessionHas('status', 'Manager account deleted.');

        $this->assertDatabaseMissing('admins', ['id' => $manager->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
        $this->assertDatabaseMissing('expo_push_tokens', ['id' => $pushToken->id]);
        $this->assertDatabaseHas('terminal_activities', ['id' => $activity->id, 'admin_id' => null]);
        $this->assertDatabaseHas('terminal_activities', ['admin_id' => $admin->id, 'activity' => 'Manager account deleted']);
        $this->assertDatabaseHas('toda_organizations', ['id' => $organization->id]);
        $this->actingAs($admin)->get(route('managers'))->assertDontSee($manager->email);
    }

    public function test_non_admins_and_inactive_admins_cannot_delete_managers(): void
    {
        $target = User::factory()->create(['role' => 'Manager']);

        $this->delete(route('managers.destroy', $target))->assertRedirect(route('login'));

        foreach ([['Manager', 'Active'], ['DRIVER', 'Active'], ['Administrator', 'Inactive']] as [$role, $status]) {
            $actor = User::factory()->create(['role' => $role, 'status' => $status]);
            $this->actingAs($actor)->delete(route('managers.destroy', $target))->assertForbidden();
        }

        $this->assertDatabaseHas('admins', ['id' => $target->id]);
    }

    public function test_manager_delete_route_rejects_other_account_roles(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator', 'status' => 'Active']);
        $driver = User::factory()->create(['role' => 'DRIVER']);

        foreach ([$admin, $driver] as $target) {
            $this->actingAs($admin)->delete(route('managers.destroy', $target))->assertNotFound();
            $this->assertDatabaseHas('admins', ['id' => $target->id]);
        }
    }
}
