<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $project_id
 * @property string $title
 * @property string $description
 * @property string $status
 * @property string $priority
 * @property int|null $assigned_to
 * @property array<int, array{type: string, id: int}>|null $assignees
 * @property \Illuminate\Support\Carbon|null $deadline
 */
class ProjectTicket extends Model
{
    protected $fillable = [
        'project_id',
        'title',
        'description',
        'status',
        'priority',
        'created_by_user_id',
        'created_by_client_contact_id',
        'assigned_to',
        'assignees',
        'finished_at',
        'deadline',
    ];

    protected function casts(): array
    {
        return [
            'assignees' => 'array',
            'finished_at' => 'datetime',
            'deadline' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id'
        );
    }

    public function clientCreator(): BelongsTo
    {
        return $this->belongsTo(
            ClientContact::class,
            'created_by_client_contact_id'
        );
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'assigned_to'
        );
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            TicketTag::class,
            'project_ticket_tag'
        );
    }
}