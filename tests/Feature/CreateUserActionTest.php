<?php

use App\Actions\Users\CreateUserAction;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Maize\MagicLogin\Facades\MagicLink;

beforeEach(function () {
    // Create an admin user for authorization
    $this->admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);
    
    $this->actingAs($this->admin);
});

describe('CreateUserAction', function () {
    it('successfully creates a user with valid data', function () {
        $result = CreateUserAction::run(
            name: 'John Doe',
            email: 'john@example.com',
            role: 'manager',
            sendWelcomeEmail: false
        );

        expect($result['success'])->toBeTrue();
        expect($result['message'])->toBe('User created successfully');
        expect($result['data']['user'])->toBeInstanceOf(User::class);
        expect($result['data']['user']->name)->toBe('John Doe');
        expect($result['data']['user']->email)->toBe('john@example.com');
        expect($result['data']['user']->role)->toBe('manager');
        expect($result['data']['user']->email_verified_at)->not->toBeNull();
        expect($result['data']['magic_link_sent'])->toBeFalse();
        expect($result['data']['role_assigned'])->toBe('manager');
    });

    it('creates user with default user role when no role specified', function () {
        $result = CreateUserAction::run(
            name: 'Jane Doe',
            email: 'jane@example.com'
        );

        expect($result['success'])->toBeTrue();
        expect($result['data']['user']->role)->toBe('user');
    });

    it('pre-verifies email for admin-created users', function () {
        $result = CreateUserAction::run(
            name: 'Test User',
            email: 'test@example.com',
            role: 'user'
        );

        expect($result['success'])->toBeTrue();
        expect($result['data']['user']->email_verified_at)->not->toBeNull();
    });

    it('generates random password for new users', function () {
        $result = CreateUserAction::run(
            name: 'Password Test',
            email: 'password@example.com',
            role: 'user'
        );

        expect($result['success'])->toBeTrue();
        $user = $result['data']['user'];
        expect($user->password)->not->toBeEmpty();
        expect(Hash::check('password', $user->password))->toBeFalse(); // Not the default password
    });

    it('fails with empty name', function () {
        $result = CreateUserAction::run(
            name: '',
            email: 'test@example.com',
            role: 'user'
        );

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toBe('Name and email are required');
    });

    it('fails with empty email', function () {
        $result = CreateUserAction::run(
            name: 'Test User',
            email: '',
            role: 'user'
        );

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toBe('Name and email are required');
    });

    it('fails with invalid email format', function () {
        $result = CreateUserAction::run(
            name: 'Test User',
            email: 'invalid-email',
            role: 'user'
        );

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toBe('Invalid email format');
    });

    it('fails with invalid role', function () {
        $result = CreateUserAction::run(
            name: 'Test User',
            email: 'test@example.com',
            role: 'invalid-role'
        );

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toBe('Invalid role. Must be admin, manager, or user');
    });

    it('fails when email already exists', function () {
        // Create first user
        User::factory()->create(['email' => 'existing@example.com']);

        $result = CreateUserAction::run(
            name: 'Test User',
            email: 'existing@example.com',
            role: 'user'
        );

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toBe('A user with this email already exists');
    });

    it('accepts all valid roles', function () {
        $validRoles = ['admin', 'manager', 'user'];

        foreach ($validRoles as $role) {
            $result = CreateUserAction::run(
                name: "Test {$role}",
                email: "test-{$role}@example.com",
                role: $role
            );

            expect($result['success'])->toBeTrue();
            expect($result['data']['user']->role)->toBe($role);
        }
    });

    it('normalizes email to lowercase', function () {
        $result = CreateUserAction::run(
            name: 'Test User',
            email: 'TEST@EXAMPLE.COM',
            role: 'user'
        );

        expect($result['success'])->toBeTrue();
        expect($result['data']['user']->email)->toBe('test@example.com');
    });

    it('trims whitespace from name', function () {
        $result = CreateUserAction::run(
            name: '  John Doe  ',
            email: 'john@example.com',
            role: 'user'
        );

        expect($result['success'])->toBeTrue();
        expect($result['data']['user']->name)->toBe('John Doe');
    });

    it('handles magic link sending gracefully when it fails', function () {
        // Mock MagicLink to throw an exception
        MagicLink::shouldReceive('send')->andThrow(new Exception('Mail service unavailable'));

        $result = CreateUserAction::run(
            name: 'Test User',
            email: 'test@example.com',
            role: 'user',
            sendWelcomeEmail: true
        );

        // User should still be created successfully even if magic link fails
        expect($result['success'])->toBeTrue();
        expect($result['data']['magic_link_sent'])->toBeFalse();
    });
});