<?php

namespace App\Actions\Traits;

use Illuminate\Support\Facades\Gate;

/**
 * 🔐 AUTHORIZATION TRAIT FOR ACTIONS
 *
 * Provides consistent authorization methods for Action classes
 * Following Laravel best practices for permission checking
 */
trait HasAuthorization
{
    /**
     * Authorize a permission or ability
     */
    protected function authorize(string $ability, mixed $arguments = []): void
    {
        if (! auth()->check()) {
            abort(401, 'Authentication required');
        }

        if (! Gate::allows($ability, $arguments)) {
            abort(403, "Insufficient permissions: {$ability}");
        }
    }

    /**
     * Check if user has permission (returns boolean)
     */
    protected function can(string $ability, mixed $arguments = []): bool
    {
        if (! auth()->check()) {
            return false;
        }

        return Gate::allows($ability, $arguments);
    }

    /**
     * Check if user has any of the given permissions
     */
    protected function canAny(array $abilities, mixed $arguments = []): bool
    {
        if (! auth()->check()) {
            return false;
        }

        foreach ($abilities as $ability) {
            if (Gate::allows($ability, $arguments)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Authorize with fallback to role check
     */
    protected function authorizeWithRole(string $permission, string $role): void
    {
        if (! auth()->check()) {
            abort(401, 'Authentication required');
        }

        $user = auth()->user();

        if (! ($user->can($permission) || $user->hasRole($role))) {
            abort(403, "Insufficient permissions: requires {$permission} or {$role} role");
        }
    }
}
