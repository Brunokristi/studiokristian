<?php

namespace App\Services\Billing;

use App\Models\Company;
use App\Models\CompanyTrial;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ApplicationTrialService
{
    public function startFor(Company $company, Project $project): ?CompanyTrial
    {
        if (! $project->trial_enabled) {
            return null;
        }

        $trial = CompanyTrial::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'project_id' => $project->id,
            ],
            [
                'status' => CompanyTrial::STATUS_ACTIVE,
                'started_at' => now(),
                'expires_at' => now()->addDays($project->trial_duration_days),
                'credits_allowance' => $project->trial_credits,
                'credits_used' => 0,
            ]
        );

        return $this->expireIfNeeded($trial);
    }

    public function consume(CompanyTrial $trial, int $credits): CompanyTrial
    {
        if ($credits < 1) {
            throw new RuntimeException('Trial credit usage must be positive.');
        }

        return DB::transaction(function () use ($trial, $credits) {
            $trial = CompanyTrial::query()
                ->lockForUpdate()
                ->findOrFail($trial->id);

            $this->expireIfNeeded($trial);

            if (! $trial->isActive()) {
                throw new RuntimeException('The application trial is no longer active.');
            }

            if ($credits > $trial->creditsRemaining()) {
                throw new RuntimeException('The application trial has insufficient credits.');
            }

            $trial->increment('credits_used', $credits);

            return $trial->fresh();
        });
    }

    public function expireIfNeeded(CompanyTrial $trial): CompanyTrial
    {
        if (
            $trial->status === CompanyTrial::STATUS_ACTIVE &&
            ! $trial->expires_at?->isFuture()
        ) {
            $trial->update([
                'status' => CompanyTrial::STATUS_EXPIRED,
            ]);
        }

        return $trial;
    }

    public function forCompany(Company $company, Project $project): ?CompanyTrial
    {
        $trial = CompanyTrial::query()
            ->where('company_id', $company->id)
            ->where('project_id', $project->id)
            ->first();

        return $trial
            ? $this->expireIfNeeded($trial)
            : null;
    }

    public function markConverted(Company $company, Project $project): void
    {
        CompanyTrial::query()
            ->where('company_id', $company->id)
            ->where('project_id', $project->id)
            ->where('status', CompanyTrial::STATUS_ACTIVE)
            ->update([
                'status' => CompanyTrial::STATUS_CONVERTED,
                'converted_at' => now(),
            ]);
    }
}
