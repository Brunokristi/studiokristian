<?php

namespace Tests\Feature\Admin;

use App\Models\ClientContact;
use App\Models\Company;
use App\Models\User;
use App\Notifications\ClientContactInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ContactInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_is_resent_for_an_active_client_contact(): void
    {
        Notification::fake();

        [$admin, $company, $contact] = $this->fixture();

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/clients/{$company->id}/contacts/{$contact->id}/resend-invitation")
            ->assertNoContent();

        Notification::assertSentTo($contact, ClientContactInvitationNotification::class);
    }

    public function test_archived_client_reports_the_client_as_the_blocker_not_the_contact(): void
    {
        Notification::fake();

        [$admin, $company, $contact] = $this->fixture();
        $company->update(['status' => 'archived']);

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/clients/{$company->id}/contacts/{$contact->id}/resend-invitation")
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'This client is archived, so its contacts cannot access the portal. Restore the client first.'
            );

        Notification::assertNothingSent();
    }

    public function test_inactive_contact_reports_the_contact_as_the_blocker(): void
    {
        Notification::fake();

        [$admin, $company, $contact] = $this->fixture();
        $contact->update(['active' => false]);

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/clients/{$company->id}/contacts/{$contact->id}/resend-invitation")
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'This contact is inactive. Activate the contact before sending an invitation.'
            );
    }

    public function test_revoked_access_reports_the_revocation(): void
    {
        Notification::fake();

        [$admin, $company, $contact] = $this->fixture();
        $contact->update(['access_revoked_at' => now()]);

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/clients/{$company->id}/contacts/{$contact->id}/resend-invitation")
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                "This contact's portal access was revoked. Restore access before sending an invitation."
            );
    }

    public function test_contact_from_another_client_is_not_found(): void
    {
        [$admin, $company] = $this->fixture();
        [, , $otherContact] = $this->fixture('Other');

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/clients/{$company->id}/contacts/{$otherContact->id}/resend-invitation")
            ->assertNotFound();
    }

    public function test_archiving_through_update_keeps_archived_at_consistent(): void
    {
        [$admin, $company] = $this->fixture();

        $this->actingAs($admin)
            ->putJson("/admin/client-portal/api/clients/{$company->id}", [
                'name' => $company->name,
                'status' => 'archived',
            ])
            ->assertOk();

        $this->assertNotNull($company->fresh()->archived_at);

        $this->actingAs($admin)
            ->putJson("/admin/client-portal/api/clients/{$company->id}", [
                'name' => $company->name,
                'status' => 'active',
            ])
            ->assertOk();

        $this->assertNull($company->fresh()->archived_at);
    }

    private function fixture(string $prefix = 'ABC'): array
    {
        $admin = User::query()->create([
            'name' => $prefix.' Admin',
            'email' => strtolower($prefix).uniqid().'@studio.test',
            'password' => bcrypt('secret-password'),
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        $company = Company::query()->create([
            'name' => $prefix.' s.r.o.',
            'status' => 'active',
        ]);

        $contact = ClientContact::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Lenka',
            'last_name' => 'Kontakt',
            'email' => strtolower($prefix).uniqid().'@client.test',
            'active' => true,
            'can_access_portal' => true,
        ]);

        return [$admin, $company, $contact];
    }
}
