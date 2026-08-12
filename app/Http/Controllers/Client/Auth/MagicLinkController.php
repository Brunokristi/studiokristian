<?php

namespace App\Http\Controllers\Client\Auth;

use App\Http\Controllers\Controller;
use App\Models\ClientContact;
use App\Models\ClientLoginToken;
use App\Notifications\ClientMagicLinkNotification;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MagicLinkController extends Controller
{
    public function create(): View
    {
        return view('apps.auth-client');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        $contact = ClientContact::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($data['email'])])
            ->where('active', true)
            ->where('can_access_portal', true)
            ->whereNull('access_revoked_at')
            ->first();

        if ($contact) {
            $plainToken = Str::random(64);
            $loginToken = $contact->loginTokens()->create([
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addMinutes(10),
                'requested_ip' => $request->ip(),
                'request_identifier' => (string) Str::uuid(),
            ]);
            $url = URL::temporarySignedRoute(
                'client.magic-link.consume',
                $loginToken->expires_at,
                ['token' => $plainToken],
            );
            $contact->notify(new ClientMagicLinkNotification($url));
        }

        $message = 'Ak je email priradený aktívnemu kontaktu, poslali sme prihlasovací odkaz.';

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : back()->with('status', $message);
    }

    public function consume(Request $request, string $token, AuditLogger $audit): RedirectResponse
    {
        $contact = DB::transaction(function () use ($token) {
            $loginToken = ClientLoginToken::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if (! $loginToken || $loginToken->used_at || $loginToken->expires_at->isPast()) {
                return null;
            }

            $contact = $loginToken->contact;
            if (! $contact || ! $contact->hasPortalAccess()) {
                return null;
            }

            $loginToken->update(['used_at' => now()]);

            return $contact;
        });

        if (! $contact) {
            return redirect()->route('client.login')->withErrors([
                'email' => 'Prihlasovací odkaz je neplatný alebo už vypršal.',
            ]);
        }

        Auth::guard('client')->login($contact);
        $request->session()->regenerate();
        $audit->record('portal.login', $contact, $contact, $contact->company_id, request: $request);

        return redirect()->intended(route('client.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('client')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('client.login');
    }
}