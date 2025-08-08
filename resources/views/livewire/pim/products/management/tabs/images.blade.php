<!-- Images Tab Content -->
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <flux:heading size="lg">Product Images</flux:heading>
        <flux:subheading class="text-zinc-600 dark:text-zinc-400">
            {{ $product->productImages->count() }} images
        </flux:subheading>
    </div>
    
    <!-- Main Images Section -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
        <div class="border-b border-zinc-200 dark:border-zinc-700 p-4">
            <div class="flex gap-2 flex-wrap">
                <flux:badge variant="outline" class="bg-blue-50 text-blue-700 border-blue-200">
                    Main Images ({{ $product->productImages->where('image_type', 'main')->count() }})
                </flux:badge>
                <flux:badge variant="outline">
                    Detail Images ({{ $product->productImages->where('image_type', 'detail')->count() }})
                </flux:badge>
                <flux:badge variant="outline">
                    Lifestyle Images ({{ $product->productImages->where('image_type', 'lifestyle')->count() }})
                </flux:badge>
                <flux:badge variant="outline">
                    Swatch Images ({{ $product->productImages->where('image_type', 'swatch')->count() }})
                </flux:badge>
            </div>
        </div>
        
        <!-- Main Images Uploader -->
        <div class="p-6">
            <livewire:components.image-uploader 
                :model-type="'product'"
                :model-id="$product->id"
                :image-type="'main'"
                :multiple="true"
                :max-files="10"
                :max-size="10240"
                :accept-types="['jpg', 'jpeg', 'png', 'webp']"
                :process-immediately="true"
                :show-preview="true"
                :allow-reorder="true"
                :show-existing-images="true"
                upload-text="Upload main product images"
                wire:key="product-main-images-{{ $product->id }}"
            />
        </div>
    </div>
    
    <!-- Detail Images Section -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
        <div class="p-6">
            <livewire:components.image-uploader 
                :model-type="'product'"
                :model-id="$product->id"
                :image-type="'detail'"
                :multiple="true"
                :max-files="8"
                :max-size="10240"
                :accept-types="['jpg', 'jpeg', 'png', 'webp']"
                :process-immediately="true"
                :show-preview="true"
                :allow-reorder="true"
                :show-existing-images="true"
                upload-text="Upload detailed product images"
                wire:key="product-detail-images-{{ $product->id }}"
            />
        </div>
    </div>
    
    <!-- Lifestyle Images Section -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
        <div class="p-6">
            <livewire:components.image-uploader 
                :model-type="'product'"
                :model-id="$product->id"
                :image-type="'lifestyle'"
                :multiple="true"
                :max-files="6"
                :max-size="10240"
                :accept-types="['jpg', 'jpeg', 'png', 'webp']"
                :process-immediately="true"
                :show-preview="true"
                :allow-reorder="true"
                :show-existing-images="true"
                upload-text="Upload lifestyle product images"
                wire:key="product-lifestyle-images-{{ $product->id }}"
            />
        </div>
    </div>
</div>