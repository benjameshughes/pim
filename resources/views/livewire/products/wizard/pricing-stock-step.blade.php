<div class="p-4 space-y-4">
    {{-- Step Header --}}
    <div class="border-b border-gray-200 dark:border-gray-700 pb-3">
        <div class="flex items-center gap-3">
            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-green-100 dark:bg-green-900/20">
                <flux:icon name="currency-dollar" class="w-4 h-4 text-green-600 dark:text-green-400" />
            </div>
            <div>
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">
                    Pricing & Stock
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Set pricing and stock levels for your product variants (optional).
                </p>
            </div>
        </div>
    </div>

    {{-- Quick Pricing Setup --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Default Pricing --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-2 mb-4">
                <flux:icon name="tag" class="w-5 h-5 text-green-500" />
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Default Pricing</h3>
            </div>
            
            <div class="space-y-4">
                <flux:field>
                    <flux:label>Retail Price (£)</flux:label>
                    <flux:input 
                        type="number"
                        step="0.01"
                        placeholder="0.00"
                        class="text-lg"
                    />
                    <flux:description>Base price for all variants</flux:description>
                </flux:field>
                
                <flux:field>
                    <flux:checkbox>
                        Apply to all variants automatically
                    </flux:checkbox>
                </flux:field>
            </div>
        </div>

        {{-- Stock Management --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-2 mb-4">
                <flux:icon name="cube" class="w-5 h-5 text-blue-500" />
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Stock Settings</h3>
            </div>
            
            <div class="space-y-4">
                <flux:field>
                    <flux:label>Default Stock Level</flux:label>
                    <flux:input 
                        type="number"
                        placeholder="0"
                        class="text-lg"
                    />
                    <flux:description>Starting inventory for all variants</flux:description>
                </flux:field>
                
                <flux:field>
                    <flux:checkbox>
                        Track inventory automatically
                    </flux:checkbox>
                </flux:field>
            </div>
        </div>
    </div>

    {{-- Marketplace Pricing --}}
    <div class="bg-gradient-to-r from-purple-50 to-pink-50 dark:from-purple-900/20 dark:to-pink-900/20 rounded-xl border border-purple-200 dark:border-purple-800 p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-purple-100 dark:bg-purple-900/50">
                <flux:icon name="globe-alt" class="w-4 h-4 text-purple-600 dark:text-purple-400" />
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Marketplace Integration</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Set different prices for different sales channels
                </p>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="flex items-center justify-between p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <flux:icon name="shopping-bag" class="w-5 h-5 text-green-500" />
                    <span class="font-medium text-gray-900 dark:text-white">Shopify</span>
                </div>
                <flux:switch size="sm" />
            </div>
            <div class="flex items-center justify-between p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <flux:icon name="truck" class="w-5 h-5 text-blue-500" />
                    <span class="font-medium text-gray-900 dark:text-white">eBay</span>
                </div>
                <flux:switch size="sm" />
            </div>
            <div class="flex items-center justify-between p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <flux:icon name="building-storefront" class="w-5 h-5 text-orange-500" />
                    <span class="font-medium text-gray-900 dark:text-white">Amazon</span>
                </div>
                <flux:switch size="sm" disabled />
            </div>
        </div>
    </div>

    {{-- Completion Summary --}}
    <div class="bg-green-50 dark:bg-green-900/20 rounded-xl border border-green-200 dark:border-green-800 p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-green-100 dark:bg-green-900/50">
                <flux:icon name="check-circle" class="w-4 h-4 text-green-600 dark:text-green-400" />
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Almost Done!</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Review your product setup before creating
                </p>
            </div>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
            <div class="p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="text-2xl font-bold text-green-600 dark:text-green-400">1</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Product</div>
            </div>
            <div class="p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">0</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Variants</div>
            </div>
            <div class="p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">0</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Images</div>
            </div>
            <div class="p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="text-2xl font-bold text-green-600 dark:text-green-400">£0</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Est. Value</div>
            </div>
        </div>
    </div>

    {{-- Advanced Pricing Notice --}}
    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-200 dark:border-blue-800 p-6">
        <div class="flex items-center gap-3">
            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/50">
                <flux:icon name="information-circle" class="w-4 h-4 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Advanced Pricing</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    You can set individual variant pricing after creating your product in the product management section.
                </p>
            </div>
        </div>
    </div>

</div>