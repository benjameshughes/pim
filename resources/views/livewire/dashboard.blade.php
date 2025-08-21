<div class="space-y-6">
    {{-- Clean Header Section --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Dashboard</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Welcome back, {{ auth()->user()->name }} • {{ now()->format('D, M j, Y') }}
            </p>
        </div>
        
        <div class="flex items-center space-x-3">
            <flux:button href="{{ route('products.create') }}" variant="primary" icon="plus">
                New Product
            </flux:button>
            <flux:button wire:click="refreshData" variant="ghost" icon="arrow-path">
                Refresh
            </flux:button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Catalog Health --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center">
                <flux:icon name="chart-bar" class="w-8 h-8 text-green-500" />
                <div class="ml-4">
                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $this->pimMetrics['catalog_health']['completeness_score'] }}%</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Catalog Health</div>
                </div>
            </div>
        </div>

        {{-- Content Efficiency --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center">
                <flux:icon name="bolt" class="w-8 h-8 text-blue-500" />
                <div class="ml-4">
                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $this->pimMetrics['content_efficiency']['automation_rate'] }}%</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Automation Rate</div>
                </div>
            </div>
        </div>

        {{-- Missing Barcodes --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center">
                <flux:icon name="exclamation-triangle" class="w-8 h-8 text-yellow-500" />
                <div class="ml-4">
                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $this->pimMetrics['operational_kpis']['missing_barcodes'] }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Missing Barcodes</div>
                </div>
            </div>
        </div>

        {{-- Export Ready --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center">
                <flux:icon name="cloud-arrow-up" class="w-8 h-8 text-purple-500" />
                <div class="ml-4">
                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $this->pimMetrics['channel_readiness']['ready_for_export'] }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Export Ready</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Data Quality Alerts --}}
    @if(!empty($this->pimWorkflow['data_quality_alerts']))
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Data Quality Alerts</h3>
                <flux:badge color="yellow" size="sm">{{ count($this->pimWorkflow['data_quality_alerts']) }} items</flux:badge>
            </div>
            
            <div class="space-y-3">
                @foreach($this->pimWorkflow['data_quality_alerts'] as $alert)
                    <div class="flex items-center justify-between p-3 rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center">
                            @if($alert['type'] === 'error')
                                <flux:icon name="x-circle" class="w-5 h-5 text-red-500 mr-3" />
                            @elseif($alert['type'] === 'warning')  
                                <flux:icon name="exclamation-triangle" class="w-5 h-5 text-yellow-500 mr-3" />
                            @else
                                <flux:icon name="information-circle" class="w-5 h-5 text-blue-500 mr-3" />
                            @endif
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $alert['message'] }}</p>
                            </div>
                        </div>
                        <flux:button variant="outline" size="sm">
                            Fix Now
                        </flux:button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Recent Activity --}}
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Recent Activity</h3>
                    <flux:button variant="ghost" size="sm">View All</flux:button>
                </div>
            </div>

            @if($this->pimWorkflow['recent_imports'])
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Activity
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Items
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Time
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($this->pimWorkflow['recent_imports'] as $import)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $import['name'] }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <flux:badge 
                                            :color="match($import['status']) {
                                                'completed' => 'green',
                                                'partial' => 'yellow', 
                                                'failed' => 'red',
                                                'processing' => 'blue',
                                                default => 'gray'
                                            }"
                                            size="sm"
                                        >
                                            {{ ucfirst($import['status']) }}
                                        </flux:badge>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900 dark:text-white">{{ $import['items'] }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900 dark:text-white">{{ $import['time'] }}</div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-6 py-12 text-center">
                    <flux:icon name="inbox" class="mx-auto h-8 w-8 text-gray-400" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No recent activity</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Your recent operations will appear here</p>
                </div>
            @endif
        </div>

        {{-- Quick Actions & Insights --}}
        <div class="space-y-6">
            {{-- Quick Actions --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Quick Actions</h3>
                
                <div class="space-y-3">
                    <flux:button href="{{ route('products.create') }}" variant="outline" size="sm" icon="plus" class="w-full justify-start">
                        Create Product
                    </flux:button>
                    <flux:button href="{{ route('barcodes.index') }}" variant="outline" size="sm" icon="qr-code" class="w-full justify-start">
                        Manage Barcodes
                    </flux:button>
                    <flux:button href="{{ route('shopify.sync') }}" variant="outline" size="sm" icon="cloud-arrow-up" class="w-full justify-start">
                        Sync Shopify
                    </flux:button>
                    <flux:button href="{{ route('import.products') }}" variant="outline" size="sm" icon="arrow-up-tray" class="w-full justify-start">
                        Import Products
                    </flux:button>
                </div>
            </div>

            {{-- Performance Bottlenecks --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Performance</h3>
                
                <div class="space-y-4">
                    @foreach($this->pimWorkflow['workflow_bottlenecks'] as $bottleneck)
                        <div class="p-3 rounded-lg border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-medium text-gray-900 dark:text-white">{{ $bottleneck['stage'] }}</h4>
                                @if($bottleneck['count'] > 0)
                                    <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                                @else
                                    <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                                @endif
                            </div>
                            <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                <span>{{ $bottleneck['count'] }} items</span>
                                <span>•</span>
                                <span>{{ $bottleneck['avg_time'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- App-Specific Widgets --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {{-- Color Management --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center">
                    <flux:icon name="swatch" class="w-6 h-6 text-pink-500 mr-3" />
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Color Management</h3>
                </div>
            </div>
            
            <div class="space-y-3">
                @php
                    $uniqueColors = \App\Models\ProductVariant::distinct('color')->count('color');
                    $recentColorChanges = \App\Models\ProductVariant::where('updated_at', '>=', now()->subWeek())->count();
                @endphp
                
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Unique Colors</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $uniqueColors }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Recent Updates</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $recentColorChanges }}</span>
                </div>
                
                <flux:button href="{{ route('shopify.colors') }}" variant="outline" size="sm" class="w-full mt-3">
                    Manage Colors
                </flux:button>
            </div>
        </div>

        {{-- Barcode Pool Status --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center">
                    <flux:icon name="qr-code" class="w-6 h-6 text-green-500 mr-3" />
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Barcode Pool</h3>
                </div>
            </div>
            
            <div class="space-y-3">
                @php
                    $totalBarcodes = \App\Models\Barcode::count();
                    $assignedBarcodes = \App\Models\Barcode::whereNotNull('variant_id')->count();
                    $availableRate = $totalBarcodes > 0 ? round((($totalBarcodes - $assignedBarcodes) / $totalBarcodes) * 100, 1) : 0;
                @endphp
                
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Available</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $availableRate }}%</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Total Pool</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $totalBarcodes }}</span>
                </div>
                
                <flux:button href="{{ route('barcodes.index') }}" variant="outline" size="sm" class="w-full mt-3">
                    Manage Pool
                </flux:button>
            </div>
        </div>

        {{-- Sync Health --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center">
                    <flux:icon name="cloud-arrow-up" class="w-6 h-6 text-blue-500 mr-3" />
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Sync Health</h3>
                </div>
            </div>
            
            <div class="space-y-3">
                @php
                    $syncStatus = $this->pimMetrics['channel_readiness']['channel_sync_status'];
                    $readyProducts = $this->pimMetrics['channel_readiness']['ready_for_export'];
                @endphp
                
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Status</span>
                    <flux:badge 
                        :color="match($syncStatus) {
                            'synced' => 'green',
                            'partial' => 'yellow',
                            'failed' => 'red',
                            'pending' => 'blue',
                            default => 'gray'
                        }"
                        size="sm"
                    >
                        {{ ucfirst($syncStatus) }}
                    </flux:badge>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Ready Products</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $readyProducts }}</span>
                </div>
                
                <flux:button href="{{ route('shopify.sync') }}" variant="outline" size="sm" class="w-full mt-3">
                    View Sync
                </flux:button>
            </div>
        </div>
    </div>
</div>