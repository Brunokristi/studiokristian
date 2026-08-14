<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectFile extends Model
{
    protected $fillable = [
        'project_id', 'project_folder_id', 'file_category_id', 'parent_file_id', 'version', 'original_filename',
        'display_name', 'extension', 'storage_path', 'disk', 'mime_type', 'size', 'checksum', 'visibility', 'uploaded_by',
    ];

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function folder(): BelongsTo { return $this->belongsTo(ProjectFolder::class, 'project_folder_id'); }

    public function isEffectivelyClientVisible(): bool
    {
        return $this->visibility === 'client' && (! $this->folder || $this->folder->isEffectivelyClientVisible());
    }
}