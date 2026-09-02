<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\PublicSite\ContactController;
use App\Http\Controllers\PublicSite\HomeController;
use App\Http\Controllers\PublicSite\ProjectController;
use App\Http\Controllers\PublicSite\ServiceController;


/*
|--------------------------------------------------------------------------
| Public Website
|--------------------------------------------------------------------------
*/

Route::get(
    '/',
    HomeController::class
)->name('home');


/*
|--------------------------------------------------------------------------
| Public API
|--------------------------------------------------------------------------
*/

Route::prefix('api')->group(function () {

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
});