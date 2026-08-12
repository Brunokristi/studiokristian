<?php

namespace App\Models;

use App\Models\Concerns\BelongsToMutableBlueprintDraft;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceBlueprintField extends Model
{
    use BelongsToMutableBlueprintDraft;
    protected $fillable = ['service_blueprint_version_id', 'key', 'label', 'description', 'type', 'required', 'default_value', 'options', 'section', 'sort_order'];
    protected function casts(): array { return ['required' => 'boolean', 'default_value' => 'json', 'options' => 'array']; }
    public function blueprintVersion(): BelongsTo { return $this->belongsTo(ServiceBlueprintVersion::class, 'service_blueprint_version_id'); }
}