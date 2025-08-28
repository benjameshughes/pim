{{-- 🏢 TEAM MANAGEMENT INDEX ✨ --}}
<div class="space-y-6">
    {{-- Header & Actions --}}
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
                🏢 Teams
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mt-1">
                Manage teams and their members
            </p>
        </div>
        <div class="flex gap-3">
            <flux:button wire:click="openCreateModal" icon="plus" variant="primary">
                Create Team
            </flux:button>
        </div>
    </div>

    {{-- Search & Filter Bar --}}
    <div class="flex gap-4 items-center">
        <div class="flex-1 max-w-none">
            <flux:input 
                wire:model.live.debounce.300ms="search" 
                placeholder="Search teams by name or description..." 
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

    {{-- Main Teams Table --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
        @if ($teams->count())
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th wire:click="sortBy('name')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800">
                            <div class="flex items-center gap-2">
                                Team Name
                                @if ($sortField === 'name')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3 h-3" />
                                @endif
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Description
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Members
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Status
                        </th>
                        <th wire:click="sortBy('created_at')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800">
                            <div class="flex items-center gap-2">
                                Created
                                @if ($sortField === 'created_at')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3 h-3" />
                                @endif
                            </div>
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($teams as $team)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $team->name }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                @if ($team->description)
                                    <div class="text-sm text-gray-900 dark:text-white">
                                        {{ Str::limit($team->description, 50) }}
                                    </div>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500 text-sm">No description</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge size="sm" color="blue">
                                    {{ $team->users_count }} {{ Str::plural('member', $team->users_count) }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge :color="$team->is_active ? 'green' : 'red'" size="sm">
                                    {{ $team->is_active ? 'Active' : 'Inactive' }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $team->created_at->format('M j, Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button 
                                        wire:click="openEditModal({{ $team->id }})" 
                                        variant="ghost" 
                                        size="sm"
                                        icon="pencil"
                                    >
                                        Edit
                                    </flux:button>
                                    <flux:button 
                                        wire:click="deleteTeam({{ $team->id }})" 
                                        wire:confirm="Are you sure you want to delete this team? This will remove all members and associated data."
                                        variant="ghost" 
                                        size="sm" 
                                        color="red"
                                        icon="trash"
                                    >
                                        Delete
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <tr>
                <td colspan="6" class="px-6 py-16 text-center">
                    <div class="flex flex-col items-center gap-4">
                        <flux:icon name="user-group" class="w-12 h-12 text-gray-400" />
                        <div>
                            <h4 class="font-semibold text-gray-900 dark:text-white">No teams found</h4>
                            <p class="text-gray-500 dark:text-gray-400 mt-1">
                                @if($search)
                                    No teams match "{{ $search }}" - try adjusting your search
                                @else
                                    Start by creating your first team!
                                @endif
                            </p>
                        </div>
                        <div class="flex gap-3">
                            @if($search)
                                <flux:button wire:click="$set('search', '')" size="sm" variant="outline">
                                    Clear Search
                                </flux:button>
                            @endif
                            <flux:button wire:click="openCreateModal" variant="primary" size="sm">
                                Create First Team
                            </flux:button>
                        </div>
                    </div>
                </td>
            </tr>
        @endif

        {{-- Pagination --}}
        @if ($teams->hasPages())
            <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700">
                {{ $teams->links() }}
            </div>
        @endif
    </div>

    {{-- Create Team Modal --}}
    <flux:modal wire:model="showCreateModal" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Create New Team</flux:heading>
                <flux:text class="mt-2" variant="muted">Set up a new team for your organization.</flux:text>
            </div>
            
            <div class="space-y-4">
                <flux:field label="Team Name">
                    <flux:input wire:model="name" placeholder="Enter team name" />
                    <flux:error name="name" />
                </flux:field>
                
                <flux:field label="Description">
                    <flux:textarea wire:model="description" placeholder="Enter team description (optional)" />
                    <flux:error name="description" />
                </flux:field>
                
                <flux:field>
                    <flux:checkbox wire:model="is_active">
                        Team is active
                    </flux:checkbox>
                </flux:field>
            </div>
            
            <div class="flex justify-end gap-3">
                <flux:button wire:click="closeCreateModal" variant="ghost">
                    Cancel
                </flux:button>
                <flux:button 
                    wire:click="createTeam" 
                    variant="primary"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>Create Team</span>
                    <span wire:loading>Creating...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Edit Team Modal --}}
    <flux:modal wire:model="showEditModal" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Edit Team</flux:heading>
                <flux:text class="mt-2" variant="muted">Update team information.</flux:text>
            </div>
            
            <div class="space-y-4">
                <flux:field label="Team Name">
                    <flux:input wire:model="name" placeholder="Enter team name" />
                    <flux:error name="name" />
                </flux:field>
                
                <flux:field label="Description">
                    <flux:textarea wire:model="description" placeholder="Enter team description (optional)" />
                    <flux:error name="description" />
                </flux:field>
                
                <flux:field>
                    <flux:checkbox wire:model="is_active">
                        Team is active
                    </flux:checkbox>
                </flux:field>
            </div>
            
            <div class="flex justify-end gap-3">
                <flux:button wire:click="closeEditModal" variant="ghost">
                    Cancel
                </flux:button>
                <flux:button 
                    wire:click="updateTeam" 
                    variant="primary"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>Update Team</span>
                    <span wire:loading>Updating...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>