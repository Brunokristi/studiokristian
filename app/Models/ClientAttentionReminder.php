<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAttentionReminder extends Model
{
    protected $fillable = [
        'company_id',
        'client_contact_id',
        'action_fingerprint',
        'action_keys',
        'last_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'action_keys' => 'array',
            'last_notified_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(ClientContact::class, 'client_contact_id');
    }
}