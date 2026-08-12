<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\Project;
use App\Models\ServiceAccount;
use App\Models\ServiceProduct;
use App\Services\AuditLogger;
use App\Services\ContractService;
use App\Services\ContractTemplateVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClientPortalAdminController extends Controller
{
    public function storeCompany(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'registration_number' => ['nullable', 'string', 'max:50'],
            'tax_number' => ['nullable', 'string', 'max:50'], 'vat_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:2000'],
        ]);
        $company = Company::query()->create($data);
        $audit->record('company.created', $request->user(), $company, $company->id, request: $request);

        return back()->with('status', 'Klient bol vytvorený.');
    }

    public function storeContact(Request $request, Company $company, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:client_contacts,email'],
            'phone' => ['nullable', 'string', 'max:50'], 'position' => ['nullable', 'string', 'max:100'],
            'can_access_portal' => ['sometimes', 'boolean'], 'can_accept_documents' => ['sometimes', 'boolean'],
        ]);
        $contact = $company->contacts()->create($data + ['active' => true]);
        $audit->record('contact.access_granted', $request->user(), $contact, $company->id, metadata: [
            'portal_access' => $contact->can_access_portal, 'document_acceptance' => $contact->can_accept_documents,
        ], request: $request);

        return back()->with('status', 'Kontakt bol pridaný.');
    }

    public function revokeContact(Request $request, ClientContact $contact, AuditLogger $audit): RedirectResponse
    {
        $contact->update(['can_access_portal' => false, 'access_revoked_at' => now()]);
        $audit->record('contact.access_revoked', $request->user(), $contact, $contact->company_id, request: $request);

        return back()->with('status', 'Prístup kontaktu bol okamžite zrušený.');
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'slug' => ['nullable', 'string', 'max:255', 'unique:service_products,slug'], 'description' => ['nullable', 'string']]);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        ServiceProduct::query()->create($data + ['active' => true]);

        return back()->with('status', 'Service Product bol vytvorený.');
    }

    public function updateProduct(Request $request, ServiceProduct $product): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:service_products,slug,'.$product->id],
            'description' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
            'default_contract_template_id' => ['nullable', 'exists:contract_templates,id'],
        ]);
        $product->update($data + ['active' => $request->boolean('active')]);

        return back()->with('status', 'Service Product bol aktualizovaný.');
    }

    public function storeServiceAccount(Request $request, Project $project, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'service_name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'login_url' => ['nullable', 'url:http,https', 'max:2048'],
            'account_identifier' => ['nullable', 'string', 'max:255'],
            'account_owner' => ['required', 'in:client,provider,third_party'],
            'billing_owner' => ['nullable', 'in:client,provider,third_party'],
            'renewal_responsibility' => ['nullable', 'in:client,provider,third_party'],
            'provider' => ['nullable', 'string', 'max:255'],
            'renewal_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'client_visible' => ['sometimes', 'boolean'],
            'provider_type' => ['required', 'in:none,external_vault,1password,bitwarden,other'],
            'external_reference' => ['nullable', 'url:http,https', 'max:2048'],
            'access_instructions' => ['nullable', 'string', 'max:2000'],
        ]);

        $account = $project->serviceAccounts()->create([
            ...collect($data)->except(['provider_type', 'external_reference', 'access_instructions'])->all(),
            'client_visible' => $request->boolean('client_visible'),
        ]);
        $account->credential()->create([
            'provider_type' => $data['provider_type'],
            'external_reference' => $data['external_reference'] ?? null,
            'access_instructions' => $data['access_instructions'] ?? null,
            'client_visible' => $request->boolean('client_visible'),
        ]);
        $audit->record('service_account.created', $request->user(), $account, $project->company_id, $project->id, [
            'service_name' => $account->service_name,
            'credential_provider' => $data['provider_type'],
        ], $request);

        return back()->with('status', 'Projektová služba bola pridaná.');
    }

    public function destroyServiceAccount(Request $request, ServiceAccount $account, AuditLogger $audit): RedirectResponse
    {
        $project = $account->project;
        $audit->record('service_account.archived', $request->user(), $account, $project->company_id, $project->id, [
            'service_name' => $account->service_name,
        ], $request);
        $account->delete();

        return back()->with('status', 'Projektová služba bola odstránená.');
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'service_product_id' => ['nullable', 'exists:service_products,id'], 'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:contract_templates,slug'],
        ]);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        ContractTemplate::query()->create($data);

        return back()->with('status', 'Zmluvná šablóna bola vytvorená.');
    }

    public function createVersion(Request $request, ContractTemplate $template, ContractTemplateVersionService $service): RedirectResponse
    {
        $data = $request->validate(['version' => ['required', 'regex:/^\d+\.\d+$/', 'max:32', 'unique:contract_template_versions,version,NULL,id,contract_template_id,'.$template->id]]);
        $version = $service->createDraft($template, $data['version'], $request->user());

        return redirect()->route('admin.client-portal.versions.edit', $version);
    }

    public function updateVersion(Request $request, ContractTemplateVersion $version): RedirectResponse
    {
        abort_unless($version->status === 'draft', 409);
        $data = $request->validate(['content' => ['required', 'string'], 'change_summary' => ['nullable', 'string', 'max:5000']]);
        $version->update($data);

        return back()->with('status', 'Draft bol uložený.');
    }

    public function publishVersion(Request $request, ContractTemplateVersion $version, ContractTemplateVersionService $service): RedirectResponse
    {
        $data = $request->validate([
            'change_policy' => ['required', 'in:future_only,requires_new_acceptance,information_only'],
            'change_summary' => ['required', 'string', 'max:5000'],
        ]);
        $service->publish($version, $data['change_policy'], $data['change_summary'], $request->user());

        return back()->with('status', 'Verzia bola publikovaná a je immutable.');
    }

    public function retireVersion(Request $request, ContractTemplateVersion $version, ContractTemplateVersionService $service): RedirectResponse
    {
        $service->retire($version, $request->user());

        return back()->with('status', 'Verzia bola vyradená. Existujúce zmluvy zostali nezmenené.');
    }

    public function generateContract(Request $request, Project $project, ContractService $service): RedirectResponse
    {
        $data = $request->validate(['contract_template_version_id' => ['required', 'exists:contract_template_versions,id'], 'number' => ['nullable', 'string', 'max:100']]);
        $version = ContractTemplateVersion::query()->findOrFail($data['contract_template_version_id']);
        $contract = $service->generate($project, $version, $data['number'] ?? null);

        return back()->with('status', 'Zmluva '.$contract->title.' v'.$contract->version.' bola vygenerovaná.');
    }
}