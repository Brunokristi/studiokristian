<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\Admin\PortfolioAdminController;
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

require __DIR__.'/auth.php';
// Catch-all route for SPA (must be last)
Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '^(?!api|admin|storage).*$');
