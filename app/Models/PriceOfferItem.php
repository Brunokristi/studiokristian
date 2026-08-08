<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceOfferItem extends Model
{
    protected $fillable = [
        'price_offer_id', 'category', 'name', 'description', 'quantity', 'unit',
        'unit_price', 'period', 'total', 'sort_order',
    ];

    public function offer(): BelongsTo { return $this->belongsTo(PriceOffer::class, 'price_offer_id'); }
}