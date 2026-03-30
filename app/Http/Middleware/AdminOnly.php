<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminOnly
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        // You can change this to use a role or permission system
        if (!$user || !$user->is_admin) {
            abort(403, 'Unauthorized.');
        }
        return $next($request);
    }
}
