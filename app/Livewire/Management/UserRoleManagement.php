<?php

namespace App\Livewire\Management;

use App\Actions\Users\AssignUserRoleAction;
use App\Actions\Users\CreateUserAction;
use App\Actions\Users\GetUsersWithRolesAction;
use App\Models\User;
use App\Services\PermissionTemplateService;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

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
    
    // Permission template modal state
    public bool $showPermissionModal = false;
    public ?User $selectedUserForPermissions = null;
    public string $selectedTemplate = '';
    public bool $showCustomPermissions = false;
    
    // Create user modal state
    public bool $showCreateModal = false;
    public string $createName = '';
    public string $createEmail = '';
    public string $createRole = 'user';
    public bool $sendWelcomeEmail = true;

    // Bulk operations
    public array $selectedUsers = [];
    public bool $selectAll = false;
    public string $bulkRole = '';

    protected $listeners = [
        'user-role-updated' => '$refresh',
        'user-created' => '$refresh',
        'refresh-users' => '$refresh',
    ];

    public function mount()
    {
        // Only admins can access user role management
        $this->authorize('manage-system-settings');
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
        $this->selectedRole = $user->getPrimaryRole() ?? 'user';
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

    // ===== CREATE USER MODAL OPERATIONS =====

    public function openCreateModal()
    {
        $this->reset(['createName', 'createEmail', 'createRole', 'sendWelcomeEmail']);
        $this->createRole = 'user';
        $this->sendWelcomeEmail = true;
        $this->showCreateModal = true;
    }

    public function closeCreateModal()
    {
        $this->showCreateModal = false;
        $this->reset(['createName', 'createEmail', 'createRole', 'sendWelcomeEmail']);
        $this->resetValidation();
    }

    public function createUser()
    {
        $this->authorize('manage-users');

        $this->validate([
            'createName' => 'required|string|max:255',
            'createEmail' => 'required|string|email|max:255|unique:users,email',
            'createRole' => 'required|in:admin,manager,user',
        ]);

        $result = CreateUserAction::run(
            $this->createName,
            $this->createEmail,
            $this->createRole,
            $this->sendWelcomeEmail
        );

        if ($result['success']) {
            $magicLinkStatus = $result['data']['magic_link_sent'] ? ' Magic login link sent!' : '';
            $this->dispatch('notify', 
                message: "User {$this->createName} created successfully with {$this->createRole} role.{$magicLinkStatus} 🎉", 
                type: 'success'
            );
            $this->closeCreateModal();
            $this->dispatch('user-created');
        } else {
            $this->dispatch('notify', message: $result['message'], type: 'error');
        }
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

    // ===== PERMISSION TEMPLATE METHODS =====

    public function openPermissionModal(User $user)
    {
        $this->selectedUserForPermissions = $user;
        $this->selectedTemplate = '';
        $this->showCustomPermissions = false;
        $this->showPermissionModal = true;
    }

    public function closePermissionModal()
    {
        $this->showPermissionModal = false;
        $this->selectedUserForPermissions = null;
        $this->selectedTemplate = '';
        $this->showCustomPermissions = false;
    }

    public function selectTemplate(string $templateKey)
    {
        $this->selectedTemplate = $templateKey;
        
        if ($templateKey === 'custom') {
            $this->showCustomPermissions = true;
        } else {
            $this->showCustomPermissions = false;
        }
    }

    public function applyPermissionTemplate()
    {
        $this->authorize('manage-role-permissions');

        if (!$this->selectedUserForPermissions || !$this->selectedTemplate) {
            $this->dispatch('notify', message: 'Please select a template', type: 'error');
            return;
        }

        if ($this->selectedTemplate === 'custom') {
            $this->dispatch('notify', message: 'Custom permissions not implemented yet', type: 'info');
            return;
        }

        try {
            $success = PermissionTemplateService::applyTemplate(
                $this->selectedTemplate, 
                $this->selectedUserForPermissions
            );

            if ($success) {
                $template = PermissionTemplateService::getTemplate($this->selectedTemplate);
                $this->dispatch('notify', 
                    message: "Applied {$template['name']} template ({$template['permission_count']} permissions) to {$this->selectedUserForPermissions->name} 🎭", 
                    type: 'success'
                );
                $this->closePermissionModal();
                $this->dispatch('user-role-updated');
            } else {
                $this->dispatch('notify', message: 'Failed to apply template', type: 'error');
            }

        } catch (\Exception $e) {
            $this->dispatch('notify', message: 'Error applying template: ' . $e->getMessage(), type: 'error');
        }
    }

    public function getPermissionTemplates(): array
    {
        return PermissionTemplateService::getTemplates();
    }

    public function getTemplateStats(): array
    {
        return PermissionTemplateService::getTemplateStats();
    }

    public function render()
    {
        return view('livewire.management.user-role-management', [
            'users' => $this->getUsers(),
            'roleStatistics' => $this->getRoleStatistics(),
            'availableRoles' => AssignUserRoleAction::getAvailableRoles(),
            'permissionTemplates' => $this->getPermissionTemplates(),
            'templateStats' => $this->getTemplateStats(),
        ]);
    }
}
