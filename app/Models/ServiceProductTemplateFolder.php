<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceProductTemplateFolder extends Model
{
    protected $fillable = [
        'service_product_id',
        'parent_id',
        'client_key',
        'type',
        'name',
        'original_filename',
        'extension',
        'resource_type',
        'requirement_level',
        'requires_client_signature',
        'template_name',
        'content',
        'document_revision',
        'url',
        'disk',
        'storage_path',
        'mime_type',
        'size',
        'checksum',
        'uploaded_by',
        'client_visible',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'requires_client_signature' => 'boolean',
            'client_visible' => 'boolean',
            'document_revision' => 'integer',
            'size' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function serviceProduct(): BelongsTo
    {
        return $this->belongsTo(
            ServiceProduct::class
        );
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'parent_id'
        );
    }

    public function children(): HasMany
    {
        return $this->hasMany(
            self::class,
            'parent_id'
        )->orderBy('sort_order');
    }

    public function isFolder(): bool
    {
        return (
            $this->type ?: 'folder'
        ) === 'folder';
    }

    public function isFile(): bool
    {
        return (
            $this->type ?: 'folder'
        ) === 'file';
    }

    public function isDocument(): bool
    {
        return $this->resource_type === 'document';
    }

    public function isLink(): bool
    {
        return $this->resource_type === 'link';
    }

    public function hasStoredFile(): bool
    {
        return filled(
            $this->storage_path
        );
    }
}