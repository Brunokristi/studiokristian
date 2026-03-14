<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectFeature extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'title',
        'title_translations',
        'description',
        'description_translations',
        'sort_order',
    ];

    protected $casts = [
        'title_translations' => 'array',
        'description_translations' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
