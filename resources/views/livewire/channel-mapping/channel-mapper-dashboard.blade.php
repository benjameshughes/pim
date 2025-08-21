<div class="space-y-6">
    {{-- Header Section --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                Channel Mapper Dashboard
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">
                Manage field mappings and sync status across all marketplace channels
            </p>
        </div>
        
        <div class="flex items-center gap-3">
            <flux:button 
                wire:click="runFieldDiscovery" 
                variant="primary"
                :loading="$isDiscovering"
                icon="refresh-cw"
            >
                Discover Fields
            </flux:button>
            
            <flux:button 
                wire:click="syncValueLists" 
                variant="outline"
                icon="download"
            >
                Sync Lists
            </flux:button>
        </div>
    </div>

    {{-- Health Alerts --}}
    @if(count($this->healthAlerts) > 0)
        <div class="space-y-3">
            @foreach($this->healthAlerts as $alert)
                <div class="flex items-center justify-between p-4 rounded-lg border-l-4 
                    @if($alert['type'] === 'error') bg-red-50 border-red-500 dark:bg-red-950/20 
                    @elseif($alert['type'] === 'warning') bg-yellow-50 border-yellow-500 dark:bg-yellow-950/20 
                    @else bg-blue-50 border-blue-500 dark:bg-blue-950/20 @endif">
                    
                    <div class="flex items-center gap-3">
                        <flux:icon 
                            :name="$alert['type'] === 'error' ? 'circle-alert' : 'triangle-alert'" 
                            class="w-5 h-5 @if($alert['type'] === 'error') text-red-600 @elseif($alert['type'] === 'warning') text-yellow-600 @else text-blue-600 @endif"
                        />
                        <div>
                            <h3 class="font-medium @if($alert['type'] === 'error') text-red-900 dark:text-red-100 @elseif($alert['type'] === 'warning') text-yellow-900 dark:text-yellow-100 @else text-blue-900 dark:text-blue-100 @endif">
                                {{ $alert['title'] }}
                            </h3>
                            <p class="text-sm @if($alert['type'] === 'error') text-red-700 dark:text-red-300 @elseif($alert['type'] === 'warning') text-yellow-700 dark:text-yellow-300 @else text-blue-700 dark:text-blue-300 @endif">
                                {{ $alert['message'] }}
                            </p>
                        </div>
                    </div>
                    
                    <flux:button 
                        wire:click="handleAlertAction('{{ $alert['action'] }}')"
                        size="sm"
                        variant="outline"
                    >
                        {{ $alert['action_label'] }}
                    </flux:button>
                </div>
            @endforeach
        </div>
    @endif

    {{-- System Overview Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {{-- Field Definitions Card --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Field Definitions</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white">
                        {{ $this->systemStats['field_definitions']['total_fields'] ?? 0 }}
                    </p>
                </div>
                <flux:icon name="layers" class="w-8 h-8 text-blue-600" />
            </div>
            <div class="mt-4 flex items-center text-sm">
                <span class="text-green-600 font-medium">
                    {{ $this->systemStats['field_definitions']['active_fields'] ?? 0 }} active
                </span>
                <span class="text-gray-500 ml-2">
                    • {{ $this->systemStats['field_definitions']['required_fields'] ?? 0 }} required
                </span>
            </div>
        </div>

        {{-- Value Lists Card --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Value Lists</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white">
                        {{ $this->systemStats['value_lists']['total_lists'] ?? 0 }}
                    </p>
                </div>
                <flux:icon name="list" class="w-8 h-8 text-green-600" />
            </div>
            <div class="mt-4 flex items-center text-sm">
                <span class="text-green-600 font-medium">
                    {{ $this->systemStats['value_lists']['synced_lists'] ?? 0 }} synced
                </span>
                <span class="text-gray-500 ml-2">
                    • {{ $this->systemStats['value_lists']['total_values'] ?? 0 }} total values
                </span>
            </div>
        </div>

        {{-- Sync Accounts Card --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Sync Accounts</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white">
                        {{ $this->systemStats['sync_accounts'] ?? 0 }}
                    </p>
                </div>
                <flux:icon name="link" class="w-8 h-8 text-purple-600" />
            </div>
            <div class="mt-4 flex items-center text-sm">
                @php
                    $channels = collect(['mirakl', 'shopify', 'ebay', 'amazon']);
                    $activeChannels = $channels->filter(fn($ch) => $this->getChannelSummary($ch)['sync_accounts'] > 0);
                @endphp
                <span class="text-purple-600 font-medium">
                    {{ $activeChannels->count() }} channels
                </span>
                <span class="text-gray-500 ml-2">active</span>
            </div>
        </div>

        {{-- Health Score Card --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Health Score</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white">
                        {{ round($this->systemStats['discovery_health']['overall_health']['score'] ?? 0, 1) }}%
                    </p>
                </div>
                <flux:icon name="heart" class="w-8 h-8 text-{{ $this->getHealthStatusColor($this->systemStats['discovery_health']['overall_health']['score'] ?? 0) }}-600" />
            </div>
            <div class="mt-4">
                <flux:badge 
                    variant="{{ $this->getHealthStatusColor($this->systemStats['discovery_health']['overall_health']['score'] ?? 0) }}"
                    size="sm"
                >
                    {{ $this->getHealthStatusText($this->systemStats['discovery_health']['overall_health']['score'] ?? 0) }}
                </flux:badge>
            </div>
        </div>
    </div>

    {{-- Navigation Tabs --}}
    <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="-mb-px flex space-x-8">
            @foreach(['overview' => 'Overview', 'fields' => 'Field Definitions', 'mappings' => 'Mappings', 'value-lists' => 'Value Lists'] as $tab => $label)
                <button 
                    wire:click="setActiveTab('{{ $tab }}')"
                    class="py-2 px-1 border-b-2 font-medium text-sm transition-colors
                        @if($activeTab === $tab) 
                            border-blue-500 text-blue-600 
                        @else 
                            border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300
                        @endif"
                >
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- Tab Content --}}
    <div class="mt-6">
        @switch($activeTab)
            @case('overview')
                {{-- Channel Overview --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {{-- Channel Summary --}}
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                            Channel Summary
                        </h3>
                        
                        <div class="space-y-4">
                            @foreach(['mirakl' => 'Mirakl', 'shopify' => 'Shopify', 'ebay' => 'eBay', 'amazon' => 'Amazon'] as $channel => $label)
                                @php $summary = $this->getChannelSummary($channel); @endphp
                                @if($summary['sync_accounts'] > 0)
                                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <div class="flex items-center gap-3">
                                            <flux:badge variant="blue" size="sm">{{ $label }}</flux:badge>
                                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                                {{ $summary['sync_accounts'] }} account{{ $summary['sync_accounts'] !== 1 ? 's' : '' }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-4 text-sm text-gray-500">
                                            <span>{{ $summary['fields'] }} fields</span>
                                            <span>{{ $summary['mappings'] }} mappings</span>
                                            <span>{{ $summary['value_lists'] }} lists</span>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    {{-- Recent Activity --}}
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                            Recent Mappings
                        </h3>
                        
                        <div class="space-y-3">
                            @forelse($this->recentMappings as $mapping)
                                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <div>
                                        <p class="font-medium text-sm text-gray-900 dark:text-white">
                                            {{ $mapping->channel_field_code }}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            {{ $mapping->syncAccount->marketplace_type }}:{{ $mapping->syncAccount->account_name }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <flux:badge 
                                            variant="{{ $mapping->mapping_type_color }}" 
                                            size="sm"
                                        >
                                            {{ ucfirst(str_replace('_', ' ', $mapping->mapping_type)) }}
                                        </flux:badge>
                                        <p class="text-xs text-gray-500 mt-1">
                                            {{ $mapping->updated_at->diffForHumans() }}
                                        </p>
                                    </div>
                                </div>
                            @empty
                                <p class="text-gray-500 text-center py-8">No mappings configured yet</p>
                            @endforelse
                        </div>
                    </div>
                </div>
                @break

            @case('fields')
                {{-- Field Definitions Tab --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg border">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                Field Definitions
                            </h3>
                            
                            <div class="flex items-center gap-3">
                                {{-- Channel Filter --}}
                                <flux:select wire:model.live="selectedChannel" placeholder="All Channels">
                                    <flux:select.option value="">All Channels</flux:select.option>
                                    <flux:select.option value="mirakl">Mirakl</flux:select.option>
                                    <flux:select.option value="shopify">Shopify</flux:select.option>
                                    <flux:select.option value="ebay">eBay</flux:select.option>
                                    <flux:select.option value="amazon">Amazon</flux:select.option>
                                </flux:select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Field
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Channel
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Type
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Required
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Last Verified
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($this->fieldDefinitions as $field)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div>
                                                <p class="font-medium text-gray-900 dark:text-white">
                                                    {{ $field->field_label }}
                                                </p>
                                                <p class="text-sm text-gray-500">{{ $field->field_code }}</p>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <flux:badge variant="blue" size="sm">
                                                {{ $field->channel_type }}{{ $field->channel_subtype ? ':' . $field->channel_subtype : '' }}
                                            </flux:badge>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <flux:badge 
                                                variant="{{ $field->field_type_color }}" 
                                                size="sm"
                                            >
                                                {{ $field->field_type }}
                                            </flux:badge>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($field->is_required)
                                                <flux:badge variant="red" size="sm">Required</flux:badge>
                                            @else
                                                <flux:badge variant="gray" size="sm">Optional</flux:badge>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $field->last_verified_at?->diffForHumans() ?? 'Never' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                            No field definitions found
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        {{ $this->fieldDefinitions->links() }}
                    </div>
                </div>
                @break

            @case('mappings')
                {{-- Mappings Tab --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg border">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                Field Mappings
                            </h3>
                            
                            <flux:button variant="primary" icon="plus">
                                Add Mapping
                            </flux:button>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <p class="text-gray-500 text-center py-8">
                            Mapping interface coming soon...
                        </p>
                    </div>
                </div>
                @break

            @case('value-lists')
                {{-- Value Lists Tab --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg border">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                            Value Lists
                        </h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        List
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Channel
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Values
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Last Synced
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($this->valueLists as $valueList)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div>
                                                <p class="font-medium text-gray-900 dark:text-white">
                                                    {{ $valueList->list_name }}
                                                </p>
                                                <p class="text-sm text-gray-500">{{ $valueList->list_code }}</p>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <flux:badge variant="blue" size="sm">
                                                {{ $valueList->channel_type }}{{ $valueList->channel_subtype ? ':' . $valueList->channel_subtype : '' }}
                                            </flux:badge>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ number_format($valueList->values_count) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <flux:badge 
                                                variant="{{ $valueList->sync_status_color }}" 
                                                size="sm"
                                            >
                                                {{ ucfirst($valueList->sync_status) }}
                                            </flux:badge>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $valueList->last_synced_at?->diffForHumans() ?? 'Never' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                            No value lists found
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @break
        @endswitch
    </div>

    {{-- Discovery Modal --}}
    @if($showDiscoveryModal)
        <flux:modal wire:model="showDiscoveryModal" name="discovery-modal">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    Field Discovery Progress
                </h3>
                
                @if($isDiscovering)
                    <div class="flex items-center gap-3 py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                        <span class="text-gray-600 dark:text-gray-400">
                            Discovering field requirements...
                        </span>
                    </div>
                @else
                    @if(isset($discoveryResults['success']) && $discoveryResults['success'] === false)
                        <div class="text-red-600 py-4">
                            <flux:icon name="circle-alert" class="w-5 h-5 inline mr-2" />
                            Discovery failed: {{ $discoveryResults['error'] }}
                        </div>
                    @elseif(isset($discoveryResults['summary']))
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="font-medium">Accounts Processed:</span>
                                    {{ $discoveryResults['summary']['total_accounts'] }}
                                </div>
                                <div>
                                    <span class="font-medium">Successful:</span>
                                    {{ $discoveryResults['summary']['successful'] }}
                                </div>
                                <div>
                                    <span class="font-medium">Fields Discovered:</span>
                                    {{ $discoveryResults['summary']['total_fields'] }}
                                </div>
                                <div>
                                    <span class="font-medium">Value Lists:</span>
                                    {{ $discoveryResults['summary']['total_value_lists'] }}
                                </div>
                            </div>
                            
                            @if(count($discoveryResults['summary']['errors']) > 0)
                                <div class="mt-4">
                                    <h4 class="font-medium text-red-600 mb-2">Errors:</h4>
                                    <ul class="text-sm text-red-600 space-y-1">
                                        @foreach($discoveryResults['summary']['errors'] as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    @endif
                @endif
            </div>
            
            <div class="flex justify-end gap-3 p-6 border-t border-gray-200 dark:border-gray-700">
                <flux:button 
                    wire:click="closeDiscoveryModal" 
                    variant="outline"
                    :disabled="$isDiscovering"
                >
                    Close
                </flux:button>
            </div>
        </flux:modal>
    @endif
</div>
