<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Product Images</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $product->images->count() }} image(s)</p>
        </div>
        
        <flux:button wire:navigate href="{{ route('dam.index') }}" variant="outline" icon="plus">
            Manage Images
        </flux:button>
    </div>

    @if($product->images->count() > 0)
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
                            @if($image->is_primary)
                                <flux:badge color="blue" size="sm">Primary</flux:badge>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-12">
            <flux:icon name="photo" class="w-12 h-12 text-gray-400 mx-auto mb-4" />
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">No images</h3>
            <p class="text-gray-500 dark:text-gray-400 mt-2">Upload images using the Product Wizard or Image Library.</p>
            <flux:button wire:navigate href="{{ route('dam.index') }}" variant="primary" icon="plus" class="mt-4">
                Add Images
            </flux:button>
        </div>
    @endif
</div>