<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class BillingTrialResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'status' => $this->status,
            'started_at' => $this->started_at?->toIso8601String(),
            'ends_at' => $this->expires_at?->toIso8601String(),
            'credit_allowance' => $this->credits_allowance,
            'credits_used' => $this->credits_used,
            'credits_remaining' => $this->creditsRemaining(),
        ];
    }
}
