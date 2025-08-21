<div class="p-6 space-y-6">
    {{-- Simple Header --}}
    <div>
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Product Images</h2>
        <p class="text-sm text-gray-600 dark:text-gray-400">Upload images for your product</p>
    </div>

    {{-- Image Count --}}
    @if($productImages->isNotEmpty())
        <div class="text-sm text-gray-600">
            {{ $productImages->count() }} image(s) uploaded
            @if($this->imageStats['has_primary_image']) • Primary image set @endif
        </div>
    @endif

    {{-- Upload Options --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- Upload New Images --}}
        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
            @if($isUploading)
                <div class="animate-spin w-6 h-6 border-2 border-blue-600 border-t-transparent rounded-full mx-auto mb-2"></div>
                <p class="text-sm text-gray-600">Uploading...</p>
            @else
                <input 
                    type="file" 
                    wire:model="newProductImages" 
                    multiple 
                    accept="image/*"
                    class="hidden"
                    id="imageUpload"
                />
                <label for="imageUpload" class="cursor-pointer">
                    <flux:icon name="photo" class="w-8 h-8 text-gray-400 mx-auto mb-2" />
                    <p class="text-sm text-gray-600 mb-2">Upload New Images</p>
                    <flux:button variant="primary" size="sm" type="button">
                        <flux:icon.upload class="w-4 h-4 mr-1" />
                        Choose Files
                    </flux:button>
                </label>
            @endif
        </div>

        {{-- Link Existing Images from DAM --}}
        <div class="border-2 border-dashed border-blue-300 rounded-lg p-6 text-center bg-blue-50 dark:bg-blue-900/20">
            <flux:icon name="link" class="w-8 h-8 text-blue-500 mx-auto mb-2" />
            <p class="text-sm text-blue-700 dark:text-blue-300 mb-2">Link from Image Library</p>
            <flux:button 
                variant="outline" 
                size="sm" 
                type="button"
                wire:click="$dispatch('open-image-selector', { targetType: 'product', targetId: {{ $product?->id ?? 0 }}, options: { maxSelection: 10, allowMultiple: true, setPrimaryOnSingle: true } })"
                :disabled="!$product"
            >
                <flux:icon.photo class="w-4 h-4 mr-1" />
                Browse Library
            </flux:button>
            @if(!$product)
                <p class="text-xs text-gray-500 mt-2">Available after product is created</p>
            @endif
        </div>
    </div>

    {{-- Uploaded Images Grid --}}
    @if($productImages->isNotEmpty())
        <div class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                <flux:icon name="photo" class="w-5 h-5" />
                Product Images ({{ $productImages->count() }})
            </h3>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                @foreach($productImages as $image)
                    <div class="relative group bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm hover:shadow-md transition-all duration-200">
                        {{-- Primary Badge --}}
                        @if((is_array($image) && ($image['is_primary'] ?? false)) || (!is_array($image) && $image->is_primary))
                            <div class="absolute top-2 left-2 bg-yellow-500 text-white text-xs px-2 py-1 rounded-full font-medium flex items-center gap-1 z-20 shadow-lg">
                                <flux:icon name="star" class="w-3 h-3" />
                                Primary
                            </div>
                        @endif

                        {{-- Image Preview --}}
                        <div class="aspect-square bg-gray-100 dark:bg-gray-700 flex items-center justify-center relative overflow-hidden">
                            @php
                                $imageUrl = is_array($image) ? ($image['url'] ?? '') : $image->url;
                                $imageFilename = is_array($image) ? ($image['filename'] ?? 'Image') : $image->filename;
                            @endphp
                            @if($imageUrl)
                                <img 
                                    src="{{ $imageUrl }}" 
                                    alt="{{ $imageFilename }}" 
                                    class="w-full h-full object-cover transition-transform duration-200 group-hover:scale-105" 
                                    loading="lazy"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                >
                                <div class="hidden w-full h-full bg-gray-200 dark:bg-gray-600 items-center justify-center">
                                    <flux:icon name="photo" class="w-8 h-8 text-gray-400" />
                                </div>
                            @else
                                <flux:icon name="photo" class="w-8 h-8 text-gray-400" />
                            @endif
                        </div>

                        {{-- Image Info --}}
                        <div class="p-3 space-y-2">
                            <p class="text-xs font-medium text-gray-900 dark:text-white truncate" title="{{ $imageFilename }}">
                                {{ $imageFilename }}
                            </p>
                            @php
                                $imageSize = is_array($image) ? ($image['size'] ?? null) : $image->size;
                            @endphp
                            @if($imageSize)
                                <p class="text-xs text-gray-600 dark:text-gray-400">
                                    {{ number_format($imageSize / 1024, 1) }}KB
                                </p>
                            @endif
                        </div>

                        {{-- Hover Actions --}}
                        <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-center justify-center gap-2 z-10">
                            @php
                                $imageId = is_array($image) ? ($image['id'] ?? '') : $image->id;
                                $isPrimary = is_array($image) ? ($image['is_primary'] ?? false) : $image->is_primary;
                            @endphp
                            @if(!$isPrimary)
                                <flux:button 
                                    wire:click="setPrimaryImage('{{ $imageId }}')"
                                    variant="outline"
                                    size="sm"
                                    class="bg-white/90 text-black hover:bg-white border-white/90"
                                    title="Set as primary image"
                                >
                                    <flux:icon name="star" class="w-3 h-3" />
                                </flux:button>
                            @endif
                            
                            <flux:button 
                                wire:click="removeImage('{{ $imageId }}')"
                                variant="outline"
                                size="sm"
                                class="bg-red-500/90 text-white hover:bg-red-600 border-red-500/90"
                                wire:confirm="Remove this image?"
                                title="Remove image"
                            >
                                <flux:icon name="trash" class="w-3 h-3" />
                            </flux:button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Empty State --}}
    @if($productImages->isEmpty())
        <div class="text-center py-12 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-800/50">
            <flux:icon name="photo" class="w-16 h-16 text-gray-400 mx-auto mb-4" />
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">No Images Yet</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                Upload some high-quality images to showcase your product
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-500">
                The first image you upload will automatically become the primary image
            </p>
        </div>
    @endif

    {{-- Validation Errors --}}
    @if($this->validationErrors->isNotEmpty())
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
            <div class="flex items-center gap-2 mb-2">
                <flux:icon name="exclamation-circle" class="w-5 h-5 text-red-600 dark:text-red-400" />
                <h4 class="font-semibold text-red-900 dark:text-red-100">Upload Errors</h4>
            </div>
            <ul class="list-disc list-inside space-y-1 text-sm text-red-800 dark:text-red-200">
                @foreach($this->validationErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Success Summary --}}
    @if($productImages->isNotEmpty())
        <div class="bg-green-50 dark:bg-green-900/20 rounded-xl border border-green-200 dark:border-green-800 p-4">
            <div class="flex items-center gap-2 mb-2">
                <flux:icon name="check-circle" class="w-5 h-5 text-green-600 dark:text-green-400" />
                <h4 class="font-semibold text-green-900 dark:text-green-100">Images Ready!</h4>
            </div>
            <div class="text-sm text-green-800 dark:text-green-200 space-y-1">
                <p>✅ {{ $productImages->count() }} {{ Str::plural('image', $productImages->count()) }} uploaded successfully</p>
                @if($this->imageStats['has_primary_image'])
                    <p>⭐ Primary image selected</p>
                @endif
                @if($isEditMode)
                    <p>💾 Images saved to R2 cloud storage</p>
                @else
                    <p>⏳ Images will be saved when you complete the product creation</p>
                @endif
            </div>
        </div>
    @endif

    {{-- Future: Variant Images Section --}}
    @if($enableVariantImages && $availableVariants->isNotEmpty())
        <div class="space-y-4 pt-6 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <flux:icon name="squares-2x2" class="w-5 h-5" />
                        Variant-Specific Images
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Assign specific images to individual product variants</p>
                </div>
                
                <flux:switch wire:model.live="enableVariantImages" />
            </div>

            <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800 p-4">
                <div class="flex items-center gap-2">
                    <flux:icon name="wrench-screwdriver" class="w-5 h-5 text-yellow-600 dark:text-yellow-400" />
                    <span class="font-medium text-yellow-900 dark:text-yellow-100">Coming Soon!</span>
                </div>
                <p class="text-sm text-yellow-800 dark:text-yellow-200 mt-1">
                    Variant-specific image upload functionality will be added in a future update.
                </p>
            </div>
        </div>
    @endif

    {{-- DAM Image Selector Component --}}
    <livewire:d-a-m.image-selector />

</div>