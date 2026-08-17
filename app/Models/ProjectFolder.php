<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectFolder extends Model
{
    use SoftDeletes;
    protected $fillable = ['project_id', 'parent_id', 'source_blueprint_folder_id', 'type', 'name', 'resource_type', 'requirement_level', 'requires_client_signature', 'template_name', 'content', 'url', 'client_visible', 'sort_order', 'created_by'];
    protected function casts(): array { return ['client_visible' => 'boolean', 'requires_client_signature' => 'boolean']; }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order'); }
    public function files(): HasMany { return $this->hasMany(ProjectFile::class); }

    public function signatures(): HasMany { return $this->hasMany(ProjectFolderSignature::class); }

    public function isEffectivelyClientVisible(): bool
    {
        if (! $this->client_visible) return false;
        return $this->parent ? $this->parent->isEffectivelyClientVisible() : true;
    }
}