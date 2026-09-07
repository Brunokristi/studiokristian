<?php

namespace Tests\Feature\ProjectBilling;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectInvoice;
use App\Models\ProjectSubscription;
use App\Models\SaasInvoice;
use App\Models\ServiceProduct;
use App\Notifications\ProjectInvoiceIssuedNotification;
use App\Services\ProjectBilling\StripeProjectBillingGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Stripe\StripeObject;
use Tests\TestCase;

class ProjectBillingWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'whsec_project_billing';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Notification::fake();

        config([
            'services.stripe.webhook_secret' => $this->webhookSecret,
            'services.stripe.project_billing_webhook_secret' => $this->webhookSecret,
            'billing.invoice.pdf_disk' => 'local',
        ]);

        $this->mock(StripeProjectBillingGateway::class, function (MockInterface $mock): void {
            $mock->shouldReceive('domainMetadata')->andReturn(['billing_domain' => 'custom_project']);
            $mock->shouldReceive('updateInvoice')->andReturn(StripeObject::constructFrom(['id' => 'in_stub']));
            $mock->shouldReceive('retrieveCharge')->andReturn(StripeObject::constructFrom(['id' => 'ch_stub']));
            $mock->shouldReceive('retrieveSubscription')->andReturn(
                StripeObject::constructFrom(['id' => 'sub_project_1', 'default_payment_method' => null])
            );
            $mock->shouldReceive('updateSubscription')->andReturn(StripeObject::constructFrom(['id' => 'sub_project_1']));
        });
    }

    public function test_stripe_generated_recurring_invoice_creates_a_local_invoice_with_our_number(): void
    {
        $subscription = $this->subscription();

        $payload = $this->invoicePayload('evt_pb_created', 'invoice.created', [
            'id' => 'in_recurring_1',
            'status' => 'draft',
            'subscription' => $subscription->stripe_subscription_id,
        ]);

        $this->postJson('/api/webhooks/stripe/project-billing', $payload, [
            'Stripe-Signature' => $this->signature($payload),
        ])->assertOk();

        $invoice = ProjectInvoice::query()->firstOrFail();

        $this->assertSame(now()->format('Y').'001', $invoice->invoice_number);
        $this->assertSame($subscription->project_id, $invoice->project_id);
        $this->assertSame($subscription->id, $invoice->project_subscription_id);
        $this->assertSame('in_recurring_1', $invoice->stripe_invoice_id);

        // The custom PDF is generated for Stripe-originated recurring invoices too.
        $this->assertNotNull($invoice->pdf_path);
        $this->assertTrue(Storage::disk('local')->exists($invoice->pdf_path));
    }

    public function test_duplicate_webhook_delivery_never_duplicates_invoices_or_items(): void
    {
        $subscription = $this->subscription();

        $payload = $this->invoicePayload('evt_pb_dup', 'invoice.created', [
            'id' => 'in_recurring_dup',
            'status' => 'draft',
            'subscription' => $subscription->stripe_subscription_id,
        ]);

        $headers = ['Stripe-Signature' => $this->signature($payload)];

        $this->postJson('/api/webhooks/stripe/project-billing', $payload, $headers)->assertOk();
        $this->postJson('/api/webhooks/stripe/project-billing', $payload, $headers)
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertDatabaseCount('project_invoices', 1);
        $this->assertDatabaseCount('project_invoice_items', 1);
        $this->assertDatabaseCount('project_billing_webhook_events', 1);
    }

    public function test_payment_success_and_failure_are_synchronized_from_stripe(): void
    {
        $subscription = $this->subscription();

        $created = $this->invoicePayload('evt_pb_c2', 'invoice.created', [
            'id' => 'in_lifecycle',
            'status' => 'draft',
            'subscription' => $subscription->stripe_subscription_id,
        ]);
        $this->postJson('/api/webhooks/stripe/project-billing', $created, [
            'Stripe-Signature' => $this->signature($created),
        ])->assertOk();

        $failed = $this->invoicePayload('evt_pb_failed', 'invoice.payment_failed', [
            'id' => 'in_lifecycle',
            'status' => 'open',
            'subscription' => $subscription->stripe_subscription_id,
            'amount_paid' => 0,
        ]);
        $this->postJson('/api/webhooks/stripe/project-billing', $failed, [
            'Stripe-Signature' => $this->signature($failed),
        ])->assertOk();

        $invoice = ProjectInvoice::query()->firstOrFail();
        $this->assertSame(ProjectInvoice::PAYMENT_FAILED, $invoice->payment_status);
        $this->assertNotNull($invoice->payment_failed_at);

        $paid = $this->invoicePayload('evt_pb_paid', 'invoice.paid', [
            'id' => 'in_lifecycle',
            'status' => 'paid',
            'subscription' => $subscription->stripe_subscription_id,
            'amount_paid' => 8500,
            'amount_due' => 0,
        ]);
        $this->postJson('/api/webhooks/stripe/project-billing', $paid, [
            'Stripe-Signature' => $this->signature($paid),
        ])->assertOk();

        $invoice->refresh();
        $this->assertSame(ProjectInvoice::STATUS_PAID, $invoice->status);
        $this->assertSame(ProjectInvoice::PAYMENT_PAID, $invoice->payment_status);
        $this->assertNull($invoice->payment_failed_at);
        $this->assertNotNull($invoice->paid_at);
        $this->assertSame(0, $invoice->amount_due);

        // Still exactly one invoice through the whole failure/retry cycle.
        $this->assertDatabaseCount('project_invoices', 1);
    }

    public function test_subscription_cancellation_is_synchronized_from_stripe(): void
    {
        $subscription = $this->subscription();

        $payload = $this->eventPayload('evt_pb_sub_deleted', 'customer.subscription.deleted', [
            'id' => $subscription->stripe_subscription_id,
            'object' => 'subscription',
            'status' => 'canceled',
            'canceled_at' => now()->timestamp,
            'ended_at' => now()->timestamp,
        ]);

        $this->postJson('/api/webhooks/stripe/project-billing', $payload, [
            'Stripe-Signature' => $this->signature($payload),
        ])->assertOk();

        $subscription->refresh();
        $this->assertSame(ProjectSubscription::STATUS_CANCELED, $subscription->status);
        $this->assertNotNull($subscription->ended_at);
    }

    public function test_invoice_voiding_is_synchronized(): void
    {
        $subscription = $this->subscription();

        $created = $this->invoicePayload('evt_pb_void_c', 'invoice.created', [
            'id' => 'in_void',
            'status' => 'draft',
            'subscription' => $subscription->stripe_subscription_id,
        ]);
        $this->postJson('/api/webhooks/stripe/project-billing', $created, [
            'Stripe-Signature' => $this->signature($created),
        ])->assertOk();

        $voided = $this->invoicePayload('evt_pb_void', 'invoice.voided', [
            'id' => 'in_void',
            'status' => 'void',
            'subscription' => $subscription->stripe_subscription_id,
        ]);
        $this->postJson('/api/webhooks/stripe/project-billing', $voided, [
            'Stripe-Signature' => $this->signature($voided),
        ])->assertOk();

        $invoice = ProjectInvoice::query()->firstOrFail();
        $this->assertSame(ProjectInvoice::STATUS_VOID, $invoice->status);
        $this->assertSame(0, $invoice->amount_due);
    }

    public function test_unrelated_stripe_invoice_is_ignored_by_project_billing(): void
    {
        $payload = $this->invoicePayload('evt_pb_foreign', 'invoice.paid', [
            'id' => 'in_someone_else',
            'status' => 'paid',
            'subscription' => 'sub_not_ours',
        ]);

        $this->postJson('/api/webhooks/stripe/project-billing', $payload, [
            'Stripe-Signature' => $this->signature($payload),
        ])->assertOk();

        $this->assertDatabaseCount('project_invoices', 0);
    }

    public function test_saas_webhook_ignores_custom_project_billing_invoices(): void
    {
        $payload = $this->eventPayload('evt_saas_guard', 'invoice.paid', [
            'id' => 'in_custom_domain',
            'object' => 'invoice',
            'customer' => 'cus_custom',
            'amount_due' => 200000,
            'amount_paid' => 200000,
            'currency' => 'eur',
            'status' => 'paid',
            'metadata' => ['billing_domain' => 'custom_project'],
        ]);

        $this->postJson('/api/webhooks/stripe', $payload, [
            'Stripe-Signature' => $this->signature($payload),
        ])->assertOk();

        // Custom project billing must never leak into the SaaS billing domain.
        $this->assertSame(0, SaasInvoice::query()->count());
    }

    public function test_first_invoice_is_emailed_with_payment_link_once_finalized(): void
    {
        $subscription = $this->subscription('send_invoice');

        // Draft creation must not email the customer - there is no payment link yet.
        $created = $this->invoicePayload('evt_pb_first_created', 'invoice.created', [
            'id' => 'in_first',
            'status' => 'draft',
            'subscription' => $subscription->stripe_subscription_id,
        ]);
        $this->postJson('/api/webhooks/stripe/project-billing', $created, [
            'Stripe-Signature' => $this->signature($created),
        ])->assertOk();

        Notification::assertNothingSent();
        $this->assertNull(ProjectInvoice::query()->firstOrFail()->sent_at);

        $finalized = $this->invoicePayload('evt_pb_first_final', 'invoice.finalized', [
            'id' => 'in_first',
            'status' => 'open',
            'subscription' => $subscription->stripe_subscription_id,
            'hosted_invoice_url' => 'https://invoice.stripe.test/pay/in_first',
        ]);
        $this->postJson('/api/webhooks/stripe/project-billing', $finalized, [
            'Stripe-Signature' => $this->signature($finalized),
        ])->assertOk();

        $invoice = ProjectInvoice::query()->firstOrFail();

        $this->assertSame('https://invoice.stripe.test/pay/in_first', $invoice->hosted_invoice_url);
        $this->assertNotNull($invoice->sent_at);
        Notification::assertSentOnDemand(ProjectInvoiceIssuedNotification::class);
        $this->assertDatabaseCount('project_invoices', 1);
    }

    public function test_first_payment_saves_the_card_and_switches_to_automatic_collection(): void
    {
        $subscription = $this->subscription('send_invoice');

        // Stripe reports the card saved by the customer's hosted-invoice payment.
        $this->mock(StripeProjectBillingGateway::class, function (MockInterface $mock): void {
            $mock->shouldReceive('domainMetadata')->andReturn(['billing_domain' => 'custom_project']);
            $mock->shouldReceive('updateInvoice')->andReturn(StripeObject::constructFrom(['id' => 'in_stub']));
            $mock->shouldReceive('retrieveCharge')->andReturn(StripeObject::constructFrom(['id' => 'ch_stub']));
            $mock->shouldReceive('retrieveSubscription')->andReturn(
                StripeObject::constructFrom([
                    'id' => 'sub_project_1',
                    'default_payment_method' => 'pm_saved_card',
                ])
            );
            $mock->shouldReceive('updateSubscription')->once()
                ->with('sub_project_1', ['collection_method' => 'charge_automatically'])
                ->andReturn(StripeObject::constructFrom(['id' => 'sub_project_1']));
        });

        $paid = $this->invoicePayload('evt_pb_first_paid', 'invoice.paid', [
            'id' => 'in_first',
            'status' => 'paid',
            'subscription' => $subscription->stripe_subscription_id,
            'amount_paid' => 8500,
            'amount_due' => 0,
        ]);

        $this->postJson('/api/webhooks/stripe/project-billing', $paid, [
            'Stripe-Signature' => $this->signature($paid),
        ])->assertOk();

        $subscription->refresh();
        $this->assertSame('pm_saved_card', $subscription->stripe_default_payment_method_id);
        $this->assertSame('charge_automatically', $subscription->collection_method);
    }

    public function test_subscription_stays_invoice_first_until_a_card_is_saved(): void
    {
        $subscription = $this->subscription('send_invoice');

        $this->mock(StripeProjectBillingGateway::class, function (MockInterface $mock): void {
            $mock->shouldReceive('domainMetadata')->andReturn(['billing_domain' => 'custom_project']);
            $mock->shouldReceive('updateInvoice')->andReturn(StripeObject::constructFrom(['id' => 'in_stub']));
            $mock->shouldReceive('retrieveCharge')->andReturn(StripeObject::constructFrom(['id' => 'ch_stub']));
            $mock->shouldReceive('retrieveSubscription')->andReturn(
                StripeObject::constructFrom(['id' => 'sub_project_1', 'default_payment_method' => null])
            );
            $mock->shouldNotReceive('updateSubscription');
        });

        $paid = $this->invoicePayload('evt_pb_no_pm', 'invoice.paid', [
            'id' => 'in_bank',
            'status' => 'paid',
            'subscription' => $subscription->stripe_subscription_id,
            'amount_paid' => 8500,
            'amount_due' => 0,
        ]);

        $this->postJson('/api/webhooks/stripe/project-billing', $paid, [
            'Stripe-Signature' => $this->signature($paid),
        ])->assertOk();

        $subscription->refresh();
        $this->assertNull($subscription->stripe_default_payment_method_id);
        $this->assertSame('send_invoice', $subscription->collection_method);
    }

    public function test_shared_stripe_endpoint_routes_subscription_invoices_to_project_billing(): void
    {
        $subscription = $this->subscription('send_invoice');

        // Stripe is usually configured with a single endpoint - the default one.
        $payload = $this->invoicePayload('evt_shared_created', 'invoice.created', [
            'id' => 'in_shared',
            'status' => 'draft',
            'subscription' => $subscription->stripe_subscription_id,
        ]);

        $this->postJson('/api/webhooks/stripe', $payload, [
            'Stripe-Signature' => $this->signature($payload),
        ])->assertOk();

        $invoice = ProjectInvoice::query()->firstOrFail();

        $this->assertSame($subscription->id, $invoice->project_subscription_id);
        $this->assertSame(now()->format('Y').'001', $invoice->invoice_number);
        // It must not have been adopted by the SaaS billing domain.
        $this->assertSame(0, SaasInvoice::query()->count());
    }

    public function test_shared_stripe_endpoint_emails_the_payment_link_on_finalize(): void
    {
        $subscription = $this->subscription('send_invoice');

        foreach ([
            ['evt_shared_c', 'invoice.created', 'draft', null],
            ['evt_shared_f', 'invoice.finalized', 'open', 'https://invoice.stripe.test/pay/in_shared2'],
        ] as [$eventId, $type, $status, $url]) {
            $payload = $this->invoicePayload($eventId, $type, array_filter([
                'id' => 'in_shared2',
                'status' => $status,
                'subscription' => $subscription->stripe_subscription_id,
                'hosted_invoice_url' => $url,
            ]));

            $this->postJson('/api/webhooks/stripe', $payload, [
                'Stripe-Signature' => $this->signature($payload),
            ])->assertOk();
        }

        $invoice = ProjectInvoice::query()->firstOrFail();

        $this->assertSame('https://invoice.stripe.test/pay/in_shared2', $invoice->hosted_invoice_url);
        $this->assertNotNull($invoice->sent_at);
        Notification::assertSentOnDemand(ProjectInvoiceIssuedNotification::class);
    }

    private function subscription(string $collectionMethod = 'charge_automatically'): ProjectSubscription
    {
        $company = Company::query()->create([
            'name' => 'ABC s.r.o.',
            'registration_number' => '12345678',
            'billing_email' => 'billing@abc.test',
        ]);

        $serviceProduct = ServiceProduct::query()->create([
            'name' => 'Web',
            'slug' => uniqid('web-'),
            'active' => true,
        ]);

        $project = Project::query()->create([
            'company_id' => $company->id,
            'service_product_id' => $serviceProduct->id,
            'name' => 'New Website',
            'url' => uniqid('website-'),
            'summary' => '',
            'portal_status' => 'active',
            'is_published' => false,
        ]);

        return ProjectSubscription::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'status' => ProjectSubscription::STATUS_ACTIVE,
            'collection_method' => $collectionMethod,
            'stripe_subscription_id' => 'sub_project_1',
            'stripe_customer_id' => 'cus_project_1',
        ]);
    }

    private function invoicePayload(string $eventId, string $type, array $overrides): array
    {
        return $this->eventPayload($eventId, $type, array_merge([
            'object' => 'invoice',
            'customer' => 'cus_project_1',
            'currency' => 'eur',
            'subtotal' => 8500,
            'total' => 8500,
            'amount_due' => 8500,
            'amount_paid' => 0,
            'created' => now()->timestamp,
            'lines' => [
                'object' => 'list',
                'data' => [[
                    'id' => 'il_hosting',
                    'description' => 'Hosting',
                    'quantity' => 1,
                    'amount' => 8500,
                ]],
            ],
        ], $overrides));
    }

    private function eventPayload(string $id, string $type, array $object): array
    {
        return [
            'id' => $id,
            'object' => 'event',
            'api_version' => '2025-10-29.clover',
            'created' => time(),
            'livemode' => false,
            'pending_webhooks' => 1,
            'request' => ['id' => null, 'idempotency_key' => null],
            'type' => $type,
            'data' => ['object' => $object],
        ];
    }

    private function signature(array $payload): string
    {
        $timestamp = time();
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $timestamp.'.'.$json, $this->webhookSecret);

        return "t={$timestamp},v1={$signature}";
    }
}
