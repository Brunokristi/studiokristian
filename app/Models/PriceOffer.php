<?php

namespace App\Models;

use LogicException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PriceOffer extends Model
{
    protected $fillable = [
        'project_id', 'number', 'version', 'status', 'valid_until', 'currency',
        'subtotal', 'tax', 'total', 'rendered_content', 'content_hash', 'pdf_path',
        'final_pdf_path', 'final_pdf_hash', 'sent_at', 'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'valid_until' => 'date', 'sent_at' => 'datetime', 'accepted_at' => 'datetime',
            'subtotal' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $offer): void {
            if ($offer->getOriginal('status') === 'accepted') {
                throw new LogicException('Accepted price offers are immutable.');
            }
        });
        static::deleting(fn (self $offer) => $offer->status !== 'accepted');
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function items(): HasMany { return $this->hasMany(PriceOfferItem::class)->orderBy('sort_order'); }
    public function acceptance(): HasOne { return $this->hasOne(PriceOfferAcceptance::class); }
}