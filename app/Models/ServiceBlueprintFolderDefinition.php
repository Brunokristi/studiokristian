<?php

namespace App\Models;

use App\Models\Concerns\BelongsToMutableBlueprintDraft;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceBlueprintFolderDefinition extends Model
{
    use BelongsToMutableBlueprintDraft;
    protected $fillable = ['service_blueprint_version_id', 'parent_id', 'name', 'client_visible', 'sort_order'];
    protected function casts(): array { return ['client_visible' => 'boolean']; }
    public function blueprintVersion(): BelongsTo { return $this->belongsTo(ServiceBlueprintVersion::class, 'service_blueprint_version_id'); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order'); }
}