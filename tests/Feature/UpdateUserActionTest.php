<?php

use App\Actions\Users\UpdateUserAction;
use App\Models\User;

beforeEach(function () {
    // Create an admin user for authorization
    $this->admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($this->admin);

    // Create a test user to update
    $this->testUser = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
        'role' => 'user',
    ]);
});

describe('UpdateUserAction', function () {
    it('successfully updates user name', function () {
        $result = UpdateUserAction::run(
            user: $this->testUser,
            name: 'Updated Name'
        );

        expect($result['success'])->toBeTrue();
        expect($result['message'])->toBe('User updated successfully');
        expect($result['data']['user']->name)->toBe('Updated Name');
        expect($result['data']['changes_made'])->toBeTrue();
        expect($result['data']['changes'])->toHaveKey('name');
        expect($result['data']['changes']['name']['from'])->toBe('Original Name');
        expect($result['data']['changes']['name']['to'])->toBe('Updated Name');
    });

    it('successfully updates user email', function () {
        $result = UpdateUserAction::run(
            user: $this->testUser,
            email: 'updated@example.com'
        );

        expect($result['success'])->toBeTrue();
        expect($result['data']['user']->email)->toBe('updated@example.com');
        expect($result['data']['changes_made'])->toBeTrue();
        expect($result['data']['changes'])->toHaveKey('email');
        expect($result['data']['changes']['email']['from'])->toBe('original@example.com');
        expect($result['data']['changes']['email']['to'])->toBe('updated@example.com');
    });

    it('successfully updates user role', function () {
        $result = UpdateUserAction::run(
            user: $this->testUser,
            role: 'manager'
        );

        expect($result['success'])->toBeTrue();
        expect($result['data']['user']->role)->toBe('manager');
        expect($result['data']['changes_made'])->toBeTrue();
        expect($result['data']['changes'])->toHaveKey('role');
        expect($result['data']['changes']['role']['from'])->toBe('user');
        expect($result['data']['changes']['role']['to'])->toBe('manager');
    });

    it('successfully updates multiple fields at once', function () {
        $result = UpdateUserAction::run(
            user: $this->testUser,
            name: 'Multi Update',
            email: 'multi@example.com',
            role: 'manager'
        );

        expect($result['success'])->toBeTrue();
        expect($result['data']['user']->name)->toBe('Multi Update');
        expect($result['data']['user']->email)->toBe('multi@example.com');
        expect($result['data']['user']->role)->toBe('manager');
        expect($result['data']['changes_made'])->toBeTrue();
        expect($result['data']['changes'])->toHaveKeys(['name', 'email', 'role']);
    });

    it('returns no changes when data is identical', function () {
        $result = UpdateUserAction::run(
            user: $this->testUser,
            name: 'Original Name',
            email: 'original@example.com',
            role: 'user'
        );

        expect($result['success'])->toBeTrue();
        expect($result['message'])->toBe('No changes detected');
        expect($result['data']['changes_made'])->toBeFalse();
    });

    it('prevents admin from demoting themselves', function () {
        $result = UpdateUserAction::run(
            user: $this->admin, // Trying to update self
            role: 'user' // Demoting from admin to user
        );

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toBe('You cannot change your own admin role');
    });

    it('allows admin to update their own name and email but not role', function () {
        $result = UpdateUserAction::run(
            user: $this->admin,
            name: 'Updated Admin Name',
            email: 'updated.admin@example.com',
            role: 'admin' // Same role
        );

        expect($result['success'])->toBeTrue();
        expect($result['data']['user']->name)->toBe('Updated Admin Name');
        expect($result['data']['user']->email)->toBe('updated.admin@example.com');
        expect($result['data']['user']->role)->toBe('admin');
    });

    it('fails with invalid user object', function () {
        // Since the method has type hinting, we need to test this differently
        // The type hint will cause a TypeError before reaching the validation
        expect(fn () => UpdateUserAction::run(
            user: null,
            name: 'Test Name'
        ))->toThrow(TypeError::class);
    });

    it('fails with invalid role', function () {
        $result = UpdateUserAction::run(
            user: $this->testUser,
            role: 'invalid-role'
        );

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toBe('Invalid role. Must be admin, manager, or user');
    });

    it('fails with invalid email format', function () {
        $result = UpdateUserAction::run(
            user: $this->testUser,
            email: 'invalid-email'
        );

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toBe('Invalid email format');
    });

    it('fails when email already exists for another user', function () {
        // Create another user with existing email
        User::factory()->create(['email' => 'existing@example.com']);

        $result = UpdateUserAction::run(
            user: $this->testUser,
            email: 'existing@example.com'
        );

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toBe('A user with this email already exists');
    });

    it('allows updating to same email (case insensitive)', function () {
        $result = UpdateUserAction::run(
            user: $this->testUser,
            email: 'ORIGINAL@EXAMPLE.COM' // Same email, different case
        );

        expect($result['success'])->toBeTrue();
        expect($result['message'])->toBe('No changes detected');
        expect($result['data']['changes_made'])->toBeFalse();
    });

    it('normalizes email to lowercase', function () {
        $result = UpdateUserAction::run(
            user: $this->testUser,
            email: 'UPDATED@EXAMPLE.COM'
        );

        expect($result['success'])->toBeTrue();
        expect($result['data']['user']->email)->toBe('updated@example.com');
    });

    it('trims whitespace from name', function () {
        $result = UpdateUserAction::run(
            user: $this->testUser,
            name: '  Trimmed Name  '
        );

        expect($result['success'])->toBeTrue();
        expect($result['data']['user']->name)->toBe('Trimmed Name');
    });

    it('handles null parameters gracefully', function () {
        $result = UpdateUserAction::run(
            user: $this->testUser,
            name: null,
            email: null,
            role: null
        );

        expect($result['success'])->toBeTrue();
        expect($result['message'])->toBe('No changes detected');
        expect($result['data']['changes_made'])->toBeFalse();
    });

    it('accepts all valid roles', function () {
        $validRoles = ['admin', 'manager', 'user'];

        foreach ($validRoles as $role) {
            $user = User::factory()->create(['role' => 'user']);

            $result = UpdateUserAction::run(
                user: $user,
                role: $role
            );

            expect($result['success'])->toBeTrue();
            expect($result['data']['user']->role)->toBe($role);
        }
    });

    it('tracks all changes correctly', function () {
        $originalName = $this->testUser->name;
        $originalEmail = $this->testUser->email;
        $originalRole = $this->testUser->role;

        $result = UpdateUserAction::run(
            user: $this->testUser,
            name: 'New Name',
            email: 'new@example.com',
            role: 'admin'
        );

        expect($result['success'])->toBeTrue();
        $changes = $result['data']['changes'];

        expect($changes['name']['from'])->toBe($originalName);
        expect($changes['name']['to'])->toBe('New Name');
        expect($changes['email']['from'])->toBe($originalEmail);
        expect($changes['email']['to'])->toBe('new@example.com');
        expect($changes['role']['from'])->toBe($originalRole);
        expect($changes['role']['to'])->toBe('admin');
    });
});
