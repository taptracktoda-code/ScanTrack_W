<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\TodaOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_endpoint_returns_public_account_details(): void
    {
        $user = User::factory()->create([
            'name' => 'Mobile User',
            'email' => 'mobile@example.com',
            'role' => 'Driver',
            'status' => 'Active',
            'must_change_password' => false,
        ]);

        $token = $user->createToken('mobile-test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/user');

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Mobile User')
            ->assertJsonPath('data.email', 'mobile@example.com')
            ->assertJsonPath('data.role', 'Driver')
            ->assertJsonMissingPath('data.password');
    }

    public function test_user_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_manager_user_endpoint_includes_the_assigned_organization(): void
    {
        $organization = TodaOrganization::create([
            'name' => 'North TODA',
            'barangay_travel_area' => 'North Area',
        ]);
        $manager = User::factory()->create([
            'role' => 'Manager',
            'organization_id' => $organization->id,
        ]);
        $token = $manager->createToken('manager-profile-test')->plainTextToken;

        $this->withToken($token)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.organization_id', $organization->id)
            ->assertJsonPath('data.organization_name', 'North TODA')
            ->assertJsonPath('data.organization.id', $organization->id)
            ->assertJsonPath('data.organization.name', 'North TODA');
    }
}
