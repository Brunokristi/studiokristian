<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCredential extends Model
{
    protected $fillable = ['service_account_id', 'provider_type', 'external_reference', 'access_instructions', 'client_visible'];
    protected $hidden = ['external_reference'];
    protected function casts(): array { return ['client_visible' => 'boolean']; }
    public function serviceAccount(): BelongsTo { return $this->belongsTo(ServiceAccount::class); }
}