<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_admin_can_log_in(): void
    {
        config(['app.fake' => true]);
        $this->seed(AdminSeeder::class);

        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD', 'password');

        $this->post('/login', ['email' => $email, 'password' => $password]);

        $this->assertAuthenticated();
    }

    public function test_registration_route_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_admin_seeder_is_idempotent(): void
    {
        $this->seed(AdminSeeder::class);
        $this->seed(AdminSeeder::class);

        $this->assertSame(1, User::where('email', env('ADMIN_EMAIL', 'admin@example.com'))->count());
    }

    public function test_set_admin_password_command_updates_password(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com']);

        $this->artisan('app:set-admin-password', ['email' => 'admin@example.com'])
            ->expectsQuestion('New password', 'brand-new-pass')
            ->expectsQuestion('Confirm new password', 'brand-new-pass')
            ->assertExitCode(0);

        $this->assertTrue(Hash::check('brand-new-pass', $user->fresh()->password));
    }
}
