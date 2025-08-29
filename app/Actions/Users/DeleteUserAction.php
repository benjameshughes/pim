<?php

namespace App\Actions\Users;

use App\Actions\Base\BaseAction;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * 🗑️ DELETE USER ACTION
 *
 * Safely deletes users with comprehensive validation and audit logging.
 * Prevents admins from deleting themselves and handles soft delete if enabled.
 *
 * Usage: DeleteUserAction::run($user, $force = false)
 */
class DeleteUserAction extends BaseAction
{
    protected bool $useTransactions = true;

    /**
     * Static helper method for easy usage
     */
    public static function run(User $user, bool $force = false): array
    {
        $action = new static;

        return $action->handle($user, $force);
    }

    /**
     * Handle the user deletion
     *
     * @param  mixed  ...$params  - User deletion parameters
     */
    public function handle(...$params): array
    {
        $user = $params[0] ?? null;
        $force = $params[1] ?? false;

        return $this->execute($user, $force);
    }

    /**
     * Execute the delete user action
     */
    protected function performAction(...$params): array
    {
        $user = $params[0] ?? null;
        $force = $params[1] ?? false;

        // Validate inputs
        if (! $user instanceof User) {
            Log::warning('DeleteUserAction: Invalid user provided', ['user' => $user]);

            return $this->failure('Invalid user provided');
        }

        // Prevent users from deleting themselves
        if ($user->id === auth()->id()) {
            Log::warning('DeleteUserAction: User attempted self-deletion', [
                'user_id' => $user->id,
                'attempted_by' => auth()->id(),
            ]);

            return $this->failure('You cannot delete your own account');
        }

        // Prevent deletion of the last admin (unless forced)
        if ($user->isAdmin() && ! $force) {
            $adminCount = User::where('role', 'admin')->count();
            if ($adminCount <= 1) {
                Log::warning('DeleteUserAction: Attempted to delete last admin', [
                    'user_id' => $user->id,
                    'admin_count' => $adminCount,
                ]);

                return $this->failure('Cannot delete the last administrator account');
            }
        }

        try {
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'created_at' => $user->created_at,
                'email_verified_at' => $user->email_verified_at,
            ];

            $deletionType = 'hard';

            // Check if User model uses SoftDeletes trait
            if (method_exists($user, 'forceDelete')) {
                if ($force) {
                    $user->forceDelete();
                    $deletionType = 'force';
                } else {
                    $user->delete(); // Soft delete
                    $deletionType = 'soft';
                }
            } else {
                $user->delete(); // Hard delete
            }

            Log::info('DeleteUserAction: User deleted successfully', [
                'deleted_user' => $userData,
                'deletion_type' => $deletionType,
                'deleted_by' => auth()->id(),
                'force_delete' => $force,
            ]);

            $message = $deletionType === 'soft'
                ? 'User account suspended successfully'
                : 'User account deleted successfully';

            return $this->success($message, [
                'user' => $userData,
                'deletion_type' => $deletionType,
                'can_restore' => $deletionType === 'soft',
            ]);

        } catch (\Exception $e) {
            Log::error('DeleteUserAction: Failed to delete user', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'force' => $force,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->failure('Failed to delete user: '.$e->getMessage());
        }
    }

    /**
     * Check if deletion would leave system without admins
     */
    private function wouldLeaveSystemWithoutAdmins(User $user): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        $adminCount = User::where('role', 'admin')
            ->where('id', '!=', $user->id)
            ->count();

        return $adminCount === 0;
    }

    /**
     * Get user's associated data summary for audit log
     */
    private function getUserDataSummary(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'created_at' => $user->created_at->toISOString(),
            'last_login' => $user->last_login_at?->toISOString(),
            'email_verified' => ! is_null($user->email_verified_at),
        ];
    }
}
