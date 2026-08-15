<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyStorageFolder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'parent_id',
        'type',
        'name',
        'original_filename',
        'extension',
        'resource_type',
        'requirement_level',
        'requires_client_signature',
        'template_name',
        'content',
        'url',
        'disk',
        'storage_path',
        'mime_type',
        'size',
        'checksum',
        'uploaded_by',
        'client_visible',
        'sort_order',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'requires_client_signature' => 'boolean',
            'client_visible' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}
