<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\ContactController;
use App\Http\Controllers\Admin\CoworkerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InternalStorageController;
use App\Http\Controllers\Admin\LookupController;
use App\Http\Controllers\Admin\PortfolioController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\ProjectCoworkerController;
use App\Http\Controllers\Admin\ProjectFileController;
use App\Http\Controllers\Admin\ProjectTicketController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\ServiceProductController;
use App\Http\Controllers\Admin\ServiceProductTemplateController;
use App\Http\Controllers\Admin\ShellController;


/*
|--------------------------------------------------------------------------
| Admin Application
|--------------------------------------------------------------------------
|
| Main Admin SPA and Admin API.
|
*/

Route::prefix('admin/client-portal')
    ->middleware([
        'auth',
        'verified',
        'admin',
    ])
    ->name('admin.client-portal.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Admin API
        |--------------------------------------------------------------------------
        */

        Route::prefix('api')
            ->name('api.')
            ->group(function () {

                /*
                |--------------------------------------------------------------------------
                | Dashboard
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/dashboard',
                    DashboardController::class
                )->name('dashboard');


                /*
                |--------------------------------------------------------------------------
                | Portfolio
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/portfolio',
                    [PortfolioController::class, 'listing']
                )->name('portfolio.index');

                Route::get(
                    '/projects/{project}/portfolio',
                    [PortfolioController::class, 'show']
                )->name('projects.portfolio.show');

                Route::put(
                    '/projects/{project}/portfolio',
                    [PortfolioController::class, 'update']
                )->name('projects.portfolio.update');

                Route::post(
                    '/portfolio/translate',
                    [PortfolioController::class, 'translate']
                )->name('portfolio.translate');


                /*
                |--------------------------------------------------------------------------
                | Lookups
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/lookups',
                    LookupController::class
                )->name('lookups');

                Route::get(
                    '/companies/{company}/contacts/options',
                    [LookupController::class, 'contacts']
                )->name('companies.contacts.options');


                /*
                |--------------------------------------------------------------------------
                | Companies / Clients
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/clients',
                    [CompanyController::class, 'index']
                )->name('clients.index');

                Route::post(
                    '/clients',
                    [CompanyController::class, 'store']
                )->name('clients.store');

                Route::get(
                    '/clients/{company}',
                    [CompanyController::class, 'show']
                )->name('clients.show');

                Route::put(
                    '/clients/{company}',
                    [CompanyController::class, 'update']
                )->name('clients.update');

                Route::delete(
                    '/clients/{company}',
                    [CompanyController::class, 'destroy']
                )->name('clients.destroy');

                Route::post(
                    '/clients/{company}/archive',
                    [CompanyController::class, 'archive']
                )->name('clients.archive');


                /*
                |--------------------------------------------------------------------------
                | Client Contacts
                |--------------------------------------------------------------------------
                */

                Route::post(
                    '/clients/{company}/contacts',
                    [ContactController::class, 'store']
                )->name('contacts.store');

                Route::put(
                    '/clients/{company}/contacts/{contact}',
                    [ContactController::class, 'update']
                )->name('contacts.update');

                Route::post(
                    '/clients/{company}/contacts/{contact}/resend-invitation',
                    [ContactController::class, 'resendInvitation']
                )->name('contacts.resend-invitation');

                Route::delete(
                    '/clients/{company}/contacts/{contact}',
                    [ContactController::class, 'destroy']
                )->name('contacts.destroy');


                /*
                |--------------------------------------------------------------------------
                | Projects
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/projects',
                    [ProjectController::class, 'index']
                )->name('projects.index');

                Route::post(
                    '/projects',
                    [ProjectController::class, 'store']
                )->name('projects.store');

                Route::get(
                    '/projects/{project}',
                    [ProjectController::class, 'show']
                )->name('projects.show');

                Route::put(
                    '/projects/{project}',
                    [ProjectController::class, 'update']
                )->name('projects.update');

                Route::delete(
                    '/projects/{project}',
                    [ProjectController::class, 'destroy']
                )->name('projects.destroy');

                Route::post(
                    '/projects/{project}/archive',
                    [ProjectController::class, 'archive']
                )->name('projects.archive');

                Route::put(
                    '/projects/{project}/publishing',
                    [ProjectController::class, 'publish']
                )->name('projects.publish');


                /*
                |--------------------------------------------------------------------------
                | Coworkers
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/coworkers',
                    [CoworkerController::class, 'index']
                )->name('coworkers.index');

                Route::post(
                    '/coworkers',
                    [CoworkerController::class, 'store']
                )->name('coworkers.store');

                Route::get(
                    '/coworkers/{coworker}',
                    [CoworkerController::class, 'show']
                )->name('coworkers.show');

                Route::put(
                    '/coworkers/{coworker}',
                    [CoworkerController::class, 'update']
                )->name('coworkers.update');

                Route::delete(
                    '/coworkers/{coworker}',
                    [CoworkerController::class, 'destroy']
                )->name('coworkers.destroy');


                /*
                |--------------------------------------------------------------------------
                | Internal Storage
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/internal-storage',
                    [InternalStorageController::class, 'index']
                )->name('internal-storage.index');

                Route::put(
                    '/internal-storage/structure',
                    [InternalStorageController::class, 'updateStructure']
                )->name('internal-storage.structure.update');

                Route::post(
                    '/internal-storage/files',
                    [InternalStorageController::class, 'upload']
                )->name('internal-storage.files.upload');

                Route::get(
                    '/internal-storage/files/{folder}/open',
                    [InternalStorageController::class, 'open']
                )->name('internal-storage.files.open');

                Route::get(
                    '/internal-storage/files/{folder}/download',
                    [InternalStorageController::class, 'download']
                )->name('internal-storage.files.download');


                /*
                |--------------------------------------------------------------------------
                | Project Files & Structure
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/projects/{project}/files',
                    [ProjectFileController::class, 'index']
                )->name('projects.files.index');

                Route::put(
                    '/projects/{project}/structure',
                    [ProjectFileController::class, 'updateStructure']
                )->name('projects.structure.update');

                Route::post(
                    '/projects/{project}/folders',
                    [ProjectFileController::class, 'storeFolder']
                )->name('projects.folders.store');

                Route::put(
                    '/projects/{project}/folders/{folder}',
                    [ProjectFileController::class, 'updateFolder']
                )->name('projects.folders.update');

                Route::post(
                    '/projects/{project}/files',
                    [ProjectFileController::class, 'upload']
                )->name('projects.files.upload');

                Route::delete(
                    '/projects/{project}/files/{file}',
                    [ProjectFileController::class, 'destroy']
                )->name('projects.files.destroy');

                Route::patch(
                    '/projects/{project}/files/{file}',
                    [ProjectFileController::class, 'rename']
                )->name('projects.files.rename');

                Route::get(
                    '/projects/{project}/files/{file}/download',
                    [ProjectFileController::class, 'download']
                )->name('projects.files.download');

                Route::get(
                    '/projects/{project}/files/{file}/open',
                    [ProjectFileController::class, 'open']
                )->name('projects.files.open');

                Route::get(
                    '/projects/{project}/files/{file}/thumbnail',
                    [ProjectFileController::class, 'thumbnail']
                )->name('projects.files.thumbnail');

                Route::put(
                    '/projects/{project}/files/{file}/move',
                    [ProjectFileController::class, 'move']
                )->name('projects.files.move');


                /*
                |--------------------------------------------------------------------------
                | Project Coworkers & Contacts
                |--------------------------------------------------------------------------
                */

                Route::post(
                    '/projects/{project}/coworkers',
                    [ProjectCoworkerController::class, 'store']
                )->name('projects.coworkers.store');

                Route::post(
                    '/projects/{project}/coworkers/{user}/resend-invitation',
                    [ProjectCoworkerController::class, 'resendCoworkerInvitation']
                )->name('projects.coworkers.resend-invitation');

                Route::post(
                    '/projects/{project}/contacts/invite',
                    [ProjectCoworkerController::class, 'inviteContact']
                )->name('projects.contacts.invite');

                Route::post(
                    '/projects/{project}/contacts/{contact}/resend-invitation',
                    [ProjectCoworkerController::class, 'resendContactInvitation']
                )->name('projects.contacts.resend-invitation');


                /*
                |--------------------------------------------------------------------------
                | Project Tickets
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/projects/{project}/tickets',
                    [ProjectTicketController::class, 'index']
                )->name('projects.tickets.index');

                Route::post(
                    '/projects/{project}/tickets',
                    [ProjectTicketController::class, 'store']
                )->name('projects.tickets.store');

                Route::put(
                    '/projects/{project}/tickets/{ticket}',
                    [ProjectTicketController::class, 'update']
                )->name('projects.tickets.update');

                Route::delete(
                    '/projects/{project}/tickets/{ticket}',
                    [ProjectTicketController::class, 'destroy']
                )->name('projects.tickets.destroy');


                /*
                |--------------------------------------------------------------------------
                | Ticket Tags
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/projects/{project}/ticket-tags',
                    [
                        ProjectTicketController::class,
                        'tags',
                    ]
                )->name(
                    'projects.ticket-tags.index'
                );

                Route::post(
                    '/projects/{project}/ticket-tags',
                    [
                        ProjectTicketController::class,
                        'storeTag',
                    ]
                )->name(
                    'projects.ticket-tags.store'
                );


                /*
                |--------------------------------------------------------------------------
                | Service Products
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/service-products',
                    [ServiceProductController::class, 'index']
                )->name('service-products.index');

                Route::post(
                    '/service-products',
                    [ServiceProductController::class, 'store']
                )->name('service-products.store');

                Route::get(
                    '/service-products/{serviceProduct}',
                    [ServiceProductController::class, 'show']
                )->name('service-products.show');

                Route::put(
                    '/service-products/{serviceProduct}',
                    [ServiceProductController::class, 'update']
                )->name('service-products.update');

                Route::delete(
                    '/service-products/{serviceProduct}',
                    [ServiceProductController::class, 'destroy']
                )->name('service-products.destroy');

                Route::post(
                    '/service-products/{serviceProduct}/deactivate',
                    [ServiceProductController::class, 'deactivate']
                )->name('service-products.deactivate');


                /*
                |--------------------------------------------------------------------------
                | Service Product Template Structure
                |--------------------------------------------------------------------------
                |
                | This replaces the old Blueprint / Blueprint Version
                | API. A service product owns one editable template tree.
                |
                */

                Route::get(
                    '/service-products/{serviceProduct}/template',
                    [
                        ServiceProductTemplateController::class,
                        'show',
                    ]
                )->name(
                    'service-products.template.show'
                );

                Route::put(
                    '/service-products/{serviceProduct}/template',
                    [
                        ServiceProductTemplateController::class,
                        'update',
                    ]
                )->name(
                    'service-products.template.update'
                );

                Route::post(
                    '/service-products/{serviceProduct}/template/files',
                    [
                        ServiceProductTemplateController::class,
                        'upload',
                    ]
                )->name(
                    'service-products.template.files.upload'
                );

                Route::get(
                    '/service-products/{serviceProduct}/template-folders/{folder}/open',
                    [
                        ServiceProductTemplateController::class,
                        'open',
                    ]
                )->name(
                    'service-products.template.open'
                );

                Route::get(
                    '/service-products/{serviceProduct}/template-folders/{folder}/download',
                    [
                        ServiceProductTemplateController::class,
                        'download',
                    ]
                )->name(
                    'service-products.template.download'
                );

                Route::put(
                    '/service-products/{serviceProduct}/template-folders/{folder}/document',
                    [
                        ServiceProductTemplateController::class,
                        'document',
                    ]
                )->name(
                    'service-products.template.document'
                );

                Route::delete(
                    '/service-products/{serviceProduct}/template-folders/{folder}',
                    [
                        ServiceProductTemplateController::class,
                        'destroy',
                    ]
                )->name(
                    'service-products.template.destroy'
                );


                /*
                |--------------------------------------------------------------------------
                | Services
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/service-products/{serviceProduct}/services',
                    [
                        ServiceController::class,
                        'index',
                    ]
                )->name(
                    'service-products.services.index'
                );

                Route::post(
                    '/service-products/{serviceProduct}/services',
                    [
                        ServiceController::class,
                        'store',
                    ]
                )->name(
                    'service-products.services.store'
                );

                Route::put(
                    '/services/{service}',
                    [
                        ServiceController::class,
                        'update',
                    ]
                )->name(
                    'services.update'
                );

                Route::delete(
                    '/services/{service}',
                    [
                        ServiceController::class,
                        'destroy',
                    ]
                )->name(
                    'services.destroy'
                );

            });


        /*
        |--------------------------------------------------------------------------
        | Admin SPA
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/',
            ShellController::class
        )->name('index');

        Route::get(
            '/projects',
            ShellController::class
        );

        Route::get(
            '/projects/create',
            ShellController::class
        );

        Route::get(
            '/projects/{project}',
            ShellController::class
        );

        Route::get(
            '/projects/{project}/edit',
            ShellController::class
        );


        /*
        |--------------------------------------------------------------------------
        | Admin SPA Catch-All
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/{path}',
            ShellController::class
        )->where(
            'path',
            'clients(?:/.*)?'
            . '|projects(?:/.*)?'
            . '|service-products(?:/.*)?'
            . '|coworkers(?:/.*)?'
            . '|internal-storage(?:/.*)?'
            . '|portfolio(?:/.*)?'
        )->name('app');
    });


/*
|--------------------------------------------------------------------------
| Admin Portfolio Legacy Redirects
|--------------------------------------------------------------------------
|
| Keep these temporarily so old portfolio URLs continue to work.
|
*/

Route::prefix('admin/portfolio')
    ->middleware([
        'auth',
        'admin',
    ])
    ->name('admin.portfolio.')
    ->group(function () {

        Route::get(
            '/',
            fn () => redirect(
                '/admin/client-portal/portfolio'
            )
        )->name('index');

        Route::post(
            '/translate',
            [PortfolioController::class, 'translate']
        )->name('translate');

        Route::get(
            '/create',
            fn () => redirect(
                '/admin/client-portal/projects/create'
            )
        )->name('create');

        Route::post(
            '/',
            [PortfolioController::class, 'store']
        )->name('store');

        Route::get(
            '/{project}/edit',
            fn (\App\Models\Project $project) =>
                redirect(
                    '/admin/client-portal/projects/'
                    . $project->id
                    . '/portfolio'
                )
        )->name('edit');

        Route::put(
            '/{project}',
            [PortfolioController::class, 'update']
        )->name('update');

        Route::delete(
            '/{project}',
            [PortfolioController::class, 'destroy']
        )->name('destroy');
    });


/*
|--------------------------------------------------------------------------
| Admin / Coworker Shared API
|--------------------------------------------------------------------------
|
| Coworkers can access a restricted version of the Admin project API.
|
*/

Route::prefix('admin/client-portal')
    ->middleware([
        'auth',
        'verified',
        'admin_or_coworker',
    ])
    ->group(function () {

        Route::prefix('coworker-api')->group(function () {

            /*
            |--------------------------------------------------------------------------
            | Lookups
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/lookups',
                LookupController::class
            );

            Route::get(
                '/companies/{company}/contacts/options',
                [LookupController::class, 'contacts']
            );


            /*
            |--------------------------------------------------------------------------
            | Coworkers
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/coworkers',
                [CoworkerController::class, 'index']
            );


            /*
            |--------------------------------------------------------------------------
            | Projects
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/projects',
                [ProjectController::class, 'index']
            );

            Route::get(
                '/projects/{project}',
                [ProjectController::class, 'show']
            );

            Route::put(
                '/projects/{project}',
                [ProjectController::class, 'update']
            );

            Route::put(
                '/projects/{project}/publishing',
                [ProjectController::class, 'publish']
            );


            /*
            |--------------------------------------------------------------------------
            | Project Files
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/projects/{project}/files',
                [ProjectFileController::class, 'index']
            );

            Route::put(
                '/projects/{project}/structure',
                [ProjectFileController::class, 'updateStructure']
            );

            Route::post(
                '/projects/{project}/folders',
                [ProjectFileController::class, 'storeFolder']
            );

            Route::put(
                '/projects/{project}/folders/{folder}',
                [ProjectFileController::class, 'updateFolder']
            );

            Route::post(
                '/projects/{project}/files',
                [ProjectFileController::class, 'upload']
            );

            Route::delete(
                '/projects/{project}/files/{file}',
                [ProjectFileController::class, 'destroy']
            );

            Route::patch(
                '/projects/{project}/files/{file}',
                [ProjectFileController::class, 'rename']
            );

            Route::get(
                '/projects/{project}/files/{file}/download',
                [ProjectFileController::class, 'download']
            );

            Route::get(
                '/projects/{project}/files/{file}/open',
                [ProjectFileController::class, 'open']
            );

            Route::get(
                '/projects/{project}/files/{file}/thumbnail',
                [ProjectFileController::class, 'thumbnail']
            );


            /*
            |--------------------------------------------------------------------------
            | Project Coworkers & Contacts
            |--------------------------------------------------------------------------
            */

            Route::post(
                '/projects/{project}/coworkers',
                [ProjectCoworkerController::class, 'store']
            );

            Route::post(
                '/projects/{project}/coworkers/{user}/resend-invitation',
                [ProjectCoworkerController::class, 'resendCoworkerInvitation']
            );

            Route::post(
                '/projects/{project}/contacts/invite',
                [ProjectCoworkerController::class, 'inviteContact']
            );

            Route::post(
                '/projects/{project}/contacts/{contact}/resend-invitation',
                [ProjectCoworkerController::class, 'resendContactInvitation']
            );


            /*
            |--------------------------------------------------------------------------
            | Project Tickets
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/projects/{project}/tickets',
                [
                    ProjectTicketController::class,
                    'index',
                ]
            )->name(
                'projects.tickets.index'
            );

            Route::post(
                '/projects/{project}/tickets',
                [
                    ProjectTicketController::class,
                    'store',
                ]
            )->name(
                'projects.tickets.store'
            );

            Route::put(
                '/projects/{project}/tickets/{ticket}',
                [
                    ProjectTicketController::class,
                    'update',
                ]
            )->name(
                'projects.tickets.update'
            );

            Route::delete(
                '/projects/{project}/tickets/{ticket}',
                [
                    ProjectTicketController::class,
                    'destroy',
                ]
            )->name(
                'projects.tickets.destroy'
            );


            /*
            |--------------------------------------------------------------------------
            | Ticket Tags
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/projects/{project}/ticket-tags',
                [
                    ProjectTicketController::class,
                    'tags',
                ]
            )->name(
                'projects.ticket-tags.index'
            );

            Route::post(
                '/projects/{project}/ticket-tags',
                [
                    ProjectTicketController::class,
                    'storeTag',
                ]
            )->name(
                'projects.ticket-tags.store'
            );

        });
    });