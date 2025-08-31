<div class="space-y-6">
    {{-- Header and Controls --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">Performance Metrics</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400">HTTP request performance and system insights</p>
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

    {{-- Performance Metrics Cards --}}
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
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($performanceMetrics['total_requests']) }}</p>
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
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $performanceMetrics['avg_response_time'] }}ms</p>
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
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($performanceMetrics['slow_requests']) }}</p>
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
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $performanceMetrics['error_rate'] }}%</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Performance Insights --}}
    @if($performanceInsights)
        <div class="space-y-4">
            @foreach($performanceInsights as $insight)
                <div @class([
                    'p-4 rounded-lg border',
                    'bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-800' => $insight['type'] === 'error',
                    'bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800' => $insight['type'] === 'warning',
                    'bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-800' => $insight['type'] === 'info',
                    'bg-green-50 border-green-200 dark:bg-green-900/20 dark:border-green-800' => $insight['type'] === 'success',
                ])>
                    <div class="flex items-start space-x-3">
                        <flux:icon 
                            :name="match($insight['type']) {
                                'error' => 'exclamation-triangle',
                                'warning' => 'exclamation-triangle',
                                'info' => 'information-circle',
                                'success' => 'check-circle',
                                default => 'information-circle'
                            }" 
                            @class([
                                'w-5 h-5 mt-0.5',
                                'text-red-500' => $insight['type'] === 'error',
                                'text-amber-500' => $insight['type'] === 'warning',
                                'text-blue-500' => $insight['type'] === 'info',
                                'text-green-500' => $insight['type'] === 'success',
                            ])
                        />
                        
                        <div class="flex-1">
                            <h4 class="font-medium text-gray-900 dark:text-white">{{ $insight['title'] }}</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">{{ $insight['message'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">{{ $insight['action'] }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Charts and Analytics --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Response Time Distribution --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Response Time Distribution</h3>
            <div class="space-y-4">
                @foreach($responseTimeDistribution as $range => $count)
                    @php
                        $total = array_sum($responseTimeDistribution);
                        $percentage = $total > 0 ? ($count / $total) * 100 : 0;
                    @endphp
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-600 dark:text-gray-400">{{ $range }}</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $count }} ({{ number_format($percentage, 1) }}%)</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div @class([
                                'h-2 rounded-full',
                                'bg-green-500' => $range === '0-100ms',
                                'bg-blue-500' => $range === '100-500ms',
                                'bg-yellow-500' => $range === '500ms-1s',
                                'bg-orange-500' => $range === '1s-2s',
                                'bg-red-500' => $range === '2s+',
                            ]) style="width: {{ $percentage }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Status Code Distribution --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Status Code Distribution</h3>
            <div class="space-y-4">
                @foreach($statusCodeDistribution as $status => $count)
                    @php
                        $total = array_sum($statusCodeDistribution);
                        $percentage = $total > 0 ? ($count / $total) * 100 : 0;
                    @endphp
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-600 dark:text-gray-400">{{ $status }}</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $count }} ({{ number_format($percentage, 1) }}%)</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div @class([
                                'h-2 rounded-full',
                                'bg-green-500' => str_starts_with($status, '2xx'),
                                'bg-blue-500' => str_starts_with($status, '3xx'),
                                'bg-orange-500' => str_starts_with($status, '4xx'),
                                'bg-red-500' => str_starts_with($status, '5xx'),
                            ]) style="width: {{ $percentage }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Slowest Endpoints --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Slowest Endpoints</h3>
            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-500 dark:text-gray-400">Show:</span>
                <flux:select wire:model.live="endpointLimit" class="w-20">
                    <flux:select.option value="5">5</flux:select.option>
                    <flux:select.option value="10">10</flux:select.option>
                    <flux:select.option value="15">15</flux:select.option>
                    <flux:select.option value="25">25</flux:select.option>
                </flux:select>
            </div>
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
                                'text-green-600' => $endpoint['duration_ms'] < 500,
                                'text-amber-600' => $endpoint['duration_ms'] >= 500 && $endpoint['duration_ms'] < 1000,
                                'text-orange-600' => $endpoint['duration_ms'] >= 1000 && $endpoint['duration_ms'] < 3000,
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

    {{-- Recent Requests --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Recent Requests</h3>
            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-500 dark:text-gray-400">Show:</span>
                <flux:select wire:model.live="requestLimit" class="w-20">
                    <flux:select.option value="10">10</flux:select.option>
                    <flux:select.option value="25">25</flux:select.option>
                    <flux:select.option value="50">50</flux:select.option>
                    <flux:select.option value="100">100</flux:select.option>
                </flux:select>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Method</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Path</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Duration</th>
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
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $request['timestamp'] }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                No requests found
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>