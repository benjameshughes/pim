{{-- 👥 USER MANAGEMENT INDEX ✨ --}}
<div class="space-y-6">
    {{-- Header & Actions --}}
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
                👥 Users
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mt-1">
                Manage user accounts and permissions
            </p>
        </div>
        <div class="flex gap-3">
            <flux:button wire:click="openCreateModal" icon="plus" variant="primary">
                Add User
            </flux:button>
        </div>
    </div>

    {{-- Search & Filter Bar --}}
    <div class="flex gap-4 items-center">
        <div class="flex-1 max-w-none">
            <flux:input 
                wire:model.live.debounce.300ms="search" 
                placeholder="Search users by name or email..." 
                icon="magnifying-glass"
            />
        </div>
        <div class="flex-shrink-0">
            <flux:select wire:model.live="perPage" class="w-16">
                <option value="20">20</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </flux:select>
        </div>
    </div>

    {{-- Main Users Table --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
        @if ($users->count())
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th wire:click="sortBy('name')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800">
                            <div class="flex items-center gap-2">
                                Name
                                @if ($sortField === 'name')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3 h-3" />
                                @endif
                            </div>
                        </th>
                        <th wire:click="sortBy('email')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800">
                            <div class="flex items-center gap-2">
                                Email
                                @if ($sortField === 'email')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3 h-3" />
                                @endif
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Teams
                        </th>
                        <th wire:click="sortBy('created_at')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800">
                            <div class="flex items-center gap-2">
                                Created
                                @if ($sortField === 'created_at')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3 h-3" />
                                @endif
                            </div>
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($users as $user)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <flux:avatar size="xs">{{ $user->initials() }}</flux:avatar>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $user->name }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 dark:text-white">{{ $user->email }}</div>
                            </td>
                            <td class="px-6 py-4">
                                @if ($user->teams->count())
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($user->teams as $team)
                                            <div class="flex items-center gap-1">
                                                <flux:badge size="sm" color="blue">
                                                    {{ $team->name }}
                                                </flux:badge>
                                                <flux:badge size="sm" :color="match($team->pivot->role) {
                                                    'admin' => 'red',
                                                    'manager' => 'yellow', 
                                                    'user' => 'gray',
                                                    default => 'gray'
                                                }">
                                                    {{ ucfirst($team->pivot->role) }}
                                                </flux:badge>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500 text-sm">No teams</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $user->created_at->format('M j, Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button 
                                        wire:click="openEditModal({{ $user->id }})" 
                                        variant="ghost" 
                                        size="sm"
                                        icon="pencil"
                                    >
                                        Edit
                                    </flux:button>
                                    @if($user->id !== auth()->id())
                                        <flux:button 
                                            wire:click="deleteUser({{ $user->id }})" 
                                            wire:confirm="Are you sure you want to delete this user?"
                                            variant="ghost" 
                                            size="sm" 
                                            color="red"
                                            icon="trash"
                                        >
                                            Delete
                                        </flux:button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="text-center py-12">
                <flux:icon name="users" class="mx-auto h-8 w-8 text-gray-400" />
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No users found</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if ($search)
                        No users match your search criteria.
                    @else
                        Get started by adding your first user.
                    @endif
                </p>
                @if (!$search)
                    <div class="mt-6">
                        <flux:button wire:click="openCreateModal" icon="plus" variant="primary">
                            Add First User
                        </flux:button>
                    </div>
                @endif
            </div>
        @endif

        {{-- Pagination --}}
        @if ($users->hasPages())
            <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700">
                {{ $users->links() }}
            </div>
        @endif
    </div>

    {{-- Create User Modal --}}
    <flux:modal wire:model="showCreateModal" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Create New User</flux:heading>
                <flux:text class="mt-2" variant="muted">Add a new user to your team with magic login access.</flux:text>
            </div>
            
            <div class="space-y-4">
                <flux:field label="Name">
                    <flux:input wire:model="name" placeholder="Enter user's name" />
                    <flux:error name="name" />
                </flux:field>
                
                <flux:field label="Email">
                    <flux:input type="email" wire:model="email" placeholder="Enter email address" />
                    <flux:error name="email" />
                </flux:field>
                
                <flux:field label="Team">
                    <flux:select wire:model="teamId">
                        <option value="">Select a team...</option>
                        @forelse($teams as $team)
                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                        @empty
                            <option value="">No teams available - create one first</option>
                        @endforelse
                    </flux:select>
                    <flux:error name="teamId" />
                </flux:field>
                
                <flux:field label="Role">
                    <flux:select wire:model="role">
                        <option value="user">User - Read-only access</option>
                        <option value="manager">Manager - Can manage products & barcodes</option>
                        <option value="admin">Admin - Full team control</option>
                    </flux:select>
                    <flux:error name="role" />
                </flux:field>
                
                <flux:callout variant="info">
                    📧 A magic login link will be sent to this email address automatically.
                </flux:callout>
            </div>
            
            <div class="flex justify-end gap-3">
                <flux:button wire:click="closeCreateModal" variant="ghost">
                    Cancel
                </flux:button>
                <flux:button 
                    wire:click="createUser" 
                    variant="primary"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>Create User & Send Magic Link</span>
                    <span wire:loading>Creating & Sending...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Edit User Modal --}}
    <flux:modal wire:model="showEditModal" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Edit User</flux:heading>
                <flux:text class="mt-2" variant="muted">Update user information.</flux:text>
            </div>
            
            <div class="space-y-4">
                <flux:field label="Name">
                    <flux:input wire:model="name" placeholder="Enter user's name" />
                    <flux:error name="name" />
                </flux:field>
                
                <flux:field label="Email">
                    <flux:input type="email" wire:model="email" placeholder="Enter email address" />
                    <flux:error name="email" />
                </flux:field>
                
                <flux:field label="Team">
                    <flux:select wire:model="teamId">
                        <option value="">Select a team...</option>
                        @forelse($teams as $team)
                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                        @empty
                            <option value="">No teams available</option>
                        @endforelse
                    </flux:select>
                    <flux:error name="teamId" />
                </flux:field>
                
                <flux:field label="Role">
                    <flux:select wire:model="role">
                        <option value="user">User - Read-only access</option>
                        <option value="manager">Manager - Can manage products & barcodes</option>
                        <option value="admin">Admin - Full team control</option>
                    </flux:select>
                    <flux:error name="role" />
                </flux:field>
            </div>
            
            <div class="flex justify-end gap-3">
                <flux:button wire:click="closeEditModal" variant="ghost">
                    Cancel
                </flux:button>
                <flux:button 
                    wire:click="updateUser" 
                    variant="primary"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>Update User</span>
                    <span wire:loading>Updating...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>