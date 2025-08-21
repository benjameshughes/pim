<div class="space-y-6">
    {{-- ✨ PHOENIX HEADER --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $product->name }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 font-mono">{{ $product->parent_sku }}</p>
        </div>
        
        <div class="flex items-center gap-3">
            <flux:button wire:navigate href="{{ route('products.edit', $product) }}" variant="primary" icon="pencil">
                Edit
            </flux:button>
            
            <flux:dropdown>
                <flux:button variant="ghost" icon="ellipsis-horizontal" />
                
                <flux:menu>
                    <flux:menu.item wire:navigate href="{{ route('variants.create') }}?product={{ $product->id }}" icon="plus">
                        Add Variant
                    </flux:menu.item>
                    <flux:menu.item wire:click="duplicateProduct" icon="document-duplicate">
                        Duplicate Product
                    </flux:menu.item>
                    <flux:menu.separator />
                    <flux:menu.item wire:click="deleteProduct" wire:confirm="Are you sure you want to delete this product and all {{ $product->variants->count() }} variants?" icon="trash" variant="danger">
                        Delete Product
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    {{-- 📑 TAB NAVIGATION --}}
    <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="-mb-px flex space-x-8 overflow-x-auto">
            @foreach($this->productTabs->toArray($product) as $tab)
                <a href="{{ $tab['url'] }}"
                   class="py-2 px-1 border-b-2 font-medium text-sm whitespace-nowrap flex items-center space-x-2 transition-colors
                          {{ $tab['active'] 
                             ? 'border-blue-500 text-blue-600 dark:text-blue-400' 
                             : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:border-gray-600' }}"
                   @if($tab['wireNavigate']) wire:navigate @endif>
                    
                    <flux:icon name="{{ $tab['icon'] }}" class="w-4 h-4" />
                    <span>{{ $tab['label'] }}</span>
                    
                    @if(isset($tab['badge']) && $tab['badge'])
                        <flux:badge 
                            :color="$tab['badgeColor'] ?? 'gray'" 
                            size="sm">
                            {{ $tab['badge'] }}
                        </flux:badge>
                    @endif
                </a>
            @endforeach
        </nav>
    </div>

    {{-- 📑 TAB CONTENT --}}
    <div class="tab-content">
        @switch(request()->route()->getName())
            @case('products.show.variants')
                <livewire:products.product-variants-tab :product="$product" />
                @break
            @case('products.show.marketplace')  
                <livewire:products.product-marketplace :product="$product" />
                @break
            @case('products.show.attributes')
                <livewire:components.attributes-card :model="$product" />
                @break
            @case('products.show.history')
                <livewire:products.product-history :product="$product" />
                @break
            @default
                {{-- Default Overview Tab --}}
                <livewire:products.product-overview :product="$product" />
        @endswitch
    </div>
</div>