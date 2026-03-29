<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\Admin\PortfolioAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/{url}', [ProjectController::class, 'show']);
});

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('admin/portfolio')->name('admin.portfolio.')->group(function () {
    Route::get('/', [PortfolioAdminController::class, 'index'])->name('index');
    Route::get('/create', [PortfolioAdminController::class, 'create'])->name('create');
    Route::post('/', [PortfolioAdminController::class, 'store'])->name('store');
    Route::get('/{project}/edit', [PortfolioAdminController::class, 'edit'])->name('edit');
    Route::put('/{project}', [PortfolioAdminController::class, 'update'])->name('update');
    Route::delete('/{project}', [PortfolioAdminController::class, 'destroy'])->name('destroy');
});

Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '^(?!api|admin).*$');
