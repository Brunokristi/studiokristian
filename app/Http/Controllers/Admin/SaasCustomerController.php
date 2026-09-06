<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SaasSubscriptionResource;
use App\Http\Resources\Admin\SaasInvoiceResource;
use App\Http\Resources\Admin\SaasPaymentResource;
use App\Http\Resources\Admin\SaasBillingCustomerResource;
use App\Http\Resources\Admin\SaasCustomerListResource;
use App\Models\SaasInvoice;
use App\Models\SaasPayment;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Models\Company;
use App\Services\Billing\StripeBillingService;
use App\Services\Billing\StripeWebhookService;

class SaasCustomerController extends Controller
{
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        $this->authorizeSaasProject($project);

        $search = trim($request->string('search')->toString());

        $companies = Company::query()
            ->where(function ($query) use ($project) {
                $query
                    ->whereHas('saasSubscriptions', fn ($subscription) => $subscription->where('project_id', $project->id))
                    ->orWhereHas('companyTrials', fn ($trial) => $trial->where('project_id', $project->id))
                    ->orWhereHas('saasInvoices', fn ($invoice) => $invoice->where('project_id', $project->id));
            })
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->with(['saasSubscriptions' => fn ($query) => $query->where('project_id', $project->id)->with(['plan', 'price'])->latest('updated_at')])
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 25), 10), 100));

        return SaasCustomerListResource::collection($companies);
    }

    public function invoices(Request $request, Project $project): AnonymousResourceCollection
    {
        $this->authorizeSaasProject($project);

        return SaasInvoiceResource::collection(
            SaasInvoice::query()
                ->where('project_id', $project->id)
                ->with('company')
                ->latest('invoice_date')
                ->paginate(min(max($request->integer('per_page', 25), 10), 100))
        );
    }

    public function show(Request $request, Project $project, Company $company): array
    {
        $this->authorizeSaasProject($project);

        abort_unless(
            $project->saasSubscriptions()->where('company_id', $company->id)->exists() ||
            $project->companyTrials()->where('company_id', $company->id)->exists() ||
            SaasInvoice::query()->where('project_id', $project->id)->where('company_id', $company->id)->exists(),
            404
        );

        return [
            'company' => new SaasBillingCustomerResource($company),
            'subscriptions' => SaasSubscriptionResource::collection(
                $project->saasSubscriptions()->where('company_id', $company->id)->with(['plan', 'price'])->latest('updated_at')->get()
            ),
            'payments' => SaasPaymentResource::collection(
                SaasPayment::query()->where('project_id', $project->id)->where('company_id', $company->id)->latest('paid_at')->get()
            ),
            'invoices' => SaasInvoiceResource::collection(
                SaasInvoice::query()->where('project_id', $project->id)->where('company_id', $company->id)->latest('invoice_date')->get()
            ),
        ];
    }

    public function syncStripeHistory(Request $request, Project $project, Company $company, StripeBillingService $stripe, StripeWebhookService $webhooks): array
    {
        $this->authorizeSaasProject($project);

        $billingCustomer = $project->billingCustomers()->where('company_id', $company->id)->first();

        abort_unless($billingCustomer?->stripe_customer_id, 422, 'No Stripe billing customer is mapped to this Company and SaaS Project.');

        foreach ($stripe->listInvoicesForCustomer($billingCustomer->stripe_customer_id) as $invoice) {
            $webhooks->syncInvoiceFromStripe($invoice);
        }

        return $this->show($request, $project, $company);
    }

    public function payments(Request $request, Project $project): AnonymousResourceCollection
    {
        $this->authorizeSaasProject($project);

        return SaasPaymentResource::collection(
            SaasPayment::query()
                ->where('project_id', $project->id)
                ->with('company')
                ->latest('paid_at')
                ->paginate(min(max($request->integer('per_page', 25), 10), 100))
        );
    }

    private function authorizeSaasProject(Project $project): void
    {
        abort_unless(request()->user()?->is_admin, 403);
        abort_unless($project->is_saas, 404);
    }
}