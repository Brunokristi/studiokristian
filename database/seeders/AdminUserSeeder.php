<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $credentials = [
            'name' => env('ADMIN_NAME'),
            'email' => env('ADMIN_EMAIL'),
            'password' => env('ADMIN_PASSWORD'),
        ];

        if (! $credentials['email'] && ! $credentials['password']) {
            $this->command?->warn('Admin user skipped. Set ADMIN_EMAIL and ADMIN_PASSWORD to create one.');

            return;
        }

        Validator::make($credentials, [
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', Password::min(8)],
        ])->validate();

        $admin = User::query()->updateOrCreate(
            ['email' => strtolower($credentials['email'])],
            [
                'name' => $credentials['name'] ?: 'Administrator',
                'password' => Hash::make($credentials['password']),
                'is_admin' => true,
                'email_verified_at' => now(),
            ],
        );

        $this->command?->info("Admin user {$admin->email} is ready.");
    }
}