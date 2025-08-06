<div>
    <x-breadcrumb :items="[
        ['name' => 'Operations'],
        ['name' => 'Bulk Operations'],
        ['name' => 'Title Templates']
    ]" />

    <!-- Header -->
    <div class="mb-8">
        <flux:heading size="xl">Bulk Operations - Title Templates</flux:heading>
        <flux:subheading>Generate optimized product titles using customizable templates</flux:subheading>
    </div>

    <!-- Tab Navigation -->
    <x-route-tabs :tabs="$tabs" class="mb-6">
        <div class="p-6">
            <!-- Flash Messages -->
            @if (session()->has('message'))
                <div class="mb-6 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg p-4">
                    <div class="flex">
                        <flux:icon name="check-circle" class="w-5 h-5 text-emerald-600 mr-2" />
                        <div class="text-sm text-emerald-700 dark:text-emerald-300">
                            {{ session('message') }}
                        </div>
                    </div>
                </div>
            @endif

            @if (session()->has('error'))
                <div class="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                    <div class="flex">
                        <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-600 mr-2" />
                        <div class="text-sm text-red-700 dark:text-red-300">
                            {{ session('error') }}
                        </div>
                    </div>
                </div>
            @endif
            @if($selectedVariantsCount === 0)
                <!-- No Selection State -->
                <div class="text-center py-12">
                    <flux:icon name="layout-grid" class="w-16 h-16 text-zinc-400 mx-auto mb-4" />
                    <flux:heading size="lg" class="text-zinc-600 dark:text-zinc-400 mb-2">No Variants Selected</flux:heading>
                    <flux:subheading class="text-zinc-500 dark:text-zinc-500 mb-4">
                        Select variants from the Overview tab to generate optimized titles
                    </flux:subheading>
                    <flux:button wire:navigate href="{{ route('operations.bulk.overview') }}" variant="primary">
                        <flux:icon name="chart-bar" class="w-4 h-4 mr-2" />
                        Go to Overview
                    </flux:button>
                </div>
            @else
                <!-- Selected Variants Summary -->
                <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg p-4 mb-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-medium text-purple-900 dark:text-purple-100">
                                {{ $selectedVariantsCount }} variants selected
                            </h3>
                            <p class="text-xs text-purple-700 dark:text-purple-300 mt-1">
                                Generate optimized titles for all selected variants
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Template Configuration -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <!-- Template Editor -->
                    <div class="space-y-6">
                        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
                            <flux:heading size="lg" class="mb-4">Title Template</flux:heading>
                            
                            <div class="space-y-4">
                                <flux:field>
                                    <flux:label>Title Template Pattern</flux:label>
                                    <flux:textarea 
                                        wire:model.live="titleTemplate"
                                        rows="3"
                                        placeholder="Enter your title template with variables like [Brand], [ProductName], [Color], [Size]"
                                    />
                                    <flux:description>
                                        Use variables in brackets like [Brand], [ProductName], [Color], [Size], [Material]
                                    </flux:description>
                                </flux:field>

                                <flux:field>
                                    <flux:label>Description Template</flux:label>
                                    <flux:textarea 
                                        wire:model.live="descriptionTemplate"
                                        rows="3"
                                        placeholder="Enter your description template"
                                    />
                                </flux:field>
                            </div>
                        </div>

                        <!-- Marketplace Selection -->
                        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
                            <flux:heading size="lg" class="mb-4">Target Marketplaces</flux:heading>
                            <div class="space-y-2">
                                @foreach($marketplaces as $marketplace)
                                    <label class="flex items-center gap-3">
                                        <flux:checkbox 
                                            wire:model.live="selectedMarketplaces" 
                                            value="{{ $marketplace->id }}"
                                        />
                                        <span class="text-sm font-medium">{{ $marketplace->name }}</span>
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">({{ $marketplace->platform }})</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Preview Panel -->
                    <div class="space-y-6">
                        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
                            <flux:heading size="lg" class="mb-4">Template Preview</flux:heading>
                            
                            @if($previewVariantModel)
                                <div class="space-y-4">
                                    <div class="bg-zinc-50 dark:bg-zinc-700 rounded-lg p-4">
                                        <div class="text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">
                                            Preview for: {{ $previewVariantModel->product->name ?? 'Sample Product' }}
                                        </div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                            SKU: {{ $previewVariantModel->sku }} • 
                                            Color: {{ $previewVariantModel->color ?? 'N/A' }} • 
                                            Size: {{ $previewVariantModel->size ?? 'N/A' }}
                                        </div>
                                    </div>

                                    @foreach($selectedMarketplaces as $marketplaceId)
                                        @php
                                            $marketplace = $marketplaces->find($marketplaceId);
                                            if (!$marketplace) continue;
                                        @endphp
                                        <div class="border border-zinc-200 dark:border-zinc-600 rounded-lg p-4">
                                            <div class="text-sm font-medium text-zinc-600 dark:text-zinc-400 mb-2">
                                                {{ $marketplace->name }}
                                            </div>
                                            <div class="text-sm text-zinc-900 dark:text-zinc-100 font-medium mb-1">
                                                Title: {{ $titleTemplate }}
                                            </div>
                                            <div class="text-xs text-zinc-600 dark:text-zinc-400">
                                                Description: {{ $descriptionTemplate }}
                                            </div>
                                        </div>
                                    @endforeach

                                    <flux:button wire:click="clearPreview" variant="ghost" size="sm">
                                        <flux:icon name="x-mark" class="w-4 h-4 mr-2" />
                                        Clear Preview
                                    </flux:button>
                                </div>
                            @else
                                <div class="text-center py-8 text-zinc-500 dark:text-zinc-400">
                                    <flux:icon name="eye" class="w-12 h-12 mx-auto mb-3 opacity-50" />
                                    <p class="text-sm">Select a variant below to preview the generated title</p>
                                </div>
                            @endif
                        </div>

                        <!-- Available Variables -->
                        <div class="bg-zinc-50 dark:bg-zinc-700 rounded-lg border border-zinc-200 dark:border-zinc-600 p-4">
                            <flux:heading size="md" class="mb-3">Available Variables</flux:heading>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="flex justify-between">
                                    <span class="text-zinc-600 dark:text-zinc-400">[Brand]</span>
                                    <span class="text-zinc-500 dark:text-zinc-500">Product brand</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-zinc-600 dark:text-zinc-400">[ProductName]</span>
                                    <span class="text-zinc-500 dark:text-zinc-500">Product name</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-zinc-600 dark:text-zinc-400">[Color]</span>
                                    <span class="text-zinc-500 dark:text-zinc-500">Variant color</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-zinc-600 dark:text-zinc-400">[Size]</span>
                                    <span class="text-zinc-500 dark:text-zinc-500">Variant size</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-zinc-600 dark:text-zinc-400">[Material]</span>
                                    <span class="text-zinc-500 dark:text-zinc-500">Material type</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-zinc-600 dark:text-zinc-400">[SKU]</span>
                                    <span class="text-zinc-500 dark:text-zinc-500">Variant SKU</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Generate Action -->
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="md" class="mb-1">Ready to Generate</flux:heading>
                            <flux:subheading class="text-zinc-600 dark:text-zinc-400">
                                This will create titles for {{ $selectedVariantsCount }} variants across {{ count($selectedMarketplaces) }} marketplaces
                            </flux:subheading>
                        </div>
                        <flux:button 
                            wire:click="generateTitles"
                            variant="primary"
                            :disabled="empty($selectedMarketplaces) || empty($titleTemplate)"
                        >
                            <flux:icon name="sparkles" class="w-4 h-4 mr-2" />
                            Generate Titles
                        </flux:button>
                    </div>
                </div>

                <!-- Sample Variants for Preview -->
                @if($selectedVariantsCount > 0)
                    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
                        <flux:heading size="md" class="mb-4">Selected Variants (Sample)</flux:heading>
                        <div class="space-y-2 max-h-48 overflow-y-auto">
                            @foreach(array_slice($selectedVariants, 0, 10) as $variantId)
                                @php
                                    $variant = \App\Models\ProductVariant::with('product')->find($variantId);
                                    if (!$variant) continue;
                                @endphp
                                <div class="flex items-center justify-between py-2 px-3 bg-zinc-50 dark:bg-zinc-700 rounded border-l-4 border-purple-400">
                                    <div>
                                        <div class="text-sm font-medium">{{ $variant->product->name }}</div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                            SKU: {{ $variant->sku }} • 
                                            @if($variant->color) Color: {{ $variant->color }} • @endif
                                            @if($variant->size) Size: {{ $variant->size }} @endif
                                        </div>
                                    </div>
                                    <flux:button 
                                        wire:click="previewTitle({{ $variant->id }})"
                                        variant="ghost" 
                                        size="sm"
                                    >
                                        <flux:icon name="eye" class="w-4 h-4 mr-1" />
                                        Preview
                                    </flux:button>
                                </div>
                            @endforeach
                            @if(count($selectedVariants) > 10)
                                <div class="text-center text-sm text-zinc-500 dark:text-zinc-400 py-2">
                                    ... and {{ count($selectedVariants) - 10 }} more variants
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </x-route-tabs>
</div>