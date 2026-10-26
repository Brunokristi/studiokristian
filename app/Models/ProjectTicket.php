<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

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

    protected static function booted(): void
    {
        static::saving(function (self $ticket) {
            if (blank($ticket->title)) {
                $ticket->title = static::titleFromDescription((string) $ticket->description);
            }
        });
    }

    /**
     * Tickets no longer have a user-entered title; derive a short plain-text
     * summary from the first meaningful line of the Markdown description.
     */
    public static function titleFromDescription(string $description, int $limit = 90): string
    {
        foreach (preg_split('/\R/u', $description) ?: [] as $line) {
            $line = preg_replace('/^\s{0,3}(#{1,6}|>|[-*+]|\d+[.)])\s+/u', '', $line) ?? $line;
            $line = preg_replace('/^\[[ xX]\]\s+/u', '', $line) ?? $line;
            $line = preg_replace('/!?\[([^\]]*)\]\([^)]*\)/u', '$1', $line) ?? $line;
            $line = trim(str_replace(['*', '_', '~', '`'], '', $line));

            if ($line !== '') {
                return Str::limit($line, $limit, '…');
            }
        }

        return 'Ticket';
    }

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