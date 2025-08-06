<div>
    <x-breadcrumb :items="[
        ['name' => 'Products'],
        ['name' => 'Mirakl Sync']
    ]" />

    <!-- Enhanced Header with Mirakl Branding -->
    <div class="relative mb-8 overflow-hidden rounded-2xl bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-100 dark:from-blue-900 dark:via-indigo-800 dark:to-purple-900">
        <div class="absolute inset-0 bg-gradient-to-r from-blue-100/50 via-transparent to-purple-100/50 dark:from-blue-700/20 dark:via-transparent dark:to-purple-700/20"></div>
        <div class="relative flex items-center justify-between p-8">
            <div>
                <flux:heading size="xl" class="text-blue-800 dark:text-blue-100">Mirakl Connect Sync</flux:heading>
                <flux:subheading class="text-blue-600 dark:text-blue-300">Push your product catalog to Mirakl Connect</flux:subheading>
                
                <!-- Connection Status -->
                <div class="mt-4 flex items-center space-x-4">
                    @if($connectionStatus)
                        @if($connectionStatus['success'])
                            <div class="flex items-center text-sm text-emerald-600 dark:text-emerald-400">
                                <flux:icon name="circle-check" class="mr-2 h-4 w-4" />
                                Connected to Mirakl Connect
                            </div>
                        @else
                            <div class="flex items-center text-sm text-red-600 dark:text-red-400">
                                <flux:icon name="circle-x" class="mr-2 h-4 w-4" />
                                {{ $connectionStatus['message'] }}
                            </div>
                        @endif
                    @endif
                    <flux:button size="sm" variant="ghost" wire:click="testConnection" class="text-blue-600 hover:text-blue-700">
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
                    class="shadow-lg shadow-blue-500/20 border-blue-200 text-blue-600 hover:bg-blue-50 dark:border-blue-700 dark:text-blue-300 dark:hover:bg-blue-800"
                >
                    Sync Selected
                </flux:button>
                <flux:button 
                    variant="primary" 
                    icon="cloud-upload" 
                    wire:click="syncAll"
                    :disabled="$isSyncing"
                    class="shadow-lg shadow-blue-500/30"
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
                    <h3 class="text-lg font-semibold text-zinc-800 dark:text-zinc-200">Products for Mirakl Sync</h3>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Select products to push to your Mirakl Connect account</p>
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
                        <flux:icon name="hash" class="mr-2 inline h-4 w-4" />
                        SKU
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
                        <flux:icon name="cloud-upload" class="mr-2 inline h-4 w-4" />
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
                                    <div class="text-sm font-semibold text-slate-900 dark:text-slate-100 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors duration-200">
                                        {{ $product->name }}
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                        {{ Str::limit($product->description, 60) ?? 'No description' }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-5">
                            <div class="text-sm font-medium text-slate-900 dark:text-slate-100 font-mono bg-blue-50 dark:bg-blue-900/20 px-3 py-1 rounded-lg inline-block border border-blue-200 dark:border-blue-800">
                                {{ $product->parent_sku ?? '-' }}
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
                                icon="cloud-upload" 
                                wire:click="syncSingle({{ $product->id }})"
                                :disabled="$isSyncing"
                                class="text-blue-600 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors duration-200"
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
                                    <flux:icon name="cloud-upload" class="h-8 w-8 text-slate-400" />
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
                <flux:icon name="activity" class="mr-2 inline h-5 w-5" />
                Sync Results
            </h3>
            
            <div class="space-y-4">
                @foreach($syncResults as $productId => $result)
                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                        <h4 class="font-medium text-zinc-900 dark:text-zinc-100 mb-2">{{ $result['product_name'] }}</h4>
                        
                        <div class="space-y-2">
                            @foreach($result['results'] as $variantResult)
                                <div class="flex items-center justify-between text-sm">
                                    <span class="font-mono text-zinc-600 dark:text-zinc-400">{{ $variantResult['variant_sku'] }}</span>
                                    @if($variantResult['success'])
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
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>