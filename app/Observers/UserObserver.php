<?php

namespace App\Observers;

use App\Models\User;

/**
 * 👑 USER OBSERVER - SPATIE PERMISSIONS VERSION
 *
 * Handles automatic admin role assignment for the first user (ID=1)
 * using Spatie Laravel Permission package.
 */
class UserObserver
{
    /**
     * Handle the User "created" event.
     * This runs AFTER the user is saved and has an ID.
     */
    public function created(User $user): void
    {
        // Check if this is user ID 1 - assign admin role
        if ($user->id === 1 && !$user->hasRole('admin')) {
            $user->assignRole('admin');
            \Log::info('👑 User ID 1 detected - assigning admin role', [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);
        } elseif ($user->id !== 1 && !$user->hasAnyRole()) {
            // Assign default 'user' role to non-admin users
            $user->assignRole('user');
            \Log::info('👤 Assigning default user role', [
                'user_id' => $user->id,
                'name' => $user->name,
            ]);
        }
    }
}
