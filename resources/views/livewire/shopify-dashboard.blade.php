<div class="space-y-8">
    <!-- 🎭 LEGENDARY HEADER with MAXIMUM SASS -->
    <div class="bg-gradient-to-r from-emerald-500 to-green-600 dark:from-emerald-700 dark:to-green-800 rounded-2xl p-8 text-white">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-4xl font-bold mb-2">🏪 Shopify Sync Dashboard</h1>
                <p class="text-emerald-100 text-lg">Your complete sync intelligence command center with LEGENDARY monitoring!</p>
            </div>
            <div class="flex items-center gap-4">
                <flux:button variant="ghost" size="base" wire:click="refresh">
                    <flux:icon name="refresh-cw" class="w-5 h-5 mr-2" />
                    Refresh Data
                </flux:button>
                @if($healthSummary['needs_attention'] > 0)
                    <flux:button variant="primary" size="base" wire:click="syncProductsNeedingAttention">
                        <flux:icon name="zap" class="w-5 h-5 mr-2" />
                        Sync {{ $healthSummary['needs_attention'] }} Products
                    </flux:button>
                @endif
            </div>
        </div>
    </div>

    <!-- 📊 HEALTH OVERVIEW CARDS -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
        <!-- Overall Health Score -->
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-green-600 rounded-xl flex items-center justify-center">
                    <flux:icon name="activity" class="w-6 h-6 text-white" />
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold text-{{ $healthSummary['average_health'] >= 80 ? 'emerald' : ($healthSummary['average_health'] >= 60 ? 'yellow' : 'red') }}-600">
                        {{ $healthSummary['average_health'] }}%
                    </div>
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">Average Health</div>
                </div>
            </div>
            <div class="flex items-center gap-2 text-sm">
                <span class="text-zinc-600 dark:text-zinc-400">Trend:</span>
                @if($healthSummary['health_trend'] === 'improving')
                    <span class="text-emerald-600 font-medium">📈 Improving</span>
                @elseif($healthSummary['health_trend'] === 'declining')
                    <span class="text-red-600 font-medium">📉 Declining</span>
                @else
                    <span class="text-blue-600 font-medium">📊 Stable</span>
                @endif
            </div>
        </div>

        <!-- Total Products -->
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center">
                    <flux:icon name="package" class="w-6 h-6 text-white" />
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold text-blue-600">{{ $healthSummary['total_products'] }}</div>
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">Total Products</div>
                </div>
            </div>
            <div class="text-sm text-zinc-600 dark:text-zinc-400">
                Synced to Shopify
            </div>
        </div>

        <!-- Healthy Products -->
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-green-600 rounded-xl flex items-center justify-center">
                    <flux:icon name="check-circle" class="w-6 h-6 text-white" />
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold text-emerald-600">{{ $healthSummary['healthy_products'] }}</div>
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">Healthy Products</div>
                </div>
            </div>
            <div class="text-sm text-emerald-600">
                {{ $healthSummary['total_products'] > 0 ? round(($healthSummary['healthy_products'] / $healthSummary['total_products']) * 100) : 0 }}% of total
            </div>
        </div>

        <!-- Needs Attention -->
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-pink-600 rounded-xl flex items-center justify-center">
                    <flux:icon name="exclamation-triangle" class="w-6 h-6 text-white" />
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold text-{{ $healthSummary['needs_attention'] > 0 ? 'red' : 'emerald' }}-600">
                        {{ $healthSummary['needs_attention'] }}
                    </div>
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">Need Attention</div>
                </div>
            </div>
            <div class="text-sm {{ $healthSummary['needs_attention'] > 0 ? 'text-red-600' : 'text-emerald-600' }}">
                {{ $healthSummary['needs_attention'] > 0 ? 'Action required' : 'All healthy!' }}
            </div>
        </div>
    </div>

    <!-- 📈 HEALTH GRADE DISTRIBUTION -->
    @if(!empty($healthSummary['grade_distribution']))
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex items-center gap-3 mb-6">
                <flux:icon name="bar-chart-3" class="w-6 h-6 text-purple-600" />
                <h2 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">Health Grade Distribution</h2>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-10 gap-4">
                @foreach($healthSummary['grade_distribution'] as $grade => $count)
                    @php
                        $color = match($grade) {
                            'A+', 'A', 'A-' => 'emerald',
                            'B+', 'B', 'B-' => 'blue',
                            'C+', 'C', 'C-' => 'yellow',
                            'D' => 'orange',
                            'F' => 'red',
                            'N/A' => 'zinc',
                            default => 'zinc'
                        };
                    @endphp
                    <div class="text-center">
                        <div class="w-full bg-{{ $color }}-100 dark:bg-{{ $color }}-900/30 rounded-lg p-4 border border-{{ $color }}-200 dark:border-{{ $color }}-800">
                            <div class="text-2xl font-bold text-{{ $color }}-600">{{ $grade }}</div>
                            <div class="text-sm font-medium text-{{ $color }}-700 dark:text-{{ $color }}-400">{{ $count }}</div>
                        </div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                            {{ $healthSummary['total_products'] > 0 ? round(($count / $healthSummary['total_products']) * 100) : 0 }}%
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- 🔔 WEBHOOK HEALTH & RECENT ACTIVITY -->
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <!-- Webhook Health -->
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex items-center gap-3 mb-6">
                <flux:icon name="radio" class="w-6 h-6 text-indigo-600" />
                <h2 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">Webhook Health (24h)</h2>
            </div>

            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="text-center p-4 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600">{{ $webhookHealth['total_webhooks_24h'] ?? 0 }}</div>
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">Total Events</div>
                </div>
                <div class="text-center p-4 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg">
                    <div class="text-2xl font-bold text-emerald-600">{{ $webhookHealth['successful_webhooks'] ?? 0 }}</div>
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">Successful</div>
                </div>
                <div class="text-center p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                    <div class="text-2xl font-bold text-red-600">{{ $webhookHealth['failed_webhooks'] ?? 0 }}</div>
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">Failed</div>
                </div>
            </div>

            @if(!empty($webhookHealth['webhook_topics']))
                <div>
                    <h4 class="text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-3">Event Types</h4>
                    <div class="space-y-2">
                        @foreach($webhookHealth['webhook_topics'] as $topic => $count)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-zinc-600 dark:text-zinc-400">{{ str_replace('/', ' → ', $topic) }}</span>
                                <flux:badge variant="outline" class="bg-blue-50 text-blue-700 border-blue-200">{{ $count }}</flux:badge>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <!-- Recent Activity -->
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex items-center gap-3 mb-6">
                <flux:icon name="clock" class="w-6 h-6 text-orange-600" />
                <h2 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">Recent Activity</h2>
            </div>

            <div class="space-y-4 max-h-96 overflow-y-auto">
                <!-- Recent Syncs -->
                @foreach(collect($recentActivity['syncs'] ?? [])->take(5) as $activity)
                    <div class="flex items-center gap-3 p-3 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg border border-emerald-200 dark:border-emerald-800">
                        <div class="w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center">
                            <flux:icon name="refresh-cw" class="w-4 h-4 text-white" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">
                                {{ $activity['product_name'] }}
                            </div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                Sync {{ $activity['status'] }} • {{ $activity['method'] }} • Grade: {{ $activity['health_grade'] }}
                            </div>
                        </div>
                        <div class="text-xs text-zinc-400">
                            {{ \Carbon\Carbon::parse($activity['timestamp'])->diffForHumans() }}
                        </div>
                    </div>
                @endforeach

                <!-- Recent Webhooks -->
                @foreach(collect($recentActivity['webhooks'] ?? [])->take(5) as $activity)
                    <div class="flex items-center gap-3 p-3 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg border border-indigo-200 dark:border-indigo-800">
                        <div class="w-8 h-8 bg-indigo-500 rounded-full flex items-center justify-center">
                            <flux:icon name="radio" class="w-4 h-4 text-white" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">
                                {{ $activity['product_name'] }}
                            </div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ str_replace('/', ' → ', $activity['topic']) }} • 
                                <span class="text-{{ $activity['status'] === 'success' ? 'emerald' : 'red' }}-600">
                                    {{ $activity['status'] }}
                                </span>
                                @if($activity['verified'])
                                    <span class="text-emerald-600">✓ Verified</span>
                                @endif
                            </div>
                        </div>
                        <div class="text-xs text-zinc-400">
                            {{ \Carbon\Carbon::parse($activity['timestamp'])->diffForHumans() }}
                        </div>
                    </div>
                @endforeach

                @if(empty($recentActivity['syncs']) && empty($recentActivity['webhooks']))
                    <div class="text-center py-8 text-zinc-500 dark:text-zinc-400">
                        <flux:icon name="inbox" class="w-12 h-12 mx-auto mb-3 opacity-50" />
                        <p>No recent activity to display</p>
                        <p class="text-sm">Sync some products to see activity here</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- 🎉 SUCCESS TOASTS -->
    <div wire:transition class="fixed bottom-4 right-4 z-50">
        <!-- These would be handled by your toast system -->
    </div>
    <!-- 📱 RESPONSIVE DESIGN NOTES -->
    <style>
        /* Custom scrollbar for activity feed */
        .max-h-96::-webkit-scrollbar {
            width: 4px;
        }
        .max-h-96::-webkit-scrollbar-track {
            background: transparent;
        }
        .max-h-96::-webkit-scrollbar-thumb {
            background: rgba(156, 163, 175, 0.5);
            border-radius: 2px;
        }
        .max-h-96::-webkit-scrollbar-thumb:hover {
            background: rgba(156, 163, 175, 0.8);
        }
    </style>
</div>