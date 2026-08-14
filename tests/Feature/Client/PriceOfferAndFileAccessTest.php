<?php

namespace Tests\Feature\Client;

use App\Models\ClientContact;
use App\Models\Company;
use App\Models\PriceOffer;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Services\PriceOfferAcceptanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PriceOfferAndFileAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    public function test_price_offer_acceptance_is_immutable_and_idempotent(): void
    {
        [$project, $contact] = $this->domain();
        $content = json_encode([
            'client' => $project->company->name, 'project' => $project->name,
            'number' => 'PO-2026-001', 'version' => '1.0', 'total' => '1230.00', 'currency' => 'EUR',
        ], JSON_THROW_ON_ERROR);
        $offer = PriceOffer::query()->create([
            'project_id' => $project->id, 'number' => 'PO-2026-001', 'version' => '1.0',
            'status' => 'sent', 'currency' => 'EUR', 'subtotal' => 1000, 'tax' => 230,
            'total' => 1230, 'rendered_content' => $content, 'content_hash' => hash('sha256', $content),
            'sent_at' => now(),
        ]);
        $offer->items()->create([
            'category' => 'one_time', 'name' => 'Implementation', 'quantity' => 1,
            'unit' => 'project', 'unit_price' => 1000, 'total' => 1000,
        ]);
        $requestIdentifier = (string) Str::uuid();
        $request = Request::create('/client/offers/'.$offer->id.'/accept', 'POST', ['timezone' => 'Europe/Bratislava']);

        $first = app(PriceOfferAcceptanceService::class)->accept($offer, $contact, $request, $requestIdentifier);
        $second = app(PriceOfferAcceptanceService::class)->accept($offer, $contact, $request, $requestIdentifier);

        $offer->refresh();
        $this->assertTrue($first->is($second));
        $this->assertSame('accepted', $offer->status);
        $this->assertDatabaseCount('price_offer_acceptances', 1);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($offer->final_pdf_path)), $offer->final_pdf_hash);
    }

    public function test_client_can_download_only_client_visible_files_from_assigned_projects(): void
    {
        [$project, $contact] = $this->domain();
        Storage::disk('local')->put('client-portal/files/public.pdf', 'client file');
        Storage::disk('local')->put('client-portal/files/internal.pdf', 'internal file');
        $visible = $this->file($project, 'client-portal/files/public.pdf', 'client');
        $internal = $this->file($project, 'client-portal/files/internal.pdf', 'internal');

        $this->actingAs($contact, 'client')->get(route('client.files.download', $visible))->assertOk();
        $this->actingAs($contact, 'client')->get(route('client.files.download', $internal))->assertForbidden();
    }

    public function test_client_open_endpoint_respects_visibility_and_uses_inline_for_svg(): void
    {
        [$project, $contact] = $this->domain();

        $svgPath = 'client-portal/files/logo.svg';
        Storage::disk('local')->put($svgPath, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"></svg>');

        $svg = ProjectFile::query()->create([
            'project_id' => $project->id,
            'original_filename' => 'logo.svg',
            'display_name' => 'logo.svg',
            'extension' => 'svg',
            'storage_path' => $svgPath,
            'disk' => 'local',
            'mime_type' => 'image/svg+xml',
            'size' => strlen(Storage::disk('local')->get($svgPath)),
            'checksum' => hash('sha256', Storage::disk('local')->get($svgPath)),
            'visibility' => 'client',
        ]);

        $response = $this->actingAs($contact, 'client')->get(route('client.files.open', $svg));
        $response->assertOk();
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));

        Storage::disk('local')->put('client-portal/files/secret.bin', 'secret');
        $internal = ProjectFile::query()->create([
            'project_id' => $project->id,
            'original_filename' => 'secret.bin',
            'display_name' => 'secret.bin',
            'extension' => 'bin',
            'storage_path' => 'client-portal/files/secret.bin',
            'disk' => 'local',
            'mime_type' => 'application/octet-stream',
            'size' => 6,
            'checksum' => hash('sha256', 'secret'),
            'visibility' => 'internal',
        ]);

        $this->actingAs($contact, 'client')->get(route('client.files.open', $internal))->assertForbidden();
    }

    public function test_file_download_is_tenant_isolated_even_when_id_is_known(): void
    {
        [$project] = $this->domain();
        Storage::disk('local')->put('client-portal/files/public.pdf', 'client file');
        $visible = $this->file($project, 'client-portal/files/public.pdf', 'client');
        $otherCompany = Company::query()->create(['name' => 'Other s.r.o.']);
        $otherContact = ClientContact::query()->create([
            'company_id' => $otherCompany->id, 'first_name' => 'Eva', 'last_name' => 'Ina',
            'email' => 'other@example.test', 'active' => true, 'can_access_portal' => true,
        ]);

        $this->actingAs($otherContact, 'client')->get(route('client.files.download', $visible))->assertForbidden();
    }

    private function domain(): array
    {
        $company = Company::query()->create(['name' => 'ABC s.r.o.']);
        $contact = ClientContact::query()->create([
            'company_id' => $company->id, 'first_name' => 'Anna', 'last_name' => 'Kovacova',
            'email' => 'anna@example.test', 'active' => true, 'can_access_portal' => true,
            'can_accept_documents' => true,
        ]);
        $project = Project::query()->create([
            'company_id' => $company->id, 'name' => 'Portal', 'url' => 'portal', 'portal_status' => 'active',
        ]);
        $contact->projects()->attach($project);

        return [$project, $contact];
    }

    private function file(Project $project, string $path, string $visibility): ProjectFile
    {
        $contents = Storage::disk('local')->get($path);

        return ProjectFile::query()->create([
            'project_id' => $project->id, 'original_filename' => basename($path),
            'display_name' => basename($path), 'storage_path' => $path,
            'mime_type' => 'application/pdf', 'size' => strlen($contents),
            'checksum' => hash('sha256', $contents), 'visibility' => $visibility,
        ]);
    }
}