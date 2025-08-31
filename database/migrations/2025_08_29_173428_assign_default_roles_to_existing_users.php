<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Assign default roles to existing users:
     * - First user (likely the owner) gets admin role
     * - Other users get 'user' role by default
     */
    public function up(): void
    {
        // Get all users that don't have a role assigned
        $users = User::whereNull('role')->orderBy('created_at', 'asc')->get();

        if ($users->isEmpty()) {
            return; // No users to update
        }

        // First user becomes admin
        $firstUser = $users->first();
        $firstUser->update(['role' => 'admin']);

        // Remaining users get 'user' role
        if ($users->count() > 1) {
            $remainingUserIds = $users->skip(1)->pluck('id')->toArray();
            User::whereIn('id', $remainingUserIds)->update(['role' => 'user']);
        }

        // Log what happened
        \Log::info('Role assignment migration completed', [
            'total_users' => $users->count(),
            'admin_assigned' => $firstUser->email,
            'users_assigned' => $users->count() - 1,
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * Remove all role assignments (set back to null)
     */
    public function down(): void
    {
        User::whereNotNull('role')->update(['role' => null]);

        \Log::info('Role assignment migration reversed - all roles set to null');
    }
};
