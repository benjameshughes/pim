<?php

namespace App\Livewire\Management\Users;

use App\Models\Team;
use App\Models\User;
use App\Rules\AllowedEmail;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;
use Maize\MagicLogin\Facades\MagicLink;

class UserIndex extends Component
{
    use WithPagination;

    public function mount()
    {
        // Only admins can access user management
        $this->authorize('manage-system');
    }

    public string $search = '';
    public int $perPage = 20;
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';
    
    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public ?User $editingUser = null;
    
    public string $name = '';
    public string $email = '';
    public string $role = 'user';
    public ?int $teamId = null;

    public function render()
    {
        $users = $this->paginate();
        $teams = Team::where('is_active', true)->get();

        return view('livewire.management.users.user-index', compact('users', 'teams'));
    }

    public function paginate()
    {
        return User::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%"))
            ->with('teams')
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);
    }

    public function updating()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function openCreateModal()
    {
        $this->reset(['name', 'email', 'role', 'teamId']);
        $this->role = 'user'; // Default role
        $this->teamId = Team::where('is_active', true)->first()?->id; // Default to first active team
        $this->showCreateModal = true;
    }

    public function closeCreateModal()
    {
        $this->showCreateModal = false;
        $this->reset(['name', 'email', 'role', 'teamId']);
    }

    public function openEditModal(User $user)
    {
        $this->editingUser = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        
        // Get the user's current team and role
        $userTeam = $user->teams->first();
        $this->teamId = $userTeam?->id;
        $this->role = $userTeam?->pivot->role ?? 'user';
        
        $this->showEditModal = true;
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->editingUser = null;
        $this->reset(['name', 'email', 'role', 'teamId']);
    }

    public function createUser()
    {
        // Use Laravel's built-in authorization
        $this->authorize('manage-system');

        $this->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users', new AllowedEmail],
            'role' => 'required|in:admin,manager,user',
            'teamId' => 'required|exists:teams,id',
        ]);

        try {
            // Create user with random password (like your magic login flow)
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Str::random(32), // Random password - user will use magic links
            ]);

            // Get the selected team
            $team = Team::findOrFail($this->teamId);

            // Assign user to selected team with selected role
            $user->teams()->attach($this->teamId, ['role' => $this->role]);

            // Send magic link immediately
            MagicLink::send(
                authenticatable: $user,
                redirectUrl: route('dashboard'),
                expiration: now()->addMinutes(30)
            );

            $this->dispatch('success', 'User created with ' . $this->role . ' role in ' . $team->name . ' and magic login link sent to ' . $this->email);
            $this->closeCreateModal();
            $this->resetPage();

        } catch (\Exception $e) {
            logger()->error('Failed to create user or send magic link', [
                'email' => $this->email,
                'role' => $this->role,
                'error' => $e->getMessage()
            ]);

            $this->dispatch('error', 'Failed to create user or send magic link. Please try again.');
        }
    }

    public function updateUser()
    {
        // Use Laravel's built-in authorization
        $this->authorize('manage-system');

        $this->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $this->editingUser->id, new AllowedEmail],
            'role' => 'required|in:admin,manager,user',
            'teamId' => 'required|exists:teams,id',
        ]);

        // Update user basic info
        $this->editingUser->update([
            'name' => $this->name,
            'email' => $this->email,
        ]);

        // Update team and role - sync will replace all team relationships
        $this->editingUser->teams()->sync([
            $this->teamId => ['role' => $this->role]
        ]);

        $this->dispatch('success', 'User updated successfully');
        $this->closeEditModal();
    }

    public function deleteUser(User $user)
    {
        // Use Laravel's built-in authorization
        $this->authorize('manage-system');

        // Prevent users from deleting themselves
        if ($user->id === auth()->id()) {
            $this->dispatch('error', 'You cannot delete yourself!');
            return;
        }

        $user->delete();
        $this->dispatch('success', 'User deleted successfully');
        $this->resetPage();
    }
}