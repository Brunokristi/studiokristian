<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->setAdminEnvironment(null, null, null);
        parent::tearDown();
    }

    public function test_it_does_not_create_a_fallback_user_without_credentials(): void
    {
        $this->setAdminEnvironment(null, null, null);

        $this->seed(AdminUserSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_idempotently_creates_a_verified_admin_from_explicit_credentials(): void
    {
        $this->setAdminEnvironment('Studio Admin', 'admin@example.test', 'Unique!Admin2026');

        $this->seed(AdminUserSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $admin = User::query()->sole();
        $this->assertSame('admin@example.test', $admin->email);
        $this->assertTrue($admin->is_admin);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check('Unique!Admin2026', $admin->password));
    }

    private function setAdminEnvironment(?string $name, ?string $email, ?string $password): void
    {
        foreach (['ADMIN_NAME' => $name, 'ADMIN_EMAIL' => $email, 'ADMIN_PASSWORD' => $password] as $key => $value) {
            if ($value === null) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
            } else {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}