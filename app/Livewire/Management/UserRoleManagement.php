<?php

namespace App\Livewire\Management;

use App\Actions\Users\AssignUserRoleAction;
use App\Actions\Users\GetUsersWithRolesAction;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Collection;

/**
 * 👑 USER ROLE MANAGEMENT COMPONENT
 * 
 * Livewire component for managing user roles in the simplified permission system.
 * Features user listing, role assignment, filtering, and bulk operations.
 */
class UserRoleManagement extends Component
{
    use WithPagination;

    // Filters
    public string $search = '';
    public string $roleFilter = '';
    public string $sortBy = 'name';
    public string $sortDirection = 'asc';

    // Modal state
    public bool $showRoleModal = false;
    public ?User $selectedUser = null;
    public string $selectedRole = '';

    // Bulk operations
    public array $selectedUsers = [];
    public bool $selectAll = false;
    public string $bulkRole = '';

    protected $listeners = [
        'user-role-updated' => '$refresh',
        'refresh-users' => '$refresh',
    ];

    public function mount()
    {
        // Initialize component
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedRoleFilter()
    {
        $this->resetPage();
    }

    public function updatedSelectAll()
    {
        if ($this->selectAll) {
            $this->selectedUsers = $this->getUsers()->pluck('id')->toArray();
        } else {
            $this->selectedUsers = [];
        }
    }

    public function openRoleModal($userId)
    {
        $user = User::find($userId);
        if (!$user) {
            $this->dispatch('notify', message: 'User not found', type: 'error');
            return;
        }

        $this->selectedUser = $user;
        $this->selectedRole = $user->role ?? 'user';
        $this->showRoleModal = true;
    }

    public function saveRoleModal()
    {
        if (!$this->selectedUser || !$this->selectedRole) {
            $this->dispatch('notify', message: 'Please select a user and role', type: 'error');
            return;
        }

        $result = AssignUserRoleAction::run($this->selectedUser, $this->selectedRole);

        if ($result['success']) {
            $this->dispatch('notify', 
                message: "Updated {$this->selectedUser->name}'s role to {$this->selectedRole} 👑", 
                type: 'success'
            );
            $this->closeRoleModal();
            $this->dispatch('user-role-updated');
        } else {
            $this->dispatch('notify', message: $result['message'], type: 'error');
        }
    }

    public function closeRoleModal()
    {
        $this->showRoleModal = false;
        $this->selectedUser = null;
        $this->selectedRole = '';
        $this->resetValidation();
    }

    public function bulkAssignRole()
    {
        if (empty($this->selectedUsers) || empty($this->bulkRole)) {
            $this->dispatch('notify', message: 'Please select users and a role', type: 'error');
            return;
        }

        $users = User::whereIn('id', $this->selectedUsers)->get();
        $successCount = 0;
        $errors = [];

        foreach ($users as $user) {
            $result = AssignUserRoleAction::run($user, $this->bulkRole);
            if ($result['success']) {
                $successCount++;
            } else {
                $errors[] = "Failed to update {$user->name}: {$result['message']}";
            }
        }

        if ($successCount > 0) {
            $this->dispatch('notify', 
                message: "Updated {$successCount} users to {$this->bulkRole} role 🎉", 
                type: 'success'
            );
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->dispatch('notify', message: $error, type: 'error');
            }
        }

        $this->selectedUsers = [];
        $this->selectAll = false;
        $this->bulkRole = '';
        $this->dispatch('user-role-updated');
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->roleFilter = '';
        $this->sortBy = 'name';
        $this->sortDirection = 'asc';
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function getUsers(): Collection
    {
        $filters = [
            'search' => $this->search,
            'role' => $this->roleFilter,
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
        ];

        $result = GetUsersWithRolesAction::run($filters);
        
        return $result['success'] ? collect($result['data']['users']) : collect([]);
    }

    public function getRoleStatistics(): array
    {
        $result = GetUsersWithRolesAction::run();
        
        return $result['success'] ? $result['data']['role_statistics'] : [];
    }

    public function render()
    {
        return view('livewire.management.user-role-management', [
            'users' => $this->getUsers(),
            'roleStatistics' => $this->getRoleStatistics(),
            'availableRoles' => AssignUserRoleAction::getAvailableRoles(),
        ]);
    }
}
