<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class MakeAdminUser extends Command
{
    protected $signature = 'user:make-admin {userId=1 : The user ID to promote to admin}';

    protected $description = 'Assign the admin role (Spatie) to a user by ID, creating the role if missing';

    public function handle(): int
    {
        $id = (int) $this->argument('userId');

        /** @var User|null $user */
        $user = User::find($id);

        if (! $user) {
            $this->error("User ID {$id} not found");

            return self::FAILURE;
        }

        // Ensure admin role exists for web guard
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        if ($user->hasRole($role)) {
            $this->info("User {$user->id} ({$user->email}) already has admin role.");

            return self::SUCCESS;
        }

        $user->assignRole($role);

        $this->info("Assigned admin role to user {$user->id} ({$user->email}).");

        return self::SUCCESS;
    }
}

