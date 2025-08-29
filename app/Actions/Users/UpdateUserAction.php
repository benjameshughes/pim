<?php

namespace App\Actions\Users;

use App\Actions\Base\BaseAction;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * ✏️ UPDATE USER ACTION
 * 
 * Updates user profile information including name, email, and role.
 * Includes validation and prevents self-demotion for admins.
 * 
 * Usage: UpdateUserAction::run($user, 'New Name', 'new@email.com', 'manager')
 */
class UpdateUserAction extends BaseAction
{
    protected bool $useTransactions = true;

    /**
     * Static helper method for easy usage
     */
    public static function run(User $user, ?string $name = null, ?string $email = null, ?string $role = null): array
    {
        $action = new static();
        return $action->handle($user, $name, $email, $role);
    }

    /**
     * Handle the user update
     * 
     * @param mixed ...$params - User update parameters
     */
    public function handle(...$params): array
    {
        $user = $params[0] ?? null;
        $name = $params[1] ?? null;
        $email = $params[2] ?? null;
        $role = $params[3] ?? null;
        
        return $this->execute($user, $name, $email, $role);
    }

    /**
     * Execute the update user action
     */
    protected function performAction(...$params): array
    {
        $user = $params[0] ?? null;
        $name = $params[1] ?? null;
        $email = $params[2] ?? null;
        $role = $params[3] ?? null;

        // Validate inputs
        if (!$user instanceof User) {
            Log::warning('UpdateUserAction: Invalid user provided', ['user' => $user]);
            return $this->failure('Invalid user provided');
        }

        if ($role && !in_array($role, ['admin', 'manager', 'user'])) {
            Log::warning('UpdateUserAction: Invalid role provided', [
                'role' => $role,
                'valid_roles' => ['admin', 'manager', 'user']
            ]);
            return $this->failure('Invalid role. Must be admin, manager, or user');
        }

        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('UpdateUserAction: Invalid email format', ['email' => $email]);
            return $this->failure('Invalid email format');
        }

        // Check for email uniqueness if email is being changed
        if ($email && strtolower(trim($email)) !== strtolower($user->email)) {
            if (User::where('email', strtolower(trim($email)))->where('id', '!=', $user->id)->exists()) {
                Log::warning('UpdateUserAction: Email already exists', [
                    'email' => $email,
                    'user_id' => $user->id
                ]);
                return $this->failure('A user with this email already exists');
            }
        }

        // Prevent admin from demoting themselves
        if ($role && $user->id === auth()->id() && $user->isAdmin() && $role !== 'admin') {
            Log::warning('UpdateUserAction: Admin attempted self-demotion', [
                'user_id' => $user->id,
                'attempted_role' => $role
            ]);
            return $this->failure('You cannot change your own admin role');
        }

        try {
            $changes = [];
            $originalData = [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ];

            // Prepare update data
            $updateData = [];
            
            if ($name && trim($name) !== $user->name) {
                $updateData['name'] = trim($name);
                $changes['name'] = ['from' => $user->name, 'to' => trim($name)];
            }

            if ($email && strtolower(trim($email)) !== strtolower($user->email)) {
                $updateData['email'] = strtolower(trim($email));
                $changes['email'] = ['from' => $user->email, 'to' => strtolower(trim($email))];
            }

            if ($role && $role !== $user->role) {
                $updateData['role'] = $role;
                $changes['role'] = ['from' => $user->role, 'to' => $role];
            }

            // If no changes, return early
            if (empty($updateData)) {
                Log::info('UpdateUserAction: No changes detected', ['user_id' => $user->id]);
                return $this->success('No changes detected', [
                    'user' => $user,
                    'changes_made' => false
                ]);
            }

            // Perform the update
            $user->update($updateData);

            Log::info('UpdateUserAction: User updated successfully', [
                'user_id' => $user->id,
                'changes' => $changes,
                'updated_by' => auth()->id()
            ]);

            return $this->success('User updated successfully', [
                'user' => $user->fresh(),
                'changes' => $changes,
                'changes_made' => true
            ]);

        } catch (\Exception $e) {
            Log::error('UpdateUserAction: Failed to update user', [
                'user_id' => $user->id,
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->failure('Failed to update user: ' . $e->getMessage());
        }
    }

    /**
     * Get the changes summary for logging
     */
    private function getChangesSummary(array $changes): string
    {
        $summary = [];
        
        foreach ($changes as $field => $change) {
            $summary[] = "{$field}: '{$change['from']}' → '{$change['to']}'";
        }
        
        return implode(', ', $summary);
    }
}