<div wire:poll.{{ $refreshInterval }}ms="refreshData">
    {{-- Breadcrumb Navigation --}}
    <x-breadcrumb :items="[
        ['name' => 'Dashboard']
    ]" />

    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">PIM Dashboard</flux:heading>
            <flux:subheading>Monitor your product information management performance and data quality</flux:subheading>
        </div>
        
        <div class="flex items-center space-x-3">
            <flux:button variant="ghost" icon="arrow-path" wire:click="refreshData" wire:loading.attr="disabled">
                <span wire:loading.remove>Refresh</span>
                <span wire:loading>Refreshing...</span>
            </flux:button>
            
            <flux:dropdown>
                <flux:button variant="primary" icon="funnel">
                    Time Range
                </flux:button>
                
                <flux:menu>
                    <flux:menu.item>Last 7 days</flux:menu.item>
                    <flux:menu.item>Last 30 days</flux:menu.item>
                    <flux:menu.item>Last 3 months</flux:menu.item>
                    <flux:menu.separator />
                    <flux:menu.item>Custom range</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    {{-- Success Message (if any) --}}
    @if (session()->has('message'))
        <div class="mb-6 rounded-lg bg-green-100 px-6 py-4 text-green-700 dark:bg-green-900 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif

    {{-- PIM Key Metrics Cards --}}
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        {{-- Catalog Completeness --}}
        <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-zinc-800">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/50">
                            <flux:icon name="chart-bar" class="h-6 w-6 text-emerald-600 dark:text-emerald-400" />
                        </div>
                    </div>
                    <div class="ml-4 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400 truncate">
                                Catalog Completeness
                            </dt>
                            <dd class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">
                                {{ $this->pimMetrics['catalog_health']['completeness_score'] }}%
                            </dd>
                        </dl>
                    </div>
                </div>
                <div class="mt-4 flex items-center text-sm">
                    @if($this->pimMetrics['catalog_health']['data_quality_trend'] === 'improving')
                        <span class="inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-900/50 px-3 py-1 text-xs font-medium text-emerald-800 dark:text-emerald-400">
                            <flux:icon name="chevron-right" class="h-3 w-3 mr-1 rotate-[-45deg]" />
                            Improving
                        </span>
                    @elseif($this->pimMetrics['catalog_health']['data_quality_trend'] === 'declining')
                        <span class="inline-flex items-center rounded-full bg-red-100 dark:bg-red-900/50 px-3 py-1 text-xs font-medium text-red-800 dark:text-red-400">
                            <flux:icon name="chevron-down" class="h-3 w-3 mr-1" />
                            Declining
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-zinc-100 dark:bg-zinc-700 px-3 py-1 text-xs font-medium text-zinc-800 dark:text-zinc-300">
                            <flux:icon name="chevrons-up-down" class="h-3 w-3 mr-1" />
                            Stable
                        </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Time to Market --}}
        <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-zinc-800">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/50">
                            <flux:icon name="activity" class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                        </div>
                    </div>
                    <div class="ml-4 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400 truncate">
                                Avg Time to Market
                            </dt>
                            <dd class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">
                                {{ $this->pimMetrics['content_efficiency']['time_to_market'] }}d
                            </dd>
                        </dl>
                    </div>
                </div>
                <div class="mt-4 flex items-center text-sm">
                    <span class="inline-flex items-center rounded-full bg-blue-100 dark:bg-blue-900/50 px-3 py-1 text-xs font-medium text-blue-800 dark:text-blue-400">
                        {{ $this->pimMetrics['content_efficiency']['weekly_throughput'] }} items this week
                    </span>
                </div>
            </div>
        </div>

        {{-- Data Quality Issues --}}
        <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-zinc-800">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/50">
                            <flux:icon name="triangle-alert" class="h-6 w-6 text-amber-600 dark:text-amber-400" />
                        </div>
                    </div>
                    <div class="ml-4 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400 truncate">
                                Data Quality Issues
                            </dt>
                            <dd class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">
                                {{ $this->pimMetrics['operational_kpis']['missing_barcodes'] + $this->pimMetrics['operational_kpis']['pricing_gaps'] }}
                            </dd>
                        </dl>
                    </div>
                </div>
                <div class="mt-4 flex items-center text-sm">
                    <span class="inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900/50 px-3 py-1 text-xs font-medium text-amber-800 dark:text-amber-400">
                        {{ $this->pimMetrics['operational_kpis']['products_pending_approval'] }} pending approval
                    </span>
                </div>
            </div>
        </div>

        {{-- Channel Readiness --}}
        <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-zinc-800">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900/50">
                            <flux:icon name="globe" class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                        </div>
                    </div>
                    <div class="ml-4 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400 truncate">
                                Channel Ready
                            </dt>
                            <dd class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">
                                {{ $this->pimMetrics['channel_readiness']['ready_for_export'] }}
                            </dd>
                        </dl>
                    </div>
                </div>
                <div class="mt-4 flex items-center text-sm">
                    @php
                        $syncStatus = $this->pimMetrics['channel_readiness']['channel_sync_status'];
                        $statusClasses = [
                            'synced' => 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-800 dark:text-emerald-400',
                            'partial' => 'bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-400',
                            'pending' => 'bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-400'
                        ];
                    @endphp
                    <span class="inline-flex items-center rounded-full {{ $statusClasses[$syncStatus] }} px-3 py-1 text-xs font-medium">
                        {{ ucfirst($syncStatus) }} sync
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Main PIM Content Grid --}}
    <div class="grid gap-8 lg:grid-cols-12">
        {{-- PIM Analytics Section --}}
        <div class="lg:col-span-8 space-y-8">
            {{-- Catalog Completeness Trend --}}
            <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-zinc-800">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                            Catalog Completeness Trend
                        </h3>
                        <flux:button variant="ghost" size="sm" icon="chart-bar">
                            View Details
                        </flux:button>
                    </div>
                    
                    <div class="space-y-4">
                        @foreach($this->pimCharts['catalog_completeness_trend'] as $date => $score)
                        <div class="flex items-center">
                            <div class="w-20 text-sm text-zinc-600 dark:text-zinc-400">{{ \Carbon\Carbon::parse($date)->format('M d') }}</div>
                            <div class="flex-1 mx-4">
                                <div class="bg-zinc-200 dark:bg-zinc-700 rounded-full h-2">
                                    <div class="bg-gradient-to-r from-emerald-500 to-blue-500 h-2 rounded-full transition-all duration-500" 
                                         style="width: {{ $score }}%"></div>
                                </div>
                            </div>
                            <div class="w-16 text-sm font-medium text-zinc-900 dark:text-zinc-100 text-right">{{ $score }}%</div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Data Quality Score Breakdown --}}
            <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-zinc-800">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-6">
                        Data Quality Metrics
                    </h3>
                    
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
                        @foreach($this->pimCharts['data_quality_score'] as $metric => $score)
                        <div class="text-center">
                            <div class="relative w-16 h-16 mx-auto mb-2">
                                <svg class="w-16 h-16 transform -rotate-90" viewBox="0 0 36 36">
                                    <circle cx="18" cy="18" r="16" fill="none" class="stroke-zinc-200 dark:stroke-zinc-700" stroke-width="2"></circle>
                                    <circle cx="18" cy="18" r="16" fill="none" class="stroke-emerald-500" stroke-width="2" 
                                            stroke-dasharray="{{ $score }}, 100" stroke-linecap="round"></circle>
                                </svg>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="text-xs font-semibold text-zinc-900 dark:text-zinc-100">{{ $score }}%</span>
                                </div>
                            </div>
                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100 capitalize">{{ str_replace('_', ' ', $metric) }}</div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Content Velocity Chart --}}
            <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-zinc-800">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-6">
                        Content Velocity (Last 7 Days)
                    </h3>
                    
                    <div class="flex items-end space-x-2 h-32">
                        @php $maxCount = max($this->pimCharts['content_velocity']) ?: 1; @endphp
                        @foreach($this->pimCharts['content_velocity'] as $date => $count)
                        <div class="flex flex-col items-center flex-1">
                            <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-t" style="height: {{ ($count / $maxCount) * 100 }}%">
                                <div class="w-full bg-gradient-to-t from-blue-600 to-blue-400 rounded-t transition-all duration-500" 
                                     style="height: 100%"></div>
                            </div>
                            <div class="mt-2 text-xs text-zinc-600 dark:text-zinc-400">{{ \Carbon\Carbon::parse($date)->format('M j') }}</div>
                            <div class="text-xs font-medium text-zinc-900 dark:text-zinc-100">{{ $count }}</div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- PIM Workflow Sidebar --}}
        <div class="lg:col-span-4 space-y-6">
            {{-- Data Quality Alerts --}}
            <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-zinc-800">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                            Quality Alerts
                        </h3>
                        <flux:button variant="ghost" size="sm" href="/products/variants" wire:navigate>
                            View All
                        </flux:button>
                    </div>
                    
                    <div class="space-y-3">
                        @forelse($this->pimWorkflow['data_quality_alerts'] as $alert)
                        <div class="p-3 rounded-lg border {{ $alert['type'] === 'error' ? 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800' : ($alert['type'] === 'warning' ? 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800' : 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800') }}">
                            <div class="flex items-start">
                                @if($alert['type'] === 'error')
                                    <flux:icon name="circle-x" class="h-5 w-5 text-red-500 dark:text-red-400 mt-0.5 mr-3" />
                                @elseif($alert['type'] === 'warning')
                                    <flux:icon name="triangle-alert" class="h-5 w-5 text-amber-500 dark:text-amber-400 mt-0.5 mr-3" />
                                @else
                                    <flux:icon name="circle-alert" class="h-5 w-5 text-blue-500 dark:text-blue-400 mt-0.5 mr-3" />
                                @endif
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $alert['message'] }}</p>
                                    <flux:button variant="ghost" size="sm" class="mt-2">
                                        Fix Now
                                    </flux:button>
                                </div>
                            </div>
                        </div>
                        @empty
                        <div class="text-center py-6 text-zinc-500 dark:text-zinc-400">
                            <flux:icon name="circle-check" class="mx-auto h-8 w-8 mb-2 text-emerald-500" />
                            <p class="text-sm">All quality checks passed!</p>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Recent Import Activity --}}
            <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-zinc-800">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                            Recent Imports
                        </h3>
                        <flux:button variant="ghost" size="sm" href="/products/import" wire:navigate>
                            Import Data
                        </flux:button>
                    </div>
                    
                    <div class="space-y-3">
                        @foreach($this->pimWorkflow['recent_imports'] as $import)
                        <div class="flex items-center justify-between p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700/50">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">
                                    {{ $import['name'] }}
                                </p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $import['items'] }} items • {{ $import['time'] }}
                                </p>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $import['status'] === 'completed' ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-800 dark:text-emerald-400' : 'bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-400' }}">
                                {{ ucfirst($import['status']) }}
                            </span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Workflow Bottlenecks --}}
            <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-zinc-800">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">
                        Workflow Bottlenecks
                    </h3>
                    
                    <div class="space-y-3">
                        @foreach($this->pimWorkflow['workflow_bottlenecks'] as $bottleneck)
                        <div class="flex items-center justify-between p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700/50">
                            <div>
                                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $bottleneck['stage'] }}</p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">Avg: {{ $bottleneck['avg_time'] }}</p>
                            </div>
                            <div class="text-right">
                                <div class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ $bottleneck['count'] }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">pending</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- PIM Quick Actions --}}
            <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-zinc-800">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">
                        Quick Actions
                    </h3>
                    
                    <div class="space-y-3">
                        <flux:button variant="primary" class="w-full justify-start" icon="plus" href="/products/create" wire:navigate>
                            Add Product
                        </flux:button>
                        
                        <flux:button variant="outline" class="w-full justify-start" icon="document-arrow-up" href="/products/import" wire:navigate>
                            Bulk Import
                        </flux:button>
                        
                        <flux:button variant="outline" class="w-full justify-start" icon="qr-code" href="/products/barcodes" wire:navigate>
                            Assign Barcodes
                        </flux:button>
                        
                        <flux:button variant="outline" class="w-full justify-start" icon="photo" href="/products/images" wire:navigate>
                            Enrich Images
                        </flux:button>
                        
                        <flux:button variant="outline" class="w-full justify-start" icon="currency-pound" href="/products/pricing" wire:navigate>
                            Update Pricing
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Auto-refresh indicator (styled like success message) --}}
    <div wire:loading.delay class="fixed bottom-4 right-4 z-50">
        <div class="rounded-lg bg-blue-100 px-6 py-4 text-blue-700 dark:bg-blue-900 dark:text-blue-300 shadow-lg">
            <div class="flex items-center space-x-2">
                <flux:icon name="refresh-ccw" class="h-4 w-4 animate-spin" />
                <span class="text-sm font-medium">Refreshing PIM data...</span>
            </div>
        </div>
    </div>

    @script
    <script>
        $wire.on('data-refreshed', () => {
            // Optional: Show success notification
            console.log('PIM dashboard data refreshed successfully');
        });
    </script>
    @endscript
</div>