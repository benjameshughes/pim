<?php

namespace App\Observers;

use App\Models\User;

/**
 * 👑 USER OBSERVER
 *
 * Handles automatic admin assignment for the first user (ID=1)
 * and ensures new users default to 'user' role unless specified.
 */
class UserObserver
{
    /**
     * Handle the User "creating" event.
     * This runs BEFORE the user is saved to the database.
     */
    public function creating(User $user): void
    {
        // Set default role if none specified
        if (empty($user->role)) {
            $user->role = 'user';
        }
    }

    /**
     * Handle the User "created" event.
     * This runs AFTER the user is saved and has an ID.
     */
    public function created(User $user): void
    {
        // Check if this is user ID 1 - make them admin
        if ($user->id === 1 && $user->role !== 'admin') {
            $user->update(['role' => 'admin']);
            \Log::info('👑 User ID 1 detected - setting as admin', [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);
        }
    }
}
