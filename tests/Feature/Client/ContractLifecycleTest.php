<?php

namespace Tests\Feature\Client;

use App\Models\ClientContact;
use App\Models\Company;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\Project;
use App\Models\ServiceProduct;
use App\Notifications\ContractAcceptedNotification;
use App\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class ContractLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        Notification::fake();
        config()->set('client-portal.provider', [
            'company_name' => 'Studio Kristian s.r.o.',
            'registration_number' => '12345678',
            'address' => 'Bratislava',
        ]);
    }

    public function test_contract_generation_creates_an_independent_content_snapshot(): void
    {
        [$project, , $version] = $this->domain();

        $contract = app(ContractService::class)->generate($project, $version, 'SK-2026-001');

        $this->assertSame('Zmluva pre ABC s.r.o. / Portal. Verzia 1.0.', $contract->rendered_content);
        $this->assertSame(hash('sha256', $contract->rendered_content), $contract->content_hash);
        Storage::disk('local')->assertExists($contract->generated_pdf_path);
    }

    public function test_published_template_version_cannot_be_edited_in_place(): void
    {
        [, , $version] = $this->domain();

        $this->expectException(LogicException::class);
        $version->update(['content' => 'Changed text']);
    }

    public function test_old_contract_survives_template_update_and_new_contract_uses_new_version(): void
    {
        [$project, $template, $versionOne] = $this->domain();
        $service = app(ContractService::class);
        $oldContract = $service->generate($project, $versionOne);
        $versionTwo = ContractTemplateVersion::query()->create([
            'contract_template_id' => $template->id,
            'version' => '1.1',
            'content' => 'Nové znenie pre {{client.company_name}}.',
            'status' => 'published',
            'change_policy' => 'future_only',
            'change_summary' => 'Terminologická úprava.',
            'published_at' => now(),
        ]);

        $newContract = $service->generate($project, $versionTwo);

        $this->assertSame('Zmluva pre ABC s.r.o. / Portal. Verzia 1.0.', $oldContract->fresh()->rendered_content);
        $this->assertSame('1.0', $oldContract->version);
        $this->assertSame('Nové znenie pre ABC s.r.o..', $newContract->rendered_content);
        $this->assertSame('1.1', $newContract->version);
    }

    public function test_authorized_contact_can_accept_contract_once_and_download_exact_pdf(): void
    {
        [$project, , $version, $contact] = $this->domain();
        $contract = app(ContractService::class)->generate($project, $version);
        $contract->update(['status' => 'sent', 'sent_at' => now()]);
        $requestIdentifier = (string) Str::uuid();
        $payload = [
            'read_and_agreed' => '1',
            'authorized_to_act' => '1',
            'request_identifier' => $requestIdentifier,
            'timezone' => 'Europe/Bratislava',
        ];

        $this->actingAs($contact, 'client')->post(route('client.contracts.accept', $contract), $payload)
            ->assertRedirect(route('client.contracts.show', $contract));
        $this->actingAs($contact, 'client')->post(route('client.contracts.accept', $contract), $payload)
            ->assertRedirect(route('client.contracts.show', $contract));

        $contract->refresh();
        $this->assertSame('accepted', $contract->status);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($contract->final_pdf_path)), $contract->final_pdf_hash);
        $this->assertDatabaseCount('contract_acceptances', 1);
        $this->assertDatabaseCount('audit_logs', 2);
        Notification::assertSentTo($contact, ContractAcceptedNotification::class);
        $this->actingAs($contact, 'client')->get(route('client.contracts.download', $contract))->assertOk();
    }

    public function test_contact_without_signing_permission_cannot_accept(): void
    {
        [$project, , $version, $contact] = $this->domain();
        $contact->update(['can_accept_documents' => false]);
        $contract = app(ContractService::class)->generate($project, $version);
        $contract->update(['status' => 'sent']);

        $this->actingAs($contact, 'client')->post(route('client.contracts.accept', $contract), [
            'read_and_agreed' => '1', 'authorized_to_act' => '1', 'request_identifier' => (string) Str::uuid(),
        ])->assertForbidden();
        $this->assertDatabaseCount('contract_acceptances', 0);
    }

    public function test_contact_cannot_view_another_company_contract_by_changing_url_id(): void
    {
        [$project, , $version, $contact] = $this->domain();
        $contract = app(ContractService::class)->generate($project, $version);
        $otherCompany = Company::query()->create(['name' => 'Other s.r.o.']);
        $otherContact = ClientContact::query()->create([
            'company_id' => $otherCompany->id, 'first_name' => 'Eva', 'last_name' => 'Ina',
            'email' => 'eva@example.test', 'active' => true, 'can_access_portal' => true,
        ]);

        $this->actingAs($otherContact, 'client')->get(route('client.contracts.show', $contract))->assertForbidden();
        $this->actingAs($contact, 'client')->get(route('client.contracts.show', $contract))->assertOk();
    }

    private function domain(): array
    {
        $company = Company::query()->create(['name' => 'ABC s.r.o.', 'registration_number' => '87654321']);
        $contact = ClientContact::query()->create([
            'company_id' => $company->id, 'first_name' => 'Anna', 'last_name' => 'Kovacova',
            'email' => 'anna@example.test', 'position' => 'Konateľka', 'active' => true,
            'can_access_portal' => true, 'can_accept_documents' => true,
        ]);
        $product = ServiceProduct::query()->create(['name' => 'Custom Web Application Development', 'slug' => 'web-app']);
        $project = Project::query()->create([
            'company_id' => $company->id, 'service_product_id' => $product->id,
            'name' => 'Portal', 'url' => 'portal', 'portal_status' => 'active',
        ]);
        $contact->projects()->attach($project);
        $template = ContractTemplate::query()->create([
            'service_product_id' => $product->id, 'name' => 'Zmluva o dielo', 'slug' => 'zmluva-o-dielo',
        ]);
        $version = ContractTemplateVersion::query()->create([
            'contract_template_id' => $template->id, 'version' => '1.0',
            'content' => 'Zmluva pre {{client.company_name}} / {{project.name}}. Verzia {{document.version}}.',
            'status' => 'published', 'change_policy' => 'future_only', 'published_at' => now(),
        ]);

        return [$project, $template, $version, $contact];
    }
}