<?php
/**
 * 🎯 POST-DEPLOYMENT SETUP SCRIPT
 * 
 * Run this script after successful deployment to assign proper roles to users.
 * Usage: php artisan tinker < post_deployment_setup.php
 */

echo "🎯 Post-Deployment Role Assignment\n";
echo "==================================\n\n";

// Get all users and show their current state
$users = \App\Models\User::with('roles')->get();

echo "📊 Current User Status:\n";
echo "-----------------------\n";
foreach ($users as $user) {
    $roles = $user->roles->pluck('name')->join(', ') ?: 'No roles';
    echo "  User {$user->id}: {$user->name} ({$user->email}) - Roles: {$roles}\n";
}
echo "\n";

echo "🔧 Role Assignment Commands:\n";
echo "----------------------------\n";
echo "// Copy and paste these commands in tinker to assign roles:\n\n";

foreach ($users as $user) {
    if ($user->id === 1) {
        echo "// User {$user->id} ({$user->email}) - Should be admin (auto-assigned)\n";
    } else {
        echo "// User {$user->id} ({$user->email}) - Assign role as needed:\n";
        echo "User::find({$user->id})->assignRole('admin');    // For admin access\n";
        echo "User::find({$user->id})->assignRole('manager');  // For manager access\n";
        echo "// User::find({$user->id})->assignRole('user'); // Already has user role\n\n";
    }
}

echo "🎉 Available Roles & Permissions:\n";
echo "----------------------------------\n";
$roles = \Spatie\Permission\Models\Role::with('permissions')->get();
foreach ($roles as $role) {
    echo "  {$role->name}: {$role->permissions->count()} permissions\n";
}

echo "\n✅ Setup complete! Assign roles as needed above.\n";