<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;

use App\Http\Controllers\Admin\Auth\MagicLinkController as StaffMagicLinkController;
use App\Http\Controllers\Client\Auth\MagicLinkController as ClientMagicLinkController;


/*
|--------------------------------------------------------------------------
| Shared / Staff Authentication
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {

    Route::get(
        'register',
        [RegisteredUserController::class, 'create']
    )->name('register');

    Route::post(
        'register',
        [RegisteredUserController::class, 'store']
    );


    /*
    |--------------------------------------------------------------------------
    | Staff Login
    |--------------------------------------------------------------------------
    */

    Route::get(
        'login',
        [StaffMagicLinkController::class, 'create']
    )->name('login');

    Route::post(
        'login',
        [StaffMagicLinkController::class, 'store']
    )->middleware('throttle:5,1');

    Route::get(
        'staff-login/{token}',
        [StaffMagicLinkController::class, 'consume']
    )
        ->middleware([
            'signed',
            'throttle:10,1',
        ])
        ->name('staff.login.consume');


    /*
    |--------------------------------------------------------------------------
    | Password Reset
    |--------------------------------------------------------------------------
    */

    Route::get(
        'forgot-password',
        [PasswordResetLinkController::class, 'create']
    )->name('password.request');

    Route::post(
        'forgot-password',
        [PasswordResetLinkController::class, 'store']
    )->name('password.email');

    Route::get(
        'reset-password/{token}',
        [NewPasswordController::class, 'create']
    )->name('password.reset');

    Route::post(
        'reset-password',
        [NewPasswordController::class, 'store']
    )->name('password.store');
});


Route::middleware('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Email Verification
    |--------------------------------------------------------------------------
    */

    Route::get(
        'verify-email',
        EmailVerificationPromptController::class
    )->name('verification.notice');

    Route::get(
        'verify-email/{id}/{hash}',
        VerifyEmailController::class
    )
        ->middleware([
            'signed',
            'throttle:6,1',
        ])
        ->name('verification.verify');

    Route::post(
        'email/verification-notification',
        [EmailVerificationNotificationController::class, 'store']
    )
        ->middleware('throttle:6,1')
        ->name('verification.send');


    /*
    |--------------------------------------------------------------------------
    | Password
    |--------------------------------------------------------------------------
    */

    Route::get(
        'confirm-password',
        [ConfirmablePasswordController::class, 'show']
    )->name('password.confirm');

    Route::post(
        'confirm-password',
        [ConfirmablePasswordController::class, 'store']
    );

    Route::put(
        'password',
        [PasswordController::class, 'update']
    )->name('password.update');


    /*
    |--------------------------------------------------------------------------
    | Staff Logout
    |--------------------------------------------------------------------------
    */

    Route::post(
        'logout',
        [AuthenticatedSessionController::class, 'destroy']
    )->name('logout');
});


/*
|--------------------------------------------------------------------------
| Client Authentication
|--------------------------------------------------------------------------
*/

Route::prefix('client')
    ->name('client.')
    ->middleware('guest:client')
    ->group(function () {

        Route::get(
            '/login',
            [ClientMagicLinkController::class, 'create']
        )->name('login');

        Route::post(
            '/login',
            [ClientMagicLinkController::class, 'store']
        )
            ->middleware('throttle:5,1')
            ->name('login.send');

        Route::get(
            '/login/{token}',
            [ClientMagicLinkController::class, 'consume']
        )
            ->middleware([
                'signed',
                'throttle:10,1',
            ])
            ->name('magic-link.consume');
    });


/*
|--------------------------------------------------------------------------
| Client Logout
|--------------------------------------------------------------------------
*/

Route::post(
    '/client/logout',
    [ClientMagicLinkController::class, 'destroy']
)
    ->middleware([
        'auth:client',
        'client.access',
    ])
    ->name('client.logout');
