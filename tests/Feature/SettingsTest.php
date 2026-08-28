<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_update_profile_settings(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'role' => 'Administrator',
            'unit' => 'Main unit',
            'user_id' => 'ADMIN-TEST',
            'license_number' => '',
            'contact' => '',
            'address' => '',
            'status' => 'Active',
        ]);

        $this->actingAs($admin)
            ->get('/settings')
            ->assertOk()
            ->assertSee('Profile Settings')
            ->assertSee('TODA Organizations');

        $this->actingAs($admin)
            ->post('/settings/profile', [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'unit' => 'Unit 1',
                'contact' => '09171234567',
                'address' => 'Makati City',
                'profile_picture' => UploadedFile::fake()->image('avatar.jpg'),
            ])
            ->assertRedirect(route('settings.profile'));

        $this->assertDatabaseHas('admins', [
            'id' => $admin->id,
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'unit' => 'Unit 1',
        ]);
    }
}
