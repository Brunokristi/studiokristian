<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    private static ?bool $hasRoleColumn = null;
    private static ?bool $hasClientContactColumn = null;
    private static ?bool $hasProjectUserAccessTypeColumn = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'role',
        'client_contact_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'client_contact_id' => 'integer',
        ];
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withTimestamps();
    }

    public function coworkerProjects(): BelongsToMany
    {
        $relation = $this->belongsToMany(Project::class)->withTimestamps();

        if (self::hasProjectUserAccessTypeColumn()) {
            $relation
                ->withPivot('access_type')
                ->where(function ($query) {
                    $query
                        ->where('project_user.access_type', 'coworker')
                        ->orWhereNull('project_user.access_type');
                });
        }

        return $relation;
    }

    public function clientProjects(): BelongsToMany
    {
        $relation = $this->belongsToMany(Project::class)->withTimestamps();

        if (self::hasProjectUserAccessTypeColumn()) {
            $relation
                ->withPivot('access_type')
                ->wherePivot('access_type', 'client');
        } else {
            $relation->whereRaw('1 = 0');
        }

        return $relation;
    }

    public function clientContact(): BelongsTo
    {
        return $this->belongsTo(ClientContact::class);
    }

    public function loginTokens(): HasMany
    {
        return $this->hasMany(StaffLoginToken::class);
    }

    public function portalRole(): string
    {
        if ($this->is_admin) {
            return 'admin';
        }

        if (! self::hasRoleColumn()) {
            return 'coworker';
        }

        return in_array($this->role, ['admin', 'coworker', 'client'], true)
            ? $this->role
            : 'coworker';
    }

    public function isCoworker(): bool
    {
        return $this->portalRole() === 'coworker';
    }

    public function isClient(): bool
    {
        return $this->portalRole() === 'client';
    }

    public function hasProjectAccess(Project $project): bool
    {
        if ($this->portalRole() === 'admin') {
            return true;
        }

        return $this->projects()->whereKey($project->id)->exists();
    }

    public static function hasRoleColumn(): bool
    {
        if (self::$hasRoleColumn === null) {
            self::$hasRoleColumn = Schema::hasColumn('users', 'role');
        }

        return self::$hasRoleColumn;
    }

    public static function hasClientContactColumn(): bool
    {
        if (self::$hasClientContactColumn === null) {
            self::$hasClientContactColumn = Schema::hasColumn('users', 'client_contact_id');
        }

        return self::$hasClientContactColumn;
    }

    public static function hasProjectUserAccessTypeColumn(): bool
    {
        if (self::$hasProjectUserAccessTypeColumn === null) {
            self::$hasProjectUserAccessTypeColumn = Schema::hasColumn('project_user', 'access_type');
        }

        return self::$hasProjectUserAccessTypeColumn;
    }
}
