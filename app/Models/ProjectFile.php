<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectFile extends Model
{
    protected $fillable = [
        'project_id', 'file_category_id', 'parent_file_id', 'version', 'original_filename',
        'display_name', 'storage_path', 'mime_type', 'size', 'checksum', 'visibility', 'uploaded_by',
    ];

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
}