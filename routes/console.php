<?php

use App\Models\Company;
use App\Services\ClientAttentionService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (ClientAttentionService $attention): void {
    Company::query()
        ->where('status', 'active')
        ->with(['billingContact', 'contacts', 'projects.folders'])
        ->chunkById(100, fn ($companies) => $companies->each(
            fn (Company $company) => $attention->notifyCompany($company)
        ));
})->name('client-attention-reminders')->hourly()->withoutOverlapping();
