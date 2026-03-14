<?php

use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/{url}', [ProjectController::class, 'show']);
});

Route::get('/', function () {
    return view('welcome');
});

Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '^(?!api).*$');
