<?php

namespace Tests\Feature;

use App\Models\ClientContact;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class PersistentLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_magic_link_login_sets_a_long_lived_remember_cookie(): void
    {
        $user = User::query()->create([
            'name' => 'Staff User',
            'email' => 'staff@example.test',
            'password' => bcrypt('secret-password'),
            'is_admin' => false,
            'email_verified_at' => now(),
        ]);

        $plainToken = Str::random(64);
        $user->loginTokens()->create([
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(10),
            'requested_ip' => '127.0.0.1',
        ]);

        $response = $this->get(URL::temporarySignedRoute('staff.login.consume', now()->addMinutes(10), ['token' => $plainToken]));

        $response->assertRedirect('/admin/client-portal/projects');
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertLongLivedRememberCookie($response, Auth::guard('web')->getRecallerName());
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_client_magic_link_login_sets_a_long_lived_remember_cookie(): void
    {
        $company = Company::query()->create([
            'name' => 'Client Company',
            'status' => 'active',
        ]);

        $contact = ClientContact::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Lenka',
            'last_name' => 'Kontakt',
            'email' => 'lenka@example.test',
            'active' => true,
            'can_access_portal' => true,
        ]);

        $plainToken = Str::random(64);
        $contact->loginTokens()->create([
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(10),
            'requested_ip' => '127.0.0.1',
            'request_identifier' => (string) Str::uuid(),
        ]);

        $response = $this->get(URL::temporarySignedRoute('client.magic-link.consume', now()->addMinutes(10), ['token' => $plainToken]));

        $response->assertRedirect(route('client.dashboard'));
        $this->assertAuthenticatedAs($contact, 'client');
        $this->assertLongLivedRememberCookie($response, Auth::guard('client')->getRecallerName());
        $this->assertNotNull($contact->fresh()->remember_token);
    }

    private function assertLongLivedRememberCookie($response, string $cookieName): void
    {
        $cookie = collect($response->headers->getCookies())->first(
            fn ($currentCookie) => $currentCookie->getName() === $cookieName
        );

        $this->assertNotNull($cookie, 'Remember-me cookie was not set.');
        $this->assertGreaterThan(now()->addDays(300)->getTimestamp(), $cookie->getExpiresTime());
    }
}
