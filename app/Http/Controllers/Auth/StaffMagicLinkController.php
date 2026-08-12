<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\StaffLoginToken;
use App\Models\User;
use App\Notifications\StaffMagicLinkNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StaffMagicLinkController extends Controller
{
    public function create(): View { return view('auth.login'); }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        $user = User::query()->whereRaw('LOWER(email) = ?', [strtolower($data['email'])])->first();
        if ($user && ($user->is_admin || $user->projects()->exists())) {
            $plain = Str::random(64);
            $token = $user->loginTokens()->create(['token_hash' => hash('sha256', $plain), 'expires_at' => now()->addMinutes(10), 'requested_ip' => $request->ip()]);
            $user->notify(new StaffMagicLinkNotification(URL::temporarySignedRoute('staff.login.consume', $token->expires_at, ['token' => $plain])));
        }
        return back()->with('status', 'If this email belongs to a staff account, we sent a secure sign-in link.');
    }

    public function consume(Request $request, string $token): RedirectResponse
    {
        $user = DB::transaction(function () use ($token) {
            $loginToken = StaffLoginToken::query()->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $loginToken || $loginToken->used_at || $loginToken->expires_at->isPast()) return null;
            $loginToken->update(['used_at' => now()]);
            return $loginToken->user;
        });
        if (! $user) return redirect()->route('login')->withErrors(['email' => 'This sign-in link is invalid or expired.']);
        if (! $user->hasVerifiedEmail()) $user->markEmailAsVerified();
        Auth::login($user); $request->session()->regenerate();
        return redirect()->intended($user->is_admin ? route('admin.client-portal.index') : route('staff.workspace'));
    }
}