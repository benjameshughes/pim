<?php

namespace App\Livewire\Management\Users;

use App\Actions\Users\CreateUserAction;
use App\Actions\Users\DeleteUserAction;
use App\Actions\Users\UpdateUserAction;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * 👥 USER INDEX COMPONENT
 *
 * Complete user management interface with role-based CRUD operations.
 * Features user creation, editing, deletion, search, and filtering.
 * Admin-only access with comprehensive validation and audit logging.
 */
class UserIndex extends Component
{
    use WithPagination;

    // Search and filtering
    public string $search = '';

    public string $roleFilter = '';

    public string $statusFilter = '';

    public int $perPage = 15;

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    // Modal states
    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public bool $showDeleteModal = false;

    public ?User $editingUser = null;

    public ?User $deletingUser = null;

    // Form fields
    public string $name = '';

    public string $email = '';

    public string $role = 'user';

    public bool $sendWelcomeEmail = true;

    protected $listeners = [
        'user-updated' => '$refresh',
        'refresh-users' => '$refresh',
    ];

    public function mount()
    {
        // Only admins can access user management
        $this->authorize('manage-system-settings');
    }

    public function render()
    {
        return view('livewire.management.users.user-index', [
            'users' => $this->getUsers(),
            'userStats' => $this->getUserStatistics(),
        ]);
    }

    public function getUsers()
    {
        return User::query()
            ->with('roles')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->when($this->roleFilter, fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', $this->roleFilter)))
            ->when($this->statusFilter === 'verified', fn ($q) => $q->whereNotNull('email_verified_at'))
            ->when($this->statusFilter === 'unverified', fn ($q) => $q->whereNull('email_verified_at'))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);
    }

    public function getUserStatistics(): array
    {
        $totalUsers = User::count();
        $verifiedUsers = User::whereNotNull('email_verified_at')->count();

        // Count users by role using Spatie Laravel Permission
        $adminCount = User::role('admin')->count();
        $managerCount = User::role('manager')->count();
        $userCount = User::role('user')->count();

        return [
            'total' => $totalUsers,
            'admin' => $adminCount,
            'manager' => $managerCount,
            'user' => $userCount,
            'verified' => $verifiedUsers,
            'unverified' => $totalUsers - $verifiedUsers,
        ];
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedRoleFilter()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
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
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->roleFilter = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    // ===== CRUD MODAL OPERATIONS =====

    public function openCreateModal()
    {
        $this->reset(['name', 'email', 'role', 'sendWelcomeEmail']);
        $this->role = 'user';
        $this->sendWelcomeEmail = true;
        $this->showCreateModal = true;
    }

    public function closeCreateModal()
    {
        $this->showCreateModal = false;
        $this->reset(['name', 'email', 'role', 'sendWelcomeEmail']);
        $this->resetValidation();
    }

    public function openEditModal(User $user)
    {
        $this->editingUser = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role ?? 'user';
        $this->showEditModal = true;
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->editingUser = null;
        $this->reset(['name', 'email', 'role']);
        $this->resetValidation();
    }

    public function openDeleteModal(User $user)
    {
        $this->deletingUser = $user;
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal()
    {
        $this->showDeleteModal = false;
        $this->deletingUser = null;
    }

    // ===== CRUD ACTIONS =====

    public function createUser()
    {
        $this->authorize('manage-system-settings');

        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'role' => 'required|in:admin,manager,user',
        ]);

        $result = CreateUserAction::run(
            $this->name,
            $this->email,
            $this->role,
            $this->sendWelcomeEmail
        );

        if ($result['success']) {
            $magicLinkStatus = $result['data']['magic_link_sent'] ? ' Magic login link sent!' : '';
            $this->dispatch('notify',
                message: "User {$this->name} created successfully with {$this->role} role.{$magicLinkStatus} 🎉",
                type: 'success'
            );
            $this->closeCreateModal();
            $this->dispatch('user-updated');
        } else {
            $this->dispatch('notify', message: $result['message'], type: 'error');
        }
    }

    public function updateUser()
    {
        $this->authorize('manage-system-settings');

        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$this->editingUser->id,
            'role' => 'required|in:admin,manager,user',
        ]);

        $result = UpdateUserAction::run(
            $this->editingUser,
            $this->name,
            $this->email,
            $this->role
        );

        if ($result['success']) {
            $changesSummary = $result['data']['changes_made'] ? ' Changes applied.' : ' No changes made.';
            $this->dispatch('notify',
                message: "User {$this->name} updated successfully.{$changesSummary} ✨",
                type: 'success'
            );
            $this->closeEditModal();
            $this->dispatch('user-updated');
        } else {
            $this->dispatch('notify', message: $result['message'], type: 'error');
        }
    }

    public function deleteUser()
    {
        $this->authorize('manage-system-settings');

        if (! $this->deletingUser) {
            $this->dispatch('notify', message: 'No user selected for deletion', type: 'error');

            return;
        }

        $result = DeleteUserAction::run($this->deletingUser);

        if ($result['success']) {
            $this->dispatch('notify',
                message: "User {$this->deletingUser->name} deleted successfully 🗑️",
                type: 'success'
            );
            $this->closeDeleteModal();
            $this->dispatch('user-updated');
        } else {
            $this->dispatch('notify', message: $result['message'], type: 'error');
        }
    }
}
