<?php

namespace App\Actions\Users;

use App\Actions\Base\BaseAction;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * 👑 ASSIGN USER ROLE ACTION
 *
 * Assigns a role to a user in the simplified role-based permission system.
 * Replaces the complex team-based approach with simple user roles.
 *
 * Usage: AssignUserRoleAction::run($user, 'admin')
 */
class AssignUserRoleAction extends BaseAction
{
    protected bool $useTransactions = true;

    /**
     * Static helper method for easy usage
     */
    public static function run(User $user, string $role): array
    {
        $action = new static;

        return $action->handle($user, $role);
    }

    /**
     * Handle the role assignment
     *
     * @param  mixed  ...$params  - User and role parameters
     */
    public function handle(...$params): array
    {
        $user = $params[0] ?? null;
        $role = $params[1] ?? null;

        return $this->execute($user, $role);
    }

    /**
     * Execute the assign user role action
     */
    protected function performAction(...$params): array
    {
        $user = $params[0] ?? null;
        $role = $params[1] ?? null;

        // Validate inputs
        if (! $user instanceof User) {
            Log::warning('AssignUserRoleAction: Invalid user provided', ['user' => $user]);

            return $this->failure('Invalid user provided');
        }

        if (! in_array($role, ['admin', 'manager', 'user'])) {
            Log::warning('AssignUserRoleAction: Invalid role provided', [
                'role' => $role,
                'valid_roles' => ['admin', 'manager', 'user'],
            ]);

            return $this->failure('Invalid role. Must be admin, manager, or user');
        }

        $oldRole = $user->role;

        try {
            // Update user role
            $user->update(['role' => $role]);

            Log::info('AssignUserRoleAction: Role assigned successfully', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'old_role' => $oldRole,
                'new_role' => $role,
            ]);

            return $this->success('Role assigned successfully', [
                'user' => $user->fresh(),
                'old_role' => $oldRole,
                'new_role' => $role,
            ]);

        } catch (\Exception $e) {
            Log::error('AssignUserRoleAction: Failed to assign role', [
                'user_id' => $user->id,
                'role' => $role,
                'error' => $e->getMessage(),
            ]);

            return $this->failure('Failed to assign role: '.$e->getMessage());
        }
    }

    /**
     * Get available roles for the system
     */
    public static function getAvailableRoles(): array
    {
        return [
            'admin' => 'Administrator - Full system access',
            'manager' => 'Manager - Product and operations management',
            'user' => 'User - Basic read access',
        ];
    }
}
