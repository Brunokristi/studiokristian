<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceBlueprint extends Model
{
    protected $fillable = ['service_product_id', 'name'];

    public function serviceProduct(): BelongsTo { return $this->belongsTo(ServiceProduct::class); }
    public function versions(): HasMany { return $this->hasMany(ServiceBlueprintVersion::class); }
}