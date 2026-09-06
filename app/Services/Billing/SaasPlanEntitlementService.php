<?php

namespace App\Services\Billing;

use App\Models\SaasPlan;
use App\Models\SaasPlanFeature;

class SaasPlanEntitlementService
{
    /**
     * Full replace: any feature not present in $entitlements is unassigned from the plan.
     * The feature definition itself is untouched - only the plan's configured value.
     */
    public function sync(SaasPlan $plan, array $entitlements): void
    {
        $seen = [];

        foreach ($entitlements as $row) {
            $featureId = (int) ($row['feature_id'] ?? 0);

            if (! $featureId) {
                continue;
            }

            $planFeature = SaasPlanFeature::query()->updateOrCreate(
                [
                    'saas_plan_id' => $plan->id,
                    'saas_feature_id' => $featureId,
                ],
                [
                    'boolean_value' => $row['boolean_value'] ?? null,
                    'limit_value' => $row['is_unlimited'] ?? false ? null : ($row['limit_value'] ?? null),
                    'is_unlimited' => (bool) ($row['is_unlimited'] ?? false),
                    'is_custom' => (bool) ($row['is_custom'] ?? false),
                ]
            );

            $seen[] = $planFeature->id;
        }

        $plan->planFeatures()
            ->whereNotIn('id', $seen)
            ->delete();
    }
}
