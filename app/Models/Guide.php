<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Guide extends Model
{
    protected $fillable = ['project_id', 'title', 'content', 'category', 'client_visible', 'sort_order'];
    protected function casts(): array { return ['client_visible' => 'boolean']; }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
}