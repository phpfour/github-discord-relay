<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SetAdminPassword extends Command
{
    protected $signature = 'app:set-admin-password {email? : The admin email address}';

    protected $description = 'Set (or reset) the password for the single admin account';

    public function handle(): int
    {
        $email = $this->argument('email') ?: env('ADMIN_EMAIL', 'admin@example.com');

        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->error("No user found with email [{$email}].");

            return self::FAILURE;
        }

        $password = $this->secret('New password');
        $confirm = $this->secret('Confirm new password');

        if ($password === null || $password === '') {
            $this->error('Password cannot be empty.');

            return self::FAILURE;
        }

        if ($password !== $confirm) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        $user->forceFill(['password' => Hash::make($password)])->save();

        $this->info("Password updated for [{$email}].");

        return self::SUCCESS;
    }
}
