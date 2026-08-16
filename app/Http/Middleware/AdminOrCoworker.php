<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class AdminOrCoworker
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Unauthorized.');
        }

        if ($user->is_admin) {
            return $next($request);
        }

        if (! User::hasRoleColumn() || $user->portalRole() === 'coworker') {
            return $next($request);
        }

        abort(403, 'Unauthorized.');
    }
}
