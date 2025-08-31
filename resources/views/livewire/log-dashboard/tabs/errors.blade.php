<div class="space-y-6">
    {{-- Header and Filters --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">Error Analysis</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400">System errors, warnings, and debugging information</p>
        </div>
        
        <div class="flex flex-col sm:flex-row gap-3">
            {{-- Search --}}
            <div class="relative">
                <flux:input 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="Search errors..." 
                    class="w-full sm:w-64"
                >
                    <x-slot name="iconTrailing">
                        <flux:icon name="magnifying-glass" class="w-4 h-4" />
                    </x-slot>
                </flux:input>
            </div>
            
            {{-- Filters --}}
            <div class="flex gap-2">
                <flux:select wire:model.live="levelFilter" class="w-36">
                    @foreach($availableLevels as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                
                <flux:select wire:model.live="timeFilter" class="w-32">
                    @foreach($timeFilters as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                
                <flux:button wire:click="clearFilters" variant="ghost" size="sm">
                    <flux:icon name="x-mark" class="w-4 h-4 mr-2" />
                    Clear
                </flux:button>
                
                <flux:button wire:click="toggleContext" :variant="$showContext ? 'primary' : 'ghost'" size="sm">
                    <flux:icon name="code-bracket" class="w-4 h-4 mr-2" />
                    Context
                </flux:button>
            </div>
        </div>
    </div>

    {{-- Error Statistics --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
            <div class="text-center">
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($errorStats['total_errors']) }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">Total</p>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
            <div class="text-center">
                <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ number_format($errorStats['critical_errors']) }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">Critical</p>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
            <div class="text-center">
                <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ number_format($errorStats['warnings']) }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">Warnings</p>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
            <div class="text-center">
                <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($errorStats['unique_messages']) }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">Unique</p>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
            <div class="text-center">
                <div class="flex items-center justify-center">
                    <p @class([
                        'text-2xl font-bold',
                        'text-green-600 dark:text-green-400' => $errorStats['trend'] <= 0,
                        'text-red-600 dark:text-red-400' => $errorStats['trend'] > 0,
                    ])>
                        {{ $errorStats['trend'] > 0 ? '+' : '' }}{{ $errorStats['trend'] }}%
                    </p>
                    <flux:icon 
                        :name="$errorStats['trend'] > 0 ? 'arrow-trending-up' : 'arrow-trending-down'"
                        @class([
                            'w-4 h-4 ml-1',
                            'text-green-600 dark:text-green-400' => $errorStats['trend'] <= 0,
                            'text-red-600 dark:text-red-400' => $errorStats['trend'] > 0,
                        ])
                    />
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400">24h Trend</p>
            </div>
        </div>
    </div>

    {{-- Analytics Panels --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Top Errors --}}
        @if($topErrors->count() > 0)
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Most Frequent Errors</h3>
                <div class="space-y-3">
                    @foreach($topErrors as $error)
                        <div class="p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                            <div class="flex items-start justify-between">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center space-x-2 mb-1">
                                        <flux:badge 
                                            size="sm" 
                                            :variant="$error['level'] === 'ERROR' ? 'danger' : 'warning'"
                                        >
                                            {{ $error['level'] }}
                                        </flux:badge>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $error['count'] }}x</span>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-300 truncate">{{ $error['message'] }}</p>
                                    @if($error['paths'])
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            {{ implode(', ', $error['paths']) }}
                                        </p>
                                    @endif
                                </div>
                                <span class="text-xs text-gray-500 dark:text-gray-400 ml-3">
                                    {{ \Carbon\Carbon::parse($error['latest'])->diffForHumans() }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Error Patterns --}}
        @if($errorPatterns->count() > 0)
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Error Patterns</h3>
                <div class="space-y-3">
                    @foreach($errorPatterns as $pattern)
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $pattern['pattern'] }}</p>
                                @if($pattern['latest'])
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        Latest: {{ \Carbon\Carbon::parse($pattern['latest'])->diffForHumans() }}
                                    </p>
                                @endif
                            </div>
                            <flux:badge size="sm" variant="ghost">{{ $pattern['count'] }}</flux:badge>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Errors by Hour Chart --}}
    @if($errorsByHour)
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Errors by Hour</h3>
            <div class="flex items-end justify-between h-32 space-x-1">
                @php
                    $maxCount = max(array_values($errorsByHour) ?: [1]);
                @endphp
                @foreach($errorsByHour as $hour => $count)
                    <div class="flex flex-col items-center flex-1">
                        <div 
                            class="bg-red-500 dark:bg-red-400 rounded-t-sm w-full mb-2 transition-all duration-300"
                            style="height: {{ $count > 0 ? max(4, ($count / $maxCount) * 100) : 0 }}px"
                            title="{{ $count }} errors at {{ $hour }}"
                        ></div>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ substr($hour, 0, 2) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Error List --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Error Log</h3>
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Per page:</span>
                    <flux:select wire:model.live="perPage" class="w-20">
                        <flux:select.option value="10">10</flux:select.option>
                        <flux:select.option value="20">20</flux:select.option>
                        <flux:select.option value="50">50</flux:select.option>
                        <flux:select.option value="100">100</flux:select.option>
                    </flux:select>
                </div>
            </div>
        </div>
        
        <div class="divide-y divide-gray-200 dark:divide-gray-700">
            @forelse($errors as $error)
                <div class="p-6">
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center space-x-2 mb-2">
                                <flux:badge 
                                    size="sm" 
                                    :variant="$error['level'] === 'ERROR' ? 'danger' : 'warning'"
                                >
                                    {{ $error['level'] }}
                                </flux:badge>
                                
                                @if($error['path'])
                                    <span class="text-sm font-mono text-gray-600 dark:text-gray-400">{{ $error['path'] }}</span>
                                @endif
                            </div>
                            
                            <p class="text-sm text-gray-900 dark:text-white mb-2">{{ $error['message'] }}</p>
                            
                            @if($error['user_id'])
                                <div class="flex items-center space-x-2 text-xs text-gray-500 dark:text-gray-400">
                                    <flux:icon name="user" class="w-3 h-3" />
                                    <span>User ID: {{ $error['user_id'] }}</span>
                                </div>
                            @endif
                            
                            @if($showContext && isset($error['context']) && $error['context'])
                                <div class="mt-3 p-3 bg-gray-100 dark:bg-gray-700 rounded-lg">
                                    <details>
                                        <summary class="text-xs font-medium text-gray-600 dark:text-gray-400 cursor-pointer hover:text-gray-800 dark:hover:text-gray-200">
                                            Show Context
                                        </summary>
                                        <pre class="text-xs text-gray-600 dark:text-gray-300 mt-2 whitespace-pre-wrap">{{ json_encode($error['context'], JSON_PRETTY_PRINT) }}</pre>
                                    </details>
                                </div>
                            @endif
                        </div>
                        
                        <div class="text-right ml-4">
                            <time class="text-sm text-gray-500 dark:text-gray-400" datetime="{{ $error['timestamp'] }}">
                                {{ \Carbon\Carbon::parse($error['timestamp'])->format('M j, g:i A') }}
                            </time>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                {{ \Carbon\Carbon::parse($error['timestamp'])->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <flux:icon name="check-circle" class="w-12 h-12 text-green-400 dark:text-green-500 mx-auto mb-4" />
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">No errors found! 🎉</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Your system is running smoothly with no errors matching your criteria.
                    </p>
                </div>
            @endforelse
        </div>
        
        {{-- Pagination --}}
        @if($errors->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $errors->links() }}
            </div>
        @endif
    </div>
</div>