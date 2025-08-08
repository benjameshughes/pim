<x-layouts.app.sidebar>
    <x-page-template 
        title="{{ $product->name }}"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Products', 'href' => route('products.index')],
            ['label' => $product->name, 'current' => true]
        ]"
        :actions="[
            [
                'type' => 'link',
                'label' => 'Edit Product',
                'href' => route('products.edit', $product),
                'variant' => 'primary',
                'icon' => 'pencil',
                'wire:navigate' => true
            ],
            [
                'type' => 'link',
                'label' => 'Create Variant',
                'href' => route('products.variants.create-for-product', $product),
                'variant' => 'outline',
                'icon' => 'plus',
                'wire:navigate' => true
            ],
            [
                'type' => 'link',
                'label' => 'Delete Product',
                'href' => route('products.delete', $product),
                'variant' => 'negative',
                'icon' => 'trash',
                'wire:navigate' => true
            ]
        ]"
    >
        <x-slot:icon>
            <flux:icon name="cube" class="h-6 w-6 text-white" />
        </x-slot:icon>

        <x-slot:subtitle>
            @if($product->parent_sku)
                SKU: {{ $product->parent_sku }}
            @else
                Product Details
            @endif
        </x-slot:subtitle>

        <x-slot:stats>
            <x-stats-grid>
                <x-stats-card 
                    title="Status"
                    :value="ucfirst($product->status)"
                    :color="$product->status === 'active' ? 'green' : ($product->status === 'draft' ? 'yellow' : 'red')"
                    icon="check-circle"
                />
                <x-stats-card 
                    title="Variants"
                    :value="$product->variants->count()"
                    icon="squares-2x2"
                    color="purple"
                    :href="$product->variants->count() > 0 ? route('products.variants.index', ['product' => $product->id]) : null"
                />
                <x-stats-card 
                    title="Images"
                    :value="$product->productImages->count()"
                    icon="photo"
                    color="blue"
                />
                <x-stats-card 
                    title="Categories"
                    :value="$product->categories->count()"
                    icon="tag"
                    color="indigo"
                />
            </x-stats-grid>
        </x-slot:stats>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            {{-- Main Product Information --}}
            <div class="xl:col-span-2 space-y-6">
                {{-- Product Images --}}
                @if($product->productImages->count() > 0)
                    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">
                            Product Images
                        </h3>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                            @foreach($product->productImages->take(8) as $image)
                                <div class="aspect-square rounded-lg overflow-hidden bg-zinc-100 dark:bg-zinc-800">
                                    <img 
                                        src="{{ \Storage::url($image->path) }}" 
                                        alt="{{ $product->name }}"
                                        class="w-full h-full object-cover hover:scale-105 transition-transform cursor-pointer"
                                    >
                                </div>
                            @endforeach
                            @if($product->productImages->count() > 8)
                                <div class="aspect-square rounded-lg bg-zinc-50 dark:bg-zinc-700 flex items-center justify-center">
                                    <div class="text-center">
                                        <flux:icon name="plus" class="h-6 w-6 text-zinc-400 mx-auto mb-1" />
                                        <span class="text-sm text-zinc-500">
                                            +{{ $product->productImages->count() - 8 }} more
                                        </span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Product Description --}}
                @if($product->description)
                    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">
                            Description
                        </h3>
                        <div class="prose prose-sm dark:prose-invert max-w-none">
                            <p class="text-zinc-700 dark:text-zinc-300">
                                {{ $product->description }}
                            </p>
                        </div>
                    </div>
                @endif

                {{-- Product Features --}}
                @if($product->features && count($product->features) > 0)
                    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">
                            Features
                        </h3>
                        <ul class="space-y-2">
                            @foreach($product->features as $feature)
                                <li class="flex items-start gap-2">
                                    <flux:icon name="check" class="h-4 w-4 text-green-500 mt-0.5 flex-shrink-0" />
                                    <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $feature }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Product Details --}}
                @if($product->details && count($product->details) > 0)
                    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">
                            Details
                        </h3>
                        <ul class="space-y-2">
                            @foreach($product->details as $detail)
                                <li class="flex items-start gap-2">
                                    <flux:icon name="information-circle" class="h-4 w-4 text-blue-500 mt-0.5 flex-shrink-0" />
                                    <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $detail }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Product Variants --}}
                @if($product->variants->count() > 0)
                    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
                        <div class="p-6 border-b border-zinc-200 dark:border-zinc-700">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                                    Product Variants ({{ $product->variants->count() }})
                                </h3>
                                <flux:button 
                                    href="{{ route('products.variants.create-for-product', $product) }}"
                                    variant="outline"
                                    size="sm"
                                    icon="plus"
                                    wire:navigate
                                >
                                    Add Variant
                                </flux:button>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-zinc-50 dark:bg-zinc-900">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">
                                            Variant
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">
                                            SKU
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">
                                            Color/Size
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">
                                            Price
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">
                                            Barcodes
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-zinc-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-zinc-800 divide-y divide-zinc-200 dark:divide-zinc-700">
                                    @foreach($product->variants->take(10) as $variant)
                                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center gap-3">
                                                    @php $image = $variant->variantImages->first(); @endphp
                                                    @if($image)
                                                        <img src="{{ \Storage::url($image->path) }}" alt="{{ $variant->name }}" class="w-8 h-8 rounded object-cover">
                                                    @else
                                                        <div class="w-8 h-8 bg-zinc-200 dark:bg-zinc-700 rounded flex items-center justify-center">
                                                            <flux:icon name="photo" class="h-4 w-4 text-zinc-500" />
                                                        </div>
                                                    @endif
                                                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                        {{ $variant->name ?: 'Unnamed Variant' }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-sm text-zinc-500 font-mono">
                                                    {{ $variant->sku ?: '—' }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center gap-2">
                                                    @if($variant->color)
                                                        <flux:badge variant="neutral">{{ $variant->color }}</flux:badge>
                                                    @endif
                                                    @if($variant->size)
                                                        <flux:badge variant="neutral">{{ $variant->size }}</flux:badge>
                                                    @endif
                                                    @if(!$variant->color && !$variant->size)
                                                        <span class="text-sm text-zinc-400">—</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($variant->pricing->count() > 0)
                                                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                        £{{ number_format($variant->pricing->first()->selling_price ?? 0, 2) }}
                                                    </span>
                                                @else
                                                    <span class="text-sm text-zinc-400">No price</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-sm text-zinc-500">
                                                    {{ $variant->barcodes->count() }} barcode(s)
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <div class="flex items-center justify-end gap-1">
                                                    <flux:button 
                                                        href="{{ route('products.variants.view', $variant) }}"
                                                        variant="ghost"
                                                        size="sm"
                                                        icon="eye"
                                                        wire:navigate
                                                        aria-label="View variant"
                                                    />
                                                    <flux:button 
                                                        href="{{ route('products.variants.edit', $variant) }}"
                                                        variant="ghost"
                                                        size="sm"
                                                        icon="pencil"
                                                        wire:navigate
                                                        aria-label="Edit variant"
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($product->variants->count() > 10)
                            <div class="px-6 py-4 bg-zinc-50 dark:bg-zinc-900 border-t border-zinc-200 dark:border-zinc-700">
                                <flux:button 
                                    href="{{ route('products.variants.index', ['product' => $product->id]) }}"
                                    variant="outline"
                                    size="sm"
                                    wire:navigate
                                >
                                    View All {{ $product->variants->count() }} Variants
                                </flux:button>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Sidebar Information --}}
            <div class="space-y-6">
                {{-- Product Information --}}
                <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">
                        Product Information
                    </h3>
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-zinc-500">Status</dt>
                            <dd class="mt-1">
                                <flux:badge :variant="$product->status === 'active' ? 'positive' : ($product->status === 'draft' ? 'warning' : 'negative')">
                                    {{ ucfirst($product->status) }}
                                </flux:badge>
                            </dd>
                        </div>
                        @if($product->parent_sku)
                            <div>
                                <dt class="text-sm font-medium text-zinc-500">Parent SKU</dt>
                                <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100 font-mono">
                                    {{ $product->parent_sku }}
                                </dd>
                            </div>
                        @endif
                        @if($product->slug)
                            <div>
                                <dt class="text-sm font-medium text-zinc-500">Slug</dt>
                                <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100 font-mono">
                                    {{ $product->slug }}
                                </dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-sm font-medium text-zinc-500">Created</dt>
                            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                {{ $product->created_at->format('M j, Y g:i A') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-zinc-500">Last Updated</dt>
                            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                {{ $product->updated_at->format('M j, Y g:i A') }}
                            </dd>
                        </div>
                    </dl>
                </div>

                {{-- Categories --}}
                @if($product->categories->count() > 0)
                    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">
                            Categories
                        </h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach($product->categories as $category)
                                <flux:badge variant="neutral">
                                    {{ $category->name }}
                                </flux:badge>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Quick Actions --}}
                <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">
                        Quick Actions
                    </h3>
                    <div class="space-y-3">
                        <flux:button 
                            href="{{ route('products.variants.create-for-product', $product) }}"
                            variant="outline"
                            size="sm"
                            icon="plus"
                            wire:navigate
                            class="w-full justify-start"
                        >
                            Add Variant
                        </flux:button>
                        <flux:button 
                            href="{{ route('images.index', ['product' => $product->id]) }}"
                            variant="outline"
                            size="sm"
                            icon="photo"
                            wire:navigate
                            class="w-full justify-start"
                        >
                            Manage Images
                        </flux:button>
                        <flux:button 
                            href="{{ route('pricing.index', ['product' => $product->id]) }}"
                            variant="outline"
                            size="sm"
                            icon="currency-dollar"
                            wire:navigate
                            class="w-full justify-start"
                        >
                            Manage Pricing
                        </flux:button>
                        @if($product->variants->count() > 0)
                            <flux:button 
                                href="{{ route('barcodes.index', ['product' => $product->id]) }}"
                                variant="outline"
                                size="sm"
                                icon="qr-code"
                                wire:navigate
                                class="w-full justify-start"
                            >
                                Manage Barcodes
                            </flux:button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </x-page-template>
</x-layouts.app.sidebar>