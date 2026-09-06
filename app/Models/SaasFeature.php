<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaasFeature extends Model
{
    use HasFactory;

    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_LIMIT = 'limit';

    public const TYPES = [
        self::TYPE_BOOLEAN,
        self::TYPE_LIMIT,
    ];

    protected $fillable = [
        'project_id',
        'key',
        'name',
        'description',
        'type',
        'unit',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function planValues(): HasMany
    {
        return $this->hasMany(SaasPlanFeature::class);
    }
}
