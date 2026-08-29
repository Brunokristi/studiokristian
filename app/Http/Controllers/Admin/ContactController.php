<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClientPortal\StoreContactRequest;
use App\Http\Resources\Admin\ClientPortal\ContactResource;
use App\Models\ClientContact;
use App\Models\Company;
use App\Notifications\ClientContactInvitationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ContactController extends Controller
{
    public function store(StoreContactRequest $request, Company $company): ContactResource
    {
        $contact = $company->contacts()->create($this->normalized($request));

        if ($contact->hasPortalAccess()) {
            $contact->notify(
                new ClientContactInvitationNotification(
                    $company,
                    route('client.login')
                )
            );
        }

        return new ContactResource($contact);
    }

    public function resendInvitation(
        Company $company,
        ClientContact $contact
    ): Response|JsonResponse {
        abort_unless($contact->company_id === $company->id, 404);

        if (! $contact->hasPortalAccess()) {
            return response()->json([
                'message' => 'This contact does not have portal access enabled.',
            ], 422);
        }

        $contact->notify(
            new ClientContactInvitationNotification(
                $company,
                route('client.login')
            )
        );

        return response()->noContent();
    }

    public function update(
        StoreContactRequest $request,
        Company $company,
        ClientContact $contact
    ): ContactResource {
        abort_unless($contact->company_id === $company->id, 404);

        $contact->update(
            $this->normalized(
                $request,
                $contact
            )
        );

        return new ContactResource(
            $contact->fresh()
        );
    }

    public function destroy(
        Company $company,
        ClientContact $contact
    ): Response {
        abort_unless($contact->company_id === $company->id, 404);

        $contact->delete();

        return response()->noContent();
    }

    private function normalized(
        StoreContactRequest $request,
        ?ClientContact $contact = null
    ): array {
        $data = $request->validated();

        if (! $request->boolean('active')) {
            $data['can_access_portal'] = false;
            $data['can_accept_documents'] = false;
        }

        $data['access_revoked_at'] =
            $data['can_access_portal']
                ? null
                : ($contact?->access_revoked_at ?? now());

        return $data;
    }
}