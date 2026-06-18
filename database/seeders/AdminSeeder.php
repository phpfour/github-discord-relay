<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the single admin account from ADMIN_EMAIL / ADMIN_PASSWORD. Idempotent.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD', 'password');

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Admin'),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );
    }
}
