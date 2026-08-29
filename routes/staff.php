<?php

use App\Http\Controllers\Staff\WorkspaceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Staff Workspace
|--------------------------------------------------------------------------
*/

Route::middleware('auth')
    ->prefix('workspace')
    ->name('staff.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Workspace
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/',
            [WorkspaceController::class, 'index']
        )->name('workspace');


        /*
        |--------------------------------------------------------------------------
        | Projects
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/projects',
            [WorkspaceController::class, 'projects']
        )->name('projects');


        /*
        |--------------------------------------------------------------------------
        | Tickets
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/projects/{project}/tickets',
            [WorkspaceController::class, 'storeTicket']
        )->name('tickets.store');

        Route::patch(
            '/projects/{project}/tickets/{ticket}',
            [WorkspaceController::class, 'updateTicket']
        )->name('tickets.update');


        /*
        |--------------------------------------------------------------------------
        | Files
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/projects/{project}/files/{file}',
            [WorkspaceController::class, 'file']
        )->name('files.open');
    });