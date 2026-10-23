<?php

namespace Tests\Feature;

use App\Models\OrganizationDestination;
use App\Models\TodaOrganization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerTripDestinationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_list_and_deactivate_organization_destinations(): void
    {
        [$organization, $manager, $token] = $this->managerFixture();

        $create = $this->withToken($token)->postJson('/api/manager/trip-destinations', [
            'name' => 'Central Market',
        ]);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('destination.name', 'Central Market')
            ->assertJsonPath('destination.organization_id', $organization->id);

        $destinationId = $create->json('destination.id');
        $this->withToken($token)->getJson('/api/manager/trip-destinations')
            ->assertOk()
            ->assertJsonPath('destinations.0.name', 'Central Market')
            ->assertJsonPath('destinations.0.status', 'Active');

        $this->withToken($token)->postJson('/api/manager/trip-destinations', [
            'name' => 'Central Market',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->withToken($token)->deleteJson("/api/manager/trip-destinations/{$destinationId}")
            ->assertOk();

        $this->assertDatabaseHas('organization_destinations', [
            'id' => $destinationId,
            'organization_id' => $organization->id,
            'status' => 'Inactive',
        ]);

        auth()->forgetGuards();
        $this->withToken($manager->createToken('driver-destinations')->plainTextToken)
            ->getJson('/api/manager/trip-destinations')
            ->assertOk()
            ->assertJsonPath('destinations.0.status', 'Inactive');
    }

    public function test_manager_cannot_deactivate_another_organizations_destination(): void
    {
        [, , $managerToken] = $this->managerFixture();
        [$otherOrganization] = $this->managerFixture('Other TODA');
        $destination = OrganizationDestination::create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Other Destination',
            'status' => 'Active',
        ]);

        $this->withToken($managerToken)->deleteJson('/api/manager/trip-destinations/'.$destination->id)
            ->assertNotFound();

        $this->assertDatabaseHas('organization_destinations', [
            'id' => $destination->id,
            'status' => 'Active',
        ]);
    }

    private function managerFixture(string $name = 'Test TODA'): array
    {
        $organization = TodaOrganization::create([
            'name' => $name,
            'barangay_travel_area' => $name.' Area',
            'status' => 'Active',
        ]);
        $manager = User::factory()->create([
            'role' => 'Manager',
            'organization_id' => $organization->id,
            'must_change_password' => false,
        ]);

        return [$organization, $manager, $manager->createToken('manager-destinations')->plainTextToken];
    }
}
