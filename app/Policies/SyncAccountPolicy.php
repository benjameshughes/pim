<?php

namespace App\Policies;

use App\Models\SyncAccount;
use App\Models\User;

class SyncAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-sync-accounts') || $user->hasRole('admin') || $user->hasRole('manager');
    }

    public function view(User $user, SyncAccount $account): bool
    {
        return $user->can('view-sync-accounts') || $user->hasRole('admin') || $user->hasRole('manager');
    }

    public function create(User $user): bool
    {
        return $user->can('create-sync-accounts') || $user->hasRole('admin') || $user->hasRole('manager');
    }

    public function update(User $user, SyncAccount $account): bool
    {
        return $user->can('edit-sync-accounts') || $user->hasRole('admin') || $user->hasRole('manager');
    }

    public function delete(User $user, SyncAccount $account): bool
    {
        return $user->can('delete-sync-accounts') || $user->hasRole('admin') || $user->hasRole('manager');
    }

    public function testConnection(User $user, SyncAccount $account): bool
    {
        return $user->can('test-sync-accounts') || $user->hasRole('admin') || $user->hasRole('manager');
    }
}
