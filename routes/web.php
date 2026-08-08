<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\Admin\PortfolioAdminController;
use App\Http\Controllers\Admin\ClientPortalAdminController;
use App\Http\Controllers\Client\Auth\MagicLinkController;
use App\Http\Controllers\Client\ContractController as ClientContractController;
use App\Http\Controllers\Client\ProjectController as ClientProjectController;
use App\Http\Controllers\Client\ProjectFileController as ClientProjectFileController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\PriceOfferController as ClientPriceOfferController;
use Illuminate\Support\Facades\Route;

// Public API
Route::prefix('api')->group(function () {
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/{url}', [ProjectController::class, 'show']);
});

// Home
Route::get('/', function () {
    return view('welcome');
});

Route::prefix('client')->name('client.')->group(function () {
    Route::middleware('guest:client')->group(function () {
        Route::get('/login', [MagicLinkController::class, 'create'])->name('login');
        Route::post('/login', [MagicLinkController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('login.send');
        Route::get('/login/{token}', [MagicLinkController::class, 'consume'])
            ->middleware(['signed', 'throttle:10,1'])
            ->name('magic-link.consume');
    });

    Route::middleware(['auth:client', 'client.access'])->group(function () {
        Route::get('/', ClientDashboardController::class)->name('dashboard');
        Route::get('/projects/{project}', [ClientProjectController::class, 'show'])->name('projects.show');
        Route::get('/contracts/{contract}', [ClientContractController::class, 'show'])->name('contracts.show');
        Route::post('/contracts/{contract}/accept', [ClientContractController::class, 'accept'])
            ->middleware('throttle:10,1')
            ->name('contracts.accept');
        Route::get('/contracts/{contract}/download', [ClientContractController::class, 'download'])
            ->name('contracts.download');
        Route::get('/files/{file}/download', [ClientProjectFileController::class, 'download'])->name('files.download');
        Route::get('/offers/{offer}', [ClientPriceOfferController::class, 'show'])->name('offers.show');
        Route::post('/offers/{offer}/accept', [ClientPriceOfferController::class, 'accept'])->middleware('throttle:10,1')->name('offers.accept');
        Route::get('/offers/{offer}/download', [ClientPriceOfferController::class, 'download'])->name('offers.download');
        Route::post('/logout', [MagicLinkController::class, 'destroy'])->name('logout');
    });
});

// Redirect dashboard to admin portfolio after login
Route::get('/dashboard', function () {
    return redirect()->route('admin.portfolio.index');
})->middleware(['auth', 'verified'])->name('dashboard');

// Admin Portfolio (protected)
Route::prefix('admin/portfolio')
    ->middleware(['auth', 'admin'])
    ->name('admin.portfolio.')
    ->group(function () {
        Route::get('/', [PortfolioAdminController::class, 'index'])->name('index');
        Route::post('/translate', [PortfolioAdminController::class, 'translate'])->name('translate');
        Route::get('/create', [PortfolioAdminController::class, 'create'])->name('create');
        Route::post('/', [PortfolioAdminController::class, 'store'])->name('store');
        Route::get('/{project}/edit', [PortfolioAdminController::class, 'edit'])->name('edit');
        Route::put('/{project}', [PortfolioAdminController::class, 'update'])->name('update');
        Route::delete('/{project}', [PortfolioAdminController::class, 'destroy'])->name('destroy');
    });

Route::prefix('admin/client-portal')->middleware(['auth', 'admin'])->name('admin.client-portal.')->group(function () {
    Route::get('/', [ClientPortalAdminController::class, 'index'])->name('index');
    Route::post('/companies', [ClientPortalAdminController::class, 'storeCompany'])->name('companies.store');
    Route::post('/companies/{company}/contacts', [ClientPortalAdminController::class, 'storeContact'])->name('contacts.store');
    Route::post('/contacts/{contact}/revoke', [ClientPortalAdminController::class, 'revokeContact'])->name('contacts.revoke');
    Route::post('/products', [ClientPortalAdminController::class, 'storeProduct'])->name('products.store');
    Route::put('/products/{product}', [ClientPortalAdminController::class, 'updateProduct'])->name('products.update');
    Route::post('/projects/{project}/services', [ClientPortalAdminController::class, 'storeServiceAccount'])->name('services.store');
    Route::delete('/services/{account}', [ClientPortalAdminController::class, 'destroyServiceAccount'])->name('services.destroy');
    Route::post('/templates', [ClientPortalAdminController::class, 'storeTemplate'])->name('templates.store');
    Route::post('/templates/{template}/versions', [ClientPortalAdminController::class, 'createVersion'])->name('versions.store');
    Route::get('/versions/{version}', [ClientPortalAdminController::class, 'editVersion'])->name('versions.edit');
    Route::put('/versions/{version}', [ClientPortalAdminController::class, 'updateVersion'])->name('versions.update');
    Route::post('/versions/{version}/publish', [ClientPortalAdminController::class, 'publishVersion'])->name('versions.publish');
    Route::post('/versions/{version}/retire', [ClientPortalAdminController::class, 'retireVersion'])->name('versions.retire');
    Route::post('/projects/{project}/contracts', [ClientPortalAdminController::class, 'generateContract'])->name('contracts.generate');
});

require __DIR__.'/auth.php';
// Catch-all route for SPA (must be last)
Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '^(?!api|admin|storage).*$');
