<?php

namespace App\Actions\Users;

use App\Actions\Base\BaseAction;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Maize\MagicLogin\Facades\MagicLink;

/**
 * 👤 CREATE USER ACTION
 * 
 * Creates a new user with role assignment and sends magic login link.
 * Includes comprehensive validation and error handling.
 * 
 * Usage: CreateUserAction::run('John Doe', 'john@example.com', 'manager')
 */
class CreateUserAction extends BaseAction
{
    protected bool $useTransactions = true;

    /**
     * Static helper method for easy usage
     */
    public static function run(string $name, string $email, ?string $role = null, bool $sendWelcomeEmail = true): array
    {
        $action = new static();
        return $action->handle($name, $email, $role, $sendWelcomeEmail);
    }

    /**
     * Handle the user creation
     * 
     * @param mixed ...$params - User creation parameters
     */
    public function handle(...$params): array
    {
        $name = $params[0] ?? null;
        $email = $params[1] ?? null;
        $role = $params[2] ?? null; // Don't default to 'user' - let observer handle it
        $sendWelcomeEmail = $params[3] ?? true;
        
        return $this->execute($name, $email, $role, $sendWelcomeEmail);
    }

    /**
     * Execute the create user action
     */
    protected function performAction(...$params): array
    {
        $name = $params[0] ?? null;
        $email = $params[1] ?? null;
        $role = $params[2] ?? null;
        $sendWelcomeEmail = $params[3] ?? true;

        // Validate inputs
        if (empty($name) || empty($email)) {
            Log::warning('CreateUserAction: Missing required fields', [
                'name' => $name, 
                'email' => $email
            ]);
            return $this->failure('Name and email are required');
        }

        if ($role !== null && !in_array($role, ['admin', 'manager', 'user'])) {
            Log::warning('CreateUserAction: Invalid role provided', [
                'role' => $role,
                'valid_roles' => ['admin', 'manager', 'user']
            ]);
            return $this->failure('Invalid role. Must be admin, manager, or user');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('CreateUserAction: Invalid email format', ['email' => $email]);
            return $this->failure('Invalid email format');
        }

        // Check if user already exists
        if (User::where('email', $email)->exists()) {
            Log::warning('CreateUserAction: User already exists', ['email' => $email]);
            return $this->failure('A user with this email already exists');
        }

        try {
            // Create the user data
            $userData = [
                'name' => trim($name),
                'email' => strtolower(trim($email)),
                'password' => Hash::make(Str::random(32)), // Random password - user will use magic links
                'email_verified_at' => now(), // Pre-verify since admin is creating
            ];
            
            // Add role if explicitly provided, otherwise observer handles it
            if ($role !== null) {
                $userData['role'] = $role;
            }
            
            // Create user - observer automatically handles first-user admin logic
            $user = User::create($userData);
            
            Log::info('CreateUserAction: User created', [
                'role_after_create' => $user->role,
                'user_count_before' => User::count() - 1
            ]);

            // Send welcome email with magic link if requested
            $magicLinkSent = false;
            if ($sendWelcomeEmail) {
                try {
                    MagicLink::send(
                        authenticatable: $user,
                        redirectUrl: route('dashboard'),
                        expiration: now()->addDays(7) // 7 days to first login
                    );
                    $magicLinkSent = true;
                } catch (\Exception $e) {
                    Log::warning('CreateUserAction: Failed to send magic link', [
                        'user_id' => $user->id,
                        'email' => $email,
                        'error' => $e->getMessage()
                    ]);
                    // Don't fail the entire action - user was created successfully
                }
            }

            Log::info('CreateUserAction: User created successfully', [
                'user_id' => $user->id,
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'magic_link_sent' => $magicLinkSent
            ]);

            return $this->success('User created successfully', [
                'user' => $user->fresh(),
                'magic_link_sent' => $magicLinkSent,
                'role_assigned' => $role
            ]);

        } catch (\Exception $e) {
            Log::error('CreateUserAction: Failed to create user', [
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->failure('Failed to create user: ' . $e->getMessage());
        }
    }

    /**
     * Validate email is in allowed domain if restrictions exist
     */
    private function isEmailAllowed(string $email): bool
    {
        // Add your email domain restrictions here if needed
        // For now, allow all valid emails
        return true;
    }

    /**
     * Generate secure temporary password
     */
    private function generateTemporaryPassword(): string
    {
        return Str::password(12, true, true, true, false);
    }
}