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
use App\Services\ProjectBilling\ProjectInvoiceNumberService;
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
                    // Invoice-first: no upfront charge, so no saved card is required.
                    return $collectionMethod === 'send_invoice' && count($items) === 3;
                })
                ->andReturn(StripeObject::constructFrom([
                    'id' => 'sub_custom_1',
                    'status' => 'active',
                    'current_period_end' => now()->addMonth()->timestamp,
                    'items' => ['data' => []],
                    'latest_invoice' => ['id' => 'in_first_sub', 'status' => 'draft'],
                ]));

            // The first invoice must be finalized now, not an hour later, so the
            // customer immediately gets a payable invoice with a hosted link.
            $mock->shouldReceive('retrieveInvoice')->once()
                ->with('in_first_sub')
                ->andReturn(StripeObject::constructFrom([
                    'id' => 'in_first_sub',
                    'status' => 'draft',
                    'customer' => 'cus_custom_1',
                    'currency' => 'eur',
                    'lines' => ['data' => []],
                ]));

            $mock->shouldReceive('updateInvoice')->andReturn(
                StripeObject::constructFrom(['id' => 'in_first_sub'])
            );

            $mock->shouldReceive('finalizeInvoice')->once()
                ->with('in_first_sub', true)
                ->andReturn(StripeObject::constructFrom([
                    'id' => 'in_first_sub',
                    'status' => 'open',
                    'hosted_invoice_url' => 'https://invoice.stripe.test/pay/in_first_sub',
                ]));
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
        ]);

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/invoices/{$invoice->id}/send")
            ->assertOk();
        $this->assertNotNull($invoice->fresh()->sent_at);
        Notification::assertCount(1);
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

    public function test_invoice_is_sendable_when_the_client_only_has_a_contact_email(): void
    {
        Notification::fake();

        [$admin, $project, $company] = $this->fixture();

        // No dedicated billing email - only a contact, like a real client record.
        $company->update(['billing_email' => null]);
        $company->contacts()->create([
            'first_name' => 'Lenka',
            'last_name' => 'Kontakt',
            'email' => 'kontakt@abc.test',
            'active' => true,
        ]);

        $invoice = ProjectInvoice::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'invoice_number' => '2026050',
            'status' => ProjectInvoice::STATUS_OPEN,
            'total' => 100000,
            'amount_due' => 100000,
            'issue_date' => now()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/invoices/{$invoice->id}/send")
            ->assertOk();

        $invoice->refresh();
        $this->assertSame('kontakt@abc.test', $invoice->customer_email);
        $this->assertNotNull($invoice->sent_at);
        Notification::assertCount(1);
    }

    public function test_sending_fails_clearly_when_no_email_exists_anywhere(): void
    {
        [$admin, $project, $company] = $this->fixture();
        $company->update(['billing_email' => null]);

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
            ->assertStatus(422)
            ->assertJsonPath('message', 'This client has no billing email and no contact with an email address. Add one on the client before sending the invoice.');
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
                ->andThrow(new \RuntimeException('This customer has no attached payment source.'));
        });

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/projects/{$project->id}/billing/subscription")
            ->assertStatus(422);

        $this->assertDatabaseCount('project_subscriptions', 0);
    }

    public function test_admin_can_choose_multiple_recipients_from_the_contact_list(): void
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
            ->assertJsonCount(2, 'recipients');

        Notification::assertCount(2);
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
            'billing_email' => 'billing@'.strtolower($prefix).'.test',
            'billing_address_line1' => 'Hlavná 1',
            'billing_address_city' => 'Bratislava',
            'billing_address_postal_code' => '81101',
            'billing_address_country' => 'SK',
        ]);

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

        return [$admin, $project, $company];
    }
}
