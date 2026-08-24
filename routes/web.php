<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\Admin\PortfolioAdminController;
use App\Http\Controllers\Admin\ClientPortalAdminController;
use App\Http\Controllers\Admin\ClientPortal\AdminShellController;
use App\Http\Controllers\Admin\ClientPortal\CompanyController as AdminCompanyController;
use App\Http\Controllers\Admin\ClientPortal\ContactController as AdminContactController;
use App\Http\Controllers\Admin\ClientPortal\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ClientPortal\LookupController as AdminLookupController;
use App\Http\Controllers\Admin\ClientPortal\ProjectController as AdminProjectController;
use App\Http\Controllers\Admin\ClientPortal\ServiceProductController as AdminServiceProductController;
use App\Http\Controllers\Admin\ClientPortal\ProjectFileController as AdminProjectFileController;
use App\Http\Controllers\Admin\ClientPortal\ServiceBlueprintController as AdminServiceBlueprintController;
use App\Http\Controllers\Admin\ClientPortal\ServiceBlueprintDocumentController as AdminServiceBlueprintDocumentController;
use App\Http\Controllers\Admin\ClientPortal\ServiceBlueprintFileController as AdminServiceBlueprintFileController;
use App\Http\Controllers\Admin\ClientPortal\ContractAuthoringController as AdminContractAuthoringController;
use App\Http\Controllers\Admin\ClientPortal\ContractClauseController as AdminContractClauseController;
use App\Http\Controllers\Admin\ClientPortal\ProjectDeliverableController as AdminProjectDeliverableController;
use App\Http\Controllers\Admin\ClientPortal\ProjectCoworkerController as AdminProjectCoworkerController;
use App\Http\Controllers\Admin\ClientPortal\ProjectTicketController as AdminProjectTicketController;
use App\Http\Controllers\Admin\ClientPortal\CoworkerController;
use App\Http\Controllers\Admin\ClientPortal\PortalUserController;
use App\Http\Controllers\Admin\ClientPortal\InternalStorageController;
use App\Http\Controllers\Client\Auth\MagicLinkController;
use App\Http\Controllers\Client\ContractController as ClientContractController;
use App\Http\Controllers\Client\ProjectController as ClientProjectController;
use App\Http\Controllers\Client\ProjectDocumentSignatureController as ClientProjectDocumentSignatureController;
use App\Http\Controllers\Client\ProjectFileController as ClientProjectFileController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\PriceOfferController as ClientPriceOfferController;
use App\Http\Controllers\Client\ProjectTicketController as ClientProjectTicketController;
use App\Http\Controllers\Portal\PortalController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StaffWorkspaceController;

// Public API
Route::prefix('api')->group(function () {
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/{url}', [ProjectController::class, 'show']);
});

// Home
Route::get('/', function () {
    return view('apps.public');
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
        Route::get('/files/{file}/open', [ClientProjectFileController::class, 'open'])->name('files.open');
        Route::get('/files/{file}/download', [ClientProjectFileController::class, 'download'])->name('files.download');
        Route::post('/projects/{project}/documents/{folder}/sign', [ClientProjectDocumentSignatureController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('projects.documents.sign');
        Route::post('/projects/{project}/tickets', [ClientProjectTicketController::class, 'store'])->name('tickets.store');
        Route::get('/offers/{offer}', [ClientPriceOfferController::class, 'show'])->name('offers.show');
        Route::post('/offers/{offer}/accept', [ClientPriceOfferController::class, 'accept'])->middleware('throttle:10,1')->name('offers.accept');
        Route::get('/offers/{offer}/download', [ClientPriceOfferController::class, 'download'])->name('offers.download');
        Route::post('/logout', [MagicLinkController::class, 'destroy'])->name('logout');
    });
});

// Redirect dashboard to admin portfolio after login
Route::get('/dashboard', function () {
    $user = request()->user();

    if ($user?->is_admin) {
        return redirect()->route('admin.client-portal.index');
    }

    return redirect('/admin/client-portal/projects');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('/', [PortalController::class, 'index'])->name('index');

    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/me', [PortalController::class, 'me'])->name('me');
        Route::get('/projects', [PortalController::class, 'projects'])->name('projects.index');
        Route::get('/projects/{project}', [PortalController::class, 'showProject'])->name('projects.show');
        Route::get('/projects/{project}/files/{file}/open', [PortalController::class, 'openFile'])->name('projects.files.open');
        Route::get('/projects/{project}/files/{file}/download', [PortalController::class, 'downloadFile'])->name('projects.files.download');
        Route::post('/projects/{project}/tickets', [PortalController::class, 'storeTicket'])->name('projects.tickets.store');
        Route::put('/projects/{project}/tickets/{ticket}', [PortalController::class, 'updateTicket'])->name('projects.tickets.update');
        Route::post('/projects/{project}/documents/{folder}/sign', [PortalController::class, 'signDocument'])->name('projects.documents.sign');
    });
});

Route::middleware(['auth', 'verified'])->prefix('workspace')->name('staff.')->group(function () {
    Route::get('/', [StaffWorkspaceController::class, 'index'])->name('workspace');
    Route::prefix('api')->group(function () {
        Route::get('/projects', [StaffWorkspaceController::class, 'projects'])->name('projects.index');
        Route::post('/projects/{project}/tickets', [StaffWorkspaceController::class, 'storeTicket'])->name('tickets.store');
        Route::put('/projects/{project}/tickets/{ticket}', [StaffWorkspaceController::class, 'updateTicket'])->name('tickets.update');
        Route::get('/projects/{project}/files/{file}', [StaffWorkspaceController::class, 'file'])->name('files.show');
    });
});

Route::prefix('admin/client-portal')->middleware(['auth', 'verified', 'admin_or_coworker'])->group(function () {
    Route::prefix('coworker-api')->group(function () {
        Route::get('/lookups', AdminLookupController::class);
        Route::get('/companies/{company}/contacts/options', [AdminLookupController::class, 'contacts']);
        Route::get('/coworkers', [CoworkerController::class, 'index']);

        Route::get('/projects', [AdminProjectController::class, 'index']);
        Route::get('/projects/{project}', [AdminProjectController::class, 'show']);
        Route::put('/projects/{project}', [AdminProjectController::class, 'update']);
        Route::put('/projects/{project}/publishing', [AdminProjectController::class, 'publish']);

        Route::get('/projects/{project}/files', [AdminProjectFileController::class, 'index']);
        Route::put('/projects/{project}/structure', [AdminProjectFileController::class, 'updateStructure']);
        Route::post('/projects/{project}/folders', [AdminProjectFileController::class, 'storeFolder']);
        Route::put('/projects/{project}/folders/{folder}', [AdminProjectFileController::class, 'updateFolder']);
        Route::post('/projects/{project}/files', [AdminProjectFileController::class, 'upload']);
        Route::delete('/projects/{project}/files/{file}', [AdminProjectFileController::class, 'destroy']);
        Route::patch('/projects/{project}/files/{file}', [AdminProjectFileController::class, 'rename']);
        Route::get('/projects/{project}/files/{file}/download', [AdminProjectFileController::class, 'download']);
        Route::get('/projects/{project}/files/{file}/open', [AdminProjectFileController::class, 'open']);
        Route::get('/projects/{project}/files/{file}/thumbnail', [AdminProjectFileController::class, 'thumbnail']);

        Route::post('/projects/{project}/coworkers', [AdminProjectCoworkerController::class, 'store']);
        Route::post('/projects/{project}/coworkers/{user}/resend-invitation', [AdminProjectCoworkerController::class, 'resendCoworkerInvitation']);
        Route::post('/projects/{project}/contacts/invite', [AdminProjectCoworkerController::class, 'inviteContact']);
        Route::post('/projects/{project}/contacts/{contact}/resend-invitation', [AdminProjectCoworkerController::class, 'resendContactInvitation']);

        Route::get('/projects/{project}/tickets', [AdminProjectTicketController::class, 'index']);
        Route::post('/projects/{project}/tickets', [AdminProjectTicketController::class, 'store']);
        Route::put('/projects/{project}/tickets/{ticket}', [AdminProjectTicketController::class, 'update']);
        Route::delete('/projects/{project}/tickets/{ticket}', [AdminProjectTicketController::class, 'destroy']);
        Route::post('/projects/{project}/deliverables', [AdminProjectDeliverableController::class, 'store']);
        Route::put('/projects/{project}/deliverables/{deliverable}', [AdminProjectDeliverableController::class, 'update']);
    });

    Route::get('/projects', AdminShellController::class);
    Route::get('/projects/create', AdminShellController::class);
    Route::get('/projects/{project}', AdminShellController::class);
    Route::get('/projects/{project}/edit', AdminShellController::class);
});

// Admin Portfolio (protected)
Route::prefix('admin/portfolio')
    ->middleware(['auth', 'admin'])
    ->name('admin.portfolio.')
    ->group(function () {
        Route::get('/', fn () => redirect('/admin/client-portal/portfolio'))->name('index');
        Route::post('/translate', [PortfolioAdminController::class, 'translate'])->name('translate');
        Route::get('/create', fn () => redirect('/admin/client-portal/projects/create'))->name('create');
        Route::post('/', [PortfolioAdminController::class, 'store'])->name('store');
        Route::get('/{project}/edit', fn (\App\Models\Project $project) => redirect('/admin/client-portal/projects/'.$project->id.'/portfolio'))->name('edit');
        Route::put('/{project}', [PortfolioAdminController::class, 'update'])->name('update');
        Route::delete('/{project}', [PortfolioAdminController::class, 'destroy'])->name('destroy');
    });

Route::prefix('admin/client-portal')->middleware(['auth', 'admin'])->name('admin.client-portal.')->group(function () {
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
        Route::get('/portfolio', [PortfolioAdminController::class, 'listing'])->name('portfolio.index');
        Route::get('/projects/{project}/portfolio', [PortfolioAdminController::class, 'show'])->name('projects.portfolio.show');
        Route::put('/projects/{project}/portfolio', [PortfolioAdminController::class, 'update'])->name('projects.portfolio.update');
        Route::post('/portfolio/translate', [PortfolioAdminController::class, 'translate'])->name('portfolio.translate');
        Route::get('/lookups', AdminLookupController::class)->name('lookups');
        Route::get('/companies/{company}/contacts/options', [AdminLookupController::class, 'contacts'])->name('companies.contacts.options');

        Route::get('/clients', [AdminCompanyController::class, 'index'])->name('clients.index');
        Route::post('/clients', [AdminCompanyController::class, 'store'])->name('clients.store');
        Route::get('/clients/{company}', [AdminCompanyController::class, 'show'])->name('clients.show');
        Route::put('/clients/{company}', [AdminCompanyController::class, 'update'])->name('clients.update');
        Route::delete('/clients/{company}', [AdminCompanyController::class, 'destroy'])->name('clients.destroy');
        Route::post('/clients/{company}/archive', [AdminCompanyController::class, 'archive'])->name('clients.archive');
        Route::post('/clients/{company}/contacts', [AdminContactController::class, 'store'])->name('contacts.store');
        Route::put('/clients/{company}/contacts/{contact}', [AdminContactController::class, 'update'])->name('contacts.update');
        Route::post('/clients/{company}/contacts/{contact}/resend-invitation', [AdminContactController::class, 'resendInvitation'])->name('contacts.resend-invitation');
        Route::delete('/clients/{company}/contacts/{contact}', [AdminContactController::class, 'destroy'])->name('contacts.destroy');

        Route::get('/projects', [AdminProjectController::class, 'index'])->name('projects.index');
        Route::post('/projects', [AdminProjectController::class, 'store'])->name('projects.store');
        Route::get('/projects/{project}', [AdminProjectController::class, 'show'])->name('projects.show');
        Route::put('/projects/{project}', [AdminProjectController::class, 'update'])->name('projects.update');
        Route::delete('/projects/{project}', [AdminProjectController::class, 'destroy'])->name('projects.destroy');
        Route::post('/projects/{project}/archive', [AdminProjectController::class, 'archive'])->name('projects.archive');
        Route::put('/projects/{project}/publishing', [AdminProjectController::class, 'publish'])->name('projects.publish');
        Route::get('/coworkers', [CoworkerController::class, 'index'])->name('coworkers.index');
        Route::post('/coworkers', [CoworkerController::class, 'store'])->name('coworkers.store');
        Route::get('/coworkers/{coworker}', [CoworkerController::class, 'show'])->name('coworkers.show');
        Route::put('/coworkers/{coworker}', [CoworkerController::class, 'update'])->name('coworkers.update');
        Route::delete('/coworkers/{coworker}', [CoworkerController::class, 'destroy'])->name('coworkers.destroy');
        Route::get('/portal-users', [PortalUserController::class, 'index'])->name('portal-users.index');
        Route::post('/portal-users', [PortalUserController::class, 'store'])->name('portal-users.store');
        Route::put('/portal-users/{portalUser}', [PortalUserController::class, 'update'])->name('portal-users.update');
        Route::delete('/portal-users/{portalUser}', [PortalUserController::class, 'destroy'])->name('portal-users.destroy');
        Route::get('/internal-storage', [InternalStorageController::class, 'index'])->name('internal-storage.index');
        Route::put('/internal-storage/structure', [InternalStorageController::class, 'updateStructure'])->name('internal-storage.structure.update');
        Route::post('/internal-storage/files', [InternalStorageController::class, 'upload'])->name('internal-storage.files.upload');
        Route::get('/internal-storage/files/{folder}/open', [InternalStorageController::class, 'open'])->name('internal-storage.files.open');
        Route::get('/internal-storage/files/{folder}/download', [InternalStorageController::class, 'download'])->name('internal-storage.files.download');
        Route::get('/projects/{project}/files', [AdminProjectFileController::class, 'index'])->name('projects.files.index');
        Route::put('/projects/{project}/structure', [AdminProjectFileController::class, 'updateStructure'])->name('projects.structure.update');
        Route::post('/projects/{project}/folders', [AdminProjectFileController::class, 'storeFolder'])->name('projects.folders.store');
        Route::put('/projects/{project}/folders/{folder}', [AdminProjectFileController::class, 'updateFolder'])->name('projects.folders.update');
        Route::post('/projects/{project}/files', [AdminProjectFileController::class, 'upload'])->name('projects.files.upload');
        Route::delete('/projects/{project}/files/{file}', [AdminProjectFileController::class, 'destroy'])->name('projects.files.destroy');
        Route::patch('/projects/{project}/files/{file}', [AdminProjectFileController::class, 'rename'])->name('projects.files.rename');
        Route::get('/projects/{project}/files/{file}/download', [AdminProjectFileController::class, 'download'])->name('projects.files.download');
        Route::get('/projects/{project}/files/{file}/open', [AdminProjectFileController::class, 'open'])->name('projects.files.open');
        Route::get('/projects/{project}/files/{file}/thumbnail', [AdminProjectFileController::class, 'thumbnail'])->name('projects.files.thumbnail');
        Route::put('/projects/{project}/files/{file}/move',[AdminProjectFileController::class, 'move'])->name('projects.files.move');
        Route::post('/projects/{project}/coworkers', [AdminProjectCoworkerController::class, 'store'])->name('projects.coworkers.store');
        Route::post('/projects/{project}/coworkers/{user}/resend-invitation', [AdminProjectCoworkerController::class, 'resendCoworkerInvitation'])->name('projects.coworkers.resend-invitation');
        Route::post('/projects/{project}/contacts/invite', [AdminProjectCoworkerController::class, 'inviteContact'])->name('projects.contacts.invite');
        Route::post('/projects/{project}/contacts/{contact}/resend-invitation', [AdminProjectCoworkerController::class, 'resendContactInvitation'])->name('projects.contacts.resend-invitation');
        Route::get('/projects/{project}/tickets', [AdminProjectTicketController::class, 'index'])->name('projects.tickets.index');
        Route::post('/projects/{project}/tickets', [AdminProjectTicketController::class, 'store'])->name('projects.tickets.store');
        Route::put('/projects/{project}/tickets/{ticket}', [AdminProjectTicketController::class, 'update'])->name('projects.tickets.update');
        Route::delete('/projects/{project}/tickets/{ticket}', [AdminProjectTicketController::class, 'destroy'])->name('projects.tickets.destroy');
        Route::post('/projects/{project}/deliverables', [AdminProjectDeliverableController::class, 'store'])->name('projects.deliverables.store');
        Route::put('/projects/{project}/deliverables/{deliverable}', [AdminProjectDeliverableController::class, 'update'])->name('projects.deliverables.update');

        Route::get('/service-products', [AdminServiceProductController::class, 'index'])->name('service-products.index');
        Route::post('/service-products', [AdminServiceProductController::class, 'store'])->name('service-products.store');
        Route::get('/service-products/{serviceProduct}', [AdminServiceProductController::class, 'show'])->name('service-products.show');
        Route::put('/service-products/{serviceProduct}', [AdminServiceProductController::class, 'update'])->name('service-products.update');
        Route::delete('/service-products/{serviceProduct}', [AdminServiceProductController::class, 'destroy'])->name('service-products.destroy');
        Route::post('/service-products/{serviceProduct}/deactivate', [AdminServiceProductController::class, 'deactivate'])->name('service-products.deactivate');
        Route::get('/service-products/{serviceProduct}/blueprint', [AdminServiceBlueprintController::class, 'show'])->name('service-products.blueprint.show');
        Route::post('/service-products/{serviceProduct}/blueprint', [AdminServiceBlueprintController::class, 'create'])->name('service-products.blueprint.create');
        Route::post('/service-products/{serviceProduct}/blueprint/drafts', [AdminServiceBlueprintController::class, 'draft'])->name('service-products.blueprint.draft');
        Route::put('/blueprint-versions/{version}', [AdminServiceBlueprintController::class, 'update'])->name('blueprint-versions.update');
        Route::post('/blueprint-versions/{version}/files', [AdminServiceBlueprintFileController::class, 'upload'])->name('blueprint-versions.files.upload');
        Route::get('/blueprint-folders/{folder}/open', [AdminServiceBlueprintFileController::class, 'open'])->name('blueprint-folders.files.open');
        Route::get('/blueprint-folders/{folder}/download', [AdminServiceBlueprintFileController::class, 'download'])->name('blueprint-folders.files.download');
        Route::put('/blueprint-folders/{folder}/document', [AdminServiceBlueprintDocumentController::class, 'update'])->name('blueprint-folders.document.update');
        Route::post('/blueprint-versions/{version}/publish', [AdminServiceBlueprintController::class, 'publish'])->name('blueprint-versions.publish');
        Route::post('/service-products/{serviceProduct}/contract-template', [AdminContractAuthoringController::class, 'createTemplate'])->name('contract-templates.create');
        Route::post('/contract-templates/{template}/drafts', [AdminContractAuthoringController::class, 'draft'])->name('contract-templates.draft');
        Route::put('/contract-template-versions/{version}', [AdminContractAuthoringController::class, 'update'])->name('contract-template-versions.update');
        Route::post('/contract-template-versions/{version}/publish', [AdminContractAuthoringController::class, 'publish'])->name('contract-template-versions.publish');
        Route::get('/contract-clauses', [AdminContractClauseController::class, 'index'])->name('contract-clauses.index');
        Route::post('/contract-clauses', [AdminContractClauseController::class, 'store'])->name('contract-clauses.store');
        Route::post('/contract-clauses/{clause}/drafts', [AdminContractClauseController::class, 'draft'])->name('contract-clauses.draft');
        Route::put('/contract-clause-versions/{version}', [AdminContractClauseController::class, 'update'])->name('contract-clause-versions.update');
        Route::post('/contract-clause-versions/{version}/publish', [AdminContractClauseController::class, 'publish'])->name('contract-clause-versions.publish');
    });

    Route::get('/', AdminShellController::class)->name('index');

    // Existing later-phase endpoints remain available for compatibility but are not exposed in Phase 1 UI.
    Route::post('/projects/{project}/services', [ClientPortalAdminController::class, 'storeServiceAccount'])->name('services.store');
    Route::delete('/services/{account}', [ClientPortalAdminController::class, 'destroyServiceAccount'])->name('services.destroy');
    Route::post('/templates', [ClientPortalAdminController::class, 'storeTemplate'])->name('templates.store');
    Route::post('/templates/{template}/versions', [ClientPortalAdminController::class, 'createVersion'])->name('versions.store');
    Route::get('/versions/{version}', function (\App\Models\ContractTemplateVersion $version) {
        $productId = $version->template->service_product_id;
        return redirect($productId ? '/admin/client-portal/service-products/'.$productId : '/admin/client-portal/service-products');
    })->name('versions.edit');
    Route::put('/versions/{version}', [ClientPortalAdminController::class, 'updateVersion'])->name('versions.update');
    Route::post('/versions/{version}/publish', [ClientPortalAdminController::class, 'publishVersion'])->name('versions.publish');
    Route::post('/versions/{version}/retire', [ClientPortalAdminController::class, 'retireVersion'])->name('versions.retire');
    Route::post('/projects/{project}/contracts', [ClientPortalAdminController::class, 'generateContract'])->name('contracts.generate');
    Route::get('/{path}', AdminShellController::class)
        ->where('path', 'clients(?:/.*)?|projects(?:/.*)?|service-products(?:/.*)?|coworkers(?:/.*)?|internal-storage(?:/.*)?|portfolio(?:/.*)?')
        ->name('app');
});

require __DIR__.'/auth.php';
// Catch-all route for SPA (must be last)
Route::get('/{any}', function () {
    return view('apps.public');
})->where('any', '^(?!api|admin|storage).*$');
