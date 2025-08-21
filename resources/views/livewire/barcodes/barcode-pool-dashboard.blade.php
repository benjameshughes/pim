<div class="space-y-6">
    {{-- Header Section --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">🏊‍♂️ Barcode Pool Dashboard</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                GS1 Barcode Management Center • {{ $this->poolStats['total'] ?? 0 }} total barcodes
            </p>
        </div>
        
        <div class="flex items-center space-x-3">
            {{-- Barcode Type Selector --}}
            <div class="flex bg-gray-100 dark:bg-gray-700 rounded-lg p-1">
                @foreach($availableTypes as $type => $label)
                    <button 
                        wire:click="selectType('{{ $type }}')"
                        class="{{ $selectedType === $type ? 'bg-white dark:bg-gray-800 shadow-sm text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-400' }} px-3 py-1 text-sm font-medium rounded-md transition-colors"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
            
            <flux:button wire:click="refreshPoolData" variant="ghost" icon="arrow-path" :loading="$loading['refresh'] ?? false">
                Refresh
            </flux:button>
            
            <flux:button wire:click="importPoolData" variant="primary" icon="arrow-up-tray" :loading="$loading['import'] ?? false">
                Import Pool
            </flux:button>
        </div>
    </div>

    {{-- Pool Statistics Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Available Barcodes --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center">
                <flux:icon name="qr-code" class="w-8 h-8 text-green-500" />
                <div class="ml-4">
                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">
                        {{ number_format($this->poolStats['available'] ?? 0) }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Available</div>
                </div>
            </div>
        </div>

        {{-- Assignment Rate --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center">
                <flux:icon name="chart-bar" class="w-8 h-8 {{ $assignmentRateTrend === 'critical' ? 'text-red-500' : ($assignmentRateTrend === 'warning' ? 'text-yellow-500' : 'text-blue-500') }}" />
                <div class="ml-4">
                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">
                        {{ number_format($this->poolStats['assignment_rate'] ?? 0, 1) }}%
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Assignment Rate</div>
                </div>
            </div>
        </div>

        {{-- Recent Assignments --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center">
                <flux:icon name="clock" class="w-8 h-8 text-purple-500" />
                <div class="ml-4">
                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">
                        {{ $this->recentAssignments->count() ?? 0 }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">This Week</div>
                </div>
            </div>
        </div>

        {{-- Pool Health Score --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center">
                <flux:icon name="{{ $this->poolHealth['overall_score'] >= 8 ? 'check-circle' : ($this->poolHealth['overall_score'] >= 6 ? 'exclamation-triangle' : 'x-circle') }}" 
                          class="w-8 h-8 {{ $this->poolHealth['overall_score'] >= 8 ? 'text-green-500' : ($this->poolHealth['overall_score'] >= 6 ? 'text-yellow-500' : 'text-red-500') }}" />
                <div class="ml-4">
                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">
                        {{ $this->poolHealth['overall_score'] ?? 0 }}/10
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Pool Health</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Pool Health Alerts --}}
    @if(!empty($this->poolHealth['alerts']))
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Pool Health Alerts</h3>
                <flux:badge color="{{ $assignmentRateTrend === 'critical' ? 'red' : ($assignmentRateTrend === 'warning' ? 'yellow' : 'green') }}" size="sm">
                    {{ count($this->poolHealth['alerts']) }} alerts
                </flux:badge>
            </div>
            
            <div class="space-y-3">
                @foreach($this->poolHealth['alerts'] as $alert)
                    <div class="flex items-center justify-between p-3 rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center">
                            @if($alert['severity'] === 'critical')
                                <flux:icon name="x-circle" class="w-5 h-5 text-red-500 mr-3" />
                            @elseif($alert['severity'] === 'warning')  
                                <flux:icon name="exclamation-triangle" class="w-5 h-5 text-yellow-500 mr-3" />
                            @else
                                <flux:icon name="information-circle" class="w-5 h-5 text-blue-500 mr-3" />
                            @endif
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $alert['message'] }}</p>
                                @if(isset($alert['details']))
                                    <p class="text-xs text-gray-600 dark:text-gray-400">{{ $alert['details'] }}</p>
                                @endif
                            </div>
                        </div>
                        @if(isset($alert['action']))
                            <flux:button variant="outline" size="sm" wire:click="{{ $alert['action'] }}">
                                Fix Now
                            </flux:button>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Recent Assignments --}}
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Recent Assignments</h3>
                    <flux:button variant="ghost" size="sm">View All</flux:button>
                </div>
            </div>

            @if($this->recentAssignments && $this->recentAssignments->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Barcode
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Variant
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Quality
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Assigned
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($this->recentAssignments as $assignment)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-mono text-gray-900 dark:text-white">
                                            {{ substr($assignment->barcode, 0, 8) }}...
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $assignment->barcode_type }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $assignment->assignedVariant?->sku ?? 'N/A' }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $assignment->assignedVariant?->product?->name ?? 'Unknown Product' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <flux:badge 
                                            :color="$assignment->quality_score >= 8 ? 'green' : ($assignment->quality_score >= 6 ? 'yellow' : 'red')"
                                            size="sm"
                                        >
                                            {{ $assignment->quality_score }}/10
                                        </flux:badge>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900 dark:text-white">
                                            {{ $assignment->assigned_at?->diffForHumans() ?? 'Never' }}
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-6 py-12 text-center">
                    <flux:icon name="qr-code" class="mx-auto h-8 w-8 text-gray-400" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No recent assignments</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Recent barcode assignments will appear here</p>
                </div>
            @endif
        </div>

        {{-- Pool Management & Analytics --}}
        <div class="space-y-6">
            {{-- Pool Management Actions --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Pool Management</h3>
                
                <div class="space-y-3">
                    <flux:button wire:click="reserveRange(100)" variant="outline" size="sm" icon="lock-closed" class="w-full justify-start" :loading="$loading['reserve'] ?? false">
                        Reserve 100 Barcodes
                    </flux:button>
                    <flux:button wire:click="reserveRange(500)" variant="outline" size="sm" icon="lock-closed" class="w-full justify-start" :loading="$loading['reserve'] ?? false">
                        Reserve 500 Barcodes
                    </flux:button>
                    <flux:button wire:click="importPoolData" variant="outline" size="sm" icon="arrow-up-tray" class="w-full justify-start" :loading="$loading['import'] ?? false">
                        Import New Pool
                    </flux:button>
                    <flux:button wire:click="refreshPoolData" variant="outline" size="sm" icon="arrow-path" class="w-full justify-start" :loading="$loading['refresh'] ?? false">
                        Refresh Statistics
                    </flux:button>
                </div>
            </div>

            {{-- Quality Distribution --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Quality Distribution</h3>
                
                <div class="space-y-3">
                    @foreach($this->qualityDistribution as $quality)
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <span class="text-sm font-medium text-gray-900 dark:text-white w-8">{{ $quality['score'] }}</span>
                                <div class="ml-3 flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2 w-20">
                                    <div class="bg-{{ $quality['score'] >= 8 ? 'green' : ($quality['score'] >= 6 ? 'yellow' : 'red') }}-500 h-2 rounded-full" 
                                         style="width: {{ $quality['percentage'] }}%"></div>
                                </div>
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400 ml-3">
                                {{ $quality['count'] }} ({{ $quality['percentage'] }}%)
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Pool Status Summary --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Status Breakdown</h3>
                
                <div class="space-y-3">
                    @foreach(['available', 'assigned', 'reserved', 'legacy_archive', 'problematic'] as $status)
                        @php $count = $this->poolStats['by_status'][$status] ?? 0; @endphp
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <flux:badge :color="$this->getStatusColor($status)" size="sm" class="mr-3">
                                    {{ ucfirst(str_replace('_', ' ', $status)) }}
                                </flux:badge>
                            </div>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ number_format($count) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Legacy Data Overview --}}
    @if(($this->poolStats['legacy_count'] ?? 0) > 0)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center">
                    <flux:icon name="archive-box" class="w-6 h-6 text-gray-500 mr-3" />
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Legacy Data Archive</h3>
                </div>
                <flux:badge color="gray" size="sm">{{ number_format($this->poolStats['legacy_count'] ?? 0) }} archived</flux:badge>
            </div>
            
            <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    <strong>{{ number_format($this->poolStats['legacy_count'] ?? 0) }} legacy barcodes</strong> from rows 1-39,999 are preserved for historical purposes.
                    Active assignments begin from row 40,000+ as configured. Legacy data maintains audit trail while not interfering with new assignments.
                </p>
                <div class="mt-3 flex items-center text-xs text-gray-500 dark:text-gray-400">
                    <flux:icon name="information-circle" class="w-4 h-4 mr-2" />
                    Legacy barcodes are excluded from automatic assignments but remain searchable
                </div>
            </div>
        </div>
    @endif
</div>
