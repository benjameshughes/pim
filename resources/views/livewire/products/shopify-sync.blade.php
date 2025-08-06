<div>
    <x-breadcrumb :items="[
        ['name' => 'Products'],
        ['name' => 'Shopify Sync']
    ]" />

    <!-- Enhanced Header with Shopify Branding -->
    <div class="relative mb-8 overflow-hidden rounded-2xl bg-gradient-to-br from-green-50 via-emerald-50 to-teal-100 dark:from-green-900 dark:via-emerald-800 dark:to-teal-900">
        <div class="absolute inset-0 bg-gradient-to-r from-green-100/50 via-transparent to-teal-100/50 dark:from-green-700/20 dark:via-transparent dark:to-teal-700/20"></div>
        <div class="relative flex items-center justify-between p-8">
            <div>
                <flux:heading size="xl" class="text-green-800 dark:text-green-100">Shopify Sync</flux:heading>
                <flux:subheading class="text-green-600 dark:text-green-300">Push your products to Shopify with color-based parent splitting</flux:subheading>
                
                <!-- Connection Status -->
                <div class="mt-4 flex items-center space-x-4">
                    @if($connectionStatus)
                        @if($connectionStatus['success'])
                            <div class="flex items-center text-sm text-emerald-600 dark:text-emerald-400">
                                <flux:icon name="circle-check" class="mr-2 h-4 w-4" />
                                Connected to Shopify
                            </div>
                        @else
                            <div class="flex items-center text-sm text-red-600 dark:text-red-400">
                                <flux:icon name="circle-x" class="mr-2 h-4 w-4" />
                                {{ $connectionStatus['message'] }}
                            </div>
                        @endif
                    @endif
                    <flux:button size="sm" variant="ghost" wire:click="testConnection" class="text-green-600 hover:text-green-700">
                        Test Connection
                    </flux:button>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <flux:button 
                    variant="outline" 
                    icon="refresh-ccw" 
                    wire:click="syncSelected"
                    :disabled="empty($selectedProducts) || $isSyncing"
                    class="shadow-lg shadow-green-500/20 border-green-200 text-green-600 hover:bg-green-50 dark:border-green-700 dark:text-green-300 dark:hover:bg-green-800"
                >
                    Sync Selected
                </flux:button>
                <flux:button 
                    variant="primary" 
                    icon="shopping-bag" 
                    wire:click="syncAll"
                    :disabled="$isSyncing"
                    class="shadow-lg shadow-green-500/30 bg-green-600 hover:bg-green-700"
                >
                    @if($isSyncing)
                        <flux:icon name="loader" class="mr-2 h-4 w-4 animate-spin" />
                        Syncing...
                    @else
                        Sync All
                    @endif
                </flux:button>
            </div>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="mb-6 flex items-center rounded-xl border border-emerald-200 bg-gradient-to-r from-emerald-50 to-green-50 px-6 py-4 shadow-lg shadow-emerald-500/10 dark:border-emerald-800 dark:from-emerald-900/20 dark:to-green-900/20">
            <flux:icon name="circle-check" class="mr-3 h-5 w-5 text-emerald-500" />
            <div class="flex-1">
                <p class="font-medium text-emerald-800 dark:text-emerald-200">Success!</p>
                <p class="text-sm text-emerald-700 dark:text-emerald-300">{{ session('message') }}</p>
            </div>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-6 flex items-center rounded-xl border border-red-200 bg-gradient-to-r from-red-50 to-red-50 px-6 py-4 shadow-lg shadow-red-500/10 dark:border-red-800 dark:from-red-900/20 dark:to-red-900/20">
            <flux:icon name="circle-x" class="mr-3 h-5 w-5 text-red-500" />
            <div class="flex-1">
                <p class="font-medium text-red-800 dark:text-red-200">Error!</p>
                <p class="text-sm text-red-700 dark:text-red-300">{{ session('error') }}</p>
            </div>
        </div>
    @endif

    <!-- Two-Tab System: Export and Import -->
    <div class="mb-6 rounded-xl border border-zinc-200 bg-white shadow-lg shadow-zinc-900/5 dark:border-zinc-700 dark:bg-zinc-800/50">
        <div class="border-b border-zinc-200 dark:border-zinc-700">
            <nav class="-mb-px flex space-x-8 px-6" aria-label="Tabs">
                <button 
                    wire:click="$set('activeTab', 'export')" 
                    class="border-transparent py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap {{ ($activeTab ?? 'export') === 'export' ? 'border-green-500 text-green-600' : 'text-zinc-500 hover:text-zinc-700 hover:border-zinc-300' }}"
                >
                    <flux:icon name="upload" class="mr-2 inline h-4 w-4" />
                    Export to Shopify
                </button>
                <button 
                    wire:click="$set('activeTab', 'import')" 
                    class="border-transparent py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap {{ ($activeTab ?? 'export') === 'import' ? 'border-blue-500 text-blue-600' : 'text-zinc-500 hover:text-zinc-700 hover:border-zinc-300' }}"
                >
                    <flux:icon name="download" class="mr-2 inline h-4 w-4" />
                    Import from Shopify
                </button>
            </nav>
        </div>

        <!-- Export Tab Content -->
        @if(($activeTab ?? 'export') === 'export')
            <div class="p-6">
                <div class="flex items-start">
                    <flux:icon name="info" class="mr-3 h-5 w-5 text-green-500 mt-0.5" />
                    <div>
                        <p class="font-medium text-green-800 dark:text-green-200">Color-Based Parent Splitting</p>
                        <p class="text-sm text-green-700 dark:text-green-300 mt-1">
                            Each product will be split by color into separate Shopify products. For example, "Blackout Blind" with colors [Black, White, Cream] 
                            will create 3 Shopify products: "Blackout Blind - Black", "Blackout Blind - White", and "Blackout Blind - Cream".
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Import Tab Content -->
        @if(($activeTab ?? 'export') === 'import')
            <div class="p-6">
                <div class="flex items-start justify-between">
                    <div class="flex items-start">
                        <flux:icon name="info" class="mr-3 h-5 w-5 text-blue-500 mt-0.5" />
                        <div>
                            <p class="font-medium text-blue-800 dark:text-blue-200">Import Shopify Products</p>
                            <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                                Import existing Shopify products into your Laravel PIM as separate products. This won't affect your existing products.
                                Products will be imported with full attributes, pricing, and barcodes.
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4 ml-6">
                        <div class="flex items-center space-x-2">
                            <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Mode:</label>
                            <flux:select wire:model.live="importMode" class="w-32">
                                <flux:select.option value="preview">Preview</flux:select.option>
                                <flux:select.option value="import">Import</flux:select.option>
                            </flux:select>
                        </div>
                        <div class="flex items-center space-x-2">
                            <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Limit:</label>
                            <flux:input wire:model.live="importLimit" type="number" min="1" max="100" class="w-20" />
                        </div>
                        <flux:button 
                            variant="primary" 
                            icon="download" 
                            wire:click="importFromShopify"
                            :disabled="$isImporting"
                            class="bg-blue-600 hover:bg-blue-700"
                        >
                            @if($isImporting)
                                <flux:icon name="loader" class="mr-2 h-4 w-4 animate-spin" />
                                {{ $importMode === 'preview' ? 'Previewing...' : 'Importing...' }}
                            @else
                                {{ $importMode === 'preview' ? 'Preview Import' : 'Import Products' }}
                            @endif
                        </flux:button>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Search & Filter Section -->
    <div class="mb-6 rounded-xl border border-zinc-200 bg-white p-6 shadow-lg shadow-zinc-900/5 dark:border-zinc-700 dark:bg-zinc-800/50">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-zinc-800 dark:text-zinc-200">Select Products to Sync</h3>
            <flux:badge variant="outline" class="bg-zinc-50 text-zinc-600 border-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:border-zinc-700">
                {{ $products->count() }} of {{ $products->total() }} shown
            </flux:badge>
        </div>
        <div class="flex flex-col gap-4 md:flex-row md:items-end">
            <div class="flex-1">
                <flux:input 
                    wire:model.live.debounce.300ms="search" 
                    type="search" 
                    placeholder="Search products by name or description..."
                    icon="search"
                    class="w-full shadow-sm"
                />
            </div>
            <div class="w-full md:w-48">
                <flux:select wire:model.live="statusFilter" placeholder="Filter by status">
                    <flux:select.option value="">All Status</flux:select.option>
                    <flux:select.option value="active">Active</flux:select.option>
                    <flux:select.option value="inactive">Inactive</flux:select.option>
                    <flux:select.option value="discontinued">Discontinued</flux:select.option>
                </flux:select>
            </div>
        </div>
    </div>

    <!-- Products Table -->
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-lg shadow-zinc-900/5 dark:border-zinc-700 dark:bg-zinc-800/50">
        <div class="bg-gradient-to-r from-zinc-50 to-zinc-100/50 px-6 py-4 dark:from-zinc-800 dark:to-zinc-700/50">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-800 dark:text-zinc-200">Products for Shopify Sync</h3>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Products will be split by color into separate Shopify products</p>
                </div>
                <div class="flex items-center">
                    <flux:checkbox 
                        wire:model.live="selectAll" 
                        wire:click="toggleSelectAll"
                        class="mr-2"
                    />
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Select All</span>
                </div>
            </div>
        </div>
        
        <table class="w-full">
            <thead class="bg-gradient-to-r from-zinc-50 via-zinc-100/30 to-zinc-50 dark:from-zinc-700 dark:via-zinc-800/20 dark:to-zinc-700">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                        Select
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                        <flux:icon name="package" class="mr-2 inline h-4 w-4" />
                        Product
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                        <flux:icon name="palette" class="mr-2 inline h-4 w-4" />
                        Colors
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                        <flux:icon name="layers" class="mr-2 inline h-4 w-4" />
                        Variants
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                        <flux:icon name="activity" class="mr-2 inline h-4 w-4" />
                        Status
                    </th>
                    <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                        <flux:icon name="shopping-bag" class="mr-2 inline h-4 w-4" />
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-zinc-800">
                @forelse ($products as $product)
                    <tr wire:key="product-{{ $product->id }}" class="group hover:bg-zinc-50/80 dark:hover:bg-zinc-700/50 transition-all duration-200">
                        <td class="px-6 py-5">
                            <flux:checkbox 
                                wire:model.live="selectedProducts" 
                                value="{{ $product->id }}"
                            />
                        </td>
                        <td class="px-6 py-5">
                            <div class="flex items-start gap-3">
                                <div class="flex-1">
                                    <div class="text-sm font-semibold text-slate-900 dark:text-slate-100 group-hover:text-green-600 dark:group-hover:text-green-400 transition-colors duration-200">
                                        {{ $product->name }}
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                        {{ Str::limit($product->description, 60) ?? 'No description' }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-5">
                            @php
                                $colors = $product->variants->map(function($v) {
                                    return $v->attributes()->byKey('color')->first()?->attribute_value;
                                })->filter()->unique();
                            @endphp
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-semibold text-green-600 bg-green-100 dark:bg-green-900/30 dark:text-green-400 rounded-full">
                                    {{ $colors->count() }}
                                </span>
                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                    @if($colors->count() > 0)
                                        {{ $colors->take(3)->implode(', ') }}@if($colors->count() > 3)...@endif
                                    @else
                                        Default
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-5">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-semibold text-blue-600 bg-blue-100 dark:bg-blue-900/30 dark:text-blue-400 rounded-full">
                                    {{ $product->variants_count }}
                                </span>
                                <span class="text-xs text-slate-500 dark:text-slate-400">variants</span>
                            </div>
                        </td>
                        <td class="px-6 py-5">
                            @if ($product->status === 'active')
                                <flux:badge variant="outline" class="bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-300 dark:border-emerald-800">
                                    <flux:icon name="circle-check" class="mr-1 h-3 w-3" />
                                    Active
                                </flux:badge>
                            @elseif ($product->status === 'inactive')
                                <flux:badge variant="outline" class="bg-slate-50 text-slate-700 border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600">
                                    <flux:icon name="circle-pause" class="mr-1 h-3 w-3" />
                                    Inactive
                                </flux:badge>
                            @else
                                <flux:badge variant="outline" class="bg-red-50 text-red-700 border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800">
                                    <flux:icon name="circle-x" class="mr-1 h-3 w-3" />
                                    Discontinued
                                </flux:badge>
                            @endif
                        </td>
                        <td class="px-6 py-5 text-right">
                            <flux:button 
                                size="sm" 
                                variant="outline" 
                                icon="shopping-bag" 
                                wire:click="syncSingle({{ $product->id }})"
                                :disabled="$isSyncing"
                                class="text-green-600 hover:text-green-700 hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors duration-200"
                            >
                                Sync Now
                            </flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center justify-center">
                                <div class="w-16 h-16 bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-600 dark:to-slate-700 rounded-2xl flex items-center justify-center mb-4 shadow-sm">
                                    <flux:icon name="shopping-bag" class="h-8 w-8 text-slate-400" />
                                </div>
                                <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-200 mb-2">No products found</h3>
                                <p class="text-sm text-slate-600 dark:text-slate-400 mb-4 max-w-sm">Create some products first or adjust your search filters.</p>
                                <flux:button variant="primary" icon="plus" :href="route('products.create')" wire:navigate>
                                    Create Product
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="mt-6 flex items-center justify-between">
        <div class="text-sm text-slate-600 dark:text-slate-400">
            Showing {{ $products->firstItem() ?? 0 }} to {{ $products->lastItem() ?? 0 }} of {{ $products->total() }} results
        </div>
        <div class="pagination-wrapper">
            {{ $products->links() }}
        </div>
    </div>

    <!-- Clear Results Button -->
    @if(!empty($syncResults))
        <div class="mt-6 flex justify-end">
            <flux:button variant="ghost" size="sm" wire:click="clearResults" class="text-zinc-600">
                Clear Results
            </flux:button>
        </div>
    @endif

    <!-- Sync Results -->
    @if(!empty($syncResults))
        <div class="mt-8 rounded-xl border border-zinc-200 bg-white p-6 shadow-lg shadow-zinc-900/5 dark:border-zinc-700 dark:bg-zinc-800/50">
            <h3 class="text-lg font-semibold text-zinc-800 dark:text-zinc-200 mb-4">
                <flux:icon name="upload" class="mr-2 inline h-5 w-5" />
                Export Results
            </h3>
            
            <div class="space-y-4">
                @foreach($syncResults as $productId => $result)
                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                        <h4 class="font-medium text-zinc-900 dark:text-zinc-100 mb-3">
                            {{ $result['product_name'] }}
                            <span class="text-sm font-normal text-zinc-600 dark:text-zinc-400">
                                ({{ $result['color_groups'] }} color groups, {{ $result['total_variants'] }} total variants)
                            </span>
                        </h4>
                        
                        <div class="space-y-2">
                            @foreach($result['results'] as $colorResult)
                                <div class="flex items-center justify-between text-sm p-2 rounded bg-zinc-50 dark:bg-zinc-800">
                                    <div class="flex items-center gap-3">
                                        <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $colorResult['color'] }}</span>
                                        <span class="text-zinc-500 dark:text-zinc-400">({{ $colorResult['variants_count'] }} variants)</span>
                                        @if($colorResult['success'] && isset($colorResult['shopify_product_id']))
                                            <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full dark:bg-green-900/20 dark:text-green-300">
                                                Shopify ID: {{ $colorResult['shopify_product_id'] }}
                                            </span>
                                        @endif
                                    </div>
                                    @if($colorResult['success'])
                                        <flux:badge variant="outline" class="bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-300 dark:border-emerald-800">
                                            <flux:icon name="circle-check" class="mr-1 h-3 w-3" />
                                            Success
                                        </flux:badge>
                                    @else
                                        <flux:badge variant="outline" class="bg-red-50 text-red-700 border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800">
                                            <flux:icon name="circle-x" class="mr-1 h-3 w-3" />
                                            Failed
                                        </flux:badge>
                                    @endif
                                </div>
                                @if(!$colorResult['success'] && isset($colorResult['error']))
                                    <div class="text-xs text-red-600 dark:text-red-400 ml-4 p-2 bg-red-50 dark:bg-red-900/10 rounded">
                                        {{ $colorResult['error'] }}
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        
                        <!-- Summary for this product -->
                        <div class="mt-3 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                            <div class="flex items-center justify-between text-sm text-zinc-600 dark:text-zinc-400">
                                <span>Success Rate: {{ $result['summary']['success_rate'] }}%</span>
                                <span>{{ $result['summary']['successful'] }}/{{ $result['summary']['total_shopify_products_created'] }} Shopify products created</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Import Results -->
    @if(!empty($importResults) && isset($importResults['results']))
        <div class="mt-8 rounded-xl border border-blue-200 bg-white p-6 shadow-lg shadow-blue-500/10 dark:border-blue-700 dark:bg-blue-800/50">
            <h3 class="text-lg font-semibold text-blue-800 dark:text-blue-200 mb-4">
                <flux:icon name="download" class="mr-2 inline h-5 w-5" />
                Import Results
                @if(isset($importResults['summary']))
                    <span class="text-sm font-normal text-blue-600 dark:text-blue-400">
                        ({{ $importResults['summary']['created'] }} created, {{ $importResults['summary']['skipped'] }} skipped, {{ $importResults['summary']['total_variants'] }} variants)
                    </span>
                @endif
            </h3>
            
            <div class="space-y-3">
                @foreach($importResults['results'] as $shopifyId => $result)
                    <div class="border border-blue-200 dark:border-blue-700 rounded-lg p-4 bg-blue-50/50 dark:bg-blue-900/20">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-medium text-blue-900 dark:text-blue-100">
                                    {{ $result['title'] }}
                                </h4>
                                <div class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                                    Handle: {{ $result['handle'] }} | Status: {{ $result['status'] }} | Variants: {{ $result['variants_count'] }}
                                </div>
                                @if(!empty($result['result']['errors']))
                                    <div class="text-xs text-red-600 dark:text-red-400 mt-1">
                                        Errors: {{ implode(', ', $result['result']['errors']) }}
                                    </div>
                                @endif
                            </div>
                            <div class="text-right">
                                @if($result['result']['action'] === 'created')
                                    <flux:badge variant="outline" class="bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-300 dark:border-emerald-800">
                                        <flux:icon name="circle-check" class="mr-1 h-3 w-3" />
                                        Imported
                                    </flux:badge>
                                @elseif($result['result']['action'] === 'would_create')
                                    <flux:badge variant="outline" class="bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/20 dark:text-blue-300 dark:border-blue-800">
                                        <flux:icon name="eye" class="mr-1 h-3 w-3" />
                                        Would Import
                                    </flux:badge>
                                @else
                                    <flux:badge variant="outline" class="bg-zinc-50 text-zinc-700 border-zinc-200 dark:bg-zinc-700 dark:text-zinc-300 dark:border-zinc-600">
                                        <flux:icon name="circle-minus" class="mr-1 h-3 w-3" />
                                        Skipped
                                    </flux:badge>
                                @endif
                                @if(isset($result['result']['product_id']))
                                    <div class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                        Laravel ID: {{ $result['result']['product_id'] }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>