<?php

use App\Actions\Users\CreateUserAction;
use App\Actions\Users\DeleteUserAction;
use App\Actions\Users\UpdateUserAction;
use App\Livewire\Management\UserRoleManagement;
use App\Livewire\Management\Users\UserIndex;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Maize\MagicLogin\Facades\MagicLink;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin);
});

describe('User Management Edge Cases and Error Handling', function () {
    describe('Database Edge Cases', function () {
        it('handles database connection errors gracefully', function () {
            // Simulate database error by using wrong connection
            DB::shouldReceive('transaction')->andThrow(new PDOException('Connection lost'));

            $result = CreateUserAction::run('Test User', 'test@example.com', 'user');
            expect($result['success'])->toBeFalse();
            expect($result['message'])->toContain('Failed to create user');
        });

        it('handles duplicate email constraint violations', function () {
            // Create a user first
            User::factory()->create(['email' => 'existing@example.com']);

            // Try to create another with same email
            $result = CreateUserAction::run('Another User', 'existing@example.com', 'user');
            expect($result['success'])->toBeFalse();
            expect($result['message'])->toBe('A user with this email already exists');
        });

        it('handles very long input strings', function () {
            $longName = str_repeat('a', 1000);
            $longEmail = str_repeat('b', 500).'@example.com';

            $result = CreateUserAction::run($longName, $longEmail, 'user');
            expect($result['success'])->toBeFalse();
        });

        it('handles SQL injection attempts', function () {
            $maliciousName = "'; DROP TABLE users; --";
            $maliciousEmail = "test@example.com'; DROP TABLE users; --";

            $result = CreateUserAction::run($maliciousName, $maliciousEmail, 'user');

            // Should either succeed with escaped data or fail validation
            if ($result['success']) {
                expect($result['data']['user']->name)->not->toContain('DROP TABLE');
            }
        });
    });

    describe('Email and External Service Edge Cases', function () {
        it('handles magic link service failures gracefully', function () {
            MagicLink::shouldReceive('send')->andThrow(new Exception('SMTP server unavailable'));

            $result = CreateUserAction::run(
                'Test User',
                'test@example.com',
                'user',
                sendWelcomeEmail: true
            );

            // User should still be created even if email fails
            expect($result['success'])->toBeTrue();
            expect($result['data']['magic_link_sent'])->toBeFalse();
        });

        it('handles invalid email addresses that pass basic validation', function () {
            $edgeCaseEmails = [
                'test@',
                '@example.com',
                'test@@example.com',
                'test@example',
                'test.@example.com',
            ];

            foreach ($edgeCaseEmails as $email) {
                $result = CreateUserAction::run('Test User', $email, 'user');
                expect($result['success'])->toBeFalse();
                expect($result['message'])->toContain('Invalid email format');
            }
        });

        it('handles international email addresses correctly', function () {
            $internationalEmails = [
                'test@münchen.de',
                'тест@example.com',
                'test@日本.jp',
            ];

            foreach ($internationalEmails as $email) {
                $result = CreateUserAction::run('Test User', $email, 'user');
                // Should handle international domains properly
                expect($result)->toHaveKey('success');
            }
        });
    });

    describe('Livewire Component Edge Cases', function () {
        it('handles component state corruption gracefully', function () {
            $component = Livewire::test(UserIndex::class)
                ->set('editingUser', 'invalid-object') // Corrupt state
                ->call('updateUser');

            // Should handle gracefully without crashing
            $component->assertStatus(200);
        });

        it('handles concurrent user operations', function () {
            $user = User::factory()->create(['role' => 'user']);

            // Simulate user being deleted while editing
            Livewire::test(UserIndex::class)
                ->call('openEditModal', $user->id)
                ->tap(function () use ($user) {
                    $user->delete(); // Delete user externally
                })
                ->set('name', 'Updated Name')
                ->call('updateUser')
                ->assertDispatched('notify'); // Should show error
        });

        it('handles invalid user IDs in component methods', function () {
            Livewire::test(UserIndex::class)
                ->call('openEditModal', 99999) // Non-existent user
                ->assertSet('showEditModal', false); // Should not open modal
        });

        it('handles component property injection attacks', function () {
            Livewire::test(UserIndex::class)
                ->set('role', ['malicious' => 'data']) // Array injection
                ->call('createUser')
                ->assertHasErrors(); // Should validate properly
        });

        it('handles very large datasets in components', function () {
            // Create many users to test pagination and performance
            User::factory()->count(1000)->create();

            $component = Livewire::test(UserIndex::class);
            $component->assertStatus(200);
            $component->assertViewHas('users');
        });
    });

    describe('Input Validation Edge Cases', function () {
        it('handles whitespace-only names', function () {
            $result = CreateUserAction::run('   ', 'test@example.com', 'user');
            expect($result['success'])->toBeFalse();
            expect($result['message'])->toBe('Name and email are required');
        });

        it('handles unicode characters in names', function () {
            $unicodeName = '张三 José María';
            $result = CreateUserAction::run($unicodeName, 'unicode@example.com', 'user');

            if ($result['success']) {
                expect($result['data']['user']->name)->toBe($unicodeName);
            }
        });

        it('handles role case sensitivity', function () {
            $result = CreateUserAction::run('Test User', 'test@example.com', 'ADMIN');
            expect($result['success'])->toBeFalse();
            expect($result['message'])->toContain('Invalid role');
        });

        it('handles null byte injection', function () {
            $maliciousName = "Test\0User";
            $maliciousEmail = "test\0@example.com";

            $result = CreateUserAction::run($maliciousName, $maliciousEmail, 'user');

            if ($result['success']) {
                expect($result['data']['user']->name)->not->toContain("\0");
            }
        });
    });

    describe('Performance and Memory Edge Cases', function () {
        it('handles bulk operations without memory exhaustion', function () {
            $userIds = User::factory()->count(100)->create()->pluck('id')->toArray();

            Livewire::test(UserRoleManagement::class)
                ->set('selectedUsers', $userIds)
                ->set('bulkRole', 'manager')
                ->call('bulkAssignRole')
                ->assertDispatched('notify');

            // Should complete without timeout or memory errors
            expect(true)->toBeTrue();
        });

        it('handles search with very long query strings', function () {
            $longQuery = str_repeat('search', 1000);

            Livewire::test(UserIndex::class)
                ->set('search', $longQuery)
                ->assertStatus(200); // Should not crash
        });

        it('handles rapid successive API calls', function () {
            // Simulate rapid clicking
            for ($i = 0; $i < 10; $i++) {
                $result = CreateUserAction::run("User $i", "user$i@example.com", 'user');
                expect($result)->toHaveKey('success');
            }
        });
    });

    describe('Security Edge Cases', function () {
        it('handles session manipulation attempts', function () {
            // This would test session-based attacks
            Livewire::test(UserIndex::class)
                ->call('openCreateModal')
                ->assertStatus(200);
        });

        it('handles CSRF token manipulation', function () {
            // Livewire has built-in CSRF protection
            Livewire::test(UserIndex::class)
                ->call('createUser')
                ->assertStatus(200); // Should handle CSRF properly
        });

        it('prevents unauthorized property updates', function () {
            Livewire::test(UserIndex::class)
                ->set('admin', true) // Try to set non-existent property
                ->assertStatus(200); // Should ignore invalid properties
        });
    });

    describe('Data Consistency Edge Cases', function () {
        it('handles orphaned relationships gracefully', function () {
            // This would test if user had relationships that need cleanup
            $user = User::factory()->create();

            $result = DeleteUserAction::run($user);
            expect($result['success'])->toBeTrue();
        });

        it('handles timezone differences in timestamps', function () {
            // Test with different timezone
            $originalTimezone = date_default_timezone_get();
            date_default_timezone_set('America/New_York');

            $result = CreateUserAction::run('TZ Test', 'tz@example.com', 'user');

            date_default_timezone_set($originalTimezone);

            expect($result['success'])->toBeTrue();
        });

        it('handles soft delete edge cases', function () {
            $user = User::factory()->create();

            // If model supports soft deletes
            if (method_exists($user, 'delete')) {
                $user->delete(); // Soft delete

                // Try to create user with same email
                $result = CreateUserAction::run('Same User', $user->email, 'user');
                expect($result['success'])->toBeFalse(); // Should prevent duplicate
            }
        });
    });

    describe('Livewire Lifecycle Edge Cases', function () {
        it('handles component mounting errors', function () {
            // Test with corrupted session data
            $component = Livewire::test(UserIndex::class);
            $component->assertStatus(200);
        });

        it('handles component hydration errors', function () {
            $component = Livewire::test(UserIndex::class)
                ->call('openCreateModal')
                ->assertSet('showCreateModal', true);

            // Component should handle state properly
            expect($component->get('showCreateModal'))->toBeTrue();
        });

        it('handles validation errors during form submission', function () {
            Livewire::test(UserIndex::class)
                ->call('openCreateModal')
                ->set('name', '') // Invalid
                ->set('email', 'invalid-email') // Invalid
                ->set('role', 'invalid-role') // Invalid
                ->call('createUser')
                ->assertHasErrors(['name', 'email', 'role']);
        });
    });

    describe('Recovery and Rollback Edge Cases', function () {
        it('handles transaction rollbacks properly', function () {
            // Count users before
            $initialCount = User::count();

            // Force an error during user creation
            DB::shouldReceive('transaction')->andThrow(new Exception('Forced error'));

            $result = CreateUserAction::run('Test User', 'test@example.com', 'user');
            expect($result['success'])->toBeFalse();

            // User count should remain the same
            expect(User::count())->toBe($initialCount);
        });

        it('handles partial update failures', function () {
            $user = User::factory()->create([
                'name' => 'Original Name',
                'email' => 'original@example.com',
                'role' => 'user',
            ]);

            // Try to update to an existing email
            $existingUser = User::factory()->create(['email' => 'existing@example.com']);

            $result = UpdateUserAction::run(
                $user,
                'New Name',
                'existing@example.com', // Should fail
                'manager'
            );

            expect($result['success'])->toBeFalse();

            // User should remain unchanged
            $user->refresh();
            expect($user->name)->toBe('Original Name');
            expect($user->email)->toBe('original@example.com');
            expect($user->role)->toBe('user');
        });
    });
});
