<?php

namespace App\Models;

use App\Models\Concerns\BelongsToMutableBlueprintDraft;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceBlueprintDeliverable extends Model
{
    use BelongsToMutableBlueprintDraft;
    protected $fillable = ['service_blueprint_version_id', 'key', 'name', 'description', 'category', 'requirement_level', 'expected_resource_type', 'client_visible', 'default_selected', 'sort_order'];
    protected function casts(): array { return ['client_visible' => 'boolean', 'default_selected' => 'boolean']; }
    public function blueprintVersion(): BelongsTo { return $this->belongsTo(ServiceBlueprintVersion::class, 'service_blueprint_version_id'); }
}