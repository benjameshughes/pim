<div class="space-y-6">
    {{-- 👥 USER MANAGEMENT HEADER --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">User Management</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Manage user accounts, roles, and permissions
            </p>
        </div>
        
        <div class="flex items-center gap-3">
            {{-- Clear Filters --}}
            <flux:button 
                wire:click="clearFilters" 
                variant="ghost"
                size="sm"
                icon="x-mark">
                Clear Filters
            </flux:button>
            
            {{-- Add User --}}
            <flux:button 
                wire:click="openCreateModal" 
                variant="primary"
                icon="plus">
                Add User
            </flux:button>
        </div>
    </div>

    {{-- 📊 USER STATISTICS CARDS --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        @foreach([
            'total' => ['Total Users', '👥', 'blue'],
            'admin' => ['Admins', '🔑', 'red'], 
            'manager' => ['Managers', '👨‍💼', 'yellow'],
            'user' => ['Users', '👤', 'green'],
            'verified' => ['Verified', '✅', 'emerald']
        ] as $key => [$label, $emoji, $color])
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ $label }}</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $userStats[$key] ?? 0 }}</p>
                    </div>
                    <div class="h-8 w-8 rounded-full bg-{{ $color }}-100 dark:bg-{{ $color }}-900 flex items-center justify-center">
                        {{ $emoji }}
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- 🔍 FILTERS AND SEARCH --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            {{-- Search --}}
            <div class="md:col-span-2">
                <flux:input 
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search users by name or email..."
                    icon="magnifying-glass"
                />
            </div>
            
            {{-- Role Filter --}}
            <div>
                <flux:select wire:model.live="roleFilter" placeholder="All Roles">
                    <flux:select.option value="">All Roles</flux:select.option>
                    <flux:select.option value="admin">Admin</flux:select.option>
                    <flux:select.option value="manager">Manager</flux:select.option>
                    <flux:select.option value="user">User</flux:select.option>
                </flux:select>
            </div>

            {{-- Status Filter --}}
            <div>
                <flux:select wire:model.live="statusFilter" placeholder="All Status">
                    <flux:select.option value="">All Status</flux:select.option>
                    <flux:select.option value="verified">Verified</flux:select.option>
                    <flux:select.option value="unverified">Unverified</flux:select.option>
                </flux:select>
            </div>

            {{-- Per Page --}}
            <div>
                <flux:select wire:model.live="perPage">
                    <flux:select.option value="15">15 per page</flux:select.option>
                    <flux:select.option value="25">25 per page</flux:select.option>
                    <flux:select.option value="50">50 per page</flux:select.option>
                </flux:select>
            </div>
        </div>
    </div>

    {{-- 👥 USERS TABLE --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        @if($users->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <button wire:click="sortBy('name')" class="flex items-center gap-2 hover:text-gray-700 dark:hover:text-gray-300">
                                    User
                                    @if($sortField === 'name')
                                        <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3 h-3" />
                                    @endif
                                </button>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <button wire:click="sortBy('role')" class="flex items-center gap-2 hover:text-gray-700 dark:hover:text-gray-300">
                                    Role
                                    @if($sortField === 'role')
                                        <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3 h-3" />
                                    @endif
                                </button>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Status
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <button wire:click="sortBy('created_at')" class="flex items-center gap-2 hover:text-gray-700 dark:hover:text-gray-300">
                                    Joined
                                    @if($sortField === 'created_at')
                                        <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3 h-3" />
                                    @endif
                                </button>
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($users as $user)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center text-sm font-bold">
                                            {{ $user->initials() }}
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $user->name }}</div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ match($user->role) {
                                            'admin' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                            'manager' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200', 
                                            'user' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                            default => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                                        } }}">
                                        {{ $user->getRoleDisplayName() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($user->email_verified_at)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            ✅ Verified
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                            ⏳ Pending
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $user->created_at->format('M d, Y') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end gap-2">
                                        <flux:button 
                                            wire:click="openEditModal({{ $user->id }})"
                                            variant="ghost"
                                            size="sm"
                                            icon="pencil">
                                            Edit
                                        </flux:button>
                                        @if($user->id !== auth()->id())
                                            <flux:button 
                                                wire:click="openDeleteModal({{ $user->id }})"
                                                variant="ghost"
                                                size="sm"
                                                icon="trash"
                                                class="text-red-600 hover:text-red-700">
                                                Delete
                                            </flux:button>
                                        @else
                                            <span class="text-xs text-gray-400 italic">Current User</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            {{-- Pagination --}}
            @if($users->hasPages())
                <div class="px-6 py-3 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
                    {{ $users->links() }}
                </div>
            @endif
        @else
            <div class="px-6 py-12 text-center">
                <flux:icon name="users" class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No users found</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if($search || $roleFilter || $statusFilter)
                        Try adjusting your search or filter criteria.
                    @else
                        Get started by creating your first user.
                    @endif
                </p>
                @if(!$search && !$roleFilter && !$statusFilter)
                    <div class="mt-6">
                        <flux:button wire:click="openCreateModal" variant="primary" icon="plus">
                            Add First User
                        </flux:button>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- 👤 CREATE USER MODAL --}}
    <flux:modal wire:model="showCreateModal" class="md:max-w-md">
        <form wire:submit="createUser">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    Create New User
                </h3>
                
                <div class="space-y-4">
                    <div>
                        <flux:field label="Name">
                            <flux:input wire:model="name" placeholder="Enter user's full name" />
                            <flux:error name="name" />
                        </flux:field>
                    </div>

                    <div>
                        <flux:field label="Email">
                            <flux:input type="email" wire:model="email" placeholder="user@example.com" />
                            <flux:error name="email" />
                        </flux:field>
                    </div>

                    <div>
                        <flux:field label="Role">
                            <flux:select wire:model="role" placeholder="Select a role">
                                <flux:select.option value="user">User - Basic read access</flux:select.option>
                                <flux:select.option value="manager">Manager - Product management</flux:select.option>
                                <flux:select.option value="admin">Admin - Full system access</flux:select.option>
                            </flux:select>
                            <flux:error name="role" />
                        </flux:field>
                    </div>

                    <div>
                        <flux:field>
                            <flux:checkbox wire:model="sendWelcomeEmail">Send welcome email with magic login link</flux:checkbox>
                        </flux:field>
                    </div>

                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                        <div class="flex">
                            <flux:icon name="information-circle" class="h-5 w-5 text-blue-400" />
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">Role Permissions</h3>
                                <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                                    <ul class="list-disc pl-5 space-y-1">
                                        <li><strong>Admin:</strong> Full system access including user management</li>
                                        <li><strong>Manager:</strong> Product and operations management</li>
                                        <li><strong>User:</strong> Basic read access to assigned areas</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end gap-3 p-6 bg-gray-50 dark:bg-gray-900">
                <flux:button type="button" wire:click="closeCreateModal" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="createUser">Create User</span>
                    <span wire:loading wire:target="createUser">Creating...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- ✏️ EDIT USER MODAL --}}
    <flux:modal wire:model="showEditModal" class="md:max-w-md">
        <form wire:submit="updateUser">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    Edit User: {{ $editingUser?->name }}
                </h3>
                
                <div class="space-y-4">
                    <div>
                        <flux:field label="Name">
                            <flux:input wire:model="name" placeholder="Enter user's full name" />
                            <flux:error name="name" />
                        </flux:field>
                    </div>

                    <div>
                        <flux:field label="Email">
                            <flux:input type="email" wire:model="email" placeholder="user@example.com" />
                            <flux:error name="email" />
                        </flux:field>
                    </div>

                    <div>
                        <flux:field label="Role">
                            <flux:select wire:model="role" placeholder="Select a role">
                                <flux:select.option value="user">User - Basic read access</flux:select.option>
                                <flux:select.option value="manager">Manager - Product management</flux:select.option>
                                <flux:select.option value="admin">Admin - Full system access</flux:select.option>
                            </flux:select>
                            <flux:error name="role" />
                        </flux:field>
                    </div>

                    @if($editingUser?->id === auth()->id() && $editingUser?->isAdmin())
                        <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4">
                            <div class="flex">
                                <flux:icon name="exclamation-triangle" class="h-5 w-5 text-yellow-400" />
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Warning</h3>
                                    <p class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">
                                        You cannot change your own admin role to prevent system lockout.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            
            <div class="flex justify-end gap-3 p-6 bg-gray-50 dark:bg-gray-900">
                <flux:button type="button" wire:click="closeEditModal" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="updateUser">Update User</span>
                    <span wire:loading wire:target="updateUser">Updating...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- 🗑️ DELETE USER CONFIRMATION MODAL --}}
    <flux:modal wire:model="showDeleteModal" class="md:max-w-md">
        <div class="p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="exclamation-triangle" class="h-6 w-6 text-red-600" />
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        Delete User
                    </h3>
                    <div class="mt-2">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Are you sure you want to delete <strong>{{ $deletingUser?->name }}</strong>? 
                            This action cannot be undone and will immediately remove their access to the system.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="flex justify-end gap-3 p-6 bg-gray-50 dark:bg-gray-900">
            <flux:button wire:click="closeDeleteModal" variant="ghost">Cancel</flux:button>
            <flux:button 
                wire:click="deleteUser" 
                variant="danger" 
                wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="deleteUser">Delete User</span>
                <span wire:loading wire:target="deleteUser">Deleting...</span>
            </flux:button>
        </div>
    </flux:modal>
</div>