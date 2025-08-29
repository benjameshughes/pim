<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * 👑 CREATE ADMIN USER COMMAND
 * 
 * Interactive command to create admin users safely.
 * Useful as a fallback or for creating additional admins.
 * 
 * Usage: php artisan user:create-admin
 */
class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'user:create-admin
                            {--name= : Admin user name}
                            {--email= : Admin user email}
                            {--password= : Admin user password}
                            {--force : Skip confirmation prompts}';

    /**
     * The console command description.
     */
    protected $description = '👑 Create a new admin user interactively';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Admin User Creation Tool');
        $this->line('');

        // Show current admin count
        $adminCount = User::where('role', 'admin')->count();
        $this->info("📊 Current admin users: {$adminCount}");
        
        if ($adminCount > 0) {
            $this->warn('⚠️  Admin users already exist in the system.');
            if (!$this->option('force') && !$this->confirm('Continue creating another admin?')) {
                return self::SUCCESS;
            }
        }

        // Get user input
        $name = $this->option('name') ?: $this->ask('👤 Admin name');
        $email = $this->option('email') ?: $this->ask('📧 Admin email');
        $password = $this->option('password') ?: $this->secret('🔒 Admin password');

        // Validate input
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
        ]);

        if ($validator->fails()) {
            $this->error('❌ Validation failed:');
            foreach ($validator->errors()->all() as $error) {
                $this->error("  • {$error}");
            }
            return self::FAILURE;
        }

        // Confirm before creation
        if (!$this->option('force')) {
            $this->line('');
            $this->info('📝 Admin user details:');
            $this->line("  Name: {$name}");
            $this->line("  Email: {$email}");
            $this->line("  Role: admin");
            $this->line('');
            
            if (!$this->confirm('Create this admin user?')) {
                $this->info('🛑 Admin creation cancelled.');
                return self::SUCCESS;
            }
        }

        try {
            // Create admin user (will trigger observer but role is already set)
            $admin = User::create([
                'name' => $name,
                'email' => $email,
                'role' => 'admin', // Explicitly set as admin
                'password' => Hash::make($password),
                'email_verified_at' => now(), // Pre-verify admin users
            ]);

            $this->line('');
            $this->info('✅ Admin user created successfully!');
            $this->line('');
            $this->table(
                ['Field', 'Value'],
                [
                    ['ID', $admin->id],
                    ['Name', $admin->name],
                    ['Email', $admin->email],
                    ['Role', $admin->role],
                    ['Verified', $admin->email_verified_at ? 'Yes' : 'No'],
                ]
            );

            $this->line('');
            $this->info('🎯 Admin can now access:');
            $this->line('  • /management/users - User management');
            $this->line('  • /management/user-roles - Role management');
            $this->line('  • All admin-protected features');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Failed to create admin user:');
            $this->error("  {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * Get suggested admin details for development
     */
    private function getDevelopmentDefaults(): array
    {
        return [
            'name' => 'System Administrator',
            'email' => 'admin@' . parse_url(config('app.url'), PHP_URL_HOST),
            'password' => 'admin123',
        ];
    }

    /**
     * Show admin creation summary
     */
    private function showSystemSummary(): void
    {
        $this->line('');
        $this->info('📊 System Summary:');
        
        $stats = User::selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN role = "admin" THEN 1 ELSE 0 END) as admins,
            SUM(CASE WHEN role = "manager" THEN 1 ELSE 0 END) as managers,
            SUM(CASE WHEN role = "user" THEN 1 ELSE 0 END) as users
        ')->first();

        $this->table(
            ['Role', 'Count'],
            [
                ['Admins', $stats->admins ?? 0],
                ['Managers', $stats->managers ?? 0],
                ['Users', $stats->users ?? 0],
                ['Total', $stats->total ?? 0],
            ]
        );
    }
}