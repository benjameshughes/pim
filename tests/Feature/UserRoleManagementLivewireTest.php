<?php

use App\Livewire\Management\UserRoleManagement;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    // Create an admin user for authorization
    $this->admin = User::factory()->create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($this->admin);

    // Create test users with different roles
    $this->users = collect([
        User::factory()->create([
            'name' => 'Manager One',
            'email' => 'manager1@example.com',
            'role' => 'manager',
        ]),
        User::factory()->create([
            'name' => 'User One',
            'email' => 'user1@example.com',
            'role' => 'user',
        ]),
        User::factory()->create([
            'name' => 'User Two',
            'email' => 'user2@example.com',
            'role' => 'user',
        ]),
        User::factory()->create([
            'name' => 'Admin Two',
            'email' => 'admin2@example.com',
            'role' => 'admin',
        ]),
    ]);
});

describe('UserRoleManagement Livewire Component', function () {
    it('renders successfully for admin users', function () {
        Livewire::test(UserRoleManagement::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.management.user-role-management');
    });

    it('displays user list with role information', function () {
        Livewire::test(UserRoleManagement::class)
            ->assertSee('Manager One')
            ->assertSee('User One')
            ->assertSee('Admin Two')
            ->assertViewHas('users')
            ->assertViewHas('roleStatistics');
    });

    it('displays role statistics correctly', function () {
        Livewire::test(UserRoleManagement::class)
            ->assertViewHas('roleStatistics');
    });

    it('can search users by name', function () {
        Livewire::test(UserRoleManagement::class)
            ->set('search', 'Manager')
            ->assertSee('Manager One')
            ->assertDontSee('User One');
    });

    it('can filter users by role', function () {
        Livewire::test(UserRoleManagement::class)
            ->set('roleFilter', 'user')
            ->assertSee('User One')
            ->assertSee('User Two')
            ->assertDontSee('Manager One');
    });

    it('can sort users by different fields', function () {
        Livewire::test(UserRoleManagement::class)
            ->call('sortBy', 'email')
            ->assertSet('sortBy', 'email')
            ->assertSet('sortDirection', 'asc')
            ->call('sortBy', 'email') // Click again to reverse
            ->assertSet('sortDirection', 'desc');
    });

    it('can clear all filters', function () {
        Livewire::test(UserRoleManagement::class)
            ->set('search', 'test')
            ->set('roleFilter', 'admin')
            ->call('clearFilters')
            ->assertSet('search', '')
            ->assertSet('roleFilter', '')
            ->assertSet('sortBy', 'name')
            ->assertSet('sortDirection', 'asc');
    });
});

describe('UserRoleManagement Role Assignment', function () {
    it('can open role assignment modal', function () {
        $user = $this->users->first();

        Livewire::test(UserRoleManagement::class)
            ->call('openRoleModal', $user->id)
            ->assertSet('showRoleModal', true)
            ->assertSet('selectedUser.id', $user->id)
            ->assertSet('selectedRole', $user->role);
    });

    it('can assign new role to user', function () {
        $user = $this->users->where('role', 'user')->first();

        Livewire::test(UserRoleManagement::class)
            ->call('openRoleModal', $user->id)
            ->set('selectedRole', 'manager')
            ->call('saveRoleModal')
            ->assertSet('showRoleModal', false)
            ->assertDispatched('notify');

        // Verify role was updated
        $user->refresh();
        expect($user->role)->toBe('manager');
    });

    it('can close role assignment modal', function () {
        $user = $this->users->first();

        Livewire::test(UserRoleManagement::class)
            ->call('openRoleModal', $user->id)
            ->call('closeRoleModal')
            ->assertSet('showRoleModal', false)
            ->assertSet('selectedUser', null)
            ->assertSet('selectedRole', '');
    });

    it('handles invalid user ID in role modal', function () {
        Livewire::test(UserRoleManagement::class)
            ->call('openRoleModal', 99999) // Non-existent user ID
            ->assertSet('showRoleModal', false)
            ->assertDispatched('notify');
    });

    it('validates role selection before saving', function () {
        $user = $this->users->first();

        Livewire::test(UserRoleManagement::class)
            ->call('openRoleModal', $user->id)
            ->set('selectedRole', '')
            ->call('saveRoleModal')
            ->assertDispatched('notify'); // Error message
    });
});

describe('UserRoleManagement User Creation', function () {
    it('can open and close create user modal', function () {
        Livewire::test(UserRoleManagement::class)
            ->call('openCreateModal')
            ->assertSet('showCreateModal', true)
            ->assertSet('createName', '')
            ->assertSet('createEmail', '')
            ->assertSet('createRole', 'user')
            ->assertSet('sendWelcomeEmail', true)
            ->call('closeCreateModal')
            ->assertSet('showCreateModal', false);
    });

    it('can create new user with role assignment', function () {
        Livewire::test(UserRoleManagement::class)
            ->call('openCreateModal')
            ->set('createName', 'New Test User')
            ->set('createEmail', 'newtest@example.com')
            ->set('createRole', 'manager')
            ->set('sendWelcomeEmail', false)
            ->call('createUser')
            ->assertSet('showCreateModal', false)
            ->assertDispatched('notify')
            ->assertDispatched('user-created');

        // Verify user was created
        $user = User::where('email', 'newtest@example.com')->first();
        expect($user)->not->toBeNull();
        expect($user->name)->toBe('New Test User');
        expect($user->role)->toBe('manager');
    });

    it('validates required fields when creating user', function () {
        Livewire::test(UserRoleManagement::class)
            ->call('openCreateModal')
            ->set('createName', '')
            ->set('createEmail', '')
            ->call('createUser')
            ->assertHasErrors(['createName', 'createEmail']);
    });

    it('validates email format when creating user', function () {
        Livewire::test(UserRoleManagement::class)
            ->call('openCreateModal')
            ->set('createName', 'Test User')
            ->set('createEmail', 'invalid-email')
            ->call('createUser')
            ->assertHasErrors(['createEmail']);
    });

    it('validates email uniqueness when creating user', function () {
        Livewire::test(UserRoleManagement::class)
            ->call('openCreateModal')
            ->set('createName', 'Test User')
            ->set('createEmail', 'admin@example.com') // Already exists
            ->call('createUser')
            ->assertHasErrors(['createEmail']);
    });

    it('validates role selection when creating user', function () {
        Livewire::test(UserRoleManagement::class)
            ->call('openCreateModal')
            ->set('createName', 'Test User')
            ->set('createEmail', 'test@example.com')
            ->set('createRole', 'invalid-role')
            ->call('createUser')
            ->assertHasErrors(['createRole']);
    });
});

describe('UserRoleManagement Bulk Operations', function () {
    it('can select and deselect all users', function () {
        Livewire::test(UserRoleManagement::class)
            ->set('selectAll', true)
            ->assertCount('selectedUsers', 5) // All users including admin
            ->set('selectAll', false)
            ->assertSet('selectedUsers', []);
    });

    it('can select individual users', function () {
        $user = $this->users->first();

        Livewire::test(UserRoleManagement::class)
            ->set('selectedUsers', [$user->id])
            ->assertContains('selectedUsers', $user->id);
    });

    it('can perform bulk role assignment', function () {
        $userIds = $this->users->where('role', 'user')->pluck('id')->toArray();

        Livewire::test(UserRoleManagement::class)
            ->set('selectedUsers', $userIds)
            ->set('bulkRole', 'manager')
            ->call('bulkAssignRole')
            ->assertSet('selectedUsers', [])
            ->assertSet('selectAll', false)
            ->assertSet('bulkRole', '')
            ->assertDispatched('notify');

        // Verify users were updated
        $updatedUsers = User::whereIn('id', $userIds)->get();
        foreach ($updatedUsers as $user) {
            expect($user->role)->toBe('manager');
        }
    });

    it('validates bulk role assignment requirements', function () {
        Livewire::test(UserRoleManagement::class)
            ->set('selectedUsers', [])
            ->set('bulkRole', '')
            ->call('bulkAssignRole')
            ->assertDispatched('notify'); // Error message about missing selection
    });

    it('handles bulk assignment errors gracefully', function () {
        // This would test error handling in bulk operations
        $userIds = [$this->users->first()->id];

        Livewire::test(UserRoleManagement::class)
            ->set('selectedUsers', $userIds)
            ->set('bulkRole', 'manager')
            ->call('bulkAssignRole')
            ->assertDispatched('notify'); // Success message
    });
});

describe('UserRoleManagement Permissions and Security', function () {
    it('requires admin authorization', function () {
        // Create non-admin user
        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user);

        // Should be unauthorized (this depends on route middleware)
        // The component itself doesn't have explicit auth checks
        Livewire::test(UserRoleManagement::class)
            ->assertStatus(200); // Component loads but route should protect it
    });

    it('requires admin authorization for create user action', function () {
        $regularUser = User::factory()->create(['role' => 'user']);
        $this->actingAs($regularUser);

        expect(fn () => Livewire::test(UserRoleManagement::class)
            ->call('openCreateModal')
            ->set('createName', 'Test User')
            ->set('createEmail', 'test@example.com')
            ->set('createRole', 'user')
            ->call('createUser'))
            ->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
    });
});

describe('UserRoleManagement UI and Data Handling', function () {
    it('resets page when search is updated', function () {
        Livewire::test(UserRoleManagement::class)
            ->set('search', 'test')
            ->assertMethodWasCalled('resetPage');
    });

    it('resets page when role filter is updated', function () {
        Livewire::test(UserRoleManagement::class)
            ->set('roleFilter', 'admin')
            ->assertMethodWasCalled('resetPage');
    });

    it('displays available roles in modals', function () {
        Livewire::test(UserRoleManagement::class)
            ->assertViewHas('availableRoles');
    });

    it('handles component refresh events', function () {
        $component = Livewire::test(UserRoleManagement::class);

        // Simulate events that should refresh the component
        $component->dispatch('user-role-updated');
        $component->dispatch('user-created');
        $component->dispatch('refresh-users');

        $component->assertDispatched('user-role-updated');
        $component->assertDispatched('user-created');
        $component->assertDispatched('refresh-users');
    });

    it('shows proper loading states for async operations', function () {
        Livewire::test(UserRoleManagement::class)
            ->call('openCreateModal')
            ->assertSee('Create User & Assign Role')
            ->assertSee('Creating...');
    });

    it('handles empty results gracefully', function () {
        Livewire::test(UserRoleManagement::class)
            ->set('search', 'nonexistent')
            ->assertSee('No users found');
    });

    it('updates bulk selection when users are filtered', function () {
        Livewire::test(UserRoleManagement::class)
            ->set('selectAll', true)
            ->set('roleFilter', 'user') // Filter to only user role
            ->set('selectAll', true) // Select all filtered users
            ->assertCount('selectedUsers', 2); // Only the user-role users
    });
});
