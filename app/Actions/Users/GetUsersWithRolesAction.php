<?php

namespace App\Actions\Users;

use App\Actions\Base\BaseAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * 👥 GET USERS WITH ROLES ACTION
 *
 * Retrieves users with their assigned roles for management interface.
 * Supports filtering and sorting for role management UI.
 *
 * Usage: GetUsersWithRolesAction::run(['role' => 'admin'])
 */
class GetUsersWithRolesAction extends BaseAction
{
    protected bool $useTransactions = false;

    /**
     * Static helper method for easy usage
     */
    public static function run(array $filters = []): array
    {
        $action = new static;

        return $action->handle($filters);
    }

    /**
     * Handle the get users with roles
     *
     * @param  mixed  ...$params  - Optional filters for the query
     */
    public function handle(...$params): array
    {
        $filters = $params[0] ?? [];

        return $this->execute($filters);
    }

    /**
     * Execute the get users with roles action
     */
    protected function performAction(...$params): array
    {
        $filters = $params[0] ?? [];

        try {
            $query = User::query()
                ->with('roles')
                ->select(['id', 'name', 'email', 'email_verified_at', 'created_at']);

            // Apply role filter
            if (isset($filters['role']) && ! empty($filters['role'])) {
                $query->whereHas('roles', function ($q) use ($filters) {
                    $q->where('name', $filters['role']);
                });
            }

            // Apply search filter
            if (isset($filters['search']) && ! empty($filters['search'])) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Apply sorting
            $sortBy = $filters['sort_by'] ?? 'name';
            $sortDirection = $filters['sort_direction'] ?? 'asc';

            if (in_array($sortBy, ['name', 'email', 'created_at'])) {
                $query->orderBy($sortBy, $sortDirection);
            }

            $users = $query->get();

            // Get role statistics
            $roleStats = $this->getRoleStatistics();

            Log::info('GetUsersWithRolesAction: Retrieved users successfully', [
                'total_users' => $users->count(),
                'filters_applied' => $filters,
                'role_stats' => $roleStats,
            ]);

            return $this->success('Users retrieved successfully', [
                'users' => $users,
                'role_statistics' => $roleStats,
                'available_roles' => AssignUserRoleAction::getAvailableRoles(),
                'filters_applied' => $filters,
            ]);

        } catch (\Exception $e) {
            Log::error('GetUsersWithRolesAction: Failed to retrieve users', [
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);

            return $this->failure('Failed to retrieve users: '.$e->getMessage());
        }
    }

    /**
     * Get role distribution statistics
     */
    private function getRoleStatistics(): array
    {
        try {
            // Get role statistics using Spatie
            $adminCount = User::role('admin')->count();
            $managerCount = User::role('manager')->count();
            $userCount = User::role('user')->count();
            $totalUsers = User::count();
            $unassignedCount = $totalUsers - ($adminCount + $managerCount + $userCount);

            return [
                'admin' => $adminCount,
                'manager' => $managerCount,
                'user' => $userCount,
                'unassigned' => $unassignedCount,
            ];

        } catch (\Exception $e) {
            Log::warning('GetUsersWithRolesAction: Failed to get role statistics', [
                'error' => $e->getMessage(),
            ]);

            return [
                'admin' => 0,
                'manager' => 0,
                'user' => 0,
                'unassigned' => 0,
            ];
        }
    }

    /**
     * Get users by specific role
     */
    public static function getUsersByRole(string $role): Collection
    {
        return User::role($role)->get();
    }

    /**
     * Get unassigned users (no role set)
     */
    public static function getUnassignedUsers(): Collection
    {
        return User::doesntHave('roles')->get();
    }
}
