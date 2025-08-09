<div class="space-y-8">
    <!-- 🎭 LEGENDARY HEADER with MAXIMUM SPARKLE -->
    <div class="bg-gradient-to-r from-purple-500 via-pink-500 to-indigo-600 dark:from-purple-700 dark:via-pink-700 dark:to-indigo-800 rounded-2xl p-8 text-white">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-4xl font-bold mb-2">🎭 LEGENDARY Webhook Dashboard</h1>
                <p class="text-purple-100 text-lg">Real-time webhook monitoring with MAXIMUM SASS and intelligence!</p>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-center">
                    <div class="text-3xl font-bold">{{ $dashboardStats['total_webhooks'] }}</div>
                    <div class="text-sm text-purple-200">Total Webhooks</div>
                </div>
                <flux:button variant="ghost" size="base" wire:click="refresh" class="bg-white/10 hover:bg-white/20 text-white border-white/20">
                    <flux:icon name="refresh-cw" class="w-5 h-5 mr-2" />
                    Refresh
                </flux:button>
            </div>
        </div>
    </div>

    <!-- 🏥 SYSTEM HEALTH STATUS -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-gradient-to-br from-{{ $dashboardStats['health_status']['color'] }}-500 to-{{ $dashboardStats['health_status']['color'] }}-600 rounded-xl flex items-center justify-center">
                    <flux:icon name="{{ $dashboardStats['health_status']['icon'] }}" class="w-6 h-6 text-white" />
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">System Health</h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Webhook processing performance</p>
                </div>
            </div>
            <div class="text-right">
                <div class="text-3xl font-bold text-{{ $dashboardStats['health_status']['color'] }}-600">
                    {{ $dashboardStats['health_status']['status'] }}
                </div>
                <div class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $dashboardStats['success_rate'] }}% Success Rate
                </div>
            </div>
        </div>

        <!-- Performance Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="text-center p-4 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg">
                <div class="text-2xl font-bold text-emerald-600">{{ $dashboardStats['successful_webhooks'] }}</div>
                <div class="text-sm text-zinc-500 dark:text-zinc-400">Successful</div>
            </div>
            <div class="text-center p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                <div class="text-2xl font-bold text-red-600">{{ $dashboardStats['failed_webhooks'] }}</div>
                <div class="text-sm text-zinc-500 dark:text-zinc-400">Failed</div>
            </div>
            <div class="text-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                <div class="text-2xl font-bold text-blue-600">{{ $dashboardStats['processing_webhooks'] + $dashboardStats['queued_webhooks'] }}</div>
                <div class="text-sm text-zinc-500 dark:text-zinc-400">In Progress</div>
            </div>
            <div class="text-center p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                <div class="text-2xl font-bold text-purple-600">{{ $dashboardStats['avg_processing_time'] }}ms</div>
                <div class="text-sm text-zinc-500 dark:text-zinc-400">Avg. Processing Time</div>
            </div>
        </div>
    </div>

    <!-- 📊 FILTERS & CONTROLS -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <!-- Search -->
            <div>
                <flux:input 
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search webhooks..."
                    class="w-full"
                />
            </div>

            <!-- Status Filter -->
            <div>
                <flux:select wire:model.live="filterStatus" class="w-full">
                    <flux:option value="all">All Statuses</flux:option>
                    <flux:option value="completed">✅ Completed</flux:option>
                    <flux:option value="failed">❌ Failed</flux:option>
                    <flux:option value="processing">⚡ Processing</flux:option>
                    <flux:option value="queued">📋 Queued</flux:option>
                </flux:select>
            </div>

            <!-- Topic Filter -->
            <div>
                <flux:select wire:model.live="filterTopic" class="w-full">
                    <flux:option value="all">All Topics</flux:option>
                    @foreach($this->availableTopics as $topic)
                        <flux:option value="{{ $topic }}">{{ str_replace('/', ' → ', $topic) }}</flux:option>
                    @endforeach
                </flux:select>
            </div>

            <!-- Time Range -->
            <div>
                <flux:select wire:model.live="timeRange" class="w-full">
                    <flux:option value="1h">Last Hour</flux:option>
                    <flux:option value="24h">Last 24 Hours</flux:option>
                    <flux:option value="7d">Last 7 Days</flux:option>
                    <flux:option value="30d">Last 30 Days</flux:option>
                </flux:select>
            </div>

            <!-- Actions -->
            <div class="flex gap-2">
                @if($dashboardStats['failed_webhooks'] > 0)
                    <flux:button variant="primary" size="sm" wire:click="retryFailedWebhooks" wire:confirm="Retry all failed webhooks?">
                        🔁 Retry Failed
                    </flux:button>
                @endif
                
                <flux:button variant="ghost" size="sm" wire:click="clearOldLogs" wire:confirm="Clear webhooks older than 30 days?">
                    🗑️ Cleanup
                </flux:button>
            </div>
        </div>
    </div>

    <!-- 📈 TOPIC BREAKDOWN -->
    @if(!empty($topicBreakdown))
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex items-center gap-3 mb-6">
                <flux:icon name="bar-chart-3" class="w-6 h-6 text-indigo-600" />
                <h2 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">Topic Performance</h2>
            </div>
            
            <div class="space-y-3">
                @foreach($topicBreakdown as $topic)
                    <div class="flex items-center justify-between p-4 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                        <div class="flex-1">
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                {{ str_replace('/', ' → ', $topic['topic']) }}
                            </div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $topic['total'] }} webhooks, {{ $topic['avg_processing_time'] }}ms avg
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="text-right">
                                <div class="text-sm font-medium text-emerald-600">{{ $topic['successful'] }} ✅</div>
                                @if($topic['failed'] > 0)
                                    <div class="text-sm font-medium text-red-600">{{ $topic['failed'] }} ❌</div>
                                @endif
                            </div>
                            <div class="w-20 text-center">
                                <flux:badge variant="outline" class="bg-{{ $topic['status_color'] }}-50 text-{{ $topic['status_color'] }}-700 border-{{ $topic['status_color'] }}-200">
                                    {{ $topic['success_rate'] }}%
                                </flux:badge>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- 📋 WEBHOOK LOGS TABLE -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700">
        <div class="px-6 py-4 border-b border-zinc-200 dark:border-zinc-700">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">Recent Webhook Logs</h2>
                <div class="text-sm text-zinc-500 dark:text-zinc-400">
                    Showing {{ $this->webhookLogs->count() }} of {{ $this->webhookLogs->total() }} webhooks
                </div>
            </div>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse($this->webhookLogs as $webhook)
                <div class="p-6 hover:bg-zinc-50 dark:hover:bg-zinc-900 transition-colors">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <!-- Status Badge -->
                                <flux:badge variant="outline" class="
                                    @if($webhook->status === 'completed') bg-emerald-50 text-emerald-700 border-emerald-200
                                    @elseif($webhook->status === 'failed' || $webhook->status === 'permanent_failure') bg-red-50 text-red-700 border-red-200
                                    @elseif($webhook->status === 'processing') bg-blue-50 text-blue-700 border-blue-200
                                    @elseif($webhook->status === 'queued') bg-yellow-50 text-yellow-700 border-yellow-200
                                    @else bg-zinc-50 text-zinc-700 border-zinc-200
                                    @endif
                                ">
                                    @if($webhook->status === 'completed') ✅
                                    @elseif($webhook->status === 'failed' || $webhook->status === 'permanent_failure') ❌
                                    @elseif($webhook->status === 'processing') ⚡
                                    @elseif($webhook->status === 'queued') 📋
                                    @else ❓
                                    @endif
                                    {{ ucfirst($webhook->status) }}
                                </flux:badge>

                                <!-- Topic -->
                                <span class="text-lg font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ str_replace('/', ' → ', $webhook->topic) }}
                                </span>

                                <!-- Processing Time -->
                                @if($webhook->metadata && isset($webhook->metadata['processing_time_ms']))
                                    <span class="text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ $webhook->metadata['processing_time_ms'] }}ms
                                    </span>
                                @endif
                            </div>

                            <div class="text-sm text-zinc-600 dark:text-zinc-400 space-y-1">
                                <div>
                                    <strong>Shopify Product ID:</strong> {{ $webhook->shopify_product_id ?? 'N/A' }}
                                </div>
                                @if($webhook->metadata && isset($webhook->metadata['shop_domain']))
                                    <div>
                                        <strong>Shop:</strong> {{ $webhook->metadata['shop_domain'] }}
                                    </div>
                                @endif
                                @if($webhook->metadata && isset($webhook->metadata['webhook_id']))
                                    <div>
                                        <strong>Webhook ID:</strong> {{ $webhook->metadata['webhook_id'] }}
                                    </div>
                                @endif
                                @if($webhook->metadata && isset($webhook->metadata['error']))
                                    <div class="text-red-600 dark:text-red-400">
                                        <strong>Error:</strong> {{ $webhook->metadata['error'] }}
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="text-right text-sm text-zinc-500 dark:text-zinc-400">
                            <div>{{ $webhook->created_at->format('M j, Y') }}</div>
                            <div>{{ $webhook->created_at->format('H:i:s') }}</div>
                            <div class="mt-1">
                                {{ $webhook->created_at->diffForHumans() }}
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <flux:icon name="inbox" class="w-12 h-12 mx-auto text-zinc-400 mb-4" />
                    <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-2">No webhook logs found</h3>
                    <p class="text-zinc-500 dark:text-zinc-400">
                        @if($search || $filterStatus !== 'all' || $filterTopic !== 'all')
                            Try adjusting your filters or search terms.
                        @else
                            Webhook logs will appear here once Shopify starts sending webhooks.
                        @endif
                    </p>
                </div>
            @endforelse
        </div>

        @if($this->webhookLogs->hasPages())
            <div class="px-6 py-4 border-t border-zinc-200 dark:border-zinc-700">
                {{ $this->webhookLogs->links() }}
            </div>
        @endif
    </div>
</div>

<!-- 🎭 LEGENDARY SUCCESS MESSAGES -->
<script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('dashboard-refreshed', () => {
            // Add some sparkle animation or notification
        });

        Livewire.on('log-cleanup-completed', (event) => {
            alert(`✨ LEGENDARY cleanup complete! Removed ${event.count} old webhook logs.`);
        });

        Livewire.on('webhooks-retried', (event) => {
            alert(`🔄 FABULOUS! Retried ${event.count} failed webhooks.`);
        });
    });
</script>