<div class="space-y-6">
    {{-- Sync Activity History --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                Sync Activity History
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Recent marketplace sync operations for this product
            </p>
        </div>

        @if($product->syncLogs->count() > 0)
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($product->syncLogs as $log)
                    <div class="px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                {{-- Status Badge --}}
                                <flux:badge 
                                    :color="match($log->status) {
                                        'success' => 'green',
                                        'failed' => 'red',
                                        'warning' => 'yellow',
                                        default => 'gray'
                                    }"
                                    size="sm">
                                    {{ ucfirst($log->status) }}
                                </flux:badge>

                                {{-- Action & Channel --}}
                                <div>
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ ucfirst($log->action) }} • {{ ucfirst($log->syncAccount->channel) }}
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ $log->syncAccount->name }}
                                    </div>
                                    @if($log->message)
                                        <div class="text-xs text-gray-600 dark:text-gray-300 mt-1">
                                            {{ $log->message }}
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Timestamp & Performance --}}
                            <div class="text-right">
                                <div class="text-sm text-gray-900 dark:text-white">
                                    {{ $log->created_at->format('M j, Y') }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $log->created_at->format('H:i:s') }}
                                </div>
                                @if($log->duration_ms)
                                    <div class="text-xs text-gray-400 dark:text-gray-500">
                                        {{ $log->duration }}
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Batch Information --}}
                        @if($log->items_processed > 0)
                            <div class="mt-3 flex items-center space-x-4 text-xs text-gray-500 dark:text-gray-400">
                                <span>Processed: {{ $log->items_processed }}</span>
                                @if($log->items_successful > 0)
                                    <span class="text-green-600">Successful: {{ $log->items_successful }}</span>
                                @endif
                                @if($log->items_failed > 0)
                                    <span class="text-red-600">Failed: {{ $log->items_failed }}</span>
                                @endif
                                @if($log->items_processed > 0)
                                    <span>Success Rate: {{ $log->success_rate }}%</span>
                                @endif
                            </div>
                        @endif

                        {{-- Additional Details --}}
                        @if($log->details && !empty($log->details))
                            <div class="mt-3">
                                <details class="text-xs">
                                    <summary class="text-gray-500 dark:text-gray-400 cursor-pointer hover:text-gray-700 dark:hover:text-gray-200">
                                        View Details
                                    </summary>
                                    <div class="mt-2 bg-gray-50 dark:bg-gray-700/50 rounded p-2">
                                        <pre class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ json_encode($log->details, JSON_PRETTY_PRINT) }}</pre>
                                    </div>
                                </details>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Load More Button --}}
            @if($product->syncLogs->count() >= 50)
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 text-center">
                    <flux:button variant="ghost" size="sm">
                        Load More History
                    </flux:button>
                </div>
            @endif
        @else
            <div class="px-6 py-12 text-center">
                <flux:icon name="clock" class="mx-auto h-8 w-8 text-gray-400" />
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No sync history</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Marketplace sync operations will appear here once you start syncing this product
                </p>
            </div>
        @endif
    </div>

    {{-- Performance Summary --}}
    @if($product->syncLogs->count() > 0)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Performance Summary</h3>
            
            @php
                $totalOps = $product->syncLogs->count();
                $successfulOps = $product->syncLogs->where('status', 'success')->count();
                $failedOps = $product->syncLogs->where('status', 'failed')->count();
                $avgDuration = $product->syncLogs->whereNotNull('duration_ms')->avg('duration_ms');
                $successRate = $totalOps > 0 ? round(($successfulOps / $totalOps) * 100, 1) : 0;
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $totalOps }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Total Operations</div>
                </div>
                
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600">{{ $successfulOps }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Successful</div>
                </div>
                
                <div class="text-center">
                    <div class="text-2xl font-bold text-red-600">{{ $failedOps }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Failed</div>
                </div>
                
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600">{{ $successRate }}%</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Success Rate</div>
                </div>
            </div>

            @if($avgDuration)
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 text-center">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Average Operation Time: 
                        <span class="font-medium">
                            @if($avgDuration < 1000)
                                {{ round($avgDuration) }}ms
                            @else
                                {{ round($avgDuration / 1000, 2) }}s
                            @endif
                        </span>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>