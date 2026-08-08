<?php

namespace Tests\Feature\Client;

use App\Models\ClientContact;
use App\Models\ClientLoginToken;
use App\Models\Company;
use App\Notifications\ClientMagicLinkNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordlessLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_contact_can_request_and_use_a_magic_link(): void
    {
        Notification::fake();
        $contact = $this->contact();

        $response = $this->post(route('client.login.send'), ['email' => strtoupper($contact->email)]);

        $response->assertSessionHas('status');
        Notification::assertSentTo($contact, ClientMagicLinkNotification::class);
        $this->assertDatabaseCount('client_login_tokens', 1);
    }

    public function test_login_request_does_not_disclose_whether_email_exists(): void
    {
        Notification::fake();
        $contact = $this->contact();

        $known = $this->post(route('client.login.send'), ['email' => $contact->email]);
        $unknown = $this->post(route('client.login.send'), ['email' => 'unknown@example.test']);

        $this->assertSame($known->getSession()->get('status'), $unknown->getSession()->get('status'));
    }

    public function test_expired_magic_link_is_rejected(): void
    {
        $contact = $this->contact();
        $plainToken = Str::random(64);
        $contact->loginTokens()->create([
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->subMinute(),
        ]);
        $url = URL::temporarySignedRoute('client.magic-link.consume', now()->subMinute(), ['token' => $plainToken]);

        $this->get($url)->assertForbidden();
        $this->assertGuest('client');
    }

    public function test_magic_link_is_single_use(): void
    {
        $contact = $this->contact();
        [$url, $token] = $this->loginUrl($contact);

        $this->get($url)->assertRedirect(route('client.dashboard'));
        $this->assertAuthenticatedAs($contact, 'client');
        $this->post(route('client.logout'));

        $this->get($url)->assertRedirect(route('client.login'));
        $this->assertNotNull($token->fresh()->used_at);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_revoked_contact_loses_access_on_the_next_request(): void
    {
        $contact = $this->contact();
        $this->actingAs($contact, 'client');
        $contact->update(['can_access_portal' => false, 'access_revoked_at' => now()]);

        $this->get(route('client.dashboard'))->assertRedirect(route('client.login'));
        $this->assertGuest('client');
    }

    private function contact(): ClientContact
    {
        $company = Company::query()->create(['name' => 'ABC s.r.o.']);

        return ClientContact::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Anna',
            'last_name' => 'Kovacova',
            'email' => 'anna@example.test',
            'active' => true,
            'can_access_portal' => true,
            'can_accept_documents' => true,
        ]);
    }

    private function loginUrl(ClientContact $contact): array
    {
        $plainToken = Str::random(64);
        $token = ClientLoginToken::query()->create([
            'client_contact_id' => $contact->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(10),
        ]);

        return [
            URL::temporarySignedRoute('client.magic-link.consume', $token->expires_at, ['token' => $plainToken]),
            $token,
        ];
    }
}