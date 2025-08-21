{{-- 💰 PRICING & STOCK FORM - COLLECTION-POWERED STEP 4 --}}
<div class="space-y-6" wire:ignore.self>

    {{-- Header with Statistics --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-foreground">Pricing & Stock</h3>
            <p class="text-sm text-muted-foreground">Set pricing and stock levels for all variants</p>
        </div>
        
        {{-- Pricing Statistics --}}
        @if($this->pricingStats->get('total_variants') > 0)
            <div class="flex flex-wrap items-center gap-4 text-sm">
                <div class="flex items-center gap-2">
                    <flux:icon name="package" class="w-4 h-4 text-muted-foreground" />
                    <span class="font-medium">{{ $this->pricingStats->get('total_variants') }}</span>
                    <span class="text-muted-foreground">Variants</span>
                </div>
                
                <div class="flex items-center gap-2">
                    <flux:icon name="dollar-sign" class="w-4 h-4 text-muted-foreground" />
                    <span class="font-medium">£{{ number_format($this->pricingStats->get('average_retail_price'), 2) }}</span>
                    <span class="text-muted-foreground">Avg Price</span>
                </div>
                
                <div class="flex items-center gap-2">
                    <flux:icon name="trending-up" class="w-4 h-4 text-muted-foreground" />
                    <span class="font-medium">£{{ number_format($this->pricingStats->get('total_inventory_value'), 2) }}</span>
                    <span class="text-muted-foreground">Total Value</span>
                </div>
            </div>
        @endif
    </div>

    {{-- Settings Panel --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Default Settings --}}
        <div class="bg-muted/30 rounded-lg p-4 space-y-4">
            <h4 class="font-semibold text-foreground flex items-center gap-2">
                <flux:icon name="settings" class="w-4 h-4" />
                Default Settings
            </h4>
            
            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Default Retail Price</flux:label>
                    <flux:input type="number" step="0.01" wire:model.blur="defaultRetailPrice" placeholder="0.00" />
                </flux:field>
                
                <flux:field>
                    <flux:label>Default Stock Level</flux:label>
                    <flux:input type="number" wire:model.live="defaultStockLevel" />
                </flux:field>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>VAT Percentage</flux:label>
                    <flux:input type="number" step="0.1" wire:model.blur="vatPercentage" placeholder="20.0" />
                </flux:field>
                
                <flux:field class="flex items-end">
                    <flux:checkbox wire:model.live="pricesIncludeVat">
                        Prices include VAT
                    </flux:checkbox>
                </flux:field>
            </div>
        </div>

        {{-- Marketplace Settings --}}
        <div class="bg-muted/30 rounded-lg p-4 space-y-4">
            <div class="flex items-center justify-between">
                <h4 class="font-semibold text-foreground flex items-center gap-2">
                    <flux:icon name="store" class="w-4 h-4" />
                    Marketplace Pricing
                </h4>
                <flux:switch wire:model.live="enableMarketplacePricing" />
            </div>
            
            @if($enableMarketplacePricing)
                <div class="space-y-4">
                    <div class="space-y-2">
                        <flux:label class="text-sm">Active Marketplaces</flux:label>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach($availableMarketplaces as $marketplace)
                                @if($marketplace['enabled'])
                                    <flux:checkbox 
                                        wire:model.live="selectedMarketplaces" 
                                        value="{{ $marketplace['id'] }}"
                                    >
                                        {{ $marketplace['name'] }}
                                    </flux:checkbox>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    {{-- Auto-Linking Settings --}}
                    <div class="space-y-3 border-t border-border/50 pt-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <flux:label class="text-sm font-medium">Auto-Link to Marketplaces</flux:label>
                                <p class="text-xs text-muted-foreground">Automatically create marketplace links when saving product</p>
                            </div>
                            <flux:switch wire:model.live="enableAutoLinking" />
                        </div>
                        
                        @if($enableAutoLinking)
                            <div class="space-y-2">
                                <flux:label class="text-xs text-muted-foreground">Select marketplaces for auto-linking:</flux:label>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach($availableMarketplaces as $marketplace)
                                        @if($marketplace['enabled'])
                                            <flux:checkbox 
                                                wire:model.live="autoLinkMarketplaces" 
                                                value="{{ $marketplace['id'] }}"
                                                class="text-sm"
                                            >
                                                <div class="flex items-center gap-2">
                                                    <flux:icon name="link" class="w-3 h-3" />
                                                    {{ $marketplace['name'] }}
                                                </div>
                                            </flux:checkbox>
                                        @endif
                                    @endforeach
                                </div>
                                
                                @if($autoLinkMarketplaces->isNotEmpty())
                                    <div class="flex items-center gap-1 text-xs text-muted-foreground mt-2">
                                        <flux:icon name="info" class="w-3 h-3" />
                                        <span>{{ $autoLinkMarketplaces->count() }} marketplace(s) selected for auto-linking</span>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Bulk Update Actions --}}
    @if($variantPricing->isNotEmpty())
        <div class="bg-card border border-border rounded-lg p-4">
            <h4 class="font-semibold text-foreground mb-4 flex items-center gap-2">
                <flux:icon name="zap" class="w-4 h-4" />
                Bulk Update Options
            </h4>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Pricing Bulk Updates --}}
                <div class="space-y-4">
                    <h5 class="font-medium text-foreground text-sm">Bulk Pricing Updates</h5>
                    <div class="space-y-2">
                        <flux:button 
                            wire:click="bulkUpdatePricing('all')"
                            variant="outline" 
                            size="sm"
                            :disabled="$defaultRetailPrice <= 0"
                        >
                            <flux:icon name="dollar-sign" class="w-4 h-4" />
                            Apply to All Variants (£{{ number_format($defaultRetailPrice, 2) }})
                        </flux:button>
                        
                        <flux:button 
                            wire:click="bulkUpdatePricing('zero_only')"
                            variant="outline" 
                            size="sm"
                            :disabled="$defaultRetailPrice <= 0"
                        >
                            <flux:icon name="target" class="w-4 h-4" />
                            Apply to Zero-Priced Only
                        </flux:button>

                        {{-- Color-specific bulk updates --}}
                        @if($variantPricing->pluck('color')->unique()->count() > 1)
                            <div class="pt-2">
                                <flux:label class="text-xs text-muted-foreground">Apply to Specific Color:</flux:label>
                                <div class="flex flex-wrap gap-1 mt-1">
                                    @foreach($variantPricing->pluck('color')->unique() as $color)
                                        <flux:button 
                                            wire:click="bulkUpdatePricing('color', '{{ $color }}')"
                                            variant="ghost" 
                                            size="xs"
                                            :disabled="$defaultRetailPrice <= 0"
                                        >
                                            {{ $color }}
                                        </flux:button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Stock Bulk Updates --}}
                <div class="space-y-4">
                    <h5 class="font-medium text-foreground text-sm">Bulk Stock Updates</h5>
                    <div class="space-y-2">
                        <flux:button 
                            wire:click="bulkUpdateStock('all')"
                            variant="outline" 
                            size="sm"
                        >
                            <flux:icon name="package" class="w-4 h-4" />
                            Apply to All Variants ({{ $defaultStockLevel }})
                        </flux:button>
                        
                        <flux:button 
                            wire:click="bulkUpdateStock('zero_only')"
                            variant="outline" 
                            size="sm"
                        >
                            <flux:icon name="package-plus" class="w-4 h-4" />
                            Apply to Zero-Stock Only
                        </flux:button>

                        {{-- Color-specific stock updates --}}
                        @if($variantPricing->pluck('color')->unique()->count() > 1)
                            <div class="pt-2">
                                <flux:label class="text-xs text-muted-foreground">Apply to Specific Color:</flux:label>
                                <div class="flex flex-wrap gap-1 mt-1">
                                    @foreach($variantPricing->pluck('color')->unique() as $color)
                                        <flux:button 
                                            wire:click="bulkUpdateStock('color', '{{ $color }}')"
                                            variant="ghost" 
                                            size="xs"
                                        >
                                            {{ $color }}
                                        </flux:button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Reset Actions --}}
            <div class="flex items-center justify-between pt-4 border-t border-border mt-4">
                <flux:button 
                    wire:click="resetToDefaults"
                    variant="ghost" 
                    size="sm"
                >
                    <flux:icon name="refresh-ccw" class="w-4 h-4" />
                    Reset to Defaults
                </flux:button>
                
                @if($this->detectEditMode(collect($existingData->get('generated_variants', []))->toArray()))
                    <div class="text-xs text-muted-foreground bg-muted px-2 py-1 rounded">
                        <flux:icon name="info" class="w-3 h-3 inline mr-1" />
                        Edit Mode: Existing prices preserved
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Variant Pricing Table --}}
    @if($variantPricing->isNotEmpty())
        <div class="bg-card border border-border rounded-lg overflow-hidden">
            <div class="p-4 border-b border-border">
                <h4 class="font-semibold text-foreground">Variant Pricing</h4>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-muted/50">
                        <tr class="text-left">
                            <th class="px-4 py-3 text-sm font-medium text-muted-foreground">Variant</th>
                            <th class="px-4 py-3 text-sm font-medium text-muted-foreground">SKU</th>
                            <th class="px-4 py-3 text-sm font-medium text-muted-foreground">Retail Price</th>
                            @if($pricesIncludeVat)
                                <th class="px-4 py-3 text-sm font-medium text-muted-foreground">Excl. VAT</th>
                            @endif
                            <th class="px-4 py-3 text-sm font-medium text-muted-foreground">Stock</th>
                            @if($enableMarketplacePricing)
                                @foreach($selectedMarketplaces as $marketplace)
                                    <th class="px-4 py-3 text-sm font-medium text-muted-foreground">
                                        {{ $availableMarketplaces->firstWhere('id', $marketplace)['name'] ?? $marketplace }}
                                    </th>
                                @endforeach
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($variantPricing as $pricing)
                            <tr class="hover:bg-muted/30 transition-colors">
                                {{-- Variant Info --}}
                                <td class="px-4 py-3">
                                    <div>
                                        <div class="font-medium text-foreground text-sm flex items-center gap-2">
                                            {{ $pricing['title'] }}
                                            @if(isset($pricing['existing_variant']) && $pricing['existing_variant'])
                                                <flux:badge size="sm" variant="outline" class="text-xs">
                                                    <flux:icon name="edit" class="w-3 h-3" />
                                                    Existing
                                                </flux:badge>
                                            @endif
                                        </div>
                                        <div class="text-xs text-muted-foreground">
                                            {{ $pricing['color'] }} • {{ $pricing['width'] }}×{{ $pricing['drop'] }}
                                        </div>
                                    </div>
                                </td>
                                
                                {{-- SKU --}}
                                <td class="px-4 py-3">
                                    <code class="text-xs bg-muted px-2 py-1 rounded">{{ $pricing['sku'] }}</code>
                                </td>
                                
                                {{-- Retail Price --}}
                                <td class="px-4 py-3">
                                    <flux:input 
                                        type="number" 
                                        step="0.01" 
                                        wire:model.blur="variantPricing.{{ $loop->index }}.retail_price"
                                        size="sm"
                                        class="w-24"
                                        placeholder="0.00"
                                    />
                                </td>
                                
                                {{-- VAT Exclusive Price --}}
                                @if($pricesIncludeVat)
                                    <td class="px-4 py-3 text-sm text-muted-foreground">
                                        £{{ number_format($pricing['retail_price'] / (1 + $vatPercentage/100), 2) }}
                                    </td>
                                @endif
                                
                                {{-- Stock Level --}}
                                <td class="px-4 py-3">
                                    <flux:input 
                                        type="number" 
                                        wire:model.live="variantPricing.{{ $loop->index }}.stock_level"
                                        size="sm"
                                        class="w-16"
                                    />
                                </td>
                                
                                {{-- Marketplace Pricing --}}
                                @if($enableMarketplacePricing)
                                    @foreach($selectedMarketplaces as $marketplace)
                                        <td class="px-4 py-3">
                                            <flux:input 
                                                type="number" 
                                                step="0.01" 
                                                wire:model.blur="variantPricing.{{ $loop->index }}.marketplace_pricing.{{ $marketplace }}"
                                                size="sm"
                                                class="w-24"
                                                placeholder="0.00"
                                            />
                                        </td>
                                    @endforeach
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        {{-- Empty State --}}
        <div class="text-center py-12 border-2 border-dashed border-border rounded-lg bg-muted/30">
            <flux:icon name="package-x" class="w-12 h-12 text-muted-foreground mx-auto mb-4" />
            <h3 class="text-lg font-semibold text-foreground mb-2">No Variants to Price</h3>
            <p class="text-muted-foreground mb-4">Please complete the variant generation step first</p>
        </div>
    @endif

    {{-- Marketplace Statistics --}}
    @if($enableMarketplacePricing && $selectedMarketplaces->isNotEmpty() && $variantPricing->isNotEmpty())
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($this->marketplaceStats as $stats)
                <div class="bg-card border border-border rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <flux:icon name="store" class="w-4 h-4 text-primary" />
                        <h4 class="font-semibold text-foreground">{{ $stats['name'] }}</h4>
                    </div>
                    
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Variants:</span>
                            <span class="font-medium">{{ $stats['variant_count'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Avg Price:</span>
                            <span class="font-medium">£{{ number_format($stats['average_price'], 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Range:</span>
                            <span class="font-medium">£{{ number_format($stats['min_price'], 2) }} - £{{ number_format($stats['max_price'], 2) }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Validation Errors --}}
    @if($validationErrors->isNotEmpty())
        <div class="bg-destructive/10 border border-destructive/20 rounded-lg p-4 space-y-2">
            <div class="flex items-center gap-2">
                <flux:icon name="alert-circle" class="w-5 h-5 text-destructive" />
                <h4 class="font-semibold text-destructive">Pricing Errors</h4>
            </div>
            <ul class="list-disc list-inside space-y-1 text-sm text-destructive">
                @foreach($validationErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Completion Summary --}}
    @if($variantPricing->isNotEmpty() && $this->pricingStats->get('total_variants') > 0)
        <div class="bg-primary/10 border border-primary/20 rounded-lg p-4">
            <div class="flex items-center gap-2 mb-3">
                <flux:icon name="check-circle" class="w-5 h-5 text-primary" />
                <h4 class="font-semibold text-primary">Pricing Complete</h4>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <div class="font-medium">{{ $this->pricingStats->get('total_variants') }} Variants</div>
                    <div class="text-muted-foreground">Ready to create</div>
                </div>
                <div>
                    <div class="font-medium">£{{ number_format($this->pricingStats->get('total_inventory_value'), 2) }}</div>
                    <div class="text-muted-foreground">Total value</div>
                </div>
                <div>
                    <div class="font-medium">{{ $this->pricingStats->get('variants_in_stock') }}</div>
                    <div class="text-muted-foreground">In stock</div>
                </div>
                <div>
                    <div class="font-medium">{{ $selectedMarketplaces->count() }}</div>
                    <div class="text-muted-foreground">Marketplaces</div>
                </div>
            </div>
        </div>
    @endif

</div>