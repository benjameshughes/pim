<div>
    <x-breadcrumb :items="[
        ['name' => 'Products'],
        ['name' => 'eBay Sync']
    ]" />

    <!-- Flash Messages -->
    @if (session()->has('message'))
        <div class="mb-4 rounded-md bg-green-50 p-4 dark:bg-green-900">
            <div class="flex">
                <flux:icon name="circle-check" class="h-5 w-5 text-green-400" />
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800 dark:text-green-200">
                        {{ session('message') }}
                    </p>
                </div>
            </div>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-4 rounded-md bg-red-50 p-4 dark:bg-red-900">
            <div class="flex">
                <flux:icon name="circle-x" class="h-5 w-5 text-red-400" />
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800 dark:text-red-200">
                        {{ session('error') }}
                    </p>
                </div>
            </div>
        </div>
    @endif

    <!-- Enhanced Header with eBay Branding -->
    <div class="relative mb-8 overflow-hidden rounded-2xl bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-100 dark:from-blue-900 dark:via-indigo-800 dark:to-purple-900">
        <div class="absolute inset-0 bg-gradient-to-r from-blue-100/50 via-transparent to-purple-100/50 dark:from-blue-700/20 dark:via-transparent dark:to-purple-700/20"></div>
        <div class="relative flex items-center justify-between p-8">
            <div>
                <flux:heading size="xl" class="text-blue-800 dark:text-blue-100">eBay Sync</flux:heading>
                <flux:subheading class="text-blue-600 dark:text-blue-300">Export your products to eBay marketplace using the Inventory API</flux:subheading>
                
                <!-- Account Selection -->
                <div class="mt-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            @if($accounts->isNotEmpty())
                                <flux:select wire:model.live="selectedAccountId" placeholder="Select eBay Account" class="min-w-48">
                                    @foreach($accounts as $account)
                                        <flux:select.option value="{{ $account->id }}">
                                            {{ $account->name }} 
                                            <span class="text-xs text-zinc-500">
                                                ({{ ucfirst(strtolower($account->environment)) }})
                                            </span>
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                            @endif
                            
                            <flux:button size="sm" variant="outline" wire:click="showConnectForm" class="text-blue-600 border-blue-200 hover:bg-blue-50">
                                <flux:icon name="plus" class="mr-1 h-4 w-4" />
                                Connect Account
                            </flux:button>
                        </div>
                        
                        @if($selectedAccountId)
                            <flux:button 
                                size="sm" 
                                variant="ghost" 
                                wire:click="removeAccount({{ $selectedAccountId }})"
                                wire:confirm="Are you sure you want to remove this eBay account?"
                                class="text-red-600 hover:text-red-700"
                            >
                                <flux:icon name="trash" class="h-4 w-4" />
                            </flux:button>
                        @endif
                    </div>
                    
                    <!-- Connection Status -->
                    <div class="flex items-center space-x-4">
                        @if($connectionStatus)
                            @if($connectionStatus['success'])
                                <div class="flex items-center text-sm text-emerald-600 dark:text-emerald-400">
                                    <flux:icon name="circle-check" class="mr-2 h-4 w-4" />
                                    {{ $connectionStatus['message'] }}
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
                    icon="building-storefront" 
                    wire:click="syncAll"
                    :disabled="$isSyncing"
                    class="shadow-lg shadow-blue-500/30 bg-blue-600 hover:bg-blue-700"
                >
                    @if($isSyncing)
                        <flux:icon name="spinner" class="animate-spin" />
                        Syncing...
                    @else
                        Sync All
                    @endif
                </flux:button>
            </div>
        </div>
    </div>

    <!-- Account Connection Notice -->
    @if($accounts->isEmpty())
        <div class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900">
            <div class="flex">
                <flux:icon name="information-circle" class="h-5 w-5 text-blue-500" />
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                        No eBay Accounts Connected
                    </h3>
                    <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                        <p>Connect your eBay account to start syncing products. You can connect multiple accounts for different marketplaces.</p>
                        <flux:button size="sm" variant="outline" wire:click="showConnectForm" class="mt-3 text-blue-600 border-blue-200 hover:bg-blue-100">
                            <flux:icon name="plus" class="mr-1 h-4 w-4" />
                            Connect Your First eBay Account
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    @elseif(!$connectionStatus || !$connectionStatus['success'])
        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900">
            <div class="flex">
                <flux:icon name="exclamation-triangle" class="h-5 w-5 text-amber-500" />
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-amber-800 dark:text-amber-200">
                        eBay Connection Issue
                    </h3>
                    <div class="mt-2 text-sm text-amber-700 dark:text-amber-300">
                        <p>{{ $connectionStatus['message'] ?? 'Unable to connect to eBay. Please check your account or try reconnecting.' }}</p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Search and Filters -->
    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
        <flux:input 
            wire:model.live.debounce.300ms="search" 
            placeholder="Search products..." 
            icon="magnifying-glass"
        />
        
        <flux:select wire:model.live="statusFilter" placeholder="Filter by sync status">
            <flux:select.option value="">All Products</flux:select.option>
            <flux:select.option value="synced">Already Synced</flux:select.option>
            <flux:select.option value="not_synced">Not Synced</flux:select.option>
        </flux:select>

        <div class="flex items-center justify-end">
            <flux:button 
                size="sm" 
                variant="ghost" 
                wire:click="clearResults"
                class="text-zinc-500 hover:text-zinc-700"
            >
                Clear Results
            </flux:button>
        </div>
    </div>

    <!-- Sync Results -->
    @if(!empty($syncResults))
        <div class="mb-6 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Sync Results</h3>
            
            @if(isset($syncResults['error']))
                <div class="rounded-md bg-red-50 p-4 dark:bg-red-900">
                    <div class="flex">
                        <flux:icon name="circle-x" class="h-5 w-5 text-red-400" />
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800 dark:text-red-200">Sync Failed</h3>
                            <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                                {{ $syncResults['error'] }}
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div class="rounded-lg bg-blue-50 p-4 dark:bg-blue-900">
                        <div class="text-2xl font-bold text-blue-600 dark:text-blue-300">{{ $syncResults['total_products'] ?? 0 }}</div>
                        <div class="text-sm text-blue-600 dark:text-blue-300">Products Processed</div>
                    </div>
                    <div class="rounded-lg bg-indigo-50 p-4 dark:bg-indigo-900">
                        <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-300">{{ $syncResults['total_variants'] ?? 0 }}</div>
                        <div class="text-sm text-indigo-600 dark:text-indigo-300">Variants Processed</div>
                    </div>
                    <div class="rounded-lg bg-emerald-50 p-4 dark:bg-emerald-900">
                        <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-300">{{ $syncResults['successful_exports'] ?? 0 }}</div>
                        <div class="text-sm text-emerald-600 dark:text-emerald-300">Successfully Exported</div>
                    </div>
                    <div class="rounded-lg bg-red-50 p-4 dark:bg-red-900">
                        <div class="text-2xl font-bold text-red-600 dark:text-red-300">{{ $syncResults['failed_exports'] ?? 0 }}</div>
                        <div class="text-sm text-red-600 dark:text-red-300">Failed Exports</div>
                    </div>
                </div>

                <!-- Successful Exports -->
                @if(!empty($syncResults['exported_items']))
                    <div class="mt-6">
                        <h4 class="mb-3 font-medium text-emerald-600 dark:text-emerald-400">Successfully Exported Items</h4>
                        <div class="space-y-2">
                            @foreach($syncResults['exported_items'] as $item)
                                <div class="flex items-center justify-between rounded-lg bg-emerald-50 p-3 dark:bg-emerald-900">
                                    <div>
                                        <span class="font-medium text-emerald-700 dark:text-emerald-300">{{ $item['sku'] }}</span>
                                        <span class="ml-2 text-sm text-emerald-600 dark:text-emerald-400">{{ $item['product'] }}</span>
                                    </div>
                                    @if(!empty($item['listing_id']))
                                        <span class="text-xs text-emerald-600 dark:text-emerald-400">Listing: {{ $item['listing_id'] }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Error Details -->
                @if(!empty($syncResults['errors']))
                    <div class="mt-6">
                        <h4 class="mb-3 font-medium text-red-600 dark:text-red-400">Export Errors</h4>
                        <div class="space-y-2">
                            @foreach($syncResults['errors'] as $error)
                                <div class="rounded-lg bg-red-50 p-3 dark:bg-red-900">
                                    <div class="font-medium text-red-700 dark:text-red-300">{{ $error['sku'] }}</div>
                                    <div class="text-sm text-red-600 dark:text-red-400">{{ $error['product'] }}</div>
                                    <div class="mt-1 text-sm text-red-500 dark:text-red-500">{{ $error['error'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif
        </div>
    @endif

    <!-- Products Table -->
    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <div class="border-b border-zinc-200 bg-zinc-50 px-6 py-3 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <flux:checkbox 
                        wire:model="selectAll" 
                        wire:click="toggleSelectAll" 
                    />
                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                        {{ $products->total() }} Products
                        @if(!empty($selectedProducts))
                            ({{ count($selectedProducts) }} selected)
                        @endif
                    </span>
                </div>
            </div>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse($products as $product)
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <flux:checkbox 
                                value="{{ $product->id }}" 
                                wire:model="selectedProducts" 
                            />
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $product->name }}
                                </div>
                                <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                    SKU: {{ $product->parent_sku }} • {{ $product->variants->count() }} variants
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-2">
                            <!-- Sync Status -->
                            @php
                                $isSynced = $product->variants->some(function($variant) {
                                    return $variant->marketplaceVariants->some(function($mv) {
                                        return $mv->marketplace->platform === 'ebay';
                                    });
                                });
                            @endphp
                            
                            @if($isSynced)
                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">
                                    <flux:icon name="circle-check" class="mr-1 h-3 w-3" />
                                    Synced
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-medium text-zinc-800 dark:bg-zinc-700 dark:text-zinc-200">
                                    <flux:icon name="minus-circle" class="mr-1 h-3 w-3" />
                                    Not Synced
                                </span>
                            @endif
                            
                            <flux:button 
                                size="sm" 
                                variant="ghost" 
                                icon="building-storefront" 
                                wire:click="syncProduct({{ $product->id }})"
                                :disabled="$isSyncing"
                            >
                                Sync
                            </flux:button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-6 py-12 text-center">
                    <flux:icon name="cube" class="mx-auto h-12 w-12 text-zinc-400" />
                    <h3 class="mt-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">No products found</h3>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        @if($search)
                            No products match your search criteria.
                        @else
                            Get started by creating your first product.
                        @endif
                    </p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Pagination -->
    @if($products->hasPages())
        <div class="mt-6">
            {{ $products->links() }}
        </div>
    @endif
    
    <!-- Connect Account Modal -->
    @if($showConnectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-zinc-800">
                <div class="mb-4 flex items-center justify-between">
                    <flux:heading size="lg">Connect eBay Account</flux:heading>
                    <flux:button size="sm" variant="ghost" wire:click="hideConnectForm">
                        <flux:icon name="x" class="h-4 w-4" />
                    </flux:button>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <flux:label for="account-name">Account Name (Optional)</flux:label>
                        <flux:input 
                            id="account-name"
                            wire:model="newAccountName" 
                            placeholder="e.g., My Store (Sandbox)" 
                            class="mt-1"
                        />
                        <flux:description>Give this account a memorable name. If left blank, we'll use your eBay username.</flux:description>
                    </div>
                    
                    <div class="rounded-lg bg-blue-50 p-4 dark:bg-blue-900">
                        <div class="flex">
                            <flux:icon name="information-circle" class="h-5 w-5 text-blue-500" />
                            <div class="ml-3 text-sm text-blue-700 dark:text-blue-300">
                                <p><strong>Environment:</strong> {{ config('services.ebay.environment') }}</p>
                                <p class="mt-1">You'll be redirected to eBay to authorize access to your account. This allows us to manage your inventory and listings securely.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end space-x-3">
                    <flux:button variant="ghost" wire:click="hideConnectForm" :disabled="$isConnecting">
                        Cancel
                    </flux:button>
                    <flux:button variant="primary" wire:click="connectNewAccount" :disabled="$isConnecting">
                        @if($isConnecting)
                            <flux:icon name="spinner" class="animate-spin mr-2 h-4 w-4" />
                            Connecting...
                        @else
                            <flux:icon name="building-storefront" class="mr-2 h-4 w-4" />
                            Connect to eBay
                        @endif
                    </flux:button>
                </div>
            </div>
        </div>
    @endif
</div>