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
        $email = (string) config('admin.email');
        $password = (string) config('admin.password');

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => (string) config('admin.name'),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );
    }
}
