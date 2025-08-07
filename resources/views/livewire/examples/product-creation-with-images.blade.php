<div class="max-w-4xl mx-auto space-y-8">
    <!-- Progress Steps -->
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
        <nav aria-label="Progress">
            <ol class="flex items-center">
                <!-- Step 1: Product -->
                <li class="relative flex-1">
                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full
                                   {{ $currentStep === 'product' ? 'bg-blue-600 text-white' : 
                                      ($productCreated ? 'bg-green-600 text-white' : 'bg-zinc-300 dark:bg-zinc-600 text-zinc-600 dark:text-zinc-400') }}">
                            @if($productCreated)
                                <flux:icon name="check" class="w-5 h-5" />
                            @else
                                <span class="text-sm font-medium">1</span>
                            @endif
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium {{ $currentStep === 'product' ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-900 dark:text-zinc-50' }}">
                                Create Product
                            </p>
                            <p class="text-xs text-zinc-500">Basic product information</p>
                        </div>
                    </div>
                    <div class="absolute top-4 right-0 w-full h-0.5 {{ $productCreated ? 'bg-green-600' : 'bg-zinc-300 dark:bg-zinc-600' }}"></div>
                </li>

                <!-- Step 2: Variant -->
                <li class="relative flex-1">
                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full
                                   {{ $currentStep === 'variant' ? 'bg-blue-600 text-white' : 
                                      ($variantCreated ? 'bg-green-600 text-white' : 
                                       ($currentStep === 'images' && !$variantCreated ? 'bg-yellow-500 text-white' : 'bg-zinc-300 dark:bg-zinc-600 text-zinc-600 dark:text-zinc-400')) }}">
                            @if($variantCreated)
                                <flux:icon name="check" class="w-5 h-5" />
                            @elseif($currentStep === 'images' && !$variantCreated)
                                <flux:icon name="x" class="w-5 h-5" />
                            @else
                                <span class="text-sm font-medium">2</span>
                            @endif
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium {{ $currentStep === 'variant' ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-900 dark:text-zinc-50' }}">
                                Create Variant
                            </p>
                            <p class="text-xs text-zinc-500">Optional: Add color/size variant</p>
                        </div>
                    </div>
                    <div class="absolute top-4 right-0 w-full h-0.5 
                               {{ $variantCreated ? 'bg-green-600' : 
                                  ($currentStep === 'images' ? 'bg-yellow-500' : 'bg-zinc-300 dark:bg-zinc-600') }}"></div>
                </li>

                <!-- Step 3: Images -->
                <li class="relative">
                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full
                                   {{ $currentStep === 'images' ? 'bg-blue-600 text-white' : 'bg-zinc-300 dark:bg-zinc-600 text-zinc-600 dark:text-zinc-400' }}">
                            <span class="text-sm font-medium">3</span>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium {{ $currentStep === 'images' ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-900 dark:text-zinc-50' }}">
                                Add Images
                            </p>
                            <p class="text-xs text-zinc-500">Upload product images</p>
                        </div>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    <!-- Step 1: Product Creation -->
    @if($currentStep === 'product')
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 p-8">
            <div class="space-y-6">
                <div class="space-y-1">
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-50">
                        Create Product
                    </flux:heading>
                    <flux:subheading class="text-zinc-600 dark:text-zinc-400">
                        Enter the basic product information to get started
                    </flux:subheading>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <flux:field>
                            <flux:label>Product Name *</flux:label>
                            <flux:input wire:model="name" placeholder="Enter product name" />
                            <flux:error name="name" />
                        </flux:field>
                    </div>

                    <div>
                        <flux:field>
                            <flux:label>SKU *</flux:label>
                            <flux:input wire:model="sku" placeholder="Enter SKU" />
                            <flux:error name="sku" />
                        </flux:field>
                    </div>

                    <div class="md:col-span-2">
                        <flux:field>
                            <flux:label>Description</flux:label>
                            <flux:textarea wire:model="description" rows="3" placeholder="Enter product description" />
                            <flux:error name="description" />
                        </flux:field>
                    </div>

                    <div>
                        <flux:field>
                            <flux:label>Price *</flux:label>
                            <flux:input.group>
                                <flux:input.group.prefix>£</flux:input.group.prefix>
                                <flux:input wire:model="price" type="number" step="0.01" min="0" placeholder="0.00" />
                            </flux:input.group>
                            <flux:error name="price" />
                        </flux:field>
                    </div>
                </div>

                <div class="flex justify-end">
                    <flux:button wire:click="createProduct" variant="primary" icon="arrow-right">
                        Create Product
                    </flux:button>
                </div>
            </div>
        </div>
    @endif

    <!-- Step 2: Variant Creation -->
    @if($currentStep === 'variant')
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 p-8">
            <div class="space-y-6">
                <div class="space-y-1">
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-50">
                        Create Variant (Optional)
                    </flux:heading>
                    <flux:subheading class="text-zinc-600 dark:text-zinc-400">
                        Add a color/size variant for "{{ $product->name }}" or skip to add images directly to the product
                    </flux:subheading>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <flux:field>
                            <flux:label>Color</flux:label>
                            <flux:input wire:model="color" placeholder="e.g., Red, Blue, Black" />
                            <flux:error name="color" />
                        </flux:field>
                    </div>

                    <div>
                        <flux:field>
                            <flux:label>Size</flux:label>
                            <flux:input wire:model="size" placeholder="e.g., S, M, L, XL" />
                            <flux:error name="size" />
                        </flux:field>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-6 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button wire:click="skipVariantCreation" variant="ghost">
                        Skip Variant Creation
                    </flux:button>
                    
                    <flux:button wire:click="createVariant" variant="primary" icon="arrow-right">
                        Create Variant
                    </flux:button>
                </div>
            </div>
        </div>
    @endif

    <!-- Step 3: Image Upload -->
    @if($currentStep === 'images')
        <div class="space-y-6">
            <!-- Context Information -->
            <div class="bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800 rounded-xl p-6">
                <div class="flex items-start space-x-3">
                    <flux:icon name="info" class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5" />
                    <div>
                        <flux:heading size="sm" class="text-blue-900 dark:text-blue-100 mb-1">
                            Adding Images to {{ $variantCreated ? 'Variant' : 'Product' }}
                        </flux:heading>
                        <p class="text-blue-800 dark:text-blue-200 text-sm">
                            @if($variantCreated)
                                Images will be associated with the "{{ $variant->color }} {{ $variant->size }}" variant.
                            @else
                                Images will be associated with the "{{ $product->name }}" product.
                            @endif
                            You can always add more images or different image types later.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Image Uploader -->
            @if($model)
                <livewire:components.image-uploader 
                    :model-type="$modelType"
                    :model-id="$model->id"
                    :image-type="'main'"
                    :multiple="true"
                    :max-files="$uploaderConfig['max_files']"
                    :max-size="$uploaderConfig['max_size']"
                    :accept-types="$uploaderConfig['accept_types']"
                    :process-immediately="$uploaderConfig['process_immediately']"
                    :show-preview="$uploaderConfig['show_preview']"
                    :allow-reorder="$uploaderConfig['allow_reorder']"
                    :show-upload-area="$uploaderConfig['show_upload_area']"
                    :show-existing-images="$uploaderConfig['show_existing_images']"
                    :view-mode="$uploaderConfig['view_mode']"
                    :upload-text="$uploaderConfig['upload_text']"
                    wire:key="creation-uploader"
                />
            @endif

            <!-- Completion Actions -->
            <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-50 mb-1">
                            Ready to finish?
                        </flux:heading>
                        <flux:subheading class="text-zinc-600 dark:text-zinc-400">
                            You can add more images later from the product management page
                        </flux:subheading>
                    </div>

                    <div class="flex items-center gap-3">
                        <flux:button wire:click="skipImageUpload" variant="ghost">
                            Skip Images
                        </flux:button>
                        
                        <flux:button wire:click="completeCreation" variant="primary" icon="check">
                            Complete Creation
                        </flux:button>
                    </div>
                </div>

                <!-- Upload Summary -->
                @if(!empty($uploadedImages))
                    <div class="mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                        <div class="text-sm text-zinc-600 dark:text-zinc-400">
                            <strong>Upload Summary:</strong>
                            @foreach($uploadedImages as $upload)
                                {{ $upload['count'] }} images uploaded{{ !$loop->last ? ',' : '' }}
                            @endforeach
                            (Total: {{ collect($uploadedImages)->sum('count') }} images)
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>