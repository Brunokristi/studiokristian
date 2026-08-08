<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    private const SENSITIVE_KEYS = [
        'password', 'token', 'secret', 'credential', 'authorization', 'cookie',
    ];

    public function record(
        string $event,
        ?Model $actor = null,
        ?Model $subject = null,
        ?int $companyId = null,
        ?int $projectId = null,
        array $metadata = [],
        ?Request $request = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
            'company_id' => $companyId,
            'project_id' => $projectId,
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $this->redact($metadata),
        ]);
    }

    private function redact(array $metadata): array
    {
        foreach ($metadata as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if (collect(self::SENSITIVE_KEYS)->contains(fn (string $sensitive) => str_contains($normalizedKey, $sensitive))) {
                $metadata[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $metadata[$key] = $this->redact($value);
            }
        }

        return $metadata;
    }
}