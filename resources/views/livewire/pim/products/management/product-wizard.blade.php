<div class="max-w-7xl mx-auto space-y-6">
    <!-- Header -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
        <div class="p-6">
            <x-breadcrumb :items="[
                ['name' => 'Products', 'url' => route('products.index')],
                ['name' => 'Create Product']
            ]" class="mb-4" />
            
            <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center">
                    <flux:icon name="sparkles" class="h-6 w-6 text-white" />
                </div>
                <div>
                    <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-100 font-semibold">
                        Create New Product
                    </flux:heading>
                    <flux:subheading class="text-zinc-600 dark:text-zinc-400">
                        Step {{ $currentStep }} of {{ $totalSteps }}: {{ $this->stepTitle }}
                    </flux:subheading>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="relative mb-6">
                <div class="flex items-center justify-between mb-2">
                    @for($i = 1; $i <= $totalSteps; $i++)
                        <div class="flex flex-col items-center relative" style="z-index: 10;">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-medium transition-all duration-200
                                @if($i < $currentStep || in_array($i, $completedSteps)) 
                                    bg-indigo-600 text-white
                                @elseif($i == $currentStep) 
                                    bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400 ring-2 ring-indigo-600
                                @else 
                                    bg-zinc-200 dark:bg-zinc-700 text-zinc-500 dark:text-zinc-400
                                @endif">
                                @if($i < $currentStep || in_array($i, $completedSteps))
                                    <flux:icon name="check" class="h-5 w-5" />
                                @else
                                    {{ $i }}
                                @endif
                            </div>
                            <span class="text-xs mt-2 text-center text-zinc-600 dark:text-zinc-400 max-w-20">
                                @switch($i)
                                    @case(1) Basic @break
                                    @case(2) Images @break
                                    @case(3) Features @break
                                    @case(4) Attributes @break
                                    @case(5) Variants @break
                                    @case(6) Barcodes @break
                                    @case(7) Review @break
                                @endswitch
                            </span>
                        </div>
                    @endfor
                </div>
                
                <!-- Progress Line -->
                <div class="absolute top-5 left-5 right-5 h-0.5 bg-zinc-200 dark:bg-zinc-700" style="z-index: 1;">
                    <div class="h-full bg-indigo-600 transition-all duration-500" 
                         style="width: {{ (($currentStep - 1) / ($totalSteps - 1)) * 100 }}%"></div>
                </div>
            </div>

            <!-- Step Description -->
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ $this->stepDescription }}
            </p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
        <div class="p-6">
            <!-- Step 1: Basic Information -->
            @if($currentStep === 1)
                <div class="space-y-6">
                    <flux:heading size="lg" class="mb-4">Basic Product Information</flux:heading>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <flux:field>
                            <flux:label>Product Name *</flux:label>
                            <flux:input wire:model.live="form.name" placeholder="e.g., Premium Window Shade" />
                            @error('form.name') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label>Parent SKU *</flux:label>
                            <div class="flex gap-2">
                                <flux:input wire:model.live="parentSku" placeholder="001" 
                                           class="{{ $skuConflictProduct ? 'border-red-500' : '' }}" />
                                <flux:button wire:click="regenerateParentSku" variant="outline" size="sm" icon="sparkles" 
                                            title="Generate new Parent SKU">
                                </flux:button>
                            </div>
                            @error('parentSku') <flux:error>{{ $message }}</flux:error> @enderror
                            @if($skuConflictProduct)
                                <div class="mt-2 p-2 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <flux:icon name="circle-x" class="h-4 w-4 text-red-500 mr-2" />
                                            <span class="text-sm text-red-700 dark:text-red-300">
                                                SKU conflict with: <strong>{{ $skuConflictProduct }}</strong>
                                            </span>
                                        </div>
                                        <flux:button wire:click="regenerateParentSku" variant="outline" size="sm" 
                                                    class="text-red-600 border-red-300 hover:bg-red-50">
                                            Generate New SKU
                                        </flux:button>
                                    </div>
                                </div>
                            @else
                                <flux:description>Auto-generated but can be customized</flux:description>
                            @endif
                        </flux:field>

                        <flux:field>
                            <flux:label>URL Slug</flux:label>
                            <flux:input wire:model="form.slug" placeholder="Auto-generated from name" />
                            @error('form.slug') <flux:error>{{ $message }}</flux:error> @enderror
                            <flux:description>Leave blank to auto-generate from product name</flux:description>
                        </flux:field>
                    </div>

                    <flux:field>
                        <flux:label>Description</flux:label>
                        <flux:textarea wire:model="form.description" rows="4" 
                                      placeholder="Describe your product in detail..." />
                        @error('form.description') <flux:error>{{ $message }}</flux:error> @enderror
                    </flux:field>

                    <flux:field>
                        <flux:label>Status *</flux:label>
                        <flux:select wire:model="form.status">
                            <flux:select.option value="active">Active</flux:select.option>
                            <flux:select.option value="inactive">Inactive</flux:select.option>
                            <flux:select.option value="discontinued">Discontinued</flux:select.option>
                        </flux:select>
                        @error('form.status') <flux:error>{{ $message }}</flux:error> @enderror
                    </flux:field>
                </div>
            @endif

            <!-- Step 2: Product Images -->
            @if($currentStep === 2)
                <div class="space-y-6">
                    <flux:heading size="lg" class="mb-4">Product Images</flux:heading>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <flux:field>
                                <flux:label>Image Type</flux:label>
                                <flux:select wire:model="imageType">
                                    <flux:select.option value="main">Main Product Image</flux:select.option>
                                    <flux:select.option value="gallery">Gallery Image</flux:select.option>
                                    <flux:select.option value="swatch">Color Swatch</flux:select.option>
                                </flux:select>
                            </flux:field>

                            <flux:field>
                                <flux:label>Upload Images</flux:label>
                                <input type="file" wire:model="newImages" multiple accept="image/*"
                                       class="w-full rounded-md border-zinc-300 dark:border-zinc-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                @error('newImages.*') <flux:error>{{ $message }}</flux:error> @enderror
                                <flux:description>Select multiple images. Maximum 5MB per image.</flux:description>
                            </flux:field>
                        </div>

                        <!-- Image Preview -->
                        <div>
                            @if($newImages && count($newImages) > 0)
                                <flux:label>Image Preview</flux:label>
                                <div class="grid grid-cols-2 gap-3 mt-2">
                                    @foreach($newImages as $index => $image)
                                        <div class="relative group">
                                            <img src="{{ $image->temporaryUrl() }}" alt="Preview" 
                                                 class="w-full h-24 object-cover rounded-lg border border-zinc-200 dark:border-zinc-600">
                                            <div class="absolute top-1 left-1">
                                                <flux:badge variant="outline" class="text-xs bg-white/90 dark:bg-zinc-800/90">
                                                    {{ ucfirst($imageType) }}
                                                </flux:badge>
                                            </div>
                                            <button type="button" wire:click="removeNewImage({{ $index }})"
                                                    class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm opacity-0 group-hover:opacity-100 transition-opacity">
                                                ×
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="border-2 border-dashed border-zinc-300 dark:border-zinc-600 rounded-lg p-8 text-center">
                                    <flux:icon name="image" class="mx-auto h-12 w-12 text-zinc-400" />
                                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">No images uploaded yet</p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-500">Images are optional but recommended</p>
                                </div>
                            @endif

                            <div wire:loading wire:target="newImages" class="mt-2 text-blue-600 text-sm">
                                Uploading images...
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Step 3: Features & Details -->
            @if($currentStep === 3)
                <div class="space-y-6">
                    <flux:heading size="lg" class="mb-4">Product Features & Details</flux:heading>
                    
                    <!-- Features Section -->
                    <div>
                        <flux:subheading class="mb-4">Product Features</flux:subheading>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            @for($i = 1; $i <= 5; $i++)
                                <flux:field>
                                    <flux:label>Feature {{ $i }}</flux:label>
                                    <flux:textarea wire:model="form.product_features_{{ $i }}" rows="2" 
                                                  placeholder="Enter product feature {{ $i }}..." />
                                </flux:field>
                            @endfor
                        </div>
                    </div>

                    <!-- Details Section -->
                    <div>
                        <flux:subheading class="mb-4">Product Details</flux:subheading>
                        <div class="space-y-4">
                            @for($i = 1; $i <= 5; $i++)
                                <flux:field>
                                    <flux:label>Detail {{ $i }}</flux:label>
                                    <flux:textarea wire:model="form.product_details_{{ $i }}" rows="3" 
                                                  placeholder="Enter product detail {{ $i }}..." />
                                </flux:field>
                            @endfor
                        </div>
                    </div>
                </div>
            @endif

            <!-- Step 4: Product Attributes -->
            @if($currentStep === 4)
                <div class="space-y-6">
                    <flux:heading size="lg" class="mb-4">Product Attributes</flux:heading>
                    
                    @if($attributeDefinitions->isNotEmpty())
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            @foreach($attributeDefinitions as $attribute)
                                <flux:field>
                                    <flux:label>
                                        {{ $attribute->label }}
                                        @if($attribute->is_required)
                                            <span class="text-red-500">*</span>
                                        @endif
                                    </flux:label>
                                    
                                    @if($attribute->data_type === 'boolean')
                                        <flux:checkbox wire:model="attributeValues.{{ $attribute->key }}">
                                            {{ $attribute->description ?: 'Enable this option' }}
                                        </flux:checkbox>
                                    @elseif($attribute->validation_rules && isset($attribute->validation_rules['options']))
                                        <flux:select wire:model="attributeValues.{{ $attribute->key }}">
                                            <flux:select.option value="">Choose an option</flux:select.option>
                                            @foreach($attribute->validation_rules['options'] as $option)
                                                <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    @elseif($attribute->data_type === 'number')
                                        <flux:input type="number" wire:model="attributeValues.{{ $attribute->key }}" 
                                                   placeholder="Enter {{ strtolower($attribute->label) }}" />
                                    @else
                                        <flux:input wire:model="attributeValues.{{ $attribute->key }}" 
                                                   placeholder="Enter {{ strtolower($attribute->label) }}" />
                                    @endif
                                    
                                    @if($attribute->description)
                                        <flux:description>{{ $attribute->description }}</flux:description>
                                    @endif
                                    
                                    @error('attributeValues.' . $attribute->key) 
                                        <flux:error>{{ $message }}</flux:error> 
                                    @enderror
                                </flux:field>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12">
                            <flux:icon name="settings" class="mx-auto h-12 w-12 text-zinc-400" />
                            <p class="mt-4 text-zinc-600 dark:text-zinc-400">No product attributes configured</p>
                            <p class="text-sm text-zinc-500">You can add attributes later or configure them in 
                                <a href="{{ route('attributes.definitions') }}" class="text-indigo-600 hover:text-indigo-500">
                                    Attribute Definitions
                                </a>
                            </p>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Step 5: Product Variants -->
            @if($currentStep === 5)
                <div class="space-y-6">
                    <flux:heading size="lg" class="mb-4">Product Variants</flux:heading>
                    
                    <!-- Generation Type Toggle -->
                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <flux:subheading>Variant Generation</flux:subheading>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">Choose how to create product variants</p>
                            </div>
                            <flux:checkbox wire:model.live="generateVariants">
                                Automatic Generation
                            </flux:checkbox>
                        </div>
                        
                        @if($generateVariants)
                            <!-- Automatic Generation -->
                            <div class="space-y-6">
                                <flux:subheading>Automatic Variant Generation</flux:subheading>
                                
                                <!-- SKU Generation Method -->
                                <div>
                                    <flux:label>SKU Generation Method</flux:label>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-2">
                                        <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700
                                            {{ $skuGenerationMethod === 'sequential' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-zinc-300 dark:border-zinc-600' }}">
                                            <input type="radio" wire:model.live="skuGenerationMethod" value="sequential" class="sr-only">
                                            <div class="flex-1">
                                                <div class="flex items-center">
                                                    <flux:icon name="layers" class="h-4 w-4 mr-2" />
                                                    <span class="font-medium text-sm">Sequential</span>
                                                </div>
                                                <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">{{ $parentSku }}-001, {{ $parentSku }}-002, etc.</p>
                                            </div>
                                        </label>
                                        
                                        <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700
                                            {{ $skuGenerationMethod === 'random' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-zinc-300 dark:border-zinc-600' }}">
                                            <input type="radio" wire:model.live="skuGenerationMethod" value="random" class="sr-only">
                                            <div class="flex-1">
                                                <div class="flex items-center">
                                                    <flux:icon name="sparkles" class="h-4 w-4 mr-2" />
                                                    <span class="font-medium text-sm">Random</span>
                                                </div>
                                                <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">{{ $parentSku }}-847, {{ $parentSku }}-192, etc.</p>
                                            </div>
                                        </label>
                                        
                                        <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700
                                            {{ $skuGenerationMethod === 'manual' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-zinc-300 dark:border-zinc-600' }}">
                                            <input type="radio" wire:model.live="skuGenerationMethod" value="manual" class="sr-only">
                                            <div class="flex-1">
                                                <div class="flex items-center">
                                                    <flux:icon name="settings" class="h-4 w-4 mr-2" />
                                                    <span class="font-medium text-sm">Manual</span>
                                                </div>
                                                <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">Based on product name</p>
                                            </div>
                                        </label>
                                    </div>
                                    
                                    <div class="mt-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                                        <div class="flex items-start">
                                            <flux:icon name="check" class="h-4 w-4 text-blue-600 dark:text-blue-400 mt-0.5 mr-2 flex-shrink-0" />
                                            <div class="text-sm text-blue-700 dark:text-blue-300">
                                                <strong>Parent SKU:</strong> {{ $parentSku }} - 
                                                @if($skuGenerationMethod === 'sequential')
                                                    Variants will be numbered sequentially starting from 001
                                                @elseif($skuGenerationMethod === 'random')
                                                    Variants will have random 3-digit numbers (001-999)
                                                @else
                                                    Variants will use product name and attributes
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Color Selection -->
                                <div>
                                    <flux:label>Available Colors</flux:label>
                                    <div class="grid grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-2 mt-2">
                                        @foreach($commonColors as $color)
                                            <label class="flex items-center space-x-2 p-2 border rounded-lg cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700
                                                {{ in_array($color, $selectedColors) ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-zinc-300 dark:border-zinc-600' }}">
                                                <input type="checkbox" wire:model.live="selectedColors" value="{{ $color }}" class="sr-only">
                                                <div class="w-4 h-4 rounded border-2 border-zinc-300 dark:border-zinc-600" 
                                                     style="background-color: {{ strtolower($color) === 'white' ? '#ffffff' : strtolower($color) }}"></div>
                                                <span class="text-xs">{{ $color }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>


                                <!-- Width Selection -->
                                <div>
                                    <flux:label>Available Widths</flux:label>
                                    <div class="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2 mt-2">
                                        @foreach($commonWidths as $width)
                                            <label class="flex items-center justify-center p-2 border rounded-lg cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700
                                                {{ in_array($width, $selectedWidths) ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-zinc-300 dark:border-zinc-600' }}">
                                                <input type="checkbox" wire:model.live="selectedWidths" value="{{ $width }}" class="sr-only">
                                                <span class="text-xs font-medium">{{ $width }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <flux:description>Window shade widths</flux:description>
                                </div>

                                <!-- Drop Selection -->
                                <div>
                                    <flux:label>Available Drops/Lengths</flux:label>
                                    <div class="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2 mt-2">
                                        @foreach($commonDrops as $drop)
                                            <label class="flex items-center justify-center p-2 border rounded-lg cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700
                                                {{ in_array($drop, $selectedDrops) ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-zinc-300 dark:border-zinc-600' }}">
                                                <input type="checkbox" wire:model.live="selectedDrops" value="{{ $drop }}" class="sr-only">
                                                <span class="text-xs font-medium">{{ $drop }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <flux:description>Window shade drops/lengths</flux:description>
                                </div>

                                <!-- Generate Button -->
                                <div class="flex justify-center">
                                    <flux:button wire:click="generateVariantMatrix" variant="outline" icon="layers">
                                        Generate Variant Matrix
                                    </flux:button>
                                </div>

                                <!-- Generated Variants Preview -->
                                @if(!empty($variantMatrix))
                                    <div>
                                        <flux:subheading>Generated Variants ({{ count($variantMatrix) }})</flux:subheading>
                                        <div class="mt-4 max-h-64 overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-lg">
                                            <table class="w-full text-sm">
                                                <thead class="bg-zinc-50 dark:bg-zinc-900">
                                                    <tr>
                                                        <th class="px-4 py-2 text-left">SKU</th>
                                                        <th class="px-4 py-2 text-left">Color</th>
                                                        <th class="px-4 py-2 text-left">Width</th>
                                                        <th class="px-4 py-2 text-left">Drop</th>
                                                        <th class="px-4 py-2 text-left">Stock</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                                    @foreach($variantMatrix as $index => $variant)
                                                        <tr>
                                                            <td class="px-4 py-2 font-mono text-xs">{{ $variant['sku'] }}</td>
                                                            <td class="px-4 py-2">{{ $variant['color'] ?: '—' }}</td>
                                                            <td class="px-4 py-2">{{ $variant['width'] ?: '—' }}</td>
                                                            <td class="px-4 py-2">{{ $variant['drop'] ?: '—' }}</td>
                                                            <td class="px-4 py-2">
                                                                <input type="number" wire:model="variantMatrix.{{ $index }}.stock_level" 
                                                                       class="w-16 px-2 py-1 text-xs border border-zinc-300 dark:border-zinc-600 rounded">
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @else
                            <!-- Manual Creation -->
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <flux:subheading>Custom Variants</flux:subheading>
                                    <flux:button wire:click="addCustomVariant" variant="outline" size="sm" icon="plus">
                                        Add Variant
                                    </flux:button>
                                </div>

                                @if(!empty($customVariants))
                                    <div class="space-y-3">
                                        @foreach($customVariants as $index => $variant)
                                            <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                                                <div class="flex items-center justify-between mb-3">
                                                    <span class="font-medium text-sm">Variant {{ $index + 1 }}</span>
                                                    <flux:button wire:click="removeCustomVariant({{ $index }})" 
                                                                variant="ghost" size="sm" icon="trash-2" class="text-red-600">
                                                    </flux:button>
                                                </div>
                                                <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                                                    <flux:field>
                                                        <flux:label>SKU *</flux:label>
                                                        <flux:input wire:model="customVariants.{{ $index }}.sku" 
                                                                   placeholder="VARIANT-SKU" />
                                                    </flux:field>
                                                    <flux:field>
                                                        <flux:label>Color</flux:label>
                                                        <flux:input wire:model="customVariants.{{ $index }}.color" 
                                                                   placeholder="White" />
                                                    </flux:field>
                                                    <flux:field>
                                                        <flux:label>Width</flux:label>
                                                        <flux:input wire:model="customVariants.{{ $index }}.width" 
                                                                   placeholder="120cm" />
                                                    </flux:field>
                                                    <flux:field>
                                                        <flux:label>Drop</flux:label>
                                                        <flux:input wire:model="customVariants.{{ $index }}.drop" 
                                                                   placeholder="160cm" />
                                                    </flux:field>
                                                    <flux:field>
                                                        <flux:label>Stock</flux:label>
                                                        <flux:input type="number" wire:model="customVariants.{{ $index }}.stock_level" 
                                                                   placeholder="0" />
                                                    </flux:field>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="text-center py-8 border-2 border-dashed border-zinc-300 dark:border-zinc-600 rounded-lg">
                                        <flux:icon name="layers" class="mx-auto h-8 w-8 text-zinc-400" />
                                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">No custom variants added</p>
                                        <flux:button wire:click="addCustomVariant" variant="primary" size="sm" class="mt-2" icon="plus">
                                            Add First Variant
                                        </flux:button>
                                    </div>
                                @endif
                            </div>
                        @endif

                        @error('variants') <flux:error>{{ $message }}</flux:error> @enderror
                    </div>
                </div>
            @endif

            <!-- Step 6: Barcode Assignment -->
            @if($currentStep === 6)
                <div class="space-y-6">
                    <flux:heading size="lg" class="mb-4">Barcode Assignment</flux:heading>
                    
                    <!-- Barcode Pool Statistics -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-6">
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0">
                                <flux:icon name="qr-code" class="h-6 w-6 text-blue-600" />
                            </div>
                            <div>
                                <flux:heading size="sm" class="text-blue-800 dark:text-blue-200">Barcode Pool Status</flux:heading>
                                <div class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                                    <strong>{{ number_format($availableBarcodesCount) }}</strong> {{ $barcodeType }} barcodes available for assignment
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Barcode Assignment Configuration -->
                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                        <flux:subheading class="mb-4">Assignment Configuration</flux:subheading>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Assignment Method -->
                            <flux:field>
                                <flux:label>Assignment Method</flux:label>
                                <flux:select wire:model.live="barcodeAssignmentMethod">
                                    <flux:select.option value="auto">Automatic Assignment</flux:select.option>
                                    <flux:select.option value="manual">Manual Assignment</flux:select.option>
                                    <flux:select.option value="skip">Skip Barcode Assignment</flux:select.option>
                                </flux:select>
                                <flux:description>
                                    @if($barcodeAssignmentMethod === 'auto')
                                        Barcodes will be automatically assigned from the pool
                                    @elseif($barcodeAssignmentMethod === 'manual')
                                        You can manually assign specific barcodes to each variant
                                    @else
                                        Variants will be created without barcodes (can be assigned later)
                                    @endif
                                </flux:description>
                            </flux:field>

                            <!-- Barcode Type -->
                            <flux:field>
                                <flux:label>Barcode Type</flux:label>
                                <flux:select wire:model.live="barcodeType">
                                    <flux:select.option value="EAN13">EAN-13 (13 digits)</flux:select.option>
                                    <flux:select.option value="EAN8">EAN-8 (8 digits)</flux:select.option>
                                    <flux:select.option value="UPC">UPC (12 digits)</flux:select.option>
                                    <flux:select.option value="CODE128">Code 128</flux:select.option>
                                    <flux:select.option value="CODE39">Code 39</flux:select.option>
                                    <flux:select.option value="CODABAR">Codabar</flux:select.option>
                                </flux:select>
                            </flux:field>
                        </div>

                        <!-- Assignment Toggle -->
                        <div class="mt-4">
                            <flux:field>
                                <div class="flex items-center space-x-3">
                                    <flux:checkbox wire:model.live="assignBarcodes" />
                                    <flux:label>Assign barcodes to all variants automatically</flux:label>
                                </div>
                                @if(!$assignBarcodes)
                                    <flux:description class="text-orange-600">
                                        Variants will be created without barcodes. You can assign them later.
                                    </flux:description>
                                @endif
                            </flux:field>
                        </div>
                    </div>

                    <!-- Barcode Assignment Preview -->
                    @if($assignBarcodes && $barcodeAssignmentMethod !== 'skip' && !empty($variantMatrix))
                        <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-4">
                                <flux:subheading>Barcode Assignment Preview</flux:subheading>
                                @if($barcodeAssignmentMethod === 'auto')
                                    <flux:button wire:click="refreshBarcodeAssignment" variant="outline" size="sm" icon="arrow-path">
                                        Refresh Assignment
                                    </flux:button>
                                @endif
                            </div>

                            <!-- Insufficient Barcodes Warning -->
                            @if(count($variantMatrix) > $availableBarcodesCount)
                                <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                                    <div class="flex items-center">
                                        <flux:icon name="exclamation-triangle" class="h-5 w-5 text-red-600 mr-2" />
                                        <div>
                                            <div class="text-sm font-medium text-red-800 dark:text-red-200">Insufficient Barcodes</div>
                                            <div class="text-sm text-red-700 dark:text-red-300">
                                                Need {{ count($variantMatrix) }} barcodes but only {{ $availableBarcodesCount }} available in pool.
                                                Please reduce variants or import more barcodes.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <!-- Assignment Table -->
                            <div class="max-h-64 overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-lg">
                                <table class="w-full text-sm">
                                    <thead class="bg-zinc-50 dark:bg-zinc-900 sticky top-0">
                                        <tr>
                                            <th class="px-4 py-2 text-left">Variant SKU</th>
                                            <th class="px-4 py-2 text-left">Details</th>
                                            <th class="px-4 py-2 text-left">Assigned Barcode</th>
                                            @if($barcodeAssignmentMethod === 'manual')
                                                <th class="px-4 py-2 text-center">Actions</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                        @foreach($variantMatrix as $index => $variant)
                                            <tr>
                                                <td class="px-4 py-2 font-mono text-xs">{{ $variant['sku'] }}</td>
                                                <td class="px-4 py-2">
                                                    <div class="flex items-center gap-1">
                                                        @if($variant['color'])
                                                            <flux:badge variant="outline" class="text-xs">{{ $variant['color'] }}</flux:badge>
                                                        @endif
                                                        @if($variant['width'])
                                                            <flux:badge variant="outline" class="text-xs">W: {{ $variant['width'] }}</flux:badge>
                                                        @endif
                                                        @if($variant['drop'])
                                                            <flux:badge variant="outline" class="text-xs">D: {{ $variant['drop'] }}</flux:badge>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="px-4 py-2">
                                                    @if(isset($variantBarcodes[$index]))
                                                        <code class="bg-green-100 dark:bg-green-900/20 px-2 py-1 rounded text-xs">
                                                            {{ $variantBarcodes[$index] }}
                                                        </code>
                                                    @else
                                                        <span class="text-zinc-500 text-xs">Not assigned</span>
                                                    @endif
                                                </td>
                                                @if($barcodeAssignmentMethod === 'manual')
                                                    <td class="px-4 py-2 text-center">
                                                        <div class="flex gap-1 justify-center">
                                                            @if(!isset($variantBarcodes[$index]))
                                                                <flux:button wire:click="assignSpecificBarcode({{ $index }})" 
                                                                            variant="outline" size="sm" class="text-xs">
                                                                    Assign
                                                                </flux:button>
                                                            @else
                                                                <flux:button wire:click="removeVariantBarcode({{ $index }})" 
                                                                            variant="ghost" size="sm" class="text-xs text-red-600">
                                                                    Remove
                                                                </flux:button>
                                                            @endif
                                                        </div>
                                                    </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <!-- Assignment Summary -->
                            <div class="mt-4 p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                                <div class="grid grid-cols-3 gap-4 text-center">
                                    <div>
                                        <div class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                                            {{ count($variantMatrix) }}
                                        </div>
                                        <div class="text-xs text-zinc-600 dark:text-zinc-400">Total Variants</div>
                                    </div>
                                    <div>
                                        <div class="text-lg font-semibold text-green-600">
                                            {{ count(array_filter($variantBarcodes)) }}
                                        </div>
                                        <div class="text-xs text-zinc-600 dark:text-zinc-400">Assigned Barcodes</div>
                                    </div>
                                    <div>
                                        <div class="text-lg font-semibold text-orange-600">
                                            {{ count($variantMatrix) - count(array_filter($variantBarcodes)) }}
                                        </div>
                                        <div class="text-xs text-zinc-600 dark:text-zinc-400">Unassigned</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @elseif(!empty($variantMatrix) && !$assignBarcodes)
                        <div class="text-center py-8 border-2 border-dashed border-zinc-300 dark:border-zinc-600 rounded-lg">
                            <flux:icon name="qr-code" class="mx-auto h-8 w-8 text-zinc-400" />
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">Barcode assignment is disabled</p>
                            <p class="text-xs text-zinc-500">Variants will be created without barcodes</p>
                        </div>
                    @elseif(empty($variantMatrix))
                        <div class="text-center py-8 border-2 border-dashed border-zinc-300 dark:border-zinc-600 rounded-lg">
                            <flux:icon name="exclamation-triangle" class="mx-auto h-8 w-8 text-orange-400" />
                            <p class="mt-2 text-sm text-orange-600">No variants available for barcode assignment</p>
                            <p class="text-xs text-zinc-500">Go back to step 5 to create variants first</p>
                        </div>
                    @endif

                    @error('barcodes') 
                        <div class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                            <flux:error>{{ $message }}</flux:error>
                        </div>
                    @enderror
                </div>
            @endif

            <!-- Step 7: Review & Create -->
            @if($currentStep === 7)
                <div class="space-y-6">
                    <flux:heading size="lg" class="mb-4">Review & Create Product</flux:heading>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Product Summary -->
                        <div class="space-y-4">
                            <flux:subheading>Product Information</flux:subheading>
                            <div class="bg-zinc-50 dark:bg-zinc-900 rounded-lg p-4 space-y-3">
                                <div>
                                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Name:</span>
                                    <span class="text-sm text-zinc-900 dark:text-zinc-100 ml-2">{{ $form->name }}</span>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Slug:</span>
                                    <span class="text-sm text-zinc-900 dark:text-zinc-100 ml-2">{{ $form->slug ?: 'Auto-generated' }}</span>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Status:</span>
                                    <flux:badge variant="outline" class="ml-2 text-xs">{{ ucfirst($form->status) }}</flux:badge>
                                </div>
                                @if($form->description)
                                    <div>
                                        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Description:</span>
                                        <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">{{ Str::limit($form->description, 100) }}</p>
                                    </div>
                                @endif
                            </div>

                            <!-- Images Summary -->
                            @if($newImages && count($newImages) > 0)
                                <div>
                                    <flux:subheading>Images ({{ count($newImages) }})</flux:subheading>
                                    <div class="grid grid-cols-3 gap-2 mt-2">
                                        @foreach($newImages as $image)
                                            <img src="{{ $image->temporaryUrl() }}" alt="Preview" 
                                                 class="w-full h-16 object-cover rounded border border-zinc-200 dark:border-zinc-600">
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <!-- Attributes Summary -->
                            @php $filledAttributes = array_filter($attributeValues) @endphp
                            @if(!empty($filledAttributes))
                                <div>
                                    <flux:subheading>Attributes ({{ count($filledAttributes) }})</flux:subheading>
                                    <div class="bg-zinc-50 dark:bg-zinc-900 rounded-lg p-4 space-y-2">
                                        @foreach($filledAttributes as $key => $value)
                                            @php $attr = $attributeDefinitions->where('key', $key)->first() @endphp
                                            @if($attr)
                                                <div class="flex justify-between">
                                                    <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $attr->label }}:</span>
                                                    <span class="text-sm text-zinc-900 dark:text-zinc-100">{{ $value }}</span>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Variants Summary -->
                        <div>
                            <flux:subheading>
                                Variants ({{ $generateVariants ? count($variantMatrix) : count($customVariants) }})
                            </flux:subheading>
                            
                            @php $variants = $generateVariants ? $variantMatrix : $customVariants @endphp
                            
                            @if(!empty($variants))
                                <div class="bg-zinc-50 dark:bg-zinc-900 rounded-lg p-4 max-h-64 overflow-y-auto">
                                    <div class="space-y-2">
                                        @foreach($variants as $index => $variant)
                                            <div class="flex items-center justify-between py-2 border-b border-zinc-200 dark:border-zinc-700 last:border-0">
                                                <div class="flex-1">
                                                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                        {{ $variant['sku'] }}
                                                    </span>
                                                    <div class="flex items-center gap-2 mt-1">
                                                        @if($variant['color'])
                                                            <flux:badge variant="outline" class="text-xs">{{ $variant['color'] }}</flux:badge>
                                                        @endif
                                                        @if($variant['width'])
                                                            <flux:badge variant="outline" class="text-xs">W: {{ $variant['width'] }}</flux:badge>
                                                        @endif
                                                        @if($variant['drop'])
                                                            <flux:badge variant="outline" class="text-xs">D: {{ $variant['drop'] }}</flux:badge>
                                                        @endif
                                                    </div>
                                                    @if($assignBarcodes && isset($variantBarcodes[$index]))
                                                        <div class="mt-1">
                                                            <code class="bg-green-100 dark:bg-green-900/20 px-2 py-1 rounded text-xs">
                                                                {{ $variantBarcodes[$index] }}
                                                            </code>
                                                        </div>
                                                    @endif
                                                </div>
                                                <span class="text-sm text-zinc-600 dark:text-zinc-400">
                                                    Stock: {{ $variant['stock_level'] ?? 0 }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <div class="text-center py-8 border-2 border-dashed border-zinc-300 dark:border-zinc-600 rounded-lg">
                                    <flux:icon name="exclamation-triangle" class="mx-auto h-8 w-8 text-orange-400" />
                                    <p class="mt-2 text-sm text-orange-600">No variants configured</p>
                                    <p class="text-xs text-zinc-500">Go back to step 5 to add variants</p>
                                </div>
                            @endif
                        </div>

                        <!-- Barcode Summary -->
                        @if($assignBarcodes && !empty($variantBarcodes))
                            <div>
                                <flux:subheading>Barcode Assignment Summary</flux:subheading>
                                <div class="bg-zinc-50 dark:bg-zinc-900 rounded-lg p-4">
                                    <div class="grid grid-cols-3 gap-4 text-center">
                                        <div>
                                            <div class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                                                {{ $barcodeType }}
                                            </div>
                                            <div class="text-xs text-zinc-600 dark:text-zinc-400">Barcode Type</div>
                                        </div>
                                        <div>
                                            <div class="text-lg font-semibold text-green-600">
                                                {{ count(array_filter($variantBarcodes)) }}
                                            </div>
                                            <div class="text-xs text-zinc-600 dark:text-zinc-400">Barcodes Assigned</div>
                                        </div>
                                        <div>
                                            <div class="text-lg font-semibold text-blue-600">
                                                {{ ucfirst($barcodeAssignmentMethod) }}
                                            </div>
                                            <div class="text-xs text-zinc-600 dark:text-zinc-400">Assignment Method</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @elseif(!$assignBarcodes)
                            <div>
                                <flux:subheading>Barcode Assignment</flux:subheading>
                                <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg p-4 text-center">
                                    <flux:icon name="exclamation-triangle" class="mx-auto h-6 w-6 text-orange-600 mb-2" />
                                    <div class="text-sm text-orange-700 dark:text-orange-300">
                                        Barcode assignment is disabled. Variants will be created without barcodes.
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Create Button -->
                    <div class="flex justify-center pt-6">
                        <flux:button wire:click="createProduct" variant="primary" icon="sparkles" 
                                    class="px-8">
                            Create Product & Variants
                        </flux:button>
                    </div>
                </div>
            @endif
        </div>

        <!-- Navigation Footer -->
        <div class="bg-zinc-50 dark:bg-zinc-900 px-6 py-4 border-t border-zinc-200 dark:border-zinc-700 rounded-b-xl">
            <div class="flex items-center justify-between">
                <div>
                    @if($currentStep > 1)
                        <flux:button wire:click="previousStep" variant="outline" icon="chevron-left">
                            Previous
                        </flux:button>
                    @endif
                </div>

                <div class="text-sm text-zinc-600 dark:text-zinc-400">
                    Step {{ $currentStep }} of {{ $totalSteps }}
                </div>

                <div>
                    @if($currentStep < $totalSteps)
                        <flux:button wire:click="nextStep" variant="primary" icon-trailing="chevron-right">
                            Next Step
                        </flux:button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div wire:loading.flex wire:target="createProduct" 
         class="fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50">
        <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 max-w-sm mx-4">
            <div class="flex items-center space-x-3">
                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600"></div>
                <span class="text-sm font-medium">Creating product...</span>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('step-changed', (event) => {
            // Smooth scroll to top when step changes
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });
</script>