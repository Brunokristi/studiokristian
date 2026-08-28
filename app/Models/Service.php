<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends Model
{
    protected $fillable = [
        'service_product_id',
        'name',
        'name_translations',
        'description',
        'description_translations',
        'active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'name_translations' => 'array',
            'description_translations' => 'array',
        ];
    }

    public function serviceProduct(): BelongsTo
    {
        return $this->belongsTo(
            ServiceProduct::class
        );
    }
}
