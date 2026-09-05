<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class CompanyTrial extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CONVERTED = 'converted';

    protected $fillable = [
        'company_id',
        'project_id',
        'status',
        'started_at',
        'expires_at',
        'credits_allowance',
        'credits_used',
        'converted_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'converted_at' => 'datetime',
        'credits_allowance' => 'integer',
        'credits_used' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE &&
            $this->expires_at?->isFuture();
    }

    public function creditsRemaining(): int
    {
        return max(
            0,
            $this->credits_allowance - $this->credits_used
        );
    }
}
