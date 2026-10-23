<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\TodaOrganization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDriverApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_list_drivers(): void
    {
        $organization = TodaOrganization::create([
            'name' => 'North TODA',
            'barangay_travel_area' => 'North Area',
            'status' => 'Active',
        ]);
        $superAdmin = User::factory()->create(['role' => 'SUPER_ADMIN', 'status' => 'Active']);
        $driver = Driver::create([
            'name' => 'Juan Dela Cruz',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'license_number' => 'LIC-1001',
            'license_expiration_date' => now()->addYear(),
            'contact_number' => '09170000000',
            'address' => 'Main Street',
            'vehicle_number' => 'ABC-1234',
            'organization_id' => $organization->id,
            'status' => 'Active',
        ]);
        $unassignedDriver = Driver::create([
            'name' => 'Ana Santos',
            'first_name' => 'Ana',
            'last_name' => 'Santos',
            'license_number' => 'LIC-1002',
            'license_expiration_date' => now()->addYear(),
            'contact_number' => '09170000001',
            'address' => 'Second Street',
            'vehicle_number' => 'XYZ-5678',
            'status' => 'Active',
        ]);

        $response = $this->withToken($superAdmin->createToken('admin-test')->plainTextToken)
            ->getJson('/api/admin/drivers');

        $response->assertOk()
            ->assertJsonCount(2, 'drivers')
            ->assertJsonFragment([
                'id' => $unassignedDriver->id,
                'organization_id' => null,
                'organization' => null,
                'toda_name' => null,
            ])
            ->assertJsonFragment([
                'id' => $driver->id,
                'name' => 'Juan Dela Cruz',
                'vehicle_number' => 'ABC-1234',
                'organization_id' => $organization->id,
                'organization' => 'North TODA',
                'toda_name' => 'North TODA',
                'status' => 'Active',
            ]);
    }

    public function test_non_super_admin_user_cannot_list_drivers(): void
    {
        $user = User::factory()->create(['role' => 'Administrator', 'status' => 'Active']);

        $this->withToken($user->createToken('admin-test')->plainTextToken)
            ->getJson('/api/admin/drivers')
            ->assertForbidden();
    }

    public function test_admin_driver_list_requires_authentication(): void
    {
        $this->getJson('/api/admin/drivers')->assertUnauthorized();
    }

    public function test_super_admin_can_log_out_from_the_mobile_api(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'status' => 'Active']);
        $token = $superAdmin->createToken('admin-test');

        $this->withToken($token->plainTextToken)
            ->postJson('/api/admin/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }
}
