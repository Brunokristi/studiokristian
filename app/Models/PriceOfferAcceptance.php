<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceOfferAcceptance extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array { return ['accepted_at' => 'immutable_datetime', 'metadata' => 'array']; }

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }
}