<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class ClientContact extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'company_id', 'first_name', 'last_name', 'email', 'phone', 'position',
        'active', 'can_access_portal', 'can_accept_documents', 'access_revoked_at',
    ];

    protected $hidden = ['remember_token'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'can_access_portal' => 'boolean',
            'can_accept_documents' => 'boolean',
            'access_revoked_at' => 'datetime',
        ];
    }

    public function getNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withTimestamps();
    }

    public function loginTokens(): HasMany
    {
        return $this->hasMany(ClientLoginToken::class);
    }

    public function hasPortalAccess(): bool
    {
        return $this->active && $this->can_access_portal && $this->access_revoked_at === null;
    }
}