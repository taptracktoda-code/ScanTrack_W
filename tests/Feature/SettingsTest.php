<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_update_profile_settings(): void
    {
        Storage::fake('public');
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
            ->get('/settings/profile')
            ->assertOk()
            ->assertSee('Crop profile picture')
            ->assertSee('Use cropped picture')
            ->assertSee('data-crop-zoom');

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

        $profilePicture = $admin->fresh()->profile_picture;
        $this->assertNotEmpty($profilePicture);
        Storage::disk('public')->assertExists($profilePicture);
    }

    public function test_replacing_profile_picture_removes_the_old_file(): void
    {
        Storage::fake('public');
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'role' => 'Administrator',
            'unit' => 'Main unit',
            'user_id' => 'ADMIN-REPLACE',
            'license_number' => '',
            'contact' => '',
            'address' => '',
            'status' => 'Active',
        ]);

        $oldPicture = UploadedFile::fake()->image('old.jpg')->store('profile-pictures', 'public');
        $admin->forceFill(['profile_picture' => $oldPicture])->save();

        $this->actingAs($admin)->post('/settings/profile', [
            'name' => $admin->name,
            'email' => $admin->email,
            'unit' => $admin->unit,
            'profile_picture' => UploadedFile::fake()->image('new.png'),
        ])->assertRedirect(route('settings.profile'));

        Storage::disk('public')->assertMissing($oldPicture);
        Storage::disk('public')->assertExists($admin->fresh()->profile_picture);
    }
}
