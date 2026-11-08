<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
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

        $this->assertStrongPassword($password);

        $admin = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => config('admin.name', 'Administrator'),
                'password' => $password,
            ],
        );

        $admin->assignRole('Admin');
    }

    /**
     * The initial administrator is the most valuable account on the panel;
     * refuse to seed it with a guessable password.
     */
    private function assertStrongPassword(string $password): void
    {
        $validator = Validator::make(
            ['password' => $password],
            ['password' => [Password::min(12)->letters()->mixedCase()->numbers()->symbols()]],
        );

        if ($validator->fails()) {
            throw new InvalidArgumentException(
                'ADMIN_PASSWORD is too weak: use at least 12 characters with upper/lower case letters, numbers and symbols.'
            );
        }
    }
}
