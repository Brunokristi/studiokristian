<?php

namespace Tests\Feature\Webhooks;

use App\Models\Company;
use App\Models\Project;
use App\Models\SaasPlan;
use App\Models\SaasPlanPrice;
use App\Models\SaasSubscription;
use App\Models\ServiceProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.stripe.webhook_secret' => $this->webhookSecret,
        ]);
    }

    public function test_valid_unsupported_event_is_acknowledged_and_stored(): void
    {
        $payload = $this->eventPayload(
            'evt_product_created',
            'product.created',
            [
                'id' => 'prod_test',
                'object' => 'product',
            ]
        );

        $this
            ->postJson(
                '/api/webhooks/stripe',
                $payload,
                [
                    'Stripe-Signature' => $this->signatureHeader($payload),
                ]
            )
            ->assertOk()
            ->assertJsonPath('received', true);

        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_product_created',
            'type' => 'product.created',
        ]);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $payload = $this->eventPayload(
            'evt_invalid_signature',
            'product.created',
            [
                'id' => 'prod_test',
                'object' => 'product',
            ]
        );

        $this
            ->postJson(
                '/api/webhooks/stripe',
                $payload,
                [
                    'Stripe-Signature' => 't='.time().',v1=invalid',
                ]
            )
            ->assertBadRequest();

        $this->assertDatabaseMissing('stripe_webhook_events', [
            'stripe_event_id' => 'evt_invalid_signature',
        ]);
    }

    public function test_duplicate_processed_event_is_acknowledged_without_reprocessing(): void
    {
        $payload = $this->eventPayload(
            'evt_duplicate',
            'product.created',
            [
                'id' => 'prod_test',
                'object' => 'product',
            ]
        );

        $headers = [
            'Stripe-Signature' => $this->signatureHeader($payload),
        ];

        $this->postJson('/api/webhooks/stripe', $payload, $headers)->assertOk();

        $this
            ->postJson('/api/webhooks/stripe', $payload, $headers)
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertDatabaseCount('stripe_webhook_events', 1);
    }

    public function test_subscription_created_event_synchronizes_local_subscription(): void
    {
        [$company, $price] = $this->saasPriceFixture();

        $payload = $this->eventPayload(
            'evt_subscription_created',
            'customer.subscription.created',
            [
                'id' => 'sub_test',
                'object' => 'subscription',
                'customer' => 'cus_test',
                'status' => 'active',
                'current_period_start' => 1_700_000_000,
                'current_period_end' => 1_702_592_000,
                'metadata' => [
                    'company_id' => (string) $company->id,
                ],
                'items' => [
                    'object' => 'list',
                    'data' => [
                        [
                            'id' => 'si_test',
                            'object' => 'subscription_item',
                            'price' => [
                                'id' => 'price_test',
                                'object' => 'price',
                            ],
                        ],
                    ],
                ],
            ]
        );

        $this
            ->postJson(
                '/api/webhooks/stripe',
                $payload,
                [
                    'Stripe-Signature' => $this->signatureHeader($payload),
                ]
            )
            ->assertOk();

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'stripe_customer_id' => 'cus_test',
        ]);

        $this->assertDatabaseHas('saas_subscriptions', [
            'company_id' => $company->id,
            'saas_plan_id' => $price->saas_plan_id,
            'saas_plan_price_id' => $price->id,
            'status' => SaasSubscription::STATUS_ACTIVE,
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => 'sub_test',
        ]);
    }

    public function test_checkout_session_completed_creates_subscription_placeholder_from_metadata(): void
    {
        [$company, $price] = $this->saasPriceFixture();

        $payload = $this->eventPayload(
            'evt_checkout_completed',
            'checkout.session.completed',
            [
                'id' => 'cs_test',
                'object' => 'checkout.session',
                'customer' => 'cus_test',
                'subscription' => 'sub_test',
                'metadata' => [
                    'company_id' => (string) $company->id,
                    'plan_id' => (string) $price->saas_plan_id,
                    'plan_price_id' => (string) $price->id,
                ],
            ]
        );

        $this
            ->postJson(
                '/api/webhooks/stripe',
                $payload,
                [
                    'Stripe-Signature' => $this->signatureHeader($payload),
                ]
            )
            ->assertOk();

        $this->assertDatabaseHas('saas_subscriptions', [
            'company_id' => $company->id,
            'saas_plan_id' => $price->saas_plan_id,
            'saas_plan_price_id' => $price->id,
            'status' => SaasSubscription::STATUS_INCOMPLETE,
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => 'sub_test',
        ]);
    }

    public function test_invoice_paid_is_stored_idempotently(): void
    {
        [$company, $price] = $this->saasPriceFixture();

        $subscription = SaasSubscription::query()->create([
            'project_id' => $price->plan->project_id,
            'company_id' => $company->id,
            'saas_plan_id' => $price->saas_plan_id,
            'saas_plan_price_id' => $price->id,
            'status' => SaasSubscription::STATUS_PAST_DUE,
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => 'sub_test',
        ]);

        $payload = $this->eventPayload(
            'evt_invoice_paid',
            'invoice.paid',
            [
                'id' => 'in_test',
                'object' => 'invoice',
                'customer' => 'cus_test',
                'subscription' => 'sub_test',
                'amount_due' => 4900,
                'amount_paid' => 4900,
                'currency' => 'eur',
                'status' => 'paid',
                'status_transitions' => [
                    'paid_at' => 1_700_000_000,
                ],
            ]
        );

        $headers = [
            'Stripe-Signature' => $this->signatureHeader($payload),
        ];

        $this->postJson('/api/webhooks/stripe', $payload, $headers)->assertOk();
        $this->postJson('/api/webhooks/stripe', $payload, $headers)->assertOk();

        $this->assertDatabaseCount('saas_invoices', 1);
        $this->assertDatabaseHas('saas_invoices', [
            'saas_subscription_id' => $subscription->id,
            'stripe_invoice_id' => 'in_test',
            'amount_paid' => 4900,
            'currency' => 'EUR',
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('saas_subscriptions', [
            'id' => $subscription->id,
            'status' => SaasSubscription::STATUS_ACTIVE,
        ]);
    }

    public function test_invoice_payment_failed_marks_subscription_past_due_without_paid_revenue(): void
    {
        [$company, $price] = $this->saasPriceFixture();

        $subscription = SaasSubscription::query()->create([
            'project_id' => $price->plan->project_id,
            'company_id' => $company->id,
            'saas_plan_id' => $price->saas_plan_id,
            'saas_plan_price_id' => $price->id,
            'status' => SaasSubscription::STATUS_ACTIVE,
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => 'sub_test',
        ]);

        $payload = $this->eventPayload(
            'evt_invoice_failed',
            'invoice.payment_failed',
            [
                'id' => 'in_failed',
                'object' => 'invoice',
                'customer' => 'cus_test',
                'subscription' => 'sub_test',
                'amount_due' => 4900,
                'amount_paid' => 0,
                'currency' => 'eur',
                'status' => 'open',
            ]
        );

        $this
            ->postJson(
                '/api/webhooks/stripe',
                $payload,
                [
                    'Stripe-Signature' => $this->signatureHeader($payload),
                ]
            )
            ->assertOk();

        $this->assertDatabaseHas('saas_invoices', [
            'saas_subscription_id' => $subscription->id,
            'stripe_invoice_id' => 'in_failed',
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        $this->assertDatabaseHas('saas_subscriptions', [
            'id' => $subscription->id,
            'status' => SaasSubscription::STATUS_PAST_DUE,
        ]);
    }

    private function saasPriceFixture(): array
    {
        $company = Company::query()->create([
            'name' => 'Acme',
        ]);

        $serviceProduct = ServiceProduct::query()->create([
            'name' => 'Build',
            'slug' => uniqid('build-'),
            'active' => true,
        ]);

        $project = Project::query()->create([
            'company_id' => $company->id,
            'service_product_id' => $serviceProduct->id,
            'name' => 'ADOCare',
            'url' => uniqid('adocare-'),
            'summary' => '',
            'portal_status' => 'draft',
            'is_published' => false,
            'is_saas' => true,
        ]);

        $plan = SaasPlan::query()->create([
            'project_id' => $project->id,
            'name' => 'Professional',
            'slug' => 'professional',
            'active' => true,
        ]);

        $price = SaasPlanPrice::query()->create([
            'saas_plan_id' => $plan->id,
            'amount' => 4900,
            'currency' => 'EUR',
            'interval' => 'monthly',
            'active' => true,
            'stripe_price_id' => 'price_test',
        ]);

        return [$company, $price->load('plan')];
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
            'request' => [
                'id' => null,
                'idempotency_key' => null,
            ],
            'type' => $type,
            'data' => [
                'object' => $object,
            ],
        ];
    }

    private function signatureHeader(array $payload): string
    {
        $timestamp = time();
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $timestamp.'.'.$json, $this->webhookSecret);

        return "t={$timestamp},v1={$signature}";
    }
}