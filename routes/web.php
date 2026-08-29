<?php

use Illuminate\Support\Facades\Route;

require __DIR__ . '/auth.php';
require __DIR__ . '/public.php';
require __DIR__ . '/client.php';
require __DIR__ . '/admin.php';


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