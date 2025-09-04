<div class="space-y-6">
    {{-- 🌟 Enhanced Header with Images Facade Stats --}}
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Product Images</h3>
            <div class="flex items-center gap-4 mt-1">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $imageStats['total_images'] }} image(s)
                </p>
                @if($imageStats['color_groups'] > 0)
                    <flux:badge color="blue" size="sm">
                        {{ $imageStats['color_groups'] }} color groups
                    </flux:badge>
                @endif
                @if($imageStats['has_primary'])
                    <flux:badge color="green" size="sm">
                        Has Primary
                    </flux:badge>
                @endif
            </div>
        </div>
        
        <div class="flex items-center gap-2">
            {{-- View Mode Toggle --}}
            <div class="flex items-center bg-gray-100 dark:bg-gray-700 rounded-lg p-1">
                <button wire:click="setViewMode('grid')" 
                        class="px-2 py-1 text-xs rounded {{ $viewMode === 'grid' ? 'bg-white dark:bg-gray-800 shadow-sm' : '' }}">
                    Grid
                </button>
                <button wire:click="setViewMode('color_groups')" 
                        class="px-2 py-1 text-xs rounded {{ $viewMode === 'color_groups' ? 'bg-white dark:bg-gray-800 shadow-sm' : '' }}">
                    Colors
                </button>
            </div>
            
            <flux:button wire:click="toggleVariants" variant="ghost" size="sm" 
                        class="{{ $showVariants ? 'text-blue-600 dark:text-blue-400' : '' }}">
                {{ $showVariants ? 'Hide' : 'Show' }} Variants
            </flux:button>
            
            <flux:button wire:click="refreshImages" variant="ghost" icon="arrow-path" size="sm">
                Refresh
            </flux:button>
            <flux:button wire:navigate href="{{ route('images.index') }}" variant="outline" icon="plus">
                Manage Images
            </flux:button>
        </div>
    </div>

    {{-- 🌟 Enhanced Content with Multiple View Modes --}}
    @if($imageStats['total_images'] > 0)
        
        @if($viewMode === 'color_groups' && count($colorGroups) > 0)
            {{-- 🎨 Color Groups View --}}
            <div class="space-y-8">
                @foreach($colorGroups as $color => $groupData)
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-6 h-6 rounded-full border-2 border-gray-300 dark:border-gray-600 shadow-sm
                                    @if(strtolower($color) === 'black') bg-gray-900
                                    @elseif(strtolower($color) === 'white') bg-white
                                    @elseif(strtolower($color) === 'red') bg-red-500
                                    @elseif(strtolower($color) === 'blue') bg-blue-500
                                    @elseif(strtolower($color) === 'green') bg-green-500
                                    @else bg-gradient-to-br from-orange-400 to-red-500
                                    @endif">
                                </div>
                                <h4 class="text-lg font-medium text-gray-900 dark:text-white">{{ $color }}</h4>
                                <flux:badge color="gray" size="sm">{{ $groupData['count'] }} images</flux:badge>
                                @if($groupData['primary'])
                                    <flux:badge color="green" size="sm">Has Primary</flux:badge>
                                @endif
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                            @foreach($groupData['images'] as $image)
                                <div class="relative group">
                                    <div class="aspect-square">
                                        <img 
                                            src="{{ $image->url }}" 
                                            alt="{{ $image->alt_text ?? $image->filename }}"
                                            class="w-full h-full object-cover rounded-lg"
                                        >
                                    </div>
                                    @if($image->id === $groupData['primary']?->id)
                                        <div class="absolute top-2 right-2">
                                            <flux:badge color="green" size="sm">Primary</flux:badge>
                                        </div>
                                    @endif
                                    <div class="mt-1">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                            {{ $image->title ?? $image->filename }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            {{-- 📷 Grid View (Default) --}}
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                @foreach($product->images as $image)
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="aspect-square">
                            <img 
                                src="{{ $image->url }}" 
                                alt="{{ $image->alt_text ?? $image->filename }}"
                                class="w-full h-full object-cover"
                            >
                        </div>
                        <div class="p-3">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                {{ $image->title ?? $image->filename }}
                            </p>
                            @if($image->alt_text)
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate">
                                    {{ $image->alt_text }}
                                </p>
                            @endif
                            <div class="flex items-center justify-between mt-2">
                                <span class="text-xs text-gray-400 dark:text-gray-500">
                                    {{ number_format($image->size / 1024, 1) }} KB
                                </span>
                                @if($image->id === $imageStats['primary_image']?->id)
                                    <flux:badge color="blue" size="sm">Primary</flux:badge>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- 🎨 Variant Images Section (when enabled) --}}
        @if($showVariants && $product->variants->count() > 0)
            <div class="mt-8 pt-8 border-t border-gray-200 dark:border-gray-700">
                <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Variant Images</h4>
                <div class="space-y-4">
                    @foreach($product->variants as $variant)
                        @php
                            $variantData = $this->getVariantImageData($variant->id);
                        @endphp
                        @if($variantData)
                            <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4">
                                <div class="flex items-start gap-4">
                                    <div class="w-4 h-4 rounded-full border border-gray-200 dark:border-gray-600 shadow-sm mt-1
                                        @if(strtolower($variant->color) === 'black') bg-gray-900
                                        @elseif(strtolower($variant->color) === 'white') bg-white
                                        @elseif(strtolower($variant->color) === 'red') bg-red-500
                                        @elseif(strtolower($variant->color) === 'blue') bg-blue-500
                                        @elseif(strtolower($variant->color) === 'green') bg-green-500
                                        @else bg-gradient-to-br from-orange-400 to-red-500
                                        @endif">
                                    </div>
                                    
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <h5 class="font-medium text-gray-900 dark:text-white">{{ $variant->color }}</h5>
                                            <span class="text-xs font-mono text-gray-500">{{ $variant->sku }}</span>
                                            @if($variantData['has_images'])
                                                <flux:badge color="green" size="sm">
                                                    Images: {{ $variantData['source'] }}
                                                </flux:badge>
                                            @else
                                                <flux:badge color="gray" size="sm">No images</flux:badge>
                                            @endif
                                        </div>
                                        
                                        @if($variantData['display_image'])
                                            <div class="flex items-center gap-4">
                                                <div class="w-16 h-16 rounded-lg overflow-hidden">
                                                    <img 
                                                        src="{{ $variantData['display_image']->url }}" 
                                                        alt="{{ $variantData['display_image']->alt_text ?? $variantData['display_image']->filename }}"
                                                        class="w-full h-full object-cover"
                                                    >
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    <p>Display image: {{ $variantData['display_image']->title ?? $variantData['display_image']->filename }}</p>
                                                    <p class="mt-1">Source: {{ ucfirst(str_replace('_', ' ', $variantData['source'])) }}</p>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

    @else
        <div class="text-center py-12">
            <flux:icon name="photo" class="w-12 h-12 text-gray-400 mx-auto mb-4" />
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">No images</h3>
            <p class="text-gray-500 dark:text-gray-400 mt-2">Upload images using the Product Wizard or Image Library.</p>
            <flux:button wire:navigate href="{{ route('images.index') }}" variant="primary" icon="plus" class="mt-4">
                Add Images
            </flux:button>
        </div>
    @endif
</div>