<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffLoginToken extends Model
{
    protected $fillable = ['user_id', 'token_hash', 'expires_at', 'used_at', 'requested_ip'];
    protected $hidden = ['token_hash'];
    protected function casts(): array { return ['expires_at' => 'datetime', 'used_at' => 'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}