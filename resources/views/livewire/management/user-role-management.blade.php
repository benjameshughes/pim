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
            {{-- Create User Button --}}
            <flux:button 
                wire:click="openCreateModal"
                variant="primary"
                icon="plus">
                Create User
            </flux:button>
            
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
                                <div class="flex items-center gap-2 justify-end">
                                    <flux:button 
                                        wire:click="openPermissionModal({{ $user->id }})"
                                        variant="ghost"
                                        size="sm"
                                        icon="key">
                                        Permissions
                                    </flux:button>
                                    <flux:button 
                                        wire:click="openRoleModal({{ $user->id }})"
                                        variant="ghost"
                                        size="sm"
                                        icon="pencil">
                                        Role
                                    </flux:button>
                                </div>
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

    {{-- 👤 CREATE USER MODAL --}}
    <flux:modal wire:model="showCreateModal" class="md:max-w-md">
        <form wire:submit="createUser">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    Create New User with Role
                </h3>
                
                <div class="space-y-4">
                    <div>
                        <flux:field label="Name">
                            <flux:input wire:model="createName" placeholder="Enter user's full name" />
                            <flux:error name="createName" />
                        </flux:field>
                    </div>

                    <div>
                        <flux:field label="Email">
                            <flux:input type="email" wire:model="createEmail" placeholder="user@example.com" />
                            <flux:error name="createEmail" />
                        </flux:field>
                    </div>

                    <div>
                        <flux:field label="Role">
                            <flux:select wire:model="createRole" placeholder="Select a role">
                                <flux:select.option value="user">User - Basic read access</flux:select.option>
                                <flux:select.option value="manager">Manager - Product management</flux:select.option>
                                <flux:select.option value="admin">Admin - Full system access</flux:select.option>
                            </flux:select>
                            <flux:error name="createRole" />
                        </flux:field>
                    </div>

                    <div>
                        <flux:field>
                            <flux:checkbox wire:model="sendWelcomeEmail">Send welcome email with magic login link</flux:checkbox>
                        </flux:field>
                    </div>

                    <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                        <div class="flex">
                            <flux:icon name="user-plus" class="h-5 w-5 text-green-400" />
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-green-800 dark:text-green-200">Quick User Creation</h3>
                                <div class="mt-2 text-sm text-green-700 dark:text-green-300">
                                    <p>User will be created with immediate role assignment and email verification. Magic login link allows instant access without password setup.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end gap-3 p-6 bg-gray-50 dark:bg-gray-900">
                <flux:button type="button" wire:click="closeCreateModal" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="createUser">Create User & Assign Role</span>
                    <span wire:loading wire:target="createUser">Creating...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- 🎭 PERMISSION TEMPLATE MODAL --}}
    <flux:modal wire:model="showPermissionModal" class="md:max-w-2xl">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                🎭 Permission Templates for {{ $selectedUserForPermissions?->name }}
            </h3>
            
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                Choose a pre-configured permission template to quickly assign appropriate access levels. 
                Templates organize our 241 permissions into logical groups.
            </p>

            {{-- Template Selection Grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                @foreach($permissionTemplates as $key => $template)
                    <div class="relative">
                        <input 
                            type="radio" 
                            wire:model.live="selectedTemplate"
                            value="{{ $key }}"
                            id="template_{{ $key }}"
                            class="sr-only"
                        >
                        <label 
                            for="template_{{ $key }}"
                            class="flex flex-col p-4 border-2 rounded-lg cursor-pointer transition-all
                                {{ $selectedTemplate === $key 
                                    ? 'border-' . $template['color'] . '-500 bg-' . $template['color'] . '-50 dark:bg-' . $template['color'] . '-900/20' 
                                    : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600' }}">
                            
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center">
                                    <div class="p-2 rounded-md bg-{{ $template['color'] }}-100 dark:bg-{{ $template['color'] }}-900">
                                        <flux:icon name="{{ $template['icon'] }}" class="h-5 w-5 text-{{ $template['color'] }}-600 dark:text-{{ $template['color'] }}-400" />
                                    </div>
                                    <div class="ml-3">
                                        <h4 class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $template['name'] }}
                                        </h4>
                                        <p class="text-xs text-{{ $template['color'] }}-600 dark:text-{{ $template['color'] }}-400">
                                            {{ $template['permission_count'] }} permissions
                                        </p>
                                    </div>
                                </div>
                                @if($selectedTemplate === $key)
                                    <flux:icon name="check-circle" class="h-5 w-5 text-{{ $template['color'] }}-600" />
                                @endif
                            </div>
                            
                            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                                {{ $template['description'] }}
                            </p>
                            
                            {{-- Permission Count Bar --}}
                            <div class="mt-3 flex items-center">
                                <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                    <div class="bg-{{ $template['color'] }}-500 h-2 rounded-full" 
                                         style="width: {{ round(($template['permission_count'] / 241) * 100, 1) }}%"></div>
                                </div>
                                <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">
                                    {{ round(($template['permission_count'] / 241) * 100, 1) }}%
                                </span>
                            </div>
                        </label>
                    </div>
                @endforeach

                {{-- Custom Template Option --}}
                <div class="relative">
                    <input 
                        type="radio" 
                        wire:model.live="selectedTemplate"
                        value="custom"
                        id="template_custom"
                        class="sr-only"
                    >
                    <label 
                        for="template_custom"
                        class="flex flex-col p-4 border-2 border-dashed rounded-lg cursor-pointer transition-all
                            {{ $selectedTemplate === 'custom' 
                                ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/20' 
                                : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500' }}">
                        
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center">
                                <div class="p-2 rounded-md bg-purple-100 dark:bg-purple-900">
                                    <flux:icon name="adjustments-horizontal" class="h-5 w-5 text-purple-600 dark:text-purple-400" />
                                </div>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white">
                                        Custom Permissions
                                    </h4>
                                    <p class="text-xs text-purple-600 dark:text-purple-400">
                                        Individual control
                                    </p>
                                </div>
                            </div>
                            @if($selectedTemplate === 'custom')
                                <flux:icon name="check-circle" class="h-5 w-5 text-purple-600" />
                            @endif
                        </div>
                        
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            Select individual permissions from all 241 available options. 
                            <span class="text-purple-600 dark:text-purple-400 font-medium">(Coming Soon)</span>
                        </p>
                    </label>
                </div>
            </div>

            {{-- Template Stats --}}
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 mb-6">
                <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">📊 Permission System Overview</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @foreach($templateStats['templates'] as $stat)
                        <div class="text-center">
                            <div class="text-lg font-bold text-gray-900 dark:text-white">{{ $stat['permission_count'] }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $stat['name'] }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 text-xs text-center text-gray-500 dark:text-gray-400">
                    Total: {{ $templateStats['total_permissions'] }} permissions across {{ $templateStats['total_templates'] }} templates
                </div>
            </div>

            @if($showCustomPermissions)
                <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4 mb-6">
                    <div class="flex items-center">
                        <flux:icon name="information-circle" class="h-5 w-5 text-purple-500 flex-shrink-0" />
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-purple-800 dark:text-purple-200">Custom Permission Builder</h4>
                            <p class="text-sm text-purple-700 dark:text-purple-300">
                                The individual permission selector with 241 checkboxes is coming in the next update. 
                                For now, use the pre-configured templates which cover 99% of use cases.
                            </p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
        
        <div class="flex justify-between items-center p-6 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
            <div class="text-sm text-gray-600 dark:text-gray-400">
                @if($selectedTemplate && $selectedTemplate !== 'custom')
                    @php $template = $permissionTemplates[$selectedTemplate] ?? null; @endphp
                    @if($template)
                        Selected: <strong>{{ $template['name'] }}</strong> ({{ $template['permission_count'] }} permissions)
                    @endif
                @elseif($selectedTemplate === 'custom')
                    Custom permissions builder (coming soon)
                @else
                    Please select a permission template
                @endif
            </div>
            
            <div class="flex gap-3">
                <flux:button wire:click="closePermissionModal" variant="ghost">Cancel</flux:button>
                <flux:button 
                    wire:click="applyPermissionTemplate" 
                    variant="primary"
                    :disabled="!$selectedTemplate || $selectedTemplate === 'custom'"
                    wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="applyPermissionTemplate">Apply Template</span>
                    <span wire:loading wire:target="applyPermissionTemplate">Applying...</span>
                </flux:button>
            </div>
        </div>
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