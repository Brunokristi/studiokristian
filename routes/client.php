<?php

use App\Http\Controllers\Client\DashboardController;
use App\Http\Controllers\Client\ProjectController;
use App\Http\Controllers\Client\ProjectDocumentSignatureController;
use App\Http\Controllers\Client\ProjectFileController;
use App\Http\Controllers\Client\ProjectTicketController;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Client Portal
|--------------------------------------------------------------------------
*/

Route::prefix('client')
    ->name('client.')
    ->middleware([
        'auth:client',
        'client.access',
    ])
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/',
            DashboardController::class
        )->name('dashboard');


        /*
        |--------------------------------------------------------------------------
        | Projects
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/projects/{project}',
            [ProjectController::class, 'show']
        )->name('projects.show');


        /*
        |--------------------------------------------------------------------------
        | Project Files
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/projects/{project}/files',
            [ProjectFileController::class, 'index']
        )->name('projects.files.index');

        Route::get(
            '/files/{file}/open',
            [ProjectFileController::class, 'open']
        )->name('files.open');

        Route::get(
            '/files/{file}/download',
            [ProjectFileController::class, 'download']
        )->name('files.download');


        /*
        |--------------------------------------------------------------------------
        | Project Documents
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/projects/{project}/documents/{folder}/sign',
            [ProjectDocumentSignatureController::class, 'store']
        )
            ->middleware('throttle:10,1')
            ->name('projects.documents.sign');


        /*
        |--------------------------------------------------------------------------
        | Project Tickets
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/projects/{project}/tickets',
            [ProjectTicketController::class, 'store']
        )->name('tickets.store');
    });