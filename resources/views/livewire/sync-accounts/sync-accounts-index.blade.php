<div class="max-w-7xl mx-auto p-6">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Sync Accounts</h1>
                <p class="mt-2 text-gray-600">Manage your marketplace and sales channel integrations</p>
            </div>
            
            <a href="{{ route('sync-accounts.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                <flux:icon name="plus" class="w-5 h-5 mr-2" />
                Add Integration
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <flux:field>
                <flux:label>Search</flux:label>
                <flux:input 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="Search by name or identifier..."
                    type="search"
                />
            </flux:field>

            <flux:field>
                <flux:label>Channel</flux:label>
                <flux:select wire:model.live="channelFilter" placeholder="All channels">
                    @foreach($channels as $channel)
                        <flux:select.option value="{{ $channel }}">{{ ucfirst($channel) }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <div class="flex items-end">
                <flux:button wire:click="$refresh" variant="outline" class="w-full">
                    <flux:icon name="refresh-cw" class="w-4 h-4 mr-2" />
                    Refresh
                </flux:button>
            </div>
        </div>
    </div>

    <!-- Sync Accounts List -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        @if($syncAccounts->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Account
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Channel
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Last Sync
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Created
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($syncAccounts as $syncAccount)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 w-10 h-10">
                                            @if($syncAccount->channel === 'shopify')
                                                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                                    <flux:icon name="storefront" class="w-6 h-6 text-green-600" />
                                                </div>
                                            @elseif($syncAccount->channel === 'ebay')
                                                <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                                                    <flux:icon name="tag" class="w-6 h-6 text-yellow-600" />
                                                </div>
                                            @else
                                                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                                                    <flux:icon name="link" class="w-6 h-6 text-gray-600" />
                                                </div>
                                            @endif
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ $syncAccount->display_name }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {{ $syncAccount->marketplace_subtype }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4">
                                    <flux:badge 
                                        color="{{ $syncAccount->channel === 'shopify' ? 'green' : ($syncAccount->channel === 'ebay' ? 'yellow' : 'gray') }}"
                                        size="sm"
                                    >
                                        {{ ucfirst($syncAccount->channel) }}
                                    </flux:badge>
                                </td>
                                
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        @if($syncAccount->is_active)
                                            <div class="flex items-center">
                                                <div class="w-2 h-2 bg-green-400 rounded-full mr-2"></div>
                                                <span class="text-sm text-green-800">Active</span>
                                            </div>
                                        @else
                                            <div class="flex items-center">
                                                <div class="w-2 h-2 bg-gray-400 rounded-full mr-2"></div>
                                                <span class="text-sm text-gray-500">Inactive</span>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    @if($syncAccount->last_sync_at)
                                        {{ $syncAccount->last_sync_at->diffForHumans() }}
                                    @else
                                        <span class="text-gray-400">Never</span>
                                    @endif
                                </td>
                                
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ $syncAccount->created_at->format('M j, Y') }}
                                </td>
                                
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end space-x-2">
                                        <!-- View/Edit -->
                                        <a 
                                            href="{{ route('sync-accounts.show', $syncAccount) }}"
                                            class="text-blue-600 hover:text-blue-900"
                                            title="View Details"
                                        >
                                            <flux:icon name="eye" class="w-4 h-4" />
                                        </a>
                                        
                                        <!-- Toggle Active Status -->
                                        <button 
                                            wire:click="toggleActive({{ $syncAccount->id }})"
                                            class="{{ $syncAccount->is_active ? 'text-orange-600 hover:text-orange-900' : 'text-green-600 hover:text-green-900' }}"
                                            title="{{ $syncAccount->is_active ? 'Deactivate' : 'Activate' }}"
                                        >
                                            <flux:icon name="{{ $syncAccount->is_active ? 'pause' : 'play' }}" class="w-4 h-4" />
                                        </button>
                                        
                                        <!-- Delete -->
                                        <button 
                                            wire:click="delete({{ $syncAccount->id }})"
                                            wire:confirm="Are you sure you want to delete this sync account?"
                                            class="text-red-600 hover:text-red-900"
                                            title="Delete"
                                        >
                                            <flux:icon name="trash-2" class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            @if($syncAccounts->hasPages())
                <div class="px-6 py-3 border-t border-gray-200">
                    {{ $syncAccounts->links() }}
                </div>
            @endif
        @else
            <div class="text-center py-12">
                <flux:icon name="link" class="w-12 h-12 mx-auto text-gray-400 mb-4" />
                <h3 class="text-lg font-medium text-gray-900 mb-2">No sync accounts found</h3>
                <p class="text-gray-600 mb-6">Get started by connecting your first marketplace or sales channel.</p>
                <a 
                    href="{{ route('sync-accounts.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    <flux:icon name="plus" class="w-5 h-5 mr-2" />
                    Add Integration
                </a>
            </div>
        @endif
    </div>
</div>