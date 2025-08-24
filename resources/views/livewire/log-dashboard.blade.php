<div class="space-y-6" 
     @if($autoRefresh) 
        x-data="{ interval: null }" 
        x-init="interval = setInterval(() => $wire.refreshData(), {{ $refreshInterval * 1000 }})"
        x-destroy="clearInterval(interval)"
     @endif>
     
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">📊 Log Dashboard</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Application logs and performance metrics</p>
        </div>
        
        <div class="flex items-center gap-3">
            <flux:button 
                wire:click="toggleAutoRefresh"
                :variant="$autoRefresh ? 'primary' : 'ghost'"
                size="sm"
            >
                @if($autoRefresh)
                    <flux:icon name="pause" class="w-4 h-4 mr-2" />
                    Auto-refresh ON
                @else
                    <flux:icon name="play" class="w-4 h-4 mr-2" />
                    Auto-refresh OFF
                @endif
            </flux:button>
            
            <flux:button wire:click="refreshData" size="sm" variant="ghost">
                <flux:icon name="arrow-path" class="w-4 h-4 mr-2" />
                Refresh
            </flux:button>
        </div>
    </div>

    {{-- Metrics Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {{-- Total Requests --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
                        <flux:icon name="globe" class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Requests</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($metrics['total_requests']) }}</p>
                </div>
            </div>
        </div>

        {{-- Average Response Time --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center">
                        <flux:icon name="bolt" class="w-5 h-5 text-green-600 dark:text-green-400" />
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Avg Response Time</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $metrics['avg_response_time'] }}ms</p>
                </div>
            </div>
        </div>

        {{-- Slow Requests --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-amber-100 dark:bg-amber-900/30 rounded-lg flex items-center justify-center">
                        <flux:icon name="clock" class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Slow Requests</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($metrics['slow_requests']) }}</p>
                </div>
            </div>
        </div>

        {{-- Error Rate --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-red-100 dark:bg-red-900/30 rounded-lg flex items-center justify-center">
                        <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-600 dark:text-red-400" />
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Error Rate</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $metrics['error_rate'] }}%</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="-mb-px flex space-x-8">
            @foreach(['overview' => 'Overview', 'requests' => 'Recent Requests', 'slow' => 'Slow Endpoints', 'errors' => 'Errors'] as $tab => $label)
                <button
                    wire:click="setActiveTab('{{ $tab }}')"
                    @class([
                        'py-2 px-1 border-b-2 font-medium text-sm',
                        'border-blue-500 text-blue-600 dark:text-blue-400' => $activeTab === $tab,
                        'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200' => $activeTab !== $tab
                    ])
                >
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- Tab Content --}}
    <div class="mt-6">
        @if($activeTab === 'overview')
            {{-- Overview Tab --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Log File Sizes --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Log File Sizes</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Laravel Log</span>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $logSizes['laravel_log_size'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Performance Log</span>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $logSizes['performance_log_size'] }}</span>
                        </div>
                    </div>
                </div>

                {{-- Recent Activity --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Recent Activity</h3>
                    <div class="space-y-3">
                        @forelse($recentRequests->take(5) as $request)
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <flux:badge 
                                        size="sm" 
                                        :variant="match($request['status']) {
                                            200, 201, 204 => 'success',
                                            404, 422 => 'warning', 
                                            500, 503 => 'danger',
                                            default => 'primary'
                                        }"
                                    >
                                        {{ $request['status'] }}
                                    </flux:badge>
                                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ $request['method'] }}</span>
                                    <span class="text-sm font-mono text-gray-900 dark:text-white">{{ $request['path'] }}</span>
                                </div>
                                @if($request['duration_ms'])
                                    <span class="text-xs text-gray-500">{{ $request['duration_ms'] }}ms</span>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">No recent requests found</p>
                        @endforelse
                    </div>
                </div>
            </div>

        @elseif($activeTab === 'requests')
            {{-- Recent Requests Tab --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Recent HTTP Requests</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Method</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Path</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Duration</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Time</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($recentRequests as $request)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <flux:badge 
                                            size="sm" 
                                            :variant="match($request['status']) {
                                                200, 201, 204 => 'success',
                                                404, 422 => 'warning', 
                                                500, 503 => 'danger',
                                                default => 'primary'
                                            }"
                                        >
                                            {{ $request['status'] }}
                                        </flux:badge>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        {{ $request['method'] }}
                                    </td>
                                    <td class="px-6 py-4 text-sm font-mono text-gray-900 dark:text-white">
                                        {{ $request['path'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        @if($request['duration_ms'])
                                            <span @class([
                                                'text-green-600' => $request['duration_ms'] < 300,
                                                'text-amber-600' => $request['duration_ms'] >= 300 && $request['duration_ms'] < 1000,
                                                'text-red-600' => $request['duration_ms'] >= 1000,
                                            ])>
                                                {{ $request['duration_ms'] }}ms
                                            </span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        {{ $request['user_id'] ? "User #{$request['user_id']}" : 'Guest' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $request['timestamp'] }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No requests found
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        @elseif($activeTab === 'slow')
            {{-- Slowest Endpoints Tab --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Slowest Endpoints</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        @forelse($slowestEndpoints as $endpoint)
                            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                                <div class="flex items-center space-x-3">
                                    <flux:badge size="sm" variant="ghost">{{ $endpoint['method'] }}</flux:badge>
                                    <span class="font-mono text-sm text-gray-900 dark:text-white">{{ $endpoint['path'] }}</span>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <flux:badge 
                                        size="sm" 
                                        :variant="match($endpoint['status']) {
                                            200, 201, 204 => 'success',
                                            404, 422 => 'warning', 
                                            500, 503 => 'danger',
                                            default => 'primary'
                                        }"
                                    >
                                        {{ $endpoint['status'] }}
                                    </flux:badge>
                                    <span @class([
                                        'text-sm font-medium',
                                        'text-amber-600' => $endpoint['duration_ms'] >= 1000 && $endpoint['duration_ms'] < 3000,
                                        'text-red-600' => $endpoint['duration_ms'] >= 3000,
                                    ])>
                                        {{ number_format($endpoint['duration_ms'], 1) }}ms
                                    </span>
                                </div>
                            </div>
                        @empty
                            <p class="text-center text-sm text-gray-500 dark:text-gray-400 py-8">
                                No slow endpoints found
                            </p>
                        @endforelse
                    </div>
                </div>
            </div>

        @elseif($activeTab === 'errors')
            {{-- Errors Tab --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Recent Errors</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        @forelse($recentErrors as $error)
                            <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
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
                                        <p class="text-sm text-gray-900 dark:text-white">{{ $error['message'] }}</p>
                                        @if($error['user_id'])
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">User ID: {{ $error['user_id'] }}</p>
                                        @endif
                                    </div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 ml-4">
                                        {{ $error['timestamp'] }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <p class="text-center text-sm text-gray-500 dark:text-gray-400 py-8">
                                No recent errors found 🎉
                            </p>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>