<?php

namespace Tests\Feature\ProjectBilling;

use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectBillingCustomer;
use App\Models\ProjectInvoice;
use App\Models\ServiceProduct;
use App\Notifications\ClientAttentionRequiredNotification;
use App\Services\ClientAttentionService;
use App\Services\ProjectBilling\StripeProjectBillingGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Stripe\StripeObject;
use Tests\TestCase;

class ClientInvoicePortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['billing.invoice.pdf_disk' => 'local']);
    }

    public function test_client_sees_only_their_own_non_draft_invoices(): void
    {
        [$contact, $company, $project] = $this->fixture();
        [, $otherCompany, $otherProject] = $this->fixture('Other');

        $open = $this->invoice($project, $company, '2026001', ProjectInvoice::STATUS_OPEN);
        $this->invoice($project, $company, '2026002', ProjectInvoice::STATUS_DRAFT);
        $foreign = $this->invoice($otherProject, $otherCompany, '2026003', ProjectInvoice::STATUS_OPEN);

        $response = $this->actingAs($contact, 'client')
            ->get(route('client.projects.show', $project))
            ->assertOk();

        $payload = $this->portalPayload($response->getContent());
        $numbers = array_column($payload['project']['invoices'], 'number');

        $this->assertContains($open->invoice_number, $numbers);
        // Drafts are internal, and another client's invoice is never exposed.
        $this->assertNotContains('2026002', $numbers);
        $this->assertNotContains($foreign->invoice_number, $numbers);
    }

    public function test_unpaid_invoice_redirects_to_the_stripe_hosted_payment_page(): void
    {
        [$contact, $company, $project] = $this->fixture();

        $invoice = $this->invoice($project, $company, '2026010', ProjectInvoice::STATUS_OPEN);
        $invoice->update(['hosted_invoice_url' => 'https://invoice.stripe.test/pay/in_1']);

        $this->actingAs($contact, 'client')
            ->get(route('client.invoices.pay', [$project, $invoice]))
            ->assertRedirect('https://invoice.stripe.test/pay/in_1');
    }

    public function test_missing_hosted_url_is_refreshed_from_stripe(): void
    {
        [$contact, $company, $project] = $this->fixture();

        $invoice = $this->invoice($project, $company, '2026011', ProjectInvoice::STATUS_OPEN);
        $invoice->update(['stripe_invoice_id' => 'in_refresh', 'hosted_invoice_url' => null]);

        $this->mock(StripeProjectBillingGateway::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveInvoice')->once()->with('in_refresh')
                ->andReturn(StripeObject::constructFrom([
                    'id' => 'in_refresh',
                    'hosted_invoice_url' => 'https://invoice.stripe.test/pay/in_refresh',
                ]));
        });

        $this->actingAs($contact, 'client')
            ->get(route('client.invoices.pay', [$project, $invoice]))
            ->assertRedirect('https://invoice.stripe.test/pay/in_refresh');

        $this->assertSame(
            'https://invoice.stripe.test/pay/in_refresh',
            $invoice->fresh()->hosted_invoice_url
        );
    }

    public function test_paid_invoice_is_not_payable_again(): void
    {
        [$contact, $company, $project] = $this->fixture();

        $invoice = $this->invoice($project, $company, '2026012', ProjectInvoice::STATUS_PAID);
        $invoice->update(['hosted_invoice_url' => 'https://invoice.stripe.test/pay/in_paid']);

        $this->actingAs($contact, 'client')
            ->get(route('client.invoices.pay', [$project, $invoice]))
            ->assertRedirect(route('client.projects.show', $project));
    }

    public function test_client_cannot_pay_or_download_another_companys_invoice(): void
    {
        [$contact] = $this->fixture();
        [, $otherCompany, $otherProject] = $this->fixture('Other');

        $foreign = $this->invoice($otherProject, $otherCompany, '2026013', ProjectInvoice::STATUS_OPEN);

        $this->actingAs($contact, 'client')
            ->get(route('client.invoices.pay', [$otherProject, $foreign]))
            ->assertNotFound();

        $this->actingAs($contact, 'client')
            ->get(route('client.invoices.pdf', [$otherProject, $foreign]))
            ->assertNotFound();
    }

    public function test_client_can_download_the_studiokristian_pdf(): void
    {
        [$contact, $company, $project] = $this->fixture();

        $invoice = $this->invoice($project, $company, '2026014', ProjectInvoice::STATUS_OPEN);

        $this->actingAs($contact, 'client')
            ->get(route('client.invoices.pdf', [$project, $invoice]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_guests_cannot_reach_the_invoice_portal(): void
    {
        [, , $project] = $this->fixture();

        $this->get(route('client.projects.show', $project))->assertRedirect();
    }

    public function test_attention_reminders_are_deduplicated_and_never_attach_invoices(): void
    {
        Notification::fake();
        [$contact, $company, $project] = $this->fixture();
        $company->update(['billing_contact_id' => $contact->id]);
        $this->invoice($project, $company, '2026015', ProjectInvoice::STATUS_OPEN);

        $attention = app(ClientAttentionService::class);

        $this->assertSame(1, $attention->notifyCompany($company->fresh()));
        $this->assertSame(0, $attention->notifyCompany($company->fresh()));

        Notification::assertSentToTimes($contact, ClientAttentionRequiredNotification::class, 1);
        Notification::assertSentTo(
            $contact,
            ClientAttentionRequiredNotification::class,
            function (ClientAttentionRequiredNotification $notification) use ($contact): bool {
                $mail = $notification->toMail($contact);

                return $mail->subject === 'Your attention is required'
                    && $mail->view === 'emails.client-attention-required'
                    && $mail->viewData['invoiceCount'] === 1
                    && $mail->viewData['signatureCount'] === 0
                    && $mail->viewData['actionUrl'] === route('client.dashboard')
                    && $mail->attachments === []
                    && $mail->rawAttachments === [];
            }
        );

        $this->invoice($project, $company, '2026017', ProjectInvoice::STATUS_OPEN);
        $this->assertSame(1, $attention->notifyCompany($company->fresh()));
        Notification::assertSentToTimes($contact, ClientAttentionRequiredNotification::class, 2);

        $this->travel(3)->days();
        $this->assertSame(1, $attention->notifyCompany($company->fresh()));
        Notification::assertSentToTimes($contact, ClientAttentionRequiredNotification::class, 3);
    }

    public function test_dashboard_action_count_includes_unpaid_invoices(): void
    {
        [$contact, $company, $project] = $this->fixture();
        $this->invoice($project, $company, '2026016', ProjectInvoice::STATUS_OPEN);

        $response = $this->actingAs($contact, 'client')->get('/client')->assertOk();
        $payload = $this->portalPayload($response->getContent());

        $this->assertSame(1, $payload['projects'][0]['unpaid_invoices_count']);
        $this->assertSame(1, $payload['projects'][0]['action_count']);
    }

    public function test_client_sees_archived_assigned_projects_and_their_invoices_inside_the_project(): void
    {
        [$contact, $company, $project] = $this->fixture();
        $project->update(['archived_at' => now(), 'portal_status' => 'completed']);
        $invoice = $this->invoice($project, $company, '2026018', ProjectInvoice::STATUS_OPEN);

        $dashboard = $this->actingAs($contact, 'client')->get('/client')->assertOk();
        $dashboardPayload = $this->portalPayload($dashboard->getContent());

        $this->assertSame($project->id, $dashboardPayload['projects'][0]['id']);

        $projectResponse = $this->get(route('client.projects.show', $project))->assertOk();
        $projectPayload = $this->portalPayload($projectResponse->getContent());

        $this->assertSame($invoice->id, $projectPayload['project']['invoices'][0]['id']);
        $this->assertSame('2026018', $projectPayload['project']['invoices'][0]['number']);
    }

    public function test_client_can_access_invoices_for_every_project_in_their_company(): void
    {
        [$contact, $company] = $this->fixture();
        [, , $unassignedProject] = $this->fixture('Unassigned');
        $unassignedProject->update(['company_id' => $company->id]);
        $invoice = $this->invoice($unassignedProject, $company, '2026019', ProjectInvoice::STATUS_OPEN);

        $assignedProject = $contact->projects()->firstOrFail();
        $response = $this->actingAs($contact, 'client')
            ->get(route('client.projects.show', $assignedProject))
            ->assertOk();
        $payload = $this->portalPayload($response->getContent());

        $dashboard = $this->get(route('client.dashboard'))->assertOk();
        $dashboardPayload = $this->portalPayload($dashboard->getContent());
        $this->assertContains($unassignedProject->id, array_column($dashboardPayload['projects'], 'id'));

        $projectResponse = $this->get(route('client.projects.show', $unassignedProject))->assertOk();
        $projectPayload = $this->portalPayload($projectResponse->getContent());
        $this->assertContains('2026019', array_column($projectPayload['project']['invoices'], 'number'));
        $this->get(route('client.invoices.pdf', [$unassignedProject, $invoice]))->assertOk();
    }

    public function test_changing_the_billing_contact_syncs_the_existing_stripe_customer(): void
    {
        [, $company] = $this->fixture();

        ProjectBillingCustomer::query()->create([
            'company_id' => $company->id,
            'stripe_customer_id' => 'cus_existing',
        ]);

        $newContact = $company->contacts()->create([
            'first_name' => 'Jana',
            'last_name' => 'Nová',
            'email' => 'new@abc.test',
            'phone' => '+421911111111',
            'active' => true,
        ]);

        $admin = \App\Models\User::query()->create([
            'name' => 'Admin',
            'email' => 'admin'.uniqid().'@studio.test',
            'password' => bcrypt('secret-password'),
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        $this->mock(StripeProjectBillingGateway::class, function (MockInterface $mock) use ($company): void {
            $mock->shouldReceive('resolveCustomer')->once()
                ->withArgs(fn (Company $c) => $c->is($company)
                    && $c->billingContact?->email === 'new@abc.test')
                ->andReturn('cus_existing');
        });

        $this->actingAs($admin)
            ->putJson("/admin/client-portal/api/clients/{$company->id}", [
                'name' => $company->name,
                'status' => 'active',
                'billing_contact_id' => $newContact->id,
            ])
            ->assertOk();

        $this->assertSame($newContact->id, $company->fresh()->billing_contact_id);
    }

    public function test_a_contact_from_another_client_cannot_be_selected_as_billing_contact(): void
    {
        [, $company] = $this->fixture();
        [, $otherCompany] = $this->fixture('Other');

        $foreignContact = $otherCompany->contacts()->create([
            'first_name' => 'Foreign',
            'last_name' => 'Contact',
            'email' => 'foreign@other.test',
            'active' => true,
        ]);

        $admin = \App\Models\User::query()->create([
            'name' => 'Admin',
            'email' => 'admin'.uniqid().'@studio.test',
            'password' => bcrypt('secret-password'),
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->putJson("/admin/client-portal/api/clients/{$company->id}", [
                'name' => $company->name,
                'status' => 'active',
                'billing_contact_id' => $foreignContact->id,
            ])
            ->assertStatus(422);

        $this->assertNull($company->fresh()->billing_contact_id);
    }

    private function portalPayload(string $html): array
    {
        preg_match(
            '/<script id="client-backoffice-data" type="application\/json">(.*?)<\/script>/s',
            $html,
            $matches
        );

        return json_decode(html_entity_decode($matches[1] ?? '{}'), true) ?: [];
    }

    private function invoice(Project $project, Company $company, string $number, string $status): ProjectInvoice
    {
        return ProjectInvoice::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'invoice_number' => $number,
            'status' => $status,
            'payment_status' => $status === ProjectInvoice::STATUS_PAID
                ? ProjectInvoice::PAYMENT_PAID
                : ProjectInvoice::PAYMENT_UNPAID,
            'total' => 120000,
            'amount_due' => $status === ProjectInvoice::STATUS_PAID ? 0 : 120000,
            'currency' => 'EUR',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
        ]);
    }

    private function fixture(string $prefix = 'ABC'): array
    {
        $company = Company::query()->create([
            'name' => $prefix.' s.r.o.',
            'status' => 'active',
            'address' => 'Hlavná 1, Bratislava',
        ]);

        $serviceProduct = ServiceProduct::query()->create([
            'name' => $prefix.' Web',
            'slug' => uniqid('web-'),
            'active' => true,
        ]);

        $project = Project::query()->create([
            'company_id' => $company->id,
            'service_product_id' => $serviceProduct->id,
            'name' => $prefix.' Website',
            'url' => uniqid('website-'),
            'summary' => '',
            'portal_status' => 'active',
            'is_published' => false,
        ]);

        $contact = ClientContact::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Lenka',
            'last_name' => 'Kontakt',
            'email' => strtolower($prefix).uniqid().'@client.test',
            'active' => true,
            'can_access_portal' => true,
        ]);

        $contact->projects()->attach($project);

        return [$contact, $company, $project];
    }
}
