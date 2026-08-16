<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectFolderSignature extends Model
{
    protected $fillable = [
        'project_id',
        'project_folder_id',
        'user_id',
        'signed_at',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'signed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(ProjectFolder::class, 'project_folder_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
