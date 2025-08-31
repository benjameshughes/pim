<div class="space-y-6">
    {{-- Header and Filters --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">Activity Log</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400">Business activities and system events</p>
        </div>
        
        <div class="flex flex-col sm:flex-row gap-3">
            {{-- Search --}}
            <div class="relative">
                <flux:input 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="Search activities..." 
                    class="w-full sm:w-64"
                >
                    <x-slot name="iconTrailing">
                        <flux:icon name="magnifying-glass" class="w-4 h-4" />
                    </x-slot>
                </flux:input>
            </div>
            
            {{-- Filters --}}
            <div class="flex gap-2">
                <flux:select wire:model.live="eventFilter" class="w-40">
                    @foreach($availableEventTypes as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                
                <flux:select wire:model.live="timeFilter" class="w-36">
                    @foreach($timeFilters as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                
                <flux:button wire:click="clearFilters" variant="ghost" size="sm">
                    <flux:icon name="x-mark" class="w-4 h-4 mr-2" />
                    Clear
                </flux:button>
            </div>
        </div>
    </div>

    {{-- Activity Statistics --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
            <div class="text-center">
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($activityStats['total']) }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">Total</p>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
            <div class="text-center">
                <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($activityStats['product_activities']) }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">Products</p>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
            <div class="text-center">
                <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ number_format($activityStats['user_activities']) }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">Users</p>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
            <div class="text-center">
                <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ number_format($activityStats['variant_activities']) }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">Variants</p>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
            <div class="text-center">
                <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ number_format($activityStats['sync_activities']) }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">Syncs</p>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
            <div class="text-center">
                <p class="text-2xl font-bold text-gray-600 dark:text-gray-400">{{ number_format($activityStats['unique_users']) }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">Users Active</p>
            </div>
        </div>
    </div>

    {{-- Insights Panel --}}
    @if($topUsers->count() > 0 || $eventTypes->count() > 0)
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Top Users --}}
            @if($topUsers->count() > 0)
                <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Most Active Users</h3>
                    <div class="space-y-3">
                        @foreach($topUsers as $user)
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900/30 rounded-full flex items-center justify-center">
                                        <span class="text-sm font-medium text-blue-600 dark:text-blue-400">
                                            {{ substr($user['name'], 0, 1) }}
                                        </span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $user['name'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ implode(', ', $user['events']) }}
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $user['count'] }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $user['latest']?->diffForHumans() }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Event Types --}}
            @if($eventTypes->count() > 0)
                <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Event Types</h3>
                    <div class="space-y-3">
                        @foreach($eventTypes as $eventType)
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <flux:badge 
                                        size="sm" 
                                        :variant="match($eventType['type']) {
                                            'product' => 'primary',
                                            'user' => 'success',
                                            'variant' => 'warning',
                                            'sync' => 'info',
                                            default => 'ghost'
                                        }"
                                    >
                                        {{ ucfirst($eventType['type']) }}
                                    </flux:badge>
                                    <span class="text-sm text-gray-600 dark:text-gray-400">events</span>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ number_format($eventType['count']) }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $eventType['latest']?->diffForHumans() }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- Activity List --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Activity Timeline</h3>
        </div>
        
        <div class="divide-y divide-gray-200 dark:divide-gray-700">
            @forelse($activities as $activity)
                <div class="p-6">
                    <div class="flex items-start space-x-4">
                        {{-- Event Icon --}}
                        <div class="flex-shrink-0">
                            <div @class([
                                'w-8 h-8 rounded-lg flex items-center justify-center',
                                'bg-blue-100 dark:bg-blue-900/30' => str_starts_with($activity->event, 'product'),
                                'bg-green-100 dark:bg-green-900/30' => str_starts_with($activity->event, 'user'),
                                'bg-purple-100 dark:bg-purple-900/30' => str_starts_with($activity->event, 'variant'),
                                'bg-orange-100 dark:bg-orange-900/30' => str_starts_with($activity->event, 'sync'),
                                'bg-gray-100 dark:bg-gray-900/30' => !str_starts_with($activity->event, 'product') && !str_starts_with($activity->event, 'user') && !str_starts_with($activity->event, 'variant') && !str_starts_with($activity->event, 'sync')
                            ])>
                                <flux:icon 
                                    :name="match(true) {
                                        str_starts_with($activity->event, 'product') => 'cube',
                                        str_starts_with($activity->event, 'user') => 'user',
                                        str_starts_with($activity->event, 'variant') => 'squares-2x2',
                                        str_starts_with($activity->event, 'sync') => 'arrow-path',
                                        default => 'document'
                                    }" 
                                    @class([
                                        'w-4 h-4',
                                        'text-blue-600 dark:text-blue-400' => str_starts_with($activity->event, 'product'),
                                        'text-green-600 dark:text-green-400' => str_starts_with($activity->event, 'user'),
                                        'text-purple-600 dark:text-purple-400' => str_starts_with($activity->event, 'variant'),
                                        'text-orange-600 dark:text-orange-400' => str_starts_with($activity->event, 'sync'),
                                        'text-gray-600 dark:text-gray-400' => !str_starts_with($activity->event, 'product') && !str_starts_with($activity->event, 'user') && !str_starts_with($activity->event, 'variant') && !str_starts_with($activity->event, 'sync')
                                    ])
                                />
                            </div>
                        </div>

                        {{-- Activity Content --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <flux:badge size="sm" variant="ghost">
                                        {{ $activity->event }}
                                    </flux:badge>
                                    
                                    @if($activity->user)
                                        <span class="text-sm text-gray-500 dark:text-gray-400">by</span>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $activity->user->name }}
                                        </span>
                                    @endif
                                </div>
                                
                                <time class="text-sm text-gray-500 dark:text-gray-400" datetime="{{ $activity->occurred_at }}">
                                    {{ $activity->occurred_at->diffForHumans() }}
                                </time>
                            </div>
                            
                            @if($activity->description)
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $activity->description }}</p>
                            @endif

                            @if($activity->subject_name)
                                <div class="mt-2 flex items-center space-x-2">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Subject:</span>
                                    <span class="text-xs font-mono bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded text-gray-900 dark:text-white">
                                        {{ $activity->subject_name }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <flux:icon name="document-text" class="w-12 h-12 text-gray-400 dark:text-gray-500 mx-auto mb-4" />
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">No activities found</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Try adjusting your search criteria or time filter.
                    </p>
                </div>
            @endforelse
        </div>
        
        {{-- Pagination --}}
        @if($activities->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $activities->links() }}
            </div>
        @endif
    </div>
</div>