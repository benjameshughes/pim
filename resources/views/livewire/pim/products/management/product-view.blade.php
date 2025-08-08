<div class="max-w-7xl mx-auto space-y-6">
    <!-- Header -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
        <div class="p-6">
            <x-breadcrumb :items="[
                ['name' => 'Products', 'url' => route('products.index')],
                ['name' => $product->name]
            ]" class="mb-4" />
            
            <div class="flex items-start justify-between">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-4 mb-3">
                        @if($product->productImages->where('image_type', 'main')->first())
                            <img src="{{ Storage::url($product->productImages->where('image_type', 'main')->first()->image_path) }}" 
                                 alt="{{ $product->name }}" 
                                 class="w-16 h-16 object-cover rounded-xl border border-zinc-200 dark:border-zinc-700">
                        @else
                            <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center">
                                <flux:icon name="package" class="h-8 w-8 text-white" />
                            </div>
                        @endif
                        <div>
                            <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-100 font-semibold">
                                {{ $product->name }}
                            </flux:heading>
                            <flux:subheading class="text-zinc-600 dark:text-zinc-400">
                                {{ $product->slug }}
                            </flux:subheading>
                            <div class="flex items-center gap-2 mt-2">
                                @if($product->status === 'active')
                                    <flux:badge variant="outline" class="bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-300 dark:border-emerald-800">
                                        <flux:icon name="circle-check" class="mr-1 h-3 w-3" />
                                        Active
                                    </flux:badge>
                                @elseif($product->status === 'inactive')
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
                                <span class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $product->variants->count() }} variants
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center gap-2">
                    <flux:button variant="outline" icon="pencil" :href="route('products.product.edit', $product)" wire:navigate>
                        Edit Product
                    </flux:button>
                    <flux:button variant="primary" icon="plus" :href="route('products.variants.create') . '?product=' . $product->id" wire:navigate>
                        Add Variant
                    </flux:button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <x-route-tabs :tabs="$tabs" class="mb-6">
        <div class="p-6" wire:cloak>
            @if($this->activeTab === 'overview')
                @include('livewire.pim.products.management.tabs.overview')
            @elseif($this->activeTab === 'variants')
                @include('livewire.pim.products.management.tabs.variants')
            @elseif($this->activeTab === 'images')
                @include('livewire.pim.products.management.tabs.images')
            @elseif($this->activeTab === 'attributes')
                @include('livewire.pim.products.management.tabs.attributes')
            @elseif($this->activeTab === 'sync')
                @include('livewire.pim.products.management.tabs.sync')
            @endif
        </div>
    </x-route-tabs>
</div>