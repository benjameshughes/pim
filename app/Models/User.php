<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Observers\UserObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

#[ObservedBy([UserObserver::class])]
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
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

    // ===== SPATIE PERMISSION HELPER METHODS =====

    /**
     * Check if user has admin role
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Check if user has manager role or higher
     */
    public function isManager(): bool
    {
        return $this->hasAnyRole(['admin', 'manager']);
    }

    /**
     * Get user's primary role display name
     */
    public function getRoleDisplayName(): string
    {
        $primaryRole = $this->roles->first();
        
        return match($primaryRole?->name) {
            'admin' => 'Administrator',
            'manager' => 'Manager', 
            'user' => 'User',
            default => 'No Role Assigned'
        };
    }

    /**
     * Get user's primary role name (highest priority role)
     */
    public function getPrimaryRole(): ?string
    {
        // Return roles in priority order: admin > manager > user
        if ($this->hasRole('admin')) return 'admin';
        if ($this->hasRole('manager')) return 'manager';
        if ($this->hasRole('user')) return 'user';
        
        // Fallback to first role if none of the standard ones
        return $this->roles->first()?->name;
    }

    /**
     * Check if user can manage system (admin permissions)
     */
    public function canManageSystem(): bool
    {
        return $this->can('access-management-area');
    }
}
