<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_cannot_create_super_admin_accounts(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'full_name' => 'Jane Doe',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'unit' => 'Main unit',
        ])->assertNotFound();
        $this->assertDatabaseMissing('admins', ['email' => 'admin@example.com']);
        $this->assertSame(0, Driver::count());
    }

    public function test_manager_cannot_sign_in_to_the_admin_web(): void
    {
        User::factory()->create([
            'email' => 'manager@example.com',
            'password' => Hash::make('password123'),
            'role' => 'Manager',
            'status' => 'Active',
        ]);

        $this->post('/login', [
            'email' => 'manager@example.com',
            'password' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_super_admin_role_can_access_the_admin_web(): void
    {
        $admin = User::factory()->create(['role' => 'SUPER_ADMIN', 'status' => 'Active']);

        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
    }

    public function test_deactivated_super_admin_token_cannot_access_admin_api(): void
    {
        $admin = User::factory()->create(['role' => 'SUPER_ADMIN', 'status' => 'Active']);
        $token = $admin->createToken('admin-test')->plainTextToken;
        $admin->update(['status' => 'Inactive']);

        $this->withToken($token)->getJson('/api/admin/dashboard')->assertForbidden();
    }

    public function test_driver_api_login_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login', [
                'email' => 'unknown@example.com',
                'password' => 'incorrect-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/login', [
            'email' => 'unknown@example.com',
            'password' => 'incorrect-password',
        ])->assertTooManyRequests();
    }
}
