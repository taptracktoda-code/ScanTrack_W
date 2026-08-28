<?php

namespace Tests\Feature;

use App\Models\Driver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_does_not_create_a_driver_entry_for_admins(): void
    {
        $response = $this->post('/register', [
            'full_name' => 'Jane Doe',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'unit' => 'Main unit',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('admins', [
            'email' => 'admin@example.com',
            'name' => 'Jane Doe',
        ]);
        $this->assertDatabaseMissing('drivers', [
            'name' => 'Administrator',
        ]);
        $this->assertEquals(0, Driver::count());
    }
}
