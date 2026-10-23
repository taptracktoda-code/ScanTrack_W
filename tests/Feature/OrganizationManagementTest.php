<?php

namespace Tests\Feature;

use App\Models\TodaOrganization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_edit_and_deactivate_an_organization(): void
    {
        $admin = User::factory()->create(['role' => 'SUPER_ADMIN']);
        $this->actingAs($admin)->post(route('settings.toda_organizations.organizations.store'), [
            'name' => 'North TODA',
            'barangay_travel_area' => 'North Area',
            'address' => 'North Terminal',
            'contact_number' => '09170000000',
            'email' => 'north@example.com',
        ])->assertRedirect(route('settings.toda_organizations'));

        $organization = TodaOrganization::where('email', 'north@example.com')->firstOrFail();
        $this->assertSame('Active', $organization->status);
        $this->assertDatabaseHas('toda_organizations', ['id' => $organization->id, 'address' => 'North Terminal']);

        $this->actingAs($admin)->put(route('settings.toda_organizations.organizations.update', $organization), [
            'name' => 'North TODA Updated',
            'barangay_travel_area' => 'North Area',
            'address' => 'Updated Terminal',
            'contact_number' => '09171111111',
            'email' => 'updated@example.com',
        ])->assertRedirect(route('settings.toda_organizations'));
        $this->assertDatabaseHas('toda_organizations', ['id' => $organization->id, 'name' => 'North TODA Updated']);

        $this->actingAs($admin)->post(route('settings.toda_organizations.organizations.status', $organization))
            ->assertRedirect(route('settings.toda_organizations'));
        $this->assertDatabaseHas('toda_organizations', ['id' => $organization->id, 'status' => 'Inactive']);
    }
}
