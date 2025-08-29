<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
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
            'role' => 'string',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function hasTeamRole(Team $team, string $role): bool
    {
        return $this->teams()
            ->wherePivot('team_id', $team->id)
            ->wherePivot('role', $role)
            ->exists();
    }

    public function isAdminOf(Team $team): bool
    {
        return $this->hasTeamRole($team, 'admin');
    }

    public function isManagerOf(Team $team): bool
    {
        return $this->hasTeamRole($team, 'manager') || $this->isAdminOf($team);
    }

    public function isMemberOf(Team $team): bool
    {
        return $this->teams()->where('team_id', $team->id)->exists();
    }

    public function canManageTeam(Team $team): bool
    {
        return $this->isAdminOf($team);
    }

    public function canManageAnyTeam(): bool
    {
        return $this->isAdmin();
    }

    public function canManageProducts(Team $team): bool
    {
        return $this->isManager() || $this->isAdmin();
    }

    public function canViewTeam(Team $team): bool
    {
        return $this->hasRole();
    }

    // ===== NEW SIMPLE ROLE-BASED METHODS =====

    /**
     * Check if user has admin role
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user has manager role or higher
     */
    public function isManager(): bool
    {
        return in_array($this->role, ['admin', 'manager']);
    }

    /**
     * Check if user has any role assigned
     */
    public function hasRole(): bool
    {
        return !empty($this->role);
    }

    /**
     * Check if user has specific role
     */
    public function hasSpecificRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Get user's role display name
     */
    public function getRoleDisplayName(): string
    {
        return match($this->role) {
            'admin' => 'Administrator',
            'manager' => 'Manager',
            'user' => 'User',
            default => 'Unassigned'
        };
    }

    // ===== LEGACY TEAM-BASED ROLE METHODS (Keep for backward compatibility) =====
    // NOTE: These methods are deprecated in favor of simple role column
    
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * @deprecated Use hasSpecificRole() instead
     */
    public function hasRoleModel($role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }
}
