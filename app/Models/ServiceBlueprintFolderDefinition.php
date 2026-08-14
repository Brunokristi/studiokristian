<?php

namespace App\Models;

use App\Models\Concerns\BelongsToMutableBlueprintDraft;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceBlueprintFolderDefinition extends Model
{
    use BelongsToMutableBlueprintDraft;

    protected $appends = ['open_url', 'download_url'];

    protected $fillable = ['service_blueprint_version_id', 'parent_id', 'type', 'name', 'original_filename', 'extension', 'resource_type', 'requirement_level', 'requires_client_signature', 'template_name', 'content', 'document_revision', 'url', 'disk', 'storage_path', 'mime_type', 'size', 'checksum', 'uploaded_by', 'client_visible', 'sort_order'];
    protected function casts(): array { return ['client_visible' => 'boolean', 'requires_client_signature' => 'boolean', 'document_revision' => 'integer', 'size' => 'integer']; }
    public function blueprintVersion(): BelongsTo { return $this->belongsTo(ServiceBlueprintVersion::class, 'service_blueprint_version_id'); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order'); }

    public function getOpenUrlAttribute(): ?string
    {
        if ($this->type !== 'file' || $this->resource_type !== 'file' || ! $this->storage_path) {
            return null;
        }

        return route('admin.client-portal.api.blueprint-folders.files.open', $this);
    }

    public function getDownloadUrlAttribute(): ?string
    {
        if ($this->type !== 'file' || $this->resource_type !== 'file' || ! $this->storage_path) {
            return null;
        }

        return route('admin.client-portal.api.blueprint-folders.files.download', $this);
    }
}