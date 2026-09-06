<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\PublicSite\ContactController;
use App\Http\Controllers\Billing\CheckoutSessionController;
use App\Http\Controllers\Api\Billing\BillingController;
use App\Http\Controllers\PublicSite\HomeController;
use App\Http\Controllers\PublicSite\ProjectController;
use App\Http\Controllers\PublicSite\SeoController;
use App\Http\Controllers\PublicSite\ServiceController;
use App\Http\Controllers\Webhooks\StripeWebhookController;


/*
|--------------------------------------------------------------------------
| Public Website
|--------------------------------------------------------------------------
*/

Route::get(
    '/',
    HomeController::class
)->name('home');

Route::get(
    '/robots.txt',
    [SeoController::class, 'robots']
);

Route::get(
    '/sitemap.xml',
    [SeoController::class, 'sitemap']
);


/*
|--------------------------------------------------------------------------
| Public API
|--------------------------------------------------------------------------
*/

Route::prefix('api')->group(function () {

    Route::prefix('v1/billing')
        ->middleware(['billing.api', 'throttle:60,1'])
        ->group(function () {
            Route::get('/plans', [BillingController::class, 'plans']);
            Route::post('/customer-credentials', [BillingController::class, 'provisionCustomerCredential']);
            Route::get('/customer/subscriptions', [BillingController::class, 'customer'])
                ->middleware('billing.api:required');
            Route::get('/customer/payments', [BillingController::class, 'payments'])
                ->middleware('billing.api:required');
            Route::get('/customer/invoices', [BillingController::class, 'invoices'])
                ->middleware('billing.api:required');
            Route::get('/customer/trial', [BillingController::class, 'trial'])
                ->middleware('billing.api:required');
            Route::post('/customer/trial', [BillingController::class, 'startTrial'])
                ->middleware('billing.api:required');
            Route::patch('/customer/profile', [BillingController::class, 'updateProfile'])
                ->middleware('billing.api:required');
            Route::post('/checkout', [BillingController::class, 'checkout'])
                ->middleware('billing.api:required');
        });

    Route::get(
        '/projects',
        [ProjectController::class, 'index']
    );

    Route::get(
        '/projects/{url}',
        [ProjectController::class, 'show']
    );

    Route::get(
        '/services',
        [ServiceController::class, 'index']
    );

    Route::get(
        '/services/{slug}',
        [ServiceController::class, 'show']
    );

    Route::post(
        '/contact',
        [ContactController::class, 'store']
    )->middleware('throttle:5,1');

    Route::post(
        '/webhooks/stripe',
        StripeWebhookController::class
    )->name('webhooks.stripe');

    Route::post(
        '/billing/checkout-sessions',
        CheckoutSessionController::class
    )->middleware('throttle:30,1')->name('billing.checkout-sessions.store');
});