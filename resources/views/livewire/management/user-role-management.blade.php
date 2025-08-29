<div class="space-y-6">
    {{-- 👑 USER ROLE MANAGEMENT HEADER --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">User Role Management</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Manage user roles and permissions across the system
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
            
            {{-- Bulk Actions Dropdown --}}
            @if(count($selectedUsers) > 0)
                <flux:dropdown>
                    <flux:button variant="primary" icon="users" size="sm">
                        {{ count($selectedUsers) }} Selected
                    </flux:button>
                    <flux:menu>
                        <flux:menu.item wire:click="bulkAssignRole" icon="user-plus">Bulk Assign Role</flux:menu.item>
                        <flux:menu.separator />
                        <flux:menu.item wire:click="$set('selectedUsers', [])" icon="x-mark">Clear Selection</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            @endif
        </div>
    </div>

    {{-- 📊 ROLE STATISTICS CARDS --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach(['admin' => '🔑', 'manager' => '👨‍💼', 'user' => '👤', 'unassigned' => '❓'] as $roleKey => $emoji)
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ ucfirst($roleKey) }}</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $roleStatistics[$roleKey] ?? 0 }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-500">
                            {{ $roleKey === 'unassigned' ? 'Need assignment' : 'Active users' }}
                        </p>
                    </div>
                    <div class="h-10 w-10 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-xl">
                        {{ $emoji }}
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- 🔍 FILTERS AND SEARCH --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            {{-- Search --}}
            <div>
                <flux:input 
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search users..."
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
                    <flux:select.option value="">Unassigned</flux:select.option>
                </flux:select>
            </div>

            {{-- Sort By --}}
            <div>
                <flux:select wire:model.live="sortBy">
                    <flux:select.option value="name">Sort by Name</flux:select.option>
                    <flux:select.option value="email">Sort by Email</flux:select.option>
                    <flux:select.option value="role">Sort by Role</flux:select.option>
                    <flux:select.option value="created_at">Sort by Date</flux:select.option>
                </flux:select>
            </div>

            {{-- Sort Direction --}}
            <div>
                <flux:button 
                    wire:click="sortDirection = sortDirection === 'asc' ? 'desc' : 'asc'"
                    variant="ghost"
                    size="sm"
                    icon="{{ $sortDirection === 'asc' ? 'arrow-up' : 'arrow-down' }}">
                    {{ $sortDirection === 'asc' ? 'Ascending' : 'Descending' }}
                </flux:button>
            </div>
        </div>
    </div>

    {{-- 👥 USERS TABLE --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th scope="col" class="relative px-6 py-3 text-left">
                            <input 
                                type="checkbox" 
                                wire:model.live="selectAll"
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                            >
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            User
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Role
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Status
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Joined
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($users as $user)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <input 
                                    type="checkbox" 
                                    wire:model.live="selectedUsers"
                                    value="{{ $user->id }}"
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                >
                            </td>
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
                                        'manager' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200', 
                                        'user' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                        default => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                                    } }}">
                                    {{ $user->getRoleDisplayName() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($user->email_verified_at)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        Verified
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                        Pending
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $user->created_at->format('M d, Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <flux:button 
                                    wire:click="openRoleModal({{ $user->id }})"
                                    variant="ghost"
                                    size="sm"
                                    icon="pencil">
                                    Edit Role
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="text-gray-500 dark:text-gray-400">
                                    <flux:icon name="users" class="mx-auto h-12 w-12 text-gray-400" />
                                    <h3 class="mt-2 text-sm font-medium">No users found</h3>
                                    <p class="mt-1 text-sm">Try adjusting your search or filter criteria.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- 👑 ROLE ASSIGNMENT MODAL --}}
    <flux:modal wire:model="showRoleModal" class="md:max-w-md">
        <form wire:submit="saveRoleModal">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    Assign Role to {{ $selectedUser?->name }}
                </h3>
                
                <div class="space-y-4">
                    <div>
                        <flux:select wire:model="selectedRole" placeholder="Select a role">
                            @foreach($availableRoles as $roleKey => $roleDescription)
                                <flux:select.option value="{{ $roleKey }}">
                                    {{ ucfirst($roleKey) }} - {{ $roleDescription }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
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
                <flux:button wire:click="closeRoleModal" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save Role</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- BULK ROLE ASSIGNMENT CONTROLS --}}
    @if(count($selectedUsers) > 0)
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                        Bulk Role Assignment ({{ count($selectedUsers) }} users selected)
                    </h3>
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                        Assign a role to all selected users at once
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <flux:select wire:model="bulkRole" placeholder="Select role">
                        @foreach($availableRoles as $roleKey => $roleDescription)
                            <flux:select.option value="{{ $roleKey }}">
                                {{ ucfirst($roleKey) }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:button 
                        wire:click="bulkAssignRole" 
                        variant="primary"
                        size="sm"
                        :disabled="empty($bulkRole)">
                        Assign Role
                    </flux:button>
                </div>
            </div>
        </div>
    @endif
</div>