<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceAccount extends Model
{
    protected $fillable = [
        'project_id', 'service_name', 'category', 'login_url', 'account_identifier',
        'account_owner', 'billing_owner', 'renewal_responsibility', 'provider',
        'renewal_date', 'notes', 'client_visible',
    ];
    protected function casts(): array { return ['renewal_date' => 'date', 'client_visible' => 'boolean']; }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function credential(): HasOne { return $this->hasOne(ServiceCredential::class); }
}