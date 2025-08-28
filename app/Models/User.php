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
        return $this->teams()->wherePivot('role', 'admin')->exists();
    }

    public function canManageProducts(Team $team): bool
    {
        return $this->isManagerOf($team);
    }

    public function canViewTeam(Team $team): bool
    {
        return $this->isMemberOf($team);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole($role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }
}
