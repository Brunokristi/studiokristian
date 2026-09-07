<?php

namespace Tests\Feature\Webhooks;

use App\Models\Company;
use App\Models\Project;
use App\Models\SaasPlan;
use App\Models\SaasPlanPrice;
use App\Models\SaasPayment;
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

        $subscription = SaasSubscription::query()
            ->where('stripe_subscription_id', 'sub_test')
            ->firstOrFail();
        $this->assertEquals(1_700_000_000, $subscription->current_period_start->timestamp);
        $this->assertEquals(1_702_592_000, $subscription->current_period_end->timestamp);
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
                'number' => 'INV-1001',
                'created' => 1_700_000_000,
                'period_start' => 1_700_000_000,
                'period_end' => 1_702_592_000,
                'hosted_invoice_url' => 'https://invoice.stripe.test/in_1001',
                'invoice_pdf' => 'https://invoice.stripe.test/in_1001.pdf',
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
            'invoice_number' => 'INV-1001',
            'hosted_invoice_url' => 'https://invoice.stripe.test/in_1001',
        ]);
        $this->assertDatabaseHas('saas_payments', [
            'saas_invoice_id' => SaasPayment::query()->firstOrFail()->saas_invoice_id,
            'company_id' => $company->id,
            'amount' => 4900,
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

    public function test_subscription_schedule_phase_activation_synchronizes_local_plan_and_clears_schedule(): void
    {
        [$company, $price] = $this->saasPriceFixture();

        $starterPrice = SaasPlanPrice::query()->create([
            'saas_plan_id' => SaasPlan::query()->create([
                'project_id' => $price->plan->project_id,
                'name' => 'Starter',
                'slug' => 'starter',
                'active' => true,
            ])->id,
            'amount' => 1900,
            'currency' => 'EUR',
            'interval' => 'monthly',
            'active' => true,
            'stripe_price_id' => 'price_starter',
        ]);

        $subscription = SaasSubscription::query()->create([
            'project_id' => $price->plan->project_id,
            'company_id' => $company->id,
            'saas_plan_id' => $price->saas_plan_id,
            'saas_plan_price_id' => $price->id,
            'scheduled_saas_plan_id' => $starterPrice->saas_plan_id,
            'scheduled_saas_plan_price_id' => $starterPrice->id,
            'stripe_schedule_id' => 'sub_sched_123',
            'status' => SaasSubscription::STATUS_ACTIVE,
            'current_period_start' => now()->subDays(30),
            'current_period_end' => now()->subMinute(),
            'stripe_customer_id' => 'cus_schedule',
            'stripe_subscription_id' => 'sub_schedule',
        ]);

        // Once the scheduled phase activates, Stripe emits a plain customer.subscription.updated
        // event on the underlying subscription reflecting the new (Starter) price.
        $payload = $this->eventPayload(
            'evt_schedule_phase_activated',
            'customer.subscription.updated',
            [
                'id' => 'sub_schedule',
                'object' => 'subscription',
                'customer' => 'cus_schedule',
                'status' => 'active',
                'current_period_start' => now()->timestamp,
                'current_period_end' => now()->addMonth()->timestamp,
                'items' => [
                    'object' => 'list',
                    'data' => [
                        [
                            'id' => 'si_starter',
                            'object' => 'subscription_item',
                            'price' => [
                                'id' => 'price_starter',
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

        $subscription->refresh();

        $this->assertEquals($starterPrice->saas_plan_id, $subscription->saas_plan_id);
        $this->assertEquals($starterPrice->id, $subscription->saas_plan_price_id);
        $this->assertNull($subscription->scheduled_saas_plan_id);
        $this->assertNull($subscription->scheduled_saas_plan_price_id);
        $this->assertNull($subscription->stripe_schedule_id);
        $this->assertEquals(SaasSubscription::STATUS_ACTIVE, $subscription->status);
    }

    public function test_duplicate_subscription_updated_event_does_not_reapply_or_duplicate(): void
    {
        [$company, $price] = $this->saasPriceFixture();

        $subscription = SaasSubscription::query()->create([
            'project_id' => $price->plan->project_id,
            'company_id' => $company->id,
            'saas_plan_id' => $price->saas_plan_id,
            'saas_plan_price_id' => $price->id,
            'status' => SaasSubscription::STATUS_ACTIVE,
            'stripe_customer_id' => 'cus_dup',
            'stripe_subscription_id' => 'sub_dup',
        ]);

        $payload = $this->eventPayload(
            'evt_subscription_updated_dup',
            'customer.subscription.updated',
            [
                'id' => 'sub_dup',
                'object' => 'subscription',
                'customer' => 'cus_dup',
                'status' => 'active',
                'current_period_start' => now()->timestamp,
                'current_period_end' => now()->addMonth()->timestamp,
                'items' => [
                    'object' => 'list',
                    'data' => [
                        [
                            'id' => 'si_dup',
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

        $headers = ['Stripe-Signature' => $this->signatureHeader($payload)];

        $this->postJson('/api/webhooks/stripe', $payload, $headers)->assertOk();
        $this
            ->postJson('/api/webhooks/stripe', $payload, $headers)
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertDatabaseCount('saas_subscriptions', 1);
        $this->assertEquals(1, SaasSubscription::query()->where('stripe_subscription_id', 'sub_dup')->count());
    }

    public function test_subscription_deleted_synchronizes_final_cancellation_state(): void
    {
        [$company, $price] = $this->saasPriceFixture();

        $subscription = SaasSubscription::query()->create([
            'project_id' => $price->plan->project_id,
            'company_id' => $company->id,
            'saas_plan_id' => $price->saas_plan_id,
            'saas_plan_price_id' => $price->id,
            'status' => SaasSubscription::STATUS_ACTIVE,
            'cancel_at_period_end' => true,
            'current_period_end' => now()->subMinute(),
            'stripe_customer_id' => 'cus_end',
            'stripe_subscription_id' => 'sub_end',
        ]);

        $payload = $this->eventPayload(
            'evt_subscription_deleted',
            'customer.subscription.deleted',
            [
                'id' => 'sub_end',
                'object' => 'subscription',
                'customer' => 'cus_end',
                'status' => 'canceled',
                'canceled_at' => now()->timestamp,
                'ended_at' => now()->timestamp,
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

        $subscription->refresh();

        $this->assertEquals(SaasSubscription::STATUS_CANCELED, $subscription->status);
        $this->assertFalse($subscription->cancel_at_period_end);
        $this->assertNotNull($subscription->ended_at);
    }

    public function test_failed_payment_keeps_original_failure_time_and_successful_retry_recovers_subscription(): void
    {
        [$company, $price] = $this->saasPriceFixture();
        $subscription = SaasSubscription::query()->create([
            'project_id' => $price->plan->project_id,
            'company_id' => $company->id,
            'saas_plan_id' => $price->saas_plan_id,
            'saas_plan_price_id' => $price->id,
            'status' => SaasSubscription::STATUS_ACTIVE,
            'cancel_at_period_end' => true,
            'stripe_customer_id' => 'cus_retry',
            'stripe_subscription_id' => 'sub_retry',
        ]);

        \Illuminate\Support\Carbon::setTestNow('2026-09-10 14:00:00');
        $failed = $this->invoiceEventPayload('evt_retry_failed', 'invoice.payment_failed', 'in_retry', 'sub_retry', 'cus_retry', 'open', 0);
        $this->postJson('/api/webhooks/stripe', $failed, ['Stripe-Signature' => $this->signatureHeader($failed)])->assertOk();
        $this->assertEquals('2026-09-10 14:00:00', $subscription->fresh()->payment_failed_at->format('Y-m-d H:i:s'));

        \Illuminate\Support\Carbon::setTestNow('2026-09-12 14:00:00');
        $failedRetry = $this->invoiceEventPayload('evt_retry_failed_again', 'invoice.payment_failed', 'in_retry', 'sub_retry', 'cus_retry', 'open', 0);
        $this->postJson('/api/webhooks/stripe', $failedRetry, ['Stripe-Signature' => $this->signatureHeader($failedRetry)])->assertOk();

        $subscription->refresh();
        $this->assertEquals(SaasSubscription::STATUS_PAST_DUE, $subscription->status);
        $this->assertTrue($subscription->cancel_at_period_end);
        $this->assertEquals('2026-09-10 14:00:00', $subscription->payment_failed_at->format('Y-m-d H:i:s'));

        $paid = $this->invoiceEventPayload('evt_retry_paid', 'invoice.paid', 'in_retry', 'sub_retry', 'cus_retry', 'paid', 4900);
        $this->postJson('/api/webhooks/stripe', $paid, ['Stripe-Signature' => $this->signatureHeader($paid)])->assertOk();

        $subscription->refresh();
        $this->assertEquals(SaasSubscription::STATUS_ACTIVE, $subscription->status);
        $this->assertNull($subscription->payment_failed_at);
        $this->assertTrue($subscription->cancel_at_period_end);
        $this->assertDatabaseCount('saas_invoices', 1);
        $this->assertDatabaseCount('saas_payments', 1);

        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_subscription_event_keeps_genuinely_absent_billing_periods_null(): void
    {
        [$company, $price] = $this->saasPriceFixture();
        $payload = $this->eventPayload(
            'evt_subscription_without_period',
            'customer.subscription.created',
            [
                'id' => 'sub_without_period',
                'object' => 'subscription',
                'customer' => 'cus_without_period',
                'status' => 'active',
                'metadata' => ['company_id' => (string) $company->id],
                'items' => [
                    'object' => 'list',
                    'data' => [[
                        'id' => 'si_without_period',
                        'object' => 'subscription_item',
                        'price' => ['id' => $price->stripe_price_id, 'object' => 'price'],
                    ]],
                ],
            ]
        );

        $this->postJson('/api/webhooks/stripe', $payload, [
            'Stripe-Signature' => $this->signatureHeader($payload),
        ])->assertOk();

        $subscription = SaasSubscription::query()
            ->where('stripe_subscription_id', 'sub_without_period')
            ->firstOrFail();
        $this->assertNull($subscription->current_period_start);
        $this->assertNull($subscription->current_period_end);
    }

    private function invoiceEventPayload(
        string $eventId,
        string $eventType,
        string $invoiceId,
        string $subscriptionId,
        string $customerId,
        string $status,
        int $amountPaid
    ): array {
        $payload = $this->eventPayload($eventId, $eventType, [
            'id' => $invoiceId,
            'object' => 'invoice',
            'customer' => $customerId,
            'subscription' => $subscriptionId,
            'amount_due' => 4900,
            'amount_paid' => $amountPaid,
            'currency' => 'eur',
            'status' => $status,
        ]);

        $payload['created'] = now()->timestamp;

        return $payload;
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