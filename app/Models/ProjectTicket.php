<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTicket extends Model
{
    protected $fillable = ['project_id', 'title', 'description', 'status', 'priority', 'created_by_user_id', 'created_by_client_contact_id', 'assigned_to', 'finished_at'];
    protected function casts(): array { return ['finished_at' => 'datetime']; }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function clientCreator(): BelongsTo { return $this->belongsTo(ClientContact::class, 'created_by_client_contact_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
}