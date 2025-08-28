<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->teams()->exists();
    }

    public function view(User $user, Team $team): bool
    {
        return $user->canViewTeam($team);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Team $team): bool
    {
        return $user->canManageTeam($team);
    }

    public function delete(User $user, Team $team): bool
    {
        return $user->canManageTeam($team);
    }

    public function manageMembers(User $user, Team $team): bool
    {
        return $user->canManageTeam($team);
    }

    public function invite(User $user, Team $team): bool
    {
        return $user->canManageTeam($team);
    }

    public function removeUser(User $user, Team $team, User $targetUser): bool
    {
        return $user->canManageTeam($team) && $user->id !== $targetUser->id;
    }

    public function changeRole(User $user, Team $team, User $targetUser): bool
    {
        return $user->canManageTeam($team) && $user->id !== $targetUser->id;
    }
}
