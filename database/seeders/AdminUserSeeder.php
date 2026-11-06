<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use InvalidArgumentException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('admin.email');
        $password = config('admin.password');

        if (blank($email) && blank($password)) {
            $this->command?->warn('Set ADMIN_EMAIL and ADMIN_PASSWORD in .env to create the initial panel administrator.');

            return;
        }

        if (blank($email) || blank($password)) {
            throw new InvalidArgumentException('Both ADMIN_EMAIL and ADMIN_PASSWORD must be set to create the panel administrator.');
        }

        $admin = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => config('admin.name', 'Administrator'),
                'password' => $password,
            ],
        );

        $admin->assignRole('Admin');
    }
}
