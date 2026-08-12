<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectDeliverable extends Model
{
    protected $fillable = ['project_id', 'source_blueprint_deliverable_id', 'key_snapshot', 'name_snapshot', 'description_snapshot', 'category_snapshot', 'requirement_level', 'expected_resource_type', 'client_visible', 'status', 'completed_at', 'completed_by', 'notes', 'sort_order'];
    protected function casts(): array { return ['client_visible' => 'boolean', 'completed_at' => 'datetime']; }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function sourceDefinition(): BelongsTo { return $this->belongsTo(ServiceBlueprintDeliverable::class, 'source_blueprint_deliverable_id'); }
}