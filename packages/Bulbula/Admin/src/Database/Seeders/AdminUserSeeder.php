<?php

namespace Bulbula\Admin\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $name     = env('BULBULA_ADMIN_NAME');
        $email    = env('BULBULA_ADMIN_EMAIL');
        $password = env('BULBULA_ADMIN_PASSWORD');

        if (empty($email) || empty($password)) {
            $this->command->error(
                ('Admin credentials are not set. Run `php artisan bulbula:install` to set up ' . config('brand.name') . ' properly.')
            );
            return;
        }

        $name = $name ?: 'Admin';

        User::firstOrCreate(
            ['email' => $email],
            [
                'name'               => $name,
                'password'           => Hash::make($password),
                'role'               => 'admin',
                'is_active'          => true,
                'email_verified_at'  => now(),
            ]
        );

        $this->command->info('Admin user created successfully!');
        $this->command->info('Email: ' . $email);
    }
}
