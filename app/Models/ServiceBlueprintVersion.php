<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ServiceBlueprintVersion extends Model
{
    protected $fillable = ['service_blueprint_id', 'version', 'status', 'change_summary', 'published_at', 'retired_at', 'created_by'];

    protected function casts(): array { return ['published_at' => 'datetime', 'retired_at' => 'datetime']; }

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if (in_array($version->getOriginal('status'), ['published', 'retired'], true)
                && array_diff(array_keys($version->getDirty()), ['status', 'retired_at', 'updated_at']) !== []) {
                throw new LogicException('Published service blueprint versions are immutable.');
            }
        });
        static::deleting(fn (self $version) => $version->status === 'draft'
            ?: throw new LogicException('Published service blueprint versions cannot be deleted.'));
    }

    public function blueprint(): BelongsTo { return $this->belongsTo(ServiceBlueprint::class, 'service_blueprint_id'); }
    public function fields(): HasMany { return $this->hasMany(ServiceBlueprintField::class)->orderBy('sort_order'); }
    public function deliverables(): HasMany { return $this->hasMany(ServiceBlueprintDeliverable::class)->orderBy('sort_order'); }
    public function folders(): HasMany { return $this->hasMany(ServiceBlueprintFolderDefinition::class)->orderBy('sort_order'); }
    public function projects(): HasMany { return $this->hasMany(Project::class); }
}