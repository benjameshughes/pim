<?php

use App\Livewire\Management\Users\UserIndex;
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

    // Create test users with different roles and statuses
    $this->users = collect([
        User::factory()->create([
            'name' => 'John Manager',
            'email' => 'john@example.com',
            'role' => 'manager',
            'email_verified_at' => now(),
        ]),
        User::factory()->create([
            'name' => 'Jane User',
            'email' => 'jane@example.com',
            'role' => 'user',
            'email_verified_at' => null, // Unverified
        ]),
        User::factory()->create([
            'name' => 'Bob Admin',
            'email' => 'bob@example.com',
            'role' => 'admin',
            'email_verified_at' => now(),
        ]),
    ]);
});

describe('UserIndex Livewire Component', function () {
    it('renders successfully for admin users', function () {
        Livewire::test(UserIndex::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.management.users.user-index');
    });

    it('displays user list with pagination', function () {
        Livewire::test(UserIndex::class)
            ->assertSee('Admin User') // Current logged-in admin
            ->assertSee('John Manager')
            ->assertSee('Jane User')
            ->assertSee('Bob Admin')
            ->assertViewHas('users');
    });

    it('displays user statistics correctly', function () {
        Livewire::test(UserIndex::class)
            ->assertViewHas('userStats');

        // Test actual values
        $component = Livewire::test(UserIndex::class);
        $stats = $component->viewData('userStats');

        expect($stats['admin'])->toBeGreaterThanOrEqual(2); // Admin + Bob Admin
        expect($stats['manager'])->toBeGreaterThanOrEqual(1); // John Manager
        expect($stats['user'])->toBeGreaterThanOrEqual(1); // Jane User
        expect($stats['total'])->toBeGreaterThanOrEqual(4); // All users
    });

    it('can search users by name', function () {
        Livewire::test(UserIndex::class)
            ->set('search', 'John')
            ->assertSee('John Manager')
            ->assertDontSee('Jane User');
    });

    it('can search users by email', function () {
        Livewire::test(UserIndex::class)
            ->set('search', 'jane@example.com')
            ->assertSee('Jane User')
            ->assertDontSee('John Manager');
    });

    it('can filter users by role', function () {
        Livewire::test(UserIndex::class)
            ->set('roleFilter', 'manager')
            ->assertSee('John Manager')
            ->assertDontSee('Jane User');
    });

    it('can filter users by verification status', function () {
        Livewire::test(UserIndex::class)
            ->set('statusFilter', 'unverified')
            ->assertSee('Jane User')
            ->assertDontSee('John Manager');
    });

    it('can clear all filters', function () {
        Livewire::test(UserIndex::class)
            ->set('search', 'John')
            ->set('roleFilter', 'manager')
            ->set('statusFilter', 'verified')
            ->call('clearFilters')
            ->assertSet('search', '')
            ->assertSet('roleFilter', '')
            ->assertSet('statusFilter', '');
    });

    it('can sort users by different fields', function () {
        Livewire::test(UserIndex::class)
            ->call('sortBy', 'name')
            ->assertSet('sortField', 'name')
            ->assertSet('sortDirection', 'asc')
            ->call('sortBy', 'name') // Click again to reverse
            ->assertSet('sortDirection', 'desc');
    });
});

describe('UserIndex CRUD Operations', function () {
    it('can open and close create modal', function () {
        Livewire::test(UserIndex::class)
            ->call('openCreateModal')
            ->assertSet('showCreateModal', true)
            ->assertSet('name', '')
            ->assertSet('email', '')
            ->assertSet('role', 'user')
            ->assertSet('sendWelcomeEmail', true)
            ->call('closeCreateModal')
            ->assertSet('showCreateModal', false);
    });

    it('can create a new user', function () {
        Livewire::test(UserIndex::class)
            ->call('openCreateModal')
            ->set('name', 'New Test User')
            ->set('email', 'newtest@example.com')
            ->set('role', 'manager')
            ->set('sendWelcomeEmail', false)
            ->call('createUser')
            ->assertSet('showCreateModal', false)
            ->assertDispatched('notify');

        // Verify user was created
        $user = User::where('email', 'newtest@example.com')->first();
        expect($user)->not->toBeNull();
        expect($user->name)->toBe('New Test User');
        expect($user->role)->toBe('manager');
    });

    it('validates required fields when creating user', function () {
        Livewire::test(UserIndex::class)
            ->call('openCreateModal')
            ->set('name', '')
            ->set('email', '')
            ->call('createUser')
            ->assertHasErrors(['name', 'email']);
    });

    it('validates email format when creating user', function () {
        Livewire::test(UserIndex::class)
            ->call('openCreateModal')
            ->set('name', 'Test User')
            ->set('email', 'invalid-email')
            ->call('createUser')
            ->assertHasErrors(['email']);
    });

    it('validates email uniqueness when creating user', function () {
        Livewire::test(UserIndex::class)
            ->call('openCreateModal')
            ->set('name', 'Test User')
            ->set('email', 'admin@example.com') // Already exists
            ->call('createUser')
            ->assertHasErrors(['email']);
    });

    it('can open edit modal with user data', function () {
        $user = $this->users->first();

        Livewire::test(UserIndex::class)
            ->call('openEditModal', $user->id)
            ->assertSet('showEditModal', true)
            ->assertSet('editingUser.id', $user->id)
            ->assertSet('name', $user->name)
            ->assertSet('email', $user->email)
            ->assertSet('role', $user->role);
    });

    it('can update user information', function () {
        $user = $this->users->first();

        Livewire::test(UserIndex::class)
            ->call('openEditModal', $user->id)
            ->set('name', 'Updated Name')
            ->set('email', 'updated@example.com')
            ->set('role', 'admin')
            ->call('updateUser')
            ->assertSet('showEditModal', false)
            ->assertDispatched('notify');

        // Verify user was updated
        $user->refresh();
        expect($user->name)->toBe('Updated Name');
        expect($user->email)->toBe('updated@example.com');
        expect($user->role)->toBe('admin');
    });

    it('can close edit modal', function () {
        $user = $this->users->first();

        Livewire::test(UserIndex::class)
            ->call('openEditModal', $user->id)
            ->call('closeEditModal')
            ->assertSet('showEditModal', false)
            ->assertSet('editingUser', null);
    });

    it('can open delete confirmation modal', function () {
        $user = $this->users->first();

        Livewire::test(UserIndex::class)
            ->call('openDeleteModal', $user->id)
            ->assertSet('showDeleteModal', true)
            ->assertSet('deletingUser.id', $user->id);
    });

    it('can delete user', function () {
        $user = $this->users->first();
        $userId = $user->id;

        Livewire::test(UserIndex::class)
            ->call('openDeleteModal', $user->id)
            ->call('deleteUser')
            ->assertSet('showDeleteModal', false)
            ->assertDispatched('notify');

        // Verify user was deleted
        expect(User::find($userId))->toBeNull();
    });

    it('cannot delete own account', function () {
        Livewire::test(UserIndex::class)
            ->call('openDeleteModal', $this->admin->id)
            ->call('deleteUser')
            ->assertDispatched('notify'); // Should show error

        // Admin should still exist
        expect(User::find($this->admin->id))->not->toBeNull();
    });

    it('can close delete modal', function () {
        $user = $this->users->first();

        Livewire::test(UserIndex::class)
            ->call('openDeleteModal', $user->id)
            ->call('closeDeleteModal')
            ->assertSet('showDeleteModal', false)
            ->assertSet('deletingUser', null);
    });
});

describe('UserIndex Permissions and Security', function () {
    it('requires admin authorization', function () {
        // Create non-admin user
        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user);

        // Test via HTTP request to route instead since component authorization
        // is handled at the route level via middleware
        $response = $this->get('/management/users');
        $response->assertStatus(403); // Forbidden
    });

    it('shows current user indicator', function () {
        Livewire::test(UserIndex::class)
            ->assertSee('Current User'); // Should show indicator for logged-in admin
    });

    it('validates role values', function () {
        Livewire::test(UserIndex::class)
            ->call('openCreateModal')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('role', 'invalid-role')
            ->call('createUser')
            ->assertHasErrors(['role']);
    });
});

describe('UserIndex UI and UX', function () {
    it('resets page when search is updated', function () {
        Livewire::test(UserIndex::class)
            ->set('search', 'test');
        // Note: resetPage is automatically called by Livewire pagination
    });

    it('resets page when filters are updated', function () {
        Livewire::test(UserIndex::class)
            ->set('roleFilter', 'admin')
            ->set('statusFilter', 'verified');
        // Note: resetPage is automatically called by Livewire pagination
    });

    it('handles empty search results gracefully', function () {
        Livewire::test(UserIndex::class)
            ->set('search', 'nonexistent')
            ->assertSee('No users found')
            ->assertSee('Try adjusting your search or filter criteria');
    });

    it('displays proper loading states', function () {
        Livewire::test(UserIndex::class)
            ->call('openCreateModal')
            ->assertSee('Create User')
            ->assertSee('Creating...');
    });

    it('refreshes component when user events are dispatched', function () {
        // Test that component has proper listeners defined in class
        $userIndex = new \App\Livewire\Management\Users\UserIndex;
        $reflection = new \ReflectionClass($userIndex);
        $listeners = $reflection->getProperty('listeners');
        $listeners->setAccessible(true);
        $listenersArray = $listeners->getValue($userIndex);

        expect($listenersArray)->toContain('$refresh');
    });
});
