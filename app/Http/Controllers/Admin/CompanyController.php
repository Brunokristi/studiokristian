<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCompanyRequest;
use App\Http\Requests\Admin\UpdateCompanyRequest;
use App\Http\Resources\Admin\CompanyResource;
use App\Models\Company;
use App\Models\ProjectBillingCustomer;
use App\Services\ProjectBilling\StripeProjectBillingGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Throwable;

class CompanyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $sort = in_array(
            $request->string('sort')->toString(),
            [
                'name',
                'registration_number',
                'status',
                'updated_at',
            ],
            true
        )
            ? $request->string('sort')->toString()
            : 'updated_at';

        $direction =
            $request->string('direction')->toString() === 'asc'
                ? 'asc'
                : 'desc';

        $search =
            trim(
                $request->string('search')->toString()
            );

        $companies = Company::query()
            ->withCount([
                'contacts',
                'projects',
            ])
            ->withCount([
                'contacts as portal_contacts_count' => fn ($query) =>
                    $query
                        ->where('active', true)
                        ->where('can_access_portal', true),
            ])
            ->when(
                $search !== '',
                fn ($query) =>
                    $query->where(function ($nested) use ($search) {
                        $nested
                            ->where(
                                'name',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'registration_number',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'tax_number',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'vat_number',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhereHas(
                                'contacts',
                                fn ($contacts) =>
                                    $contacts->where(
                                        'email',
                                        'like',
                                        "%{$search}%"
                                    )
                            );
                    })
            )
            ->when(
                $request->filled('status'),
                fn ($query) =>
                    $query->where(
                        'status',
                        $request->string('status')
                    )
            )
            ->orderBy(
                $sort,
                $direction
            )
            ->paginate(
                min(
                    max(
                        $request->integer('per_page', 25),
                        10
                    ),
                    100
                )
            );

        return CompanyResource::collection(
            $companies
        );
    }

    public function store(
        StoreCompanyRequest $request
    ): CompanyResource {
        $company = Company::query()->create(
            $request->validated()
        );

        return new CompanyResource(
            $company->load([
                'billingContact',
            ])->loadCount([
                'contacts',
                'projects',
            ])
        );
    }

    public function show(
        Company $company
    ): CompanyResource {
        return new CompanyResource(
            $company
                ->load([
                    'billingContact',
                    'contacts' => fn ($query) =>
                        $query->orderBy('last_name'),
                    'projects.serviceProduct',
                ])
                ->loadCount([
                    'contacts',
                    'projects',
                ])
        );
    }

    public function update(
        UpdateCompanyRequest $request,
        Company $company
    ): CompanyResource {
        $data = $request->validated();

        $previousBillingContactId = $company->billing_contact_id;

        if (
            array_key_exists(
                'billing_contact_id',
                $data
            ) &&
            $data['billing_contact_id'] !== null
        ) {
            abort_unless(
                $company
                    ->contacts()
                    ->whereKey(
                        $data['billing_contact_id']
                    )
                    ->exists(),
                422,
                'The selected billing contact does not belong to this client.'
            );
        }

        $company->update($data);

        if ($company->status !== 'archived') {
            $company->update([
                'archived_at' => null,
            ]);
        } elseif ($company->archived_at === null) {
            // Keep archived_at in step with status however the status was changed.
            $company->update([
                'archived_at' => now(),
            ]);
        }

        $this->syncBillingIdentityToStripe(
            $company->fresh(),
            $previousBillingContactId
        );

        return new CompanyResource(
            $company
                ->fresh()
                ->load([
                    'billingContact',
                ])
                ->loadCount([
                    'contacts',
                    'projects',
                ])
        );
    }

    /**
     * Pushes billing identity changes to an existing project-billing Stripe Customer.
     * Never provisions one - that stays part of the billing flows.
     */
    private function syncBillingIdentityToStripe(
        Company $company,
        ?int $previousBillingContactId
    ): void {
        if ($previousBillingContactId === $company->billing_contact_id) {
            return;
        }

        $hasStripeCustomer = ProjectBillingCustomer::query()
            ->where('company_id', $company->id)
            ->whereNotNull('stripe_customer_id')
            ->exists();

        if (! $hasStripeCustomer) {
            return;
        }

        try {
            app(StripeProjectBillingGateway::class)->resolveCustomer($company);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function archive(
        Company $company
    ): Response {
        $company->update([
            'status' => 'archived',
            'archived_at' => now(),
        ]);

        return response()->noContent();
    }

    public function destroy(
        Company $company
    ): JsonResponse {
        if ($company->projects()->exists()) {
            return response()->json([
                'message' =>
                    'This client cannot be deleted because it still has projects. Archive the client instead.',
            ], 422);
        }

        $company->contacts()->delete();

        $company->delete();

        return response()->json([
            'message' => 'Client deleted successfully.',
        ]);
    }
}