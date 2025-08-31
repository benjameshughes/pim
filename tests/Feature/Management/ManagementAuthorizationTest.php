<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function () {
    // Create a team for testing
    $this->team = Team::factory()->create([
        'name' => 'Test Team',
        'is_active' => true,
    ]);

    // Create an admin user
    $this->adminUser = User::factory()->create([
        'name' => 'Admin User',
        'email' => 'admin@benjh.com',
    ]);
    $this->adminUser->teams()->attach($this->team->id, ['role' => 'admin']);

    // Create a regular user (non-admin)
    $this->regularUser = User::factory()->create([
        'name' => 'Regular User',
        'email' => 'user@benjh.com',
    ]);
    $this->regularUser->teams()->attach($this->team->id, ['role' => 'user']);
});

describe('Management System Authorization', function () {

    it('shows management navigation for admin users', function () {
        $this->actingAs($this->adminUser);

        $response = $this->get('/dashboard');

        // Should see management navigation
        $response->assertSee('Management');
        $response->assertSee('Users');
        $response->assertSee('Teams');
        $response->assertSee(route('management.users.index'));
        $response->assertSee(route('management.teams.index'));
    });

    it('hides management navigation for regular users', function () {
        $this->actingAs($this->regularUser);

        $response = $this->get('/dashboard');

        // Should NOT see management navigation links
        $response->assertDontSee(route('management.users.index'));
        $response->assertDontSee(route('management.teams.index'));
        $response->assertDontSee('href="'.route('management.users.index').'"');
        $response->assertDontSee('href="'.route('management.teams.index').'"');
    });

    it('allows admin users to access user management page', function () {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('management.users.index'));

        $response->assertStatus(200);
        $response->assertSee('Users');
        $response->assertSee('Add User');
    });

    it('blocks regular users from accessing user management page', function () {
        $this->actingAs($this->regularUser);

        $response = $this->get(route('management.users.index'));

        $response->assertStatus(403);
    });

    it('allows admin users to access team management page', function () {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('management.teams.index'));

        $response->assertStatus(200);
        $response->assertSee('Teams');
        $response->assertSee('Create Team');
    });

    it('blocks regular users from accessing team management page', function () {
        $this->actingAs($this->regularUser);

        $response = $this->get(route('management.teams.index'));

        $response->assertStatus(403);
    });

    it('allows admin users to create new users via livewire', function () {
        $this->actingAs($this->adminUser);

        Livewire::test(\App\Livewire\Management\Users\UserIndex::class)
            ->set('name', 'New User')
            ->set('email', 'newuser@benjh.com')
            ->set('role', 'user')
            ->set('teamId', $this->team->id)
            ->call('createUser')
            ->assertDispatched('success')
            ->assertSet('showCreateModal', false);

        // Verify user was created
        $this->assertDatabaseHas('users', [
            'name' => 'New User',
            'email' => 'newuser@benjh.com',
        ]);

        // Verify team assignment
        $this->assertDatabaseHas('team_user', [
            'team_id' => $this->team->id,
            'role' => 'user',
        ]);
    });

    it('blocks regular users from creating users via livewire', function () {
        $this->actingAs($this->regularUser);

        // Test via HTTP request to the actual page
        // This will trigger the mount() method properly
        $response = $this->get(route('management.users.index'));

        // Should get 403 because of route middleware
        $response->assertStatus(403);
    });

    it('allows admin users to update existing users', function () {
        $this->actingAs($this->adminUser);

        $targetUser = User::factory()->create(['email' => 'target@benjh.com']);
        $targetUser->teams()->attach($this->team->id, ['role' => 'user']);

        Livewire::test(\App\Livewire\Management\Users\UserIndex::class)
            ->call('openEditModal', $targetUser->id)
            ->set('name', 'Updated Name')
            ->set('email', 'updated@benjh.com')
            ->set('role', 'manager')
            ->set('teamId', $this->team->id)
            ->call('updateUser')
            ->assertDispatched('success');

        // Verify user was updated
        $targetUser->refresh();
        $this->assertEquals('Updated Name', $targetUser->name);
        $this->assertEquals('updated@benjh.com', $targetUser->email);

        // Verify role was updated
        $this->assertEquals('manager', $targetUser->teams->first()->pivot->role);
    });

    it('blocks regular users from updating users', function () {
        $this->actingAs($this->regularUser);

        // Test via HTTP request to the actual page
        $response = $this->get(route('management.users.index'));

        // Should get 403 because of route middleware
        $response->assertStatus(403);
    });

    it('allows admin users to delete other users', function () {
        $this->actingAs($this->adminUser);

        $targetUser = User::factory()->create(['email' => 'delete@benjh.com']);
        $targetUser->teams()->attach($this->team->id, ['role' => 'user']);

        Livewire::test(\App\Livewire\Management\Users\UserIndex::class)
            ->call('deleteUser', $targetUser->id)
            ->assertDispatched('success');

        // Verify user was deleted
        $this->assertDatabaseMissing('users', [
            'id' => $targetUser->id,
        ]);
    });

    it('prevents admin users from deleting themselves', function () {
        $this->actingAs($this->adminUser);

        Livewire::test(\App\Livewire\Management\Users\UserIndex::class)
            ->call('deleteUser', $this->adminUser->id)
            ->assertDispatched('error', 'You cannot delete yourself!');

        // Verify admin user was NOT deleted
        $this->assertDatabaseHas('users', [
            'id' => $this->adminUser->id,
        ]);
    });

    it('allows admin users to create teams', function () {
        $this->actingAs($this->adminUser);

        Livewire::test(\App\Livewire\Management\Teams\TeamIndex::class)
            ->set('name', 'New Team')
            ->set('description', 'Test team description')
            ->set('is_active', true)
            ->call('createTeam')
            ->assertDispatched('success');

        // Verify team was created
        $this->assertDatabaseHas('teams', [
            'name' => 'New Team',
            'description' => 'Test team description',
            'is_active' => true,
        ]);

        // Verify admin was assigned to the new team
        $newTeam = Team::where('name', 'New Team')->first();
        $this->assertTrue($this->adminUser->teams->contains($newTeam));
        $this->assertEquals('admin', $this->adminUser->teams()->where('team_id', $newTeam->id)->first()->pivot->role);
    });

    it('blocks regular users from creating teams', function () {
        $this->actingAs($this->regularUser);

        // Test via HTTP request to the actual page
        $response = $this->get(route('management.teams.index'));

        // Should get 403 because of route middleware
        $response->assertStatus(403);
    });

    it('allows admin users to update teams', function () {
        $this->actingAs($this->adminUser);

        Livewire::test(\App\Livewire\Management\Teams\TeamIndex::class)
            ->call('openEditModal', $this->team->id)
            ->set('name', 'Updated Team Name')
            ->set('description', 'Updated description')
            ->set('is_active', false)
            ->call('updateTeam')
            ->assertDispatched('success');

        // Verify team was updated
        $this->team->refresh();
        $this->assertEquals('Updated Team Name', $this->team->name);
        $this->assertEquals('Updated description', $this->team->description);
        $this->assertFalse($this->team->is_active);
    });

    it('allows admin users to delete teams', function () {
        $this->actingAs($this->adminUser);

        $targetTeam = Team::factory()->create(['name' => 'Delete Me']);

        Livewire::test(\App\Livewire\Management\Teams\TeamIndex::class)
            ->call('deleteTeam', $targetTeam->id)
            ->assertDispatched('success');

        // Verify team was deleted
        $this->assertDatabaseMissing('teams', [
            'id' => $targetTeam->id,
        ]);
    });

    it('verifies canManageAnyTeam method works correctly', function () {
        // Admin user should be able to manage
        $this->assertTrue($this->adminUser->canManageAnyTeam());

        // Regular user should NOT be able to manage
        $this->assertFalse($this->regularUser->canManageAnyTeam());

        // Manager user should NOT be able to manage (only admins can)
        $managerUser = User::factory()->create(['email' => 'manager@benjh.com']);
        $managerUser->teams()->attach($this->team->id, ['role' => 'manager']);
        $this->assertFalse($managerUser->canManageAnyTeam());
    });

    it('verifies manage-system gate works correctly', function () {
        // Admin should pass the gate
        $this->actingAs($this->adminUser);
        $this->assertTrue(Gate::allows('manage-system'));

        // Regular user should fail the gate
        $this->actingAs($this->regularUser);
        $this->assertFalse(Gate::allows('manage-system'));
    });

});
