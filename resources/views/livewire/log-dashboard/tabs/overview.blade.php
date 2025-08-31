<div class="space-y-6">
    {{-- Header --}}
    <div>
        <h2 class="text-lg font-medium text-gray-900 dark:text-white">System Overview</h2>
        <p class="text-sm text-gray-600 dark:text-gray-400">High-level system health and activity summary</p>
    </div>

    {{-- System Health Score --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">System Health</h3>
            <flux:badge 
                size="lg" 
                :variant="match($systemHealth['status']) {
                    'excellent' => 'success',
                    'good' => 'primary',
                    'warning' => 'warning',
                    'critical' => 'danger',
                    default => 'ghost'
                }"
            >
                {{ ucfirst($systemHealth['status']) }}
            </flux:badge>
        </div>
        
        <div class="flex items-center space-x-4">
            <div class="flex-1">
                <div class="flex justify-between text-sm mb-2">
                    <span class="text-gray-600 dark:text-gray-400">Health Score</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ $systemHealth['score'] }}/100</span>
                </div>
                
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                    <div @class([
                        'h-2 rounded-full transition-all duration-300',
                        'bg-green-500' => $systemHealth['status'] === 'excellent',
                        'bg-blue-500' => $systemHealth['status'] === 'good',
                        'bg-yellow-500' => $systemHealth['status'] === 'warning',
                        'bg-red-500' => $systemHealth['status'] === 'critical',
                    ]) style="width: {{ $systemHealth['score'] }}%"></div>
                </div>
            </div>
            
            <div class="w-16 h-16 relative">
                <svg class="transform -rotate-90 w-16 h-16">
                    <circle cx="32" cy="32" r="28" stroke="currentColor" stroke-width="4" fill="transparent" class="text-gray-200 dark:text-gray-700"/>
                    <circle 
                        cx="32" 
                        cy="32" 
                        r="28" 
                        stroke="currentColor" 
                        stroke-width="4" 
                        fill="transparent"
                        stroke-dasharray="{{ 2 * 3.14159 * 28 }}"
                        stroke-dashoffset="{{ 2 * 3.14159 * 28 * (1 - $systemHealth['score'] / 100) }}"
                        @class([
                            'text-green-500' => $systemHealth['status'] === 'excellent',
                            'text-blue-500' => $systemHealth['status'] === 'good',
                            'text-yellow-500' => $systemHealth['status'] === 'warning',
                            'text-red-500' => $systemHealth['status'] === 'critical',
                        ])
                    />
                </svg>
                <div class="absolute inset-0 flex items-center justify-center">
                    <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $systemHealth['score'] }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- System Summary Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        {{-- Total Requests --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
                        <flux:icon name="globe-alt" class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                    </div>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Requests</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($systemSummary['total_requests']) }}</p>
                </div>
            </div>
        </div>

        {{-- Average Response Time --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center">
                        <flux:icon name="bolt" class="w-5 h-5 text-green-600 dark:text-green-400" />
                    </div>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Avg Time</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $systemSummary['avg_response_time'] }}ms</p>
                </div>
            </div>
        </div>

        {{-- Error Rate --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-red-100 dark:bg-red-900/30 rounded-lg flex items-center justify-center">
                        <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-600 dark:text-red-400" />
                    </div>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Errors</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $systemSummary['error_rate'] }}%</p>
                </div>
            </div>
        </div>

        {{-- Activities --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center">
                        <flux:icon name="document-text" class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                    </div>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Activities</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($systemSummary['total_activities']) }}</p>
                </div>
            </div>
        </div>

        {{-- Active Users --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-amber-100 dark:bg-amber-900/30 rounded-lg flex items-center justify-center">
                        <flux:icon name="users" class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                    </div>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Users</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($systemSummary['unique_users']) }}</p>
                </div>
            </div>
        </div>

        {{-- Recent Errors --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-gray-100 dark:bg-gray-900/30 rounded-lg flex items-center justify-center">
                        <flux:icon name="bug-ant" class="w-5 h-5 text-gray-600 dark:text-gray-400" />
                    </div>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Errors</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($systemSummary['recent_errors']) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Activity Summary --}}
    @if($recentActivitySummary->count() > 0)
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Recent Activity Summary</h3>
            <div class="space-y-4">
                @foreach($recentActivitySummary as $activityType)
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <flux:badge 
                                size="sm" 
                                :variant="match(strtolower($activityType['type'])) {
                                    'product' => 'primary',
                                    'user' => 'success',
                                    'variant' => 'warning',
                                    'sync' => 'info',
                                    default => 'ghost'
                                }"
                            >
                                {{ $activityType['type'] }}
                            </flux:badge>
                            <span class="text-sm text-gray-600 dark:text-gray-400">events</span>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ number_format($activityType['count']) }}
                            </span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $activityType['latest']?->diffForHumans() }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Quick Actions --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Quick Actions</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <flux:button variant="ghost" size="sm" class="justify-start">
                <flux:icon name="document-text" class="w-4 h-4 mr-2" />
                View Activities
            </flux:button>
            
            <flux:button variant="ghost" size="sm" class="justify-start">
                <flux:icon name="chart-bar" class="w-4 h-4 mr-2" />
                Performance
            </flux:button>
            
            <flux:button variant="ghost" size="sm" class="justify-start">
                <flux:icon name="exclamation-triangle" class="w-4 h-4 mr-2" />
                Error Logs
            </flux:button>
            
            <flux:button variant="ghost" size="sm" class="justify-start">
                <flux:icon name="arrow-path" class="w-4 h-4 mr-2" />
                Refresh Data
            </flux:button>
        </div>
    </div>
</div>