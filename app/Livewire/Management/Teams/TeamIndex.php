<?php

namespace App\Livewire\Management\Teams;

use App\Models\Team;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class TeamIndex extends Component
{
    use WithPagination;

    public function mount()
    {
        // Only admins can access team management
        $this->authorize('manage-system');
    }

    public string $search = '';
    public int $perPage = 20;
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';
    
    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public ?Team $editingTeam = null;
    
    public string $name = '';
    public string $description = '';
    public bool $is_active = true;

    public function render()
    {
        $teams = $this->paginate();

        return view('livewire.management.teams.team-index', compact('teams'));
    }

    public function paginate()
    {
        return Team::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%"))
            ->withCount(['users'])
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
        $this->reset(['name', 'description', 'is_active']);
        $this->is_active = true;
        $this->showCreateModal = true;
    }

    public function closeCreateModal()
    {
        $this->showCreateModal = false;
        $this->reset(['name', 'description', 'is_active']);
    }

    public function openEditModal(Team $team)
    {
        $this->editingTeam = $team;
        $this->name = $team->name;
        $this->description = $team->description ?? '';
        $this->is_active = $team->is_active;
        $this->showEditModal = true;
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->editingTeam = null;
        $this->reset(['name', 'description', 'is_active']);
    }

    public function createTeam()
    {
        // Use Laravel's built-in authorization
        $this->authorize('manage-system');

        $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $team = Team::create([
            'name' => $this->name,
            'description' => $this->description ?: null,
            'is_active' => $this->is_active,
        ]);

        auth()->user()->teams()->attach($team->id, ['role' => 'admin']);

        $this->dispatch('success', 'Team created successfully');
        $this->closeCreateModal();
        $this->resetPage();
    }

    public function updateTeam()
    {
        // Use Laravel's built-in authorization
        $this->authorize('manage-system');

        $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $this->editingTeam->update([
            'name' => $this->name,
            'description' => $this->description ?: null,
            'is_active' => $this->is_active,
        ]);

        $this->dispatch('success', 'Team updated successfully');
        $this->closeEditModal();
    }

    public function deleteTeam(Team $team)
    {
        // Use Laravel's built-in authorization
        $this->authorize('manage-system');

        $team->delete();
        $this->dispatch('success', 'Team deleted successfully');
        $this->resetPage();
    }
}