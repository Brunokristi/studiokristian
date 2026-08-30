<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TicketTag extends Model
{
    protected $fillable = [
        'name',
        'color',
    ];

    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(
            ProjectTicket::class,
            'project_ticket_tag',
            'ticket_tag_id',
            'project_ticket_id'
        );
    }
}