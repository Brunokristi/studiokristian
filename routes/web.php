<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Route Files
|--------------------------------------------------------------------------
*/

require __DIR__ . '/auth.php';
require __DIR__ . '/public.php';
require __DIR__ . '/client.php';
require __DIR__ . '/admin.php';


/*
|--------------------------------------------------------------------------
| Authenticated Dashboard Redirect
|--------------------------------------------------------------------------
|
| This is the common entry point after authentication.
|
| Client users are handled separately by bootstrap/app.php.
| Admins go to the admin application.
| Other authenticated users go to the staff workspace.
|
*/

Route::get(
    '/dashboard',
    function () {
        $user = request()->user();

        if ($user?->is_admin) {
            return redirect()->route(
                'admin.client-portal.index'
            );
        }

        return redirect()->route(
            'staff.workspace'
        );
    }
)
    ->middleware([
        'auth',
        'verified',
    ])
    ->name('dashboard');


/*
|--------------------------------------------------------------------------
| Public SPA Catch-All
|--------------------------------------------------------------------------
|
| This MUST remain last.
|
*/

Route::get(
    '/{any}',
    function () {
        return view('apps.public');
    }
)->where(
    'any',
    '^(?!api|admin|client|storage|login|register|logout|forgot-password|reset-password|verify-email|confirm-password|password|staff-login|dashboard|workspace).*$'
);