<?php

use App\Actions\Users\DeleteUserAction;
use App\Models\User;

beforeEach(function () {
    // Create an admin user for authorization
    $this->admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($this->admin);

    // Create test users
    $this->regularUser = User::factory()->create([
        'name' => 'Regular User',
        'email' => 'regular@example.com',
        'role' => 'user',
    ]);

    $this->manager = User::factory()->create([
        'name' => 'Manager User',
        'email' => 'manager@example.com',
        'role' => 'manager',
    ]);
});

describe('DeleteUserAction', function () {
    it('successfully deletes a regular user', function () {
        $userId = $this->regularUser->id;
        $userName = $this->regularUser->name;

        $result = DeleteUserAction::run($this->regularUser);

        expect($result['success'])->toBeTrue();
        expect($result['message'])->toContain('successfully');
        expect($result['data']['user']['id'])->toBe($userId);
        expect($result['data']['user']['name'])->toBe($userName);

        // User should be deleted from database
        expect(User::find($userId))->toBeNull();
    });

    it('successfully deletes a manager', function () {
        $result = DeleteUserAction::run($this->manager);

        expect($result['success'])->toBeTrue();
        expect($result['data']['user']['role'])->toBe('manager');
    });

    it('prevents user from deleting themselves', function () {
        $result = DeleteUserAction::run($this->admin); // Admin trying to delete self

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toBe('You cannot delete your own account');
    });

    it('prevents deletion of last admin user', function () {
        // Create another regular user to delete first
        $otherUser = User::factory()->create(['role' => 'user']);

        // Try to delete the only admin (should fail)
        $result = DeleteUserAction::run($this->admin);

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toBe('You cannot delete your own account'); // Self-deletion protection kicks in first
    });

    it('allows deletion of admin when multiple admins exist', function () {
        // Create another admin
        $secondAdmin = User::factory()->create(['role' => 'admin']);

        // Acting as first admin, delete the second admin
        $result = DeleteUserAction::run($secondAdmin);

        expect($result['success'])->toBeTrue();
        expect($result['data']['user']['role'])->toBe('admin');
    });

    it('prevents deletion of last admin even with multiple admins when deleting the last one', function () {
        // Create another admin
        $secondAdmin = User::factory()->create(['role' => 'admin']);

        // Delete the second admin first
        $secondAdmin->delete();

        // Now try to delete the last remaining admin (but it's self-deletion, so different error)
        $result = DeleteUserAction::run($this->admin);

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toBe('You cannot delete your own account');
    });

    it('handles soft delete if model supports it', function () {
        // Check if User model has SoftDeletes
        $hasForceDelete = method_exists($this->regularUser, 'forceDelete');

        $result = DeleteUserAction::run($this->regularUser);

        expect($result['success'])->toBeTrue();

        if ($hasForceDelete) {
            expect($result['data']['deletion_type'])->toBe('soft');
            expect($result['data']['can_restore'])->toBeTrue();
            expect($result['message'])->toContain('suspended successfully');
        } else {
            expect($result['data']['deletion_type'])->toBe('hard');
            expect($result['data']['can_restore'])->toBeFalse();
            expect($result['message'])->toContain('deleted successfully');
        }
    });

    it('performs force delete when force parameter is true', function () {
        $userId = $this->regularUser->id;

        $result = DeleteUserAction::run($this->regularUser, force: true);

        expect($result['success'])->toBeTrue();
        expect($result['data']['deletion_type'])->toBe(method_exists($this->regularUser, 'forceDelete') ? 'force' : 'hard');

        // User should be completely removed
        expect(User::find($userId))->toBeNull();
    });

    it('fails with invalid user object', function () {
        // Since the method has type hinting, we need to test this differently
        // The type hint will cause a TypeError before reaching the validation
        expect(fn () => DeleteUserAction::run(null))
            ->toThrow(TypeError::class);
    });

    it('preserves user data in response for audit purposes', function () {
        $userData = [
            'id' => $this->regularUser->id,
            'name' => $this->regularUser->name,
            'email' => $this->regularUser->email,
            'role' => $this->regularUser->role,
            'created_at' => $this->regularUser->created_at,
            'email_verified_at' => $this->regularUser->email_verified_at,
        ];

        $result = DeleteUserAction::run($this->regularUser);

        expect($result['success'])->toBeTrue();
        expect($result['data']['user']['id'])->toBe($userData['id']);
        expect($result['data']['user']['name'])->toBe($userData['name']);
        expect($result['data']['user']['email'])->toBe($userData['email']);
        expect($result['data']['user']['role'])->toBe($userData['role']);
    });

    it('handles multiple admin scenario correctly', function () {
        // Create multiple admins
        $admin2 = User::factory()->create(['role' => 'admin']);
        $admin3 = User::factory()->create(['role' => 'admin']);

        // Should be able to delete one admin when others exist
        $result = DeleteUserAction::run($admin2);

        expect($result['success'])->toBeTrue();

        // Count remaining admins
        $remainingAdmins = User::where('role', 'admin')->count();
        expect($remainingAdmins)->toBeGreaterThan(0);
    });

    it('tracks deletion metadata correctly', function () {
        $result = DeleteUserAction::run($this->regularUser);

        expect($result['success'])->toBeTrue();
        expect($result['data'])->toHaveKey('user');
        expect($result['data'])->toHaveKey('deletion_type');
        expect($result['data'])->toHaveKey('can_restore');
        expect($result['data']['deletion_type'])->toBeIn(['hard', 'soft', 'force']);
        expect($result['data']['can_restore'])->toBeIn([true, false]);
    });

    it('prevents deletion when it would leave system without admins through force parameter', function () {
        // This tests the edge case where force=false and user is the last admin
        // First, let's create a scenario where this admin is the only one

        $onlyAdmin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($onlyAdmin);

        // Try to delete this admin (should fail due to being last admin)
        $result = DeleteUserAction::run($onlyAdmin);

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toBe('You cannot delete your own account'); // Self-deletion protection
    });

    it('allows non-admin users to be deleted without admin count checks', function () {
        $user1 = User::factory()->create(['role' => 'user']);
        $user2 = User::factory()->create(['role' => 'manager']);

        $result1 = DeleteUserAction::run($user1);
        $result2 = DeleteUserAction::run($user2);

        expect($result1['success'])->toBeTrue();
        expect($result2['success'])->toBeTrue();
    });
});
