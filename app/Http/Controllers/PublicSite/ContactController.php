<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Requests\PublicSite\StoreContactRequest;
use App\Notifications\ContactRequestReceivedNotification;
use App\Notifications\NewContactRequestNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

class ContactController extends \App\Http\Controllers\Controller
{
    public function store(StoreContactRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Honeypot: silently accept without sending anything.
        if (!empty($data['website'])) {
            return response()->json(['message' => 'ok']);
        }

        $locale = in_array($data['locale'] ?? null, ['en', 'sk'], true)
            ? $data['locale']
            : 'en';

        Notification::route('mail', config('app.contact_email'))
            ->notify(new NewContactRequestNotification($data));

        if ($data['contactMethod'] === 'email' && !empty($data['email'])) {
            Notification::route('mail', $data['email'])
                ->notify(new ContactRequestReceivedNotification($data, $locale));
        }

        return response()->json(['message' => 'ok']);
    }
}
