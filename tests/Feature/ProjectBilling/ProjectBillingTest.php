<?php

namespace Tests\Feature\ProjectBilling;

use App\Models\BillingProduct;
use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectBillingItem;
use App\Models\ProjectInvoice;
use App\Models\ProjectSubscription;
use App\Models\ServiceProduct;
use App\Models\User;
use App\Notifications\ClientAttentionRequiredNotification;
use App\Services\ProjectBilling\ProjectInvoiceNumberService;
use App\Services\ProjectBilling\ProjectSubscriptionService;
use App\Services\ProjectBilling\StripeProjectBillingGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Stripe\StripeObject;
use Tests\TestCase;

class ProjectBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'billing.invoice.pdf_disk' => 'local',
            'billing.supplier.name' => 'Studio Kristian s.r.o.',
            'billing.supplier.registration_number' => '55555555',
            'billing.supplier.tax_number' => '2120000000',
            'billing.supplier.iban' => 'SK1111000000000000000000',
            'billing.supplier.address_line1' => 'Štúrova 5',
            'billing.supplier.city' => 'Bratislava',
            'billing.supplier.postal_code' => '81102',
        ]);
    }

    public function test_admin_can_add_one_time_and_recurring_items_to_a_project(): void
    {
        [$admin, $project] = $this->fixture();

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/items", [
                'name' => 'Website Development',
                'unit_amount' => 200000,
                'billing_type' => 'one_time',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Website Development');

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/items", [
                'name' => 'Hosting',
                'unit_amount' => 2000,
                'billing_type' => 'recurring',
                'interval' => 'month',
            ])
            ->assertCreated()
            ->assertJsonPath('data.interval', 'month');

        $this->assertDatabaseCount('project_billing_items', 2);
    }

    public function test_creating_an_invoice_generates_number_pdf_and_links_stripe_invoice(): void
    {
        [$admin, $project, $company] = $this->fixture();

        $development = $this->billingItem($project, 'Website Development', 200000);
        $branding = $this->billingItem($project, 'Branding', 50000);

        $this->mockGateway(function (MockInterface $mock): void {
            $mock->shouldReceive('resolveCustomer')->once()->andReturn('cus_custom_1');
            $mock->shouldReceive('createInvoice')->once()
                ->andReturn(StripeObject::constructFrom(['id' => 'in_custom_1']));
            $mock->shouldReceive('createInvoiceItem')->twice()
                ->andReturn(StripeObject::constructFrom(['id' => 'ii_custom']));
            $mock->shouldReceive('finalizeInvoice')->once()
                ->andReturn(StripeObject::constructFrom([
                    'id' => 'in_custom_1',
                    'hosted_invoice_url' => 'https://invoice.stripe.test/in_custom_1',
                ]));
        });

        $response = $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/invoices", [
                'billing_item_ids' => [$development->id, $branding->id],
                'payment_method' => 'stripe_hosted',
            ]);

        $response->assertCreated();

        $invoice = ProjectInvoice::query()->firstOrFail();

        $this->assertSame(now()->format('Y').'001', $invoice->invoice_number);
        $this->assertSame('in_custom_1', $invoice->stripe_invoice_id);
        $this->assertSame('cus_custom_1', $invoice->stripe_customer_id);
        $this->assertSame(250000, $invoice->total);
        $this->assertSame(250000, $invoice->amount_due);
        $this->assertSame(ProjectInvoice::STATUS_OPEN, $invoice->status);
        $this->assertCount(2, $invoice->items);

        // The customer-facing PDF is StudioKristian's own document.
        $this->assertNotNull($invoice->pdf_path);
        $this->assertTrue(Storage::disk('local')->exists($invoice->pdf_path));

        // Supplier + customer identifiers are snapshotted for accounting.
        $this->assertSame($company->registration_number, $invoice->customer_registration_number);
        $this->assertSame($company->tax_number, $invoice->customer_tax_number);
        $this->assertNotNull($invoice->supplier_registration_number);
    }

    public function test_not_being_a_vat_payer_produces_zero_tax_but_keeps_tax_structure(): void
    {
        config(['billing.tax.vat_payer' => false]);

        [$admin, $project] = $this->fixture();
        $item = $this->billingItem($project, 'Consulting', 100000);

        $this->mockGatewayForInvoice();

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/invoices", [
                'billing_item_ids' => [$item->id],
            ])
            ->assertCreated();

        $invoice = ProjectInvoice::query()->firstOrFail();

        $this->assertSame(0, $invoice->tax_amount);
        $this->assertEquals(0.0, (float) $invoice->tax_rate);
        $this->assertSame(ProjectInvoice::TAX_MODE_NONE, $invoice->tax_mode);
        $this->assertSame(100000, $invoice->total);
    }

    public function test_invoice_numbers_are_sequential_and_shared_across_payment_methods(): void
    {
        $numbers = app(ProjectInvoiceNumberService::class);
        $year = (int) now()->format('Y');

        $this->assertSame($year.'001', $numbers->next($year));
        $this->assertSame($year.'002', $numbers->next($year));
        $this->assertSame($year.'003', $numbers->next($year));

        $this->assertDatabaseHas('project_invoice_number_sequences', [
            'year' => $year,
            'last_number' => 3,
        ]);
    }

    public function test_recurring_items_are_grouped_into_a_single_stripe_subscription(): void
    {
        [$admin, $project] = $this->fixture();

        $hosting = $this->billingItem($project, 'Hosting', 2000, 'recurring');
        $monitoring = $this->billingItem($project, 'Monitoring', 1500, 'recurring');
        $maintenance = $this->billingItem($project, 'Maintenance', 5000, 'recurring');

        $this->mockGateway(function (MockInterface $mock): void {
            $mock->shouldReceive('resolveCustomer')->once()->andReturn('cus_custom_1');
            $mock->shouldReceive('createProduct')->times(3)
                ->andReturn(StripeObject::constructFrom(['id' => 'prod_custom']));
            $mock->shouldReceive('createRecurringPrice')->times(3)
                ->andReturn(StripeObject::constructFrom(['id' => 'price_custom']));
            $mock->shouldReceive('createSubscription')->once()
                ->withArgs(function ($customerId, $items, $collectionMethod) {
                    return $collectionMethod === 'send_invoice' && count($items) === 3;
                })
                ->andReturn(StripeObject::constructFrom([
                    'id' => 'sub_custom_1',
                    'status' => 'active',
                    'current_period_end' => now()->addMonth()->timestamp,
                    'items' => ['data' => []],
                    'latest_invoice' => ['id' => 'in_first_sub', 'status' => 'draft'],
                ]));

            $mock->shouldReceive('retrieveInvoice')->once()->with('in_first_sub')
                ->andReturn(StripeObject::constructFrom(['id' => 'in_first_sub', 'status' => 'draft']));
            $mock->shouldReceive('updateInvoice')->once()
                ->with('in_first_sub', ['auto_advance' => false])
                ->andReturn(StripeObject::constructFrom(['id' => 'in_first_sub']));
            $mock->shouldReceive('finalizeInvoice')->once()->with('in_first_sub', false)
                ->andReturn(StripeObject::constructFrom(['id' => 'in_first_sub', 'status' => 'open']));
        });

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/subscription")
            ->assertCreated();

        $this->assertSame(1, ProjectSubscription::query()->count());

        $subscription = ProjectSubscription::query()->firstOrFail();
        $this->assertSame('sub_custom_1', $subscription->stripe_subscription_id);
        $this->assertSame(ProjectSubscription::STATUS_ACTIVE, $subscription->status);

        foreach ([$hosting, $monitoring, $maintenance] as $item) {
            $this->assertSame($subscription->id, $item->fresh()->project_subscription_id);
            $this->assertSame(ProjectBillingItem::STATUS_ACTIVE, $item->fresh()->status);
        }

        // 20 + 15 + 50 = 85 EUR / month on ONE subscription.
        $this->assertSame(8500, $subscription->fresh('items')->monthlyTotal());
    }

    public function test_subscription_period_is_read_from_stripe_items_when_root_fields_are_absent(): void
    {
        [, $project, $company] = $this->fixture();
        $periodStart = \Illuminate\Support\Carbon::parse('2026-09-01')->timestamp;
        $periodEnd = \Illuminate\Support\Carbon::parse('2027-09-01')->timestamp;

        $subscription = ProjectSubscription::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'status' => ProjectSubscription::STATUS_ACTIVE,
            'stripe_subscription_id' => 'sub_item_period',
        ]);

        $this->mockGateway(function (MockInterface $mock) use ($periodStart, $periodEnd): void {
            $mock->shouldReceive('retrieveSubscription')
                ->once()
                ->with('sub_item_period')
                ->andReturn(StripeObject::constructFrom([
                    'id' => 'sub_item_period',
                    'items' => ['data' => [[
                        'current_period_start' => $periodStart,
                        'current_period_end' => $periodEnd,
                    ]]],
                ]));
        });

        app(ProjectSubscriptionService::class)->refreshPeriod($subscription);

        $subscription->refresh();
        $this->assertSame('2026-09-01', $subscription->current_period_start->toDateString());
        $this->assertSame('2027-09-01', $subscription->current_period_end->toDateString());
    }

    public function test_new_recurring_item_is_added_to_the_existing_subscription(): void
    {
        [$admin, $project, $company] = $this->fixture();

        $subscription = ProjectSubscription::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'status' => ProjectSubscription::STATUS_ACTIVE,
            'stripe_subscription_id' => 'sub_existing_services',
        ]);

        $item = $this->billingItem($project, 'Monitoring', 1500, 'recurring');

        $this->mockGateway(function (MockInterface $mock): void {
            $mock->shouldNotReceive('resolveCustomer');
            $mock->shouldReceive('retrieveSubscription')->once()
                ->andReturn(StripeObject::constructFrom([
                    'id' => 'sub_existing_services',
                    'items' => ['data' => []],
                ]));
            $mock->shouldReceive('createProduct')->once()
                ->andReturn(StripeObject::constructFrom(['id' => 'prod_monitoring']));
            $mock->shouldReceive('createRecurringPrice')->once()
                ->andReturn(StripeObject::constructFrom(['id' => 'price_monitoring']));
            $mock->shouldReceive('updateSubscriptionItems')->once()
                ->with('sub_existing_services', [
                    ['price' => 'price_monitoring', 'quantity' => 1],
                ])
                ->andReturn(StripeObject::constructFrom([
                    'id' => 'sub_existing_services',
                    'items' => ['data' => []],
                ]));
            $mock->shouldNotReceive('createSubscription');
        });

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/subscription")
            ->assertCreated();

        $this->assertSame($subscription->id, $item->fresh()->project_subscription_id);
        $this->assertSame(ProjectBillingItem::STATUS_ACTIVE, $item->fresh()->status);
        $this->assertSame(1, ProjectSubscription::query()->count());
    }

    public function test_future_only_recurring_items_create_a_schedule_until_their_start(): void
    {
        [$admin, $project] = $this->fixture();
        $item = $this->billingItem($project, 'Hosting', 2000, 'recurring');
        $item->update(['starts_at' => '2026-10-01']);

        $this->mockGateway(function (MockInterface $mock): void {
            $mock->shouldReceive('resolveCustomer')->once()->andReturn('cus_future');
            $mock->shouldReceive('isFutureForCustomer')->once()->andReturn(true);
            $mock->shouldReceive('createProduct')->once()
                ->andReturn(StripeObject::constructFrom(['id' => 'prod_future']));
            $mock->shouldReceive('createRecurringPrice')->once()
                ->andReturn(StripeObject::constructFrom(['id' => 'price_future']));
            $mock->shouldReceive('createFutureSchedule')->once()
                ->withArgs(function ($customer, $items, $startDate, $method, $metadata, $endDate) {
                    return $customer === 'cus_future'
                        && count($items) === 1
                        && $method === 'send_invoice'
                        && $startDate === \Illuminate\Support\Carbon::parse('2026-10-01')->timestamp
                        && $endDate === null;
                })
                ->andReturn(StripeObject::constructFrom([
                    'id' => 'sch_future',
                ]));
        });

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/subscription")
            ->assertCreated();

        $this->assertStringStartsWith('2026-10-01', (string) ProjectSubscription::query()->firstOrFail()->starts_at);
        $this->assertSame('sch_future', ProjectSubscription::query()->firstOrFail()->stripe_schedule_id);
        $this->assertSame(ProjectSubscription::STATUS_DRAFT, ProjectSubscription::query()->firstOrFail()->status);
        $this->assertNull(ProjectSubscription::query()->firstOrFail()->ends_at);
    }

    public function test_past_service_start_backdates_stripe_subscription_without_using_payment_date(): void
    {
        [$admin, $project] = $this->fixture();
        $item = $this->billingItem($project, 'Hosting', 2000, 'recurring');
        $item->update(['starts_at' => '2026-09-01']);

        $startTimestamp = \Illuminate\Support\Carbon::parse('2026-09-01')->timestamp;

        $this->mockGateway(function (MockInterface $mock) use ($startTimestamp): void {
            $mock->shouldReceive('resolveCustomer')->once()->andReturn('cus_backdated');
            $mock->shouldReceive('isFutureForCustomer')->once()->andReturn(false);
            $mock->shouldReceive('createProduct')->once()
                ->andReturn(StripeObject::constructFrom(['id' => 'prod_backdated']));
            $mock->shouldReceive('createRecurringPrice')->once()
                ->andReturn(StripeObject::constructFrom(['id' => 'price_backdated']));
            $mock->shouldReceive('createSubscription')->once()
                ->withArgs(function ($customer, $items, $method, $metadata, $idempotencyKey, $backdateStart) use ($startTimestamp) {
                    return $customer === 'cus_backdated'
                        && $method === 'send_invoice'
                        && $backdateStart === $startTimestamp
                        && $idempotencyKey !== null;
                })
                ->andReturn(StripeObject::constructFrom([
                    'id' => 'sub_backdated',
                    'status' => 'active',
                    'current_period_start' => $startTimestamp,
                    'current_period_end' => \Illuminate\Support\Carbon::parse('2026-10-01')->timestamp,
                    'items' => ['data' => []],
                    'latest_invoice' => null,
                ]));
        });

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/subscription", [
                'starts_at' => '2026-09-01T00:00:00.000000Z',
            ])
            ->assertCreated();

        $subscription = ProjectSubscription::query()->firstOrFail();
        $this->assertStringStartsWith('2026-09-01', (string) $subscription->starts_at);
        $this->assertSame($startTimestamp, $subscription->current_period_start->timestamp);
        $this->assertSame(\Illuminate\Support\Carbon::parse('2026-10-01')->timestamp, $subscription->current_period_end->timestamp);
    }

    public function test_recurring_item_price_change_updates_stripe_item_instead_of_creating_subscription(): void
    {
        [$admin, $project, $company] = $this->fixture();

        $subscription = ProjectSubscription::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'status' => ProjectSubscription::STATUS_ACTIVE,
            'stripe_subscription_id' => 'sub_price_change',
        ]);

        $item = $this->billingItem($project, 'Hosting', 2000, 'recurring');
        $item->update([
            'project_subscription_id' => $subscription->id,
            'status' => ProjectBillingItem::STATUS_ACTIVE,
            'stripe_subscription_item_id' => 'si_hosting',
            'stripe_price_id' => 'price_old',
        ]);

        $this->mockGateway(function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveSubscription')->once()
                ->andReturn(StripeObject::constructFrom([
                    'id' => 'sub_price_change',
                    'items' => ['data' => [[
                        'id' => 'si_hosting',
                        'quantity' => 1,
                        'price' => ['id' => 'price_old'],
                    ]]],
                ]));
            $mock->shouldReceive('createProduct')->once()
                ->andReturn(StripeObject::constructFrom(['id' => 'prod_hosting']));
            $mock->shouldReceive('createRecurringPrice')->once()
                ->andReturn(StripeObject::constructFrom(['id' => 'price_new']));
            $mock->shouldReceive('updateSubscriptionItems')->once()
                ->with('sub_price_change', [[
                    'id' => 'si_hosting',
                    'price' => 'price_new',
                    'quantity' => 1,
                ]])
                ->andReturn(StripeObject::constructFrom(['id' => 'sub_price_change']));
            $mock->shouldNotReceive('createSubscription');
        });

        $this->actingAs($admin)
            ->putJson("/admin/client-portal/api/projects/{$project->id}/billing/items/{$item->id}", [
                'name' => 'Hosting',
                'unit_amount' => 3000,
                'quantity' => 1,
            ])
            ->assertOk();

        $this->assertSame(3000, $item->fresh()->unit_amount);
        $this->assertSame('price_new', $item->fresh()->stripe_price_id);
        $this->assertSame(1, ProjectSubscription::query()->count());
    }

    public function test_a_project_cannot_start_two_active_subscriptions(): void
    {
        [$admin, $project, $company] = $this->fixture();
        $this->billingItem($project, 'Hosting', 2000, 'recurring');

        ProjectSubscription::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'status' => ProjectSubscription::STATUS_ACTIVE,
            'stripe_subscription_id' => 'sub_existing',
        ]);

        $this->mockGateway(function (MockInterface $mock): void {
            $mock->shouldNotReceive('createSubscription');
        });

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/subscription")
            ->assertStatus(422);

        $this->assertSame(1, ProjectSubscription::query()->count());
    }

    public function test_recurring_subscription_requires_a_pending_or_active_unassigned_service(): void
    {
        [$admin, $project, $company] = $this->fixture();

        ProjectSubscription::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'status' => ProjectSubscription::STATUS_CANCELED,
            'stripe_subscription_id' => 'sub_canceled_services',
        ]);

        $this->billingItem($project, 'Old Hosting', 2000, 'recurring')->update([
            'status' => ProjectBillingItem::STATUS_CANCELED,
            'project_subscription_id' => ProjectSubscription::query()->latest('id')->value('id'),
        ]);

        $this->mockGateway(function (MockInterface $mock): void {
            $mock->shouldNotReceive('createSubscription');
        });

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/subscription")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Add a new recurring service before starting recurring billing. Previously canceled services cannot be reactivated automatically.');
    }

    public function test_admin_can_cancel_recurring_billing_at_period_end(): void
    {
        [$admin, $project, $company] = $this->fixture();

        $subscription = ProjectSubscription::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'status' => ProjectSubscription::STATUS_ACTIVE,
            'stripe_subscription_id' => 'sub_cancel_me',
        ]);

        $this->mockGateway(function (MockInterface $mock): void {
            $mock->shouldReceive('cancelSubscription')->once()
                ->with('sub_cancel_me', true)
                ->andReturn(StripeObject::constructFrom(['id' => 'sub_cancel_me']));
        });

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/subscription/{$subscription->id}/cancel", [
                'at_period_end' => true,
            ])
            ->assertOk();

        $subscription->refresh();
        $this->assertTrue($subscription->cancel_at_period_end);
        $this->assertSame(ProjectSubscription::STATUS_ACTIVE, $subscription->status);
    }

    public function test_schedule_backed_subscription_cancellation_includes_phase_items(): void
    {
        [$admin, $project, $company] = $this->fixture();

        $subscription = ProjectSubscription::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'status' => ProjectSubscription::STATUS_ACTIVE,
            'stripe_subscription_id' => 'sub_sched_cancel',
            'stripe_schedule_id' => 'sch_cancel',
        ]);

        $this->mockGateway(function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveSchedule')->once()->with('sch_cancel')
                ->andReturn(StripeObject::constructFrom([
                    'id' => 'sch_cancel',
                    'phases' => [[
                        'start_date' => now()->subMonth()->timestamp,
                        'end_date' => now()->addMonth()->timestamp,
                        'items' => [[
                            'price' => ['id' => 'price_hosting'],
                            'quantity' => 1,
                        ]],
                    ]],
                ]));
            $mock->shouldReceive('updateSchedule')->once()
                ->withArgs(function ($id, $payload): bool {
                    return $id === 'sch_cancel'
                        && $payload['end_behavior'] === 'cancel'
                        && isset($payload['phases'][0]['items']);
                })
                ->andReturn(StripeObject::constructFrom(['id' => 'sch_cancel']));
            $mock->shouldNotReceive('cancelSubscription');
        });

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/subscription/{$subscription->id}/cancel", [
                'at_period_end' => true,
            ])
            ->assertOk();
    }

    public function test_schedule_backed_subscription_pause_includes_phase_items(): void
    {
        [$admin, $project, $company] = $this->fixture();

        $subscription = ProjectSubscription::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'status' => ProjectSubscription::STATUS_ACTIVE,
            'stripe_subscription_id' => 'sub_sched_pause',
            'stripe_schedule_id' => 'sch_pause',
        ]);

        $pauseAt = now()->addMonth()->toDateString();

        $this->mockGateway(function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveSchedule')->once()->with('sch_pause')
                ->andReturn(StripeObject::constructFrom([
                    'id' => 'sch_pause',
                    'phases' => [[
                        'start_date' => now()->subMonth()->timestamp,
                        'end_date' => now()->addMonths(2)->timestamp,
                        'items' => [[
                            'price' => ['id' => 'price_hosting'],
                            'quantity' => 1,
                        ]],
                    ]],
                ]));
            $mock->shouldReceive('updateSchedule')->once()
                ->withArgs(fn ($id, $payload) =>
                    $id === 'sch_pause'
                    && isset($payload['phases'][0]['items'])
                    && isset($payload['phases'][1]['pause_collection'])
                )
                ->andReturn(StripeObject::constructFrom(['id' => 'sch_pause']));
        });

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/subscription/{$subscription->id}/pause", [
                'paused_at' => $pauseAt,
            ])
            ->assertOk();
    }

    public function test_billing_endpoints_reject_items_from_another_project(): void
    {
        [$admin, $projectA] = $this->fixture();
        [, $projectB] = $this->fixture('Other');

        $foreignItem = $this->billingItem($projectB, 'Foreign Work', 90000);

        $this->mockGateway(function (MockInterface $mock): void {
            $mock->shouldNotReceive('createInvoice');
        });

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$projectA->id}/billing/invoices", [
                'billing_item_ids' => [$foreignItem->id],
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('project_invoices', 0);
    }

    public function test_billing_endpoints_require_an_authenticated_admin(): void
    {
        [, $project] = $this->fixture();

        $this->getJson("/admin/client-portal/api/projects/{$project->id}/billing")
            ->assertStatus(401);
    }

    public function test_project_billing_overview_reports_revenue_and_outstanding_totals(): void
    {
        [$admin, $project, $company] = $this->fixture();

        ProjectInvoice::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'invoice_number' => '2026001',
            'status' => ProjectInvoice::STATUS_PAID,
            'payment_status' => ProjectInvoice::PAYMENT_PAID,
            'total' => 200000,
            'amount_paid' => 200000,
            'amount_due' => 0,
            'issue_date' => now()->toDateString(),
        ]);

        ProjectInvoice::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'invoice_number' => '2026002',
            'status' => ProjectInvoice::STATUS_OPEN,
            'total' => 50000,
            'amount_due' => 50000,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->getJson("/admin/client-portal/api/projects/{$project->id}/billing")
            ->assertOk()
            ->assertJsonPath('metrics.total_revenue', 200000)
            ->assertJsonPath('metrics.outstanding', 50000)
            ->assertJsonPath('metrics.overdue_count', 1);
    }

    public function test_admin_can_send_the_custom_invoice_email(): void
    {
        Notification::fake();

        [$admin, $project, $company] = $this->fixture();

        $invoice = ProjectInvoice::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'invoice_number' => '2026009',
            'status' => ProjectInvoice::STATUS_OPEN,
            'total' => 120000,
            'amount_due' => 120000,
            'customer_email' => 'billing@abc.test',
            'issue_date' => now()->toDateString(),
            'hosted_invoice_url' => 'https://invoice.stripe.test/pay/2026009',
        ]);

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/invoices/{$invoice->id}/send")
            ->assertOk();
        $this->assertNotNull($invoice->fresh()->sent_at);
        Notification::assertCount(1);
    }

    public function test_attention_email_links_only_to_the_client_portal_without_an_attachment(): void
    {
        [$admin, $project, $company] = $this->fixture();

        $invoice = ProjectInvoice::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'invoice_number' => '2026010',
            'status' => ProjectInvoice::STATUS_OPEN,
            'total' => 120000,
            'amount_due' => 120000,
            'customer_email' => 'billing@abc.test',
            'issue_date' => now()->toDateString(),
            'hosted_invoice_url' => 'https://invoice.stripe.test/pay/in_custom_1',
        ]);

        $message = (new ClientAttentionRequiredNotification(1, 0))
            ->toMail($company->billingContact);

        $this->assertSame(
            route('client.dashboard'),
            $message->viewData['actionUrl']
        );
        $this->assertSame('emails.client-attention-required', $message->view);
        $this->assertSame([], $message->attachments);
        $this->assertSame([], $message->rawAttachments);
    }

    public function test_sending_an_invoice_reminds_the_client_without_fetching_a_stripe_link(): void
    {
        Notification::fake();

        [$admin, $project, $company] = $this->fixture();

        $invoice = ProjectInvoice::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'invoice_number' => '2026011',
            'status' => ProjectInvoice::STATUS_OPEN,
            'total' => 120000,
            'amount_due' => 120000,
            'customer_email' => 'billing@abc.test',
            'issue_date' => now()->toDateString(),
            'stripe_invoice_id' => 'in_missing_url',
            'hosted_invoice_url' => null,
        ]);

        $this->mockGateway(function (MockInterface $mock): void {
            $mock->shouldNotReceive('retrieveInvoice');
        });

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/invoices/{$invoice->id}/send")
            ->assertOk();

        $this->assertNull($invoice->fresh()->hosted_invoice_url);

        Notification::assertSentTo($company->billingContact, ClientAttentionRequiredNotification::class);
    }

    public function test_billing_products_are_reusable_across_projects_with_project_specific_prices(): void
    {
        [$admin, $projectA] = $this->fixture();
        [, $projectB] = $this->fixture('Second');

        $product = BillingProduct::query()->create([
            'name' => 'Hosting',
            'unit_amount' => 2000,
            'currency' => 'EUR',
            'billing_type' => 'recurring',
            'interval' => 'month',
        ]);

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$projectA->id}/billing/items", [
                'billing_product_id' => $product->id,
                'name' => 'Hosting',
                'unit_amount' => 2000,
                'billing_type' => 'recurring',
                'interval' => 'month',
            ])
            ->assertCreated();

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$projectB->id}/billing/items", [
                'billing_product_id' => $product->id,
                'name' => 'Hosting',
                'unit_amount' => 3000,
                'billing_type' => 'recurring',
                'interval' => 'month',
            ])
            ->assertCreated();

        $this->assertSame(2000, ProjectBillingItem::query()->where('project_id', $projectA->id)->value('unit_amount'));
        $this->assertSame(3000, ProjectBillingItem::query()->where('project_id', $projectB->id)->value('unit_amount'));
    }

    public function test_sending_an_old_invoice_reminds_the_current_billing_contact_without_changing_its_snapshot(): void
    {
        Notification::fake();

        [$admin, $project, $company] = $this->fixture();

        $invoice = ProjectInvoice::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'invoice_number' => '2026050',
            'status' => ProjectInvoice::STATUS_OPEN,
            'total' => 100000,
            'amount_due' => 100000,
            'customer_email' => 'a@example.test',
            'issue_date' => now()->toDateString(),
            'hosted_invoice_url' => 'https://invoice.stripe.test/pay/2026050',
        ]);

        // The client later switches to a different billing contact.
        $newContact = $company->contacts()->create([
            'first_name' => 'Jane',
            'last_name' => 'Nová',
            'email' => 'b@example.test',
            'active' => true,
            'can_access_portal' => true,
        ]);
        $company->update(['billing_contact_id' => $newContact->id]);
        $newContact->projects()->attach($project);

        $response = $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/invoices/{$invoice->id}/send")
            ->assertOk();

        $this->assertSame(['b@example.test'], $response->json('recipients'));
        $this->assertSame('a@example.test', $invoice->fresh()->customer_email);
        Notification::assertCount(1);
    }

    public function test_sending_uses_the_billing_contact_when_the_invoice_snapshot_has_no_email(): void
    {
        [$admin, $project, $company] = $this->fixture();

        $invoice = ProjectInvoice::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'invoice_number' => '2026051',
            'status' => ProjectInvoice::STATUS_OPEN,
            'total' => 100000,
            'issue_date' => now()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/invoices/{$invoice->id}/send")
            ->assertOk()
            ->assertJsonPath('recipients.0', $company->billingContact->email);
    }

    public function test_failed_stripe_subscription_creation_leaves_no_orphan_record(): void
    {
        [$admin, $project] = $this->fixture();
        $this->billingItem($project, 'Hosting', 2000, 'recurring');

        $this->mockGateway(function (MockInterface $mock): void {
            $mock->shouldReceive('resolveCustomer')->andReturn('cus_custom_1');
            $mock->shouldReceive('createProduct')
                ->andReturn(StripeObject::constructFrom(['id' => 'prod_custom']));
            $mock->shouldReceive('createRecurringPrice')
                ->andReturn(StripeObject::constructFrom(['id' => 'price_custom']));
            $mock->shouldReceive('createSubscription')
                ->andThrow(new \RuntimeException('Stripe subscription creation failed.'));
        });

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/subscription")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Stripe subscription creation failed.');

        $this->assertDatabaseCount('project_subscriptions', 0);
    }

    public function test_invoice_reminder_is_limited_to_the_selected_billing_contact(): void
    {
        Notification::fake();

        [$admin, $project, $company] = $this->fixture();

        $projectContact = $company->contacts()->create([
            'first_name' => 'Lenka',
            'last_name' => 'Projektová',
            'email' => 'lenka@abc.test',
            'active' => true,
        ]);
        $company->contacts()->create([
            'first_name' => 'Peter',
            'last_name' => 'Účtovník',
            'email' => 'uctovnik@abc.test',
            'active' => true,
        ]);
        $project->contacts()->attach($projectContact->id);

        $invoice = $this->openInvoice($project, $company, '2026060');

        $response = $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/invoices/{$invoice->id}/send", [
                'recipients' => ['lenka@abc.test', 'uctovnik@abc.test'],
            ]);

        $response->assertOk()
            ->assertJsonCount(1, 'recipients')
            ->assertJsonPath('recipients.0', $company->billingContact->email);

        Notification::assertCount(1);
        $this->assertNotNull($invoice->fresh()->sent_at);
    }

    public function test_billing_overview_exposes_project_and_company_contacts_as_recipients(): void
    {
        [$admin, $project, $company] = $this->fixture();

        $company->update(['billing_email' => 'billing@abc.test']);
        $contact = $company->contacts()->create([
            'first_name' => 'Lenka',
            'last_name' => 'Projektová',
            'email' => 'lenka@abc.test',
            'active' => true,
        ]);
        $project->contacts()->attach($contact->id);

        $response = $this->actingAs($admin)
            ->getJson("/admin/client-portal/api/projects/{$project->id}/billing")
            ->assertOk();

        $emails = array_column($response->json('recipients'), 'email');

        $this->assertContains('billing@abc.test', $emails);
        $this->assertContains('lenka@abc.test', $emails);
        // A contact on both the project and the company appears only once.
        $this->assertSame(count($emails), count(array_unique($emails)));
    }

    public function test_invoice_cannot_be_sent_to_an_address_outside_the_client_contacts(): void
    {
        Notification::fake();

        [$admin, $project, $company] = $this->fixture();
        $invoice = $this->openInvoice($project, $company, '2026061');

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/invoices/{$invoice->id}/send", [
                'recipients' => ['attacker@evil.test'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('recipients.0');

        Notification::assertNothingSent();
    }

    private function openInvoice(Project $project, Company $company, string $number): ProjectInvoice
    {
        return ProjectInvoice::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'invoice_number' => $number,
            'status' => ProjectInvoice::STATUS_OPEN,
            'total' => 100000,
            'amount_due' => 100000,
            'issue_date' => now()->toDateString(),
        ]);
    }

    public function test_invoice_snapshots_billing_identity_and_later_changes_do_not_alter_it(): void
    {
        [$admin, $project, $company] = $this->fixture();

        $contactA = $company->billingContact;
        $company->update(['address' => 'Address A']);

        $item = $this->billingItem($project, 'Consulting', 100000);
        $this->mockGatewayForInvoice();

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/invoices", [
                'billing_item_ids' => [$item->id],
            ])
            ->assertCreated();

        $first = ProjectInvoice::query()->latest('id')->firstOrFail();

        $this->assertSame($contactA->email, $first->customer_email);
        $this->assertSame('Address A', $first->customer_address);

        // The client later moves and appoints a different billing contact.
        $contactB = $company->contacts()->create([
            'first_name' => 'Jana',
            'last_name' => 'Nová',
            'email' => 'b@example.test',
            'active' => true,
        ]);

        $company->update([
            'address' => 'Address B',
            'billing_contact_id' => $contactB->id,
        ]);

        $second = $this->billingItem($project, 'More consulting', 50000);
        $this->mockGatewayForInvoice();

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/invoices", [
                'billing_item_ids' => [$second->id],
            ])
            ->assertCreated();

        // The historical invoice is untouched...
        $first->refresh();
        $this->assertSame($contactA->email, $first->customer_email);
        $this->assertSame('Address A', $first->customer_address);

        // ...while the new invoice uses the current identity.
        $latest = ProjectInvoice::query()->latest('id')->firstOrFail();
        $this->assertSame('b@example.test', $latest->customer_email);
        $this->assertSame('Address B', $latest->customer_address);
    }

    public function test_invoicing_requires_a_billing_contact_with_an_email(): void
    {
        [$admin, $project, $company] = $this->fixture();
        $company->update(['billing_contact_id' => null]);

        $item = $this->billingItem($project, 'Consulting', 100000);

        $this->mockGateway(function (MockInterface $mock): void {
            $mock->shouldNotReceive('createInvoice');
        });

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/invoices", [
                'billing_item_ids' => [$item->id],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Select a billing contact with an email address for this client before invoicing.');

        $this->assertDatabaseCount('project_invoices', 0);
    }

    public function test_stripe_customer_uses_the_company_name_and_billing_contact(): void
    {
        [, $project, $company] = $this->fixture();

        $gateway = new class extends StripeProjectBillingGateway
        {
            public array $captured = [];

            public function __construct()
            {
            }

            public function resolveCustomer(Company $company): string
            {
                $company->loadMissing('billingContact');

                $this->captured = [
                    'name' => $company->name,
                    'email' => $company->billingContact?->email,
                    'phone' => $company->billingContact?->phone,
                    'address' => $company->address,
                ];

                return 'cus_captured';
            }
        };

        $gateway->resolveCustomer($company);

        $this->assertSame($company->name, $gateway->captured['name']);
        $this->assertSame($company->billingContact->email, $gateway->captured['email']);
        $this->assertSame($company->billingContact->phone, $gateway->captured['phone']);
        $this->assertSame($company->address, $gateway->captured['address']);
    }

    public function test_a_one_time_item_can_be_invoiced_more_than_once(): void
    {
        [$admin, $project] = $this->fixture();
        $item = $this->billingItem($project, 'Consulting', 100000);

        foreach (['first', 'second'] as $round) {
            $this->mockGatewayForInvoice();

            $this->actingAs($admin)
                ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/invoices", [
                    'billing_item_ids' => [$item->id],
                ])
                ->assertCreated();
        }

        // Two distinct invoices, each with its own number from the shared sequence.
        $this->assertDatabaseCount('project_invoices', 2);

        $numbers = ProjectInvoice::query()->pluck('invoice_number')->all();
        $this->assertCount(2, array_unique($numbers));
    }

    public function test_admin_can_remove_a_billing_item(): void
    {
        [$admin, $project] = $this->fixture();
        $item = $this->billingItem($project, 'Branding', 50000);

        $this->actingAs($admin)
            ->deleteJson("/admin/client-portal/api/projects/{$project->id}/billing/items/{$item->id}")
            ->assertNoContent();

        $this->assertDatabaseCount('project_billing_items', 0);
    }

    public function test_removing_an_item_keeps_already_issued_invoice_lines(): void
    {
        [$admin, $project] = $this->fixture();
        $item = $this->billingItem($project, 'Consulting', 100000);

        $this->mockGatewayForInvoice();

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/invoices", [
                'billing_item_ids' => [$item->id],
            ])
            ->assertCreated();

        $this->actingAs($admin)
            ->deleteJson("/admin/client-portal/api/projects/{$project->id}/billing/items/{$item->id}")
            ->assertNoContent();

        // The invoice remains a complete historical document.
        $invoice = ProjectInvoice::query()->firstOrFail();
        $this->assertSame(1, $invoice->items()->count());
        $this->assertSame('Consulting', $invoice->items()->first()->name);
    }

    public function test_an_item_inside_active_recurring_billing_cannot_be_removed(): void
    {
        [$admin, $project, $company] = $this->fixture();

        $subscription = ProjectSubscription::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'status' => ProjectSubscription::STATUS_ACTIVE,
            'stripe_subscription_id' => 'sub_live',
        ]);

        $item = $this->billingItem($project, 'Hosting', 2000, 'recurring');
        $item->update(['project_subscription_id' => $subscription->id]);

        $this->actingAs($admin)
            ->deleteJson("/admin/client-portal/api/projects/{$project->id}/billing/items/{$item->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'This service is part of active recurring billing. Cancel the recurring billing before removing it.');

        $this->assertDatabaseCount('project_billing_items', 1);
    }

    public function test_an_item_from_another_project_cannot_be_removed(): void
    {
        [$admin, $projectA] = $this->fixture();
        [, $projectB] = $this->fixture('Other');

        $foreignItem = $this->billingItem($projectB, 'Foreign', 1000);

        $this->actingAs($admin)
            ->deleteJson("/admin/client-portal/api/projects/{$projectA->id}/billing/items/{$foreignItem->id}")
            ->assertNotFound();

        $this->assertDatabaseCount('project_billing_items', 1);
    }

    public function test_creating_an_invoice_emails_it_to_the_billing_contact(): void
    {
        Notification::fake();

        [$admin, $project, $company] = $this->fixture();
        $item = $this->billingItem($project, 'Website Development', 200000);

        $this->mockGatewayForInvoice();

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/invoices", [
                'billing_item_ids' => [$item->id],
            ])
            ->assertCreated();

        $invoice = ProjectInvoice::query()->firstOrFail();

        $this->assertSame($company->billingContact->email, $invoice->customer_email);
        $this->assertNotNull($invoice->sent_at);
        Notification::assertSentTo($company->billingContact, ClientAttentionRequiredNotification::class);
    }

    public function test_admin_can_mark_an_invoice_paid_by_bank_transfer(): void
    {
        Notification::fake();

        [$admin, $project, $company] = $this->fixture();

        $invoice = ProjectInvoice::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'invoice_number' => '2026070',
            'status' => ProjectInvoice::STATUS_OPEN,
            'payment_status' => ProjectInvoice::PAYMENT_UNPAID,
            'stripe_invoice_id' => 'in_bank',
            'total' => 200000,
            'amount_due' => 200000,
            'customer_email' => 'billing@abc.test',
            'issue_date' => now()->toDateString(),
        ]);

        // Stripe must be told, otherwise it keeps chasing the customer.
        $this->mockGateway(function (MockInterface $mock): void {
            $mock->shouldReceive('payInvoiceOutOfBand')->once()
                ->with('in_bank')
                ->andReturn(StripeObject::constructFrom(['id' => 'in_bank']));
        });

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/invoices/{$invoice->id}/record-payment", [
                'paid_at' => '2026-09-08',
                'payment_method' => 'bank_transfer',
            ])
            ->assertOk();

        $invoice->refresh();

        $this->assertSame(ProjectInvoice::STATUS_PAID, $invoice->status);
        $this->assertSame(ProjectInvoice::PAYMENT_PAID, $invoice->payment_status);
        $this->assertSame(ProjectInvoice::METHOD_BANK_TRANSFER, $invoice->payment_method);
        $this->assertSame(200000, $invoice->amount_paid);
        $this->assertSame(0, $invoice->amount_due);
        $this->assertSame('2026-09-08', $invoice->paid_at->toDateString());

        Notification::assertNothingSent();
    }

    public function test_an_invoice_without_a_stripe_record_can_still_be_marked_paid(): void
    {
        Notification::fake();

        [$admin, $project, $company] = $this->fixture();

        $invoice = ProjectInvoice::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'invoice_number' => '2026071',
            'status' => ProjectInvoice::STATUS_OPEN,
            'total' => 50000,
            'amount_due' => 50000,
            'issue_date' => now()->toDateString(),
        ]);

        $this->mockGateway(function (MockInterface $mock): void {
            $mock->shouldNotReceive('payInvoiceOutOfBand');
        });

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/invoices/{$invoice->id}/record-payment")
            ->assertOk();

        $this->assertSame(ProjectInvoice::STATUS_PAID, $invoice->fresh()->status);
    }

    public function test_an_already_paid_invoice_cannot_be_marked_paid_again(): void
    {
        [$admin, $project, $company] = $this->fixture();

        $invoice = ProjectInvoice::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'invoice_number' => '2026072',
            'status' => ProjectInvoice::STATUS_PAID,
            'payment_status' => ProjectInvoice::PAYMENT_PAID,
            'total' => 50000,
            'amount_due' => 0,
            'issue_date' => now()->toDateString(),
        ]);

        $this->mockGateway(function (MockInterface $mock): void {
            $mock->shouldNotReceive('payInvoiceOutOfBand');
        });

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/invoices/{$invoice->id}/record-payment")
            ->assertStatus(422)
            ->assertJsonPath('message', 'This invoice is already settled or closed.');
    }

    public function test_a_draft_invoice_can_be_settled_manually(): void
    {
        Notification::fake();

        [$admin, $project, $company] = $this->fixture();

        // Stripe mirroring can fail, leaving a real local invoice as a draft.
        $invoice = ProjectInvoice::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'invoice_number' => '2026073',
            'status' => ProjectInvoice::STATUS_DRAFT,
            'payment_status' => ProjectInvoice::PAYMENT_UNPAID,
            'total' => 80000,
            'amount_due' => 80000,
            'customer_email' => 'billing@abc.test',
            'issue_date' => now()->toDateString(),
        ]);

        $this->mockGateway(function (MockInterface $mock): void {
            $mock->shouldNotReceive('payInvoiceOutOfBand');
        });

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/invoices/{$invoice->id}/record-payment", [
                'payment_method' => 'bank_transfer',
            ])
            ->assertOk();

        $invoice->refresh();

        $this->assertSame(ProjectInvoice::STATUS_PAID, $invoice->status);
        $this->assertSame(80000, $invoice->amount_paid);
        $this->assertSame(0, $invoice->amount_due);
    }

    public function test_admin_can_edit_a_billing_item(): void
    {
        [$admin, $project] = $this->fixture();
        $item = $this->billingItem($project, 'Hosting', 2000, 'recurring');

        $this->actingAs($admin)
            ->putJson("/admin/client-portal/api/projects/{$project->id}/billing/items/{$item->id}", [
                'name' => 'Hosting Plus',
                'unit_amount' => 3000,
                'quantity' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Hosting Plus');

        $item->refresh();

        $this->assertSame(3000, $item->unit_amount);
        $this->assertSame(2, $item->quantity);
        // The billing rhythm is fixed once created.
        $this->assertSame('month', $item->interval);
    }

    public function test_billing_item_dates_round_trip_through_edit_api(): void
    {
        [$admin, $project] = $this->fixture();
        $item = $this->billingItem($project, 'Hosting', 2000, 'recurring');

        $this->actingAs($admin)
            ->putJson("/admin/client-portal/api/projects/{$project->id}/billing/items/{$item->id}", [
                'starts_at' => '2026-10-01',
                'ends_at' => '2027-03-31',
            ])
            ->assertOk()
            ->assertJsonPath('data.starts_at', '2026-10-01T00:00:00.000000Z')
            ->assertJsonPath('data.ends_at', '2027-03-31T00:00:00.000000Z');

        $item->refresh();

        $this->assertStringStartsWith('2026-10-01', (string) $item->starts_at);
        $this->assertStringStartsWith('2027-03-31', (string) $item->ends_at);
    }

    public function test_a_failed_stripe_push_can_be_retried_without_a_new_number(): void
    {
        [$admin, $project, $company] = $this->fixture();

        $invoice = ProjectInvoice::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'invoice_number' => '2026080',
            'status' => ProjectInvoice::STATUS_DRAFT,
            'collection_method' => 'send_invoice',
            'currency' => 'EUR',
            'total' => 100000,
            'amount_due' => 100000,
            'issue_date' => now()->toDateString(),
        ]);

        $invoice->items()->create([
            'name' => 'Consulting',
            'quantity' => 1,
            'unit_amount' => 100000,
            'amount' => 100000,
        ]);

        $this->mockGateway(function (MockInterface $mock): void {
            $mock->shouldReceive('resolveCustomer')->once()->andReturn('cus_retry');
            $mock->shouldReceive('createInvoice')->once()
                ->andReturn(StripeObject::constructFrom(['id' => 'in_retry']));
            $mock->shouldReceive('createInvoiceItem')->once()
                ->andReturn(StripeObject::constructFrom(['id' => 'ii_retry']));
            $mock->shouldReceive('finalizeInvoice')->once()
                ->andReturn(StripeObject::constructFrom([
                    'id' => 'in_retry',
                    'hosted_invoice_url' => 'https://invoice.stripe.test/pay/in_retry',
                ]));
        });

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/invoices/{$invoice->id}/sync-stripe")
            ->assertOk();

        $invoice->refresh();

        $this->assertSame('in_retry', $invoice->stripe_invoice_id);
        $this->assertSame(ProjectInvoice::STATUS_OPEN, $invoice->status);
        $this->assertNotNull($invoice->hosted_invoice_url);
        // The number is reused, never reissued.
        $this->assertSame('2026080', $invoice->invoice_number);
        $this->assertDatabaseCount('project_invoices', 1);
    }

    public function test_an_invoice_already_in_stripe_is_not_pushed_twice(): void
    {
        [$admin, $project, $company] = $this->fixture();

        $invoice = ProjectInvoice::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'invoice_number' => '2026081',
            'status' => ProjectInvoice::STATUS_OPEN,
            'stripe_invoice_id' => 'in_existing',
            'total' => 100000,
            'issue_date' => now()->toDateString(),
        ]);

        $this->mockGateway(function (MockInterface $mock): void {
            $mock->shouldNotReceive('createInvoice');
        });

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/invoices/{$invoice->id}/sync-stripe")
            ->assertStatus(422)
            ->assertJsonPath('message', 'This invoice already exists in Stripe.');
    }

    private function mockGatewayForInvoice(): void
    {
        $this->mockGateway(function (MockInterface $mock): void {
            $mock->shouldReceive('resolveCustomer')->andReturn('cus_custom_1');
            $mock->shouldReceive('createInvoice')
                ->andReturn(StripeObject::constructFrom(['id' => 'in_custom_'.uniqid()]));
            $mock->shouldReceive('createInvoiceItem')
                ->andReturn(StripeObject::constructFrom(['id' => 'ii_custom']));
            $mock->shouldReceive('finalizeInvoice')
                ->andReturn(StripeObject::constructFrom([
                    'id' => 'in_custom_'.uniqid(),
                    'hosted_invoice_url' => 'https://invoice.stripe.test/x',
                ]));
        });
    }

    private function mockGateway(callable $expectations): void
    {
        $this->mock(StripeProjectBillingGateway::class, function (MockInterface $mock) use ($expectations): void {
            $mock->shouldReceive('domainMetadata')->andReturn(['billing_domain' => 'custom_project']);
            $mock->shouldReceive('listDraftInvoices')->andReturn([]);
            $mock->shouldReceive('deleteInvoice')->andReturnNull();
            $expectations($mock);
        });
    }

    private function billingItem(Project $project, string $name, int $amount, string $type = 'one_time'): ProjectBillingItem
    {
        return ProjectBillingItem::query()->create([
            'project_id' => $project->id,
            'name' => $name,
            'unit_amount' => $amount,
            'currency' => 'EUR',
            'quantity' => 1,
            'billing_type' => $type,
            'interval' => $type === 'recurring' ? 'month' : null,
            'interval_count' => 1,
            'status' => ProjectBillingItem::STATUS_PENDING,
        ]);
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
            'registration_number' => '12345678',
            'tax_number' => '2023456789',
            'address' => "Hlavná 1\n81101 Bratislava\nSK",
        ]);

        $billingContact = $company->contacts()->create([
            'first_name' => 'Billing',
            'last_name' => 'Contact',
            'email' => 'billing@'.strtolower($prefix).'.test',
            'phone' => '+421900000000',
            'active' => true,
            'can_access_portal' => true,
        ]);

        $company->update(['billing_contact_id' => $billingContact->id]);
        $company->refresh();

        $serviceProduct = ServiceProduct::query()->create([
            'name' => $prefix.' Web',
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

        $billingContact->projects()->attach($project);

        return [$admin, $project, $company];
    }
}
