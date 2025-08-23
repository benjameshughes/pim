{{-- Step 4: Pricing --}}
<div class="max-w-7xl mx-auto">
    <div class="bg-white dark:bg-gray-800 rounded-lg p-8 transition-all duration-500 ease-in-out transform"
         x-transition:enter="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-4">
         
        <div class="flex items-center gap-3 mb-6">
            <div class="flex items-center justify-center w-10 h-10 bg-orange-100 dark:bg-orange-900/20 rounded-lg">
                <flux:icon name="pound-sterling" class="h-5 w-5 text-orange-600 dark:text-orange-400" />
            </div>
            <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">Pricing & Stock</h2>
        </div>
        
        @error('pricing')
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                <p class="text-red-600 text-sm">{{ $message }}</p>
            </div>
        @enderror
        
        <div class="space-y-6">
            @foreach($generated_variants as $index => $variant)
                <div class="bg-orange-50 dark:bg-orange-900/10 border border-orange-200 dark:border-orange-800 rounded-lg p-6 hover:shadow-sm transition-shadow duration-200">
                    <div class="flex items-center gap-2 mb-4">
                        <flux:icon name="tag" class="h-5 w-5 text-orange-600 dark:text-orange-400" />
                        <h5 class="font-semibold text-orange-900 dark:text-orange-100">{{ $variant['sku'] }}</h5>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <flux:field>
                            <flux:label>Retail Price (£)</flux:label>
                            <flux:input 
                                wire:model="variant_pricing.{{ $index }}.retail_price" 
                                type="number" 
                                step="0.01" 
                                tabindex="{{ ($index * 3) + 1 }}"
                                @if($index === 0) x-init="$nextTick(() => $el.focus())" @endif
                            />
                        </flux:field>
                        <flux:field>
                            <flux:label>Cost Price (£)</flux:label>
                            <flux:input 
                                wire:model="variant_pricing.{{ $index }}.cost_price" 
                                type="number" 
                                step="0.01" 
                                tabindex="{{ ($index * 3) + 2 }}"
                            />
                        </flux:field>
                        <flux:field>
                            <flux:label>Stock Quantity</flux:label>
                            <flux:input 
                                wire:model="variant_stock.{{ $index }}.quantity" 
                                type="number" 
                                tabindex="{{ ($index * 3) + 3 }}"
                            />
                        </flux:field>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>