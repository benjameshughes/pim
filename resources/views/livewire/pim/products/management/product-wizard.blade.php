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
            <div class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    @for($i = 1; $i <= $totalSteps; $i++)
                        <div class="flex items-center {{ $i < $totalSteps ? 'flex-1' : '' }}">
                            <!-- Step indicator -->
                            <div class="relative flex flex-col items-center">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-medium transition-all duration-200 z-10
                                    @if($i < $currentStep || in_array($i, $completedSteps)) 
                                        bg-indigo-600 text-white shadow-lg
                                    @elseif($i == $currentStep) 
                                        bg-white dark:bg-zinc-800 text-indigo-600 ring-4 ring-indigo-600 ring-opacity-20 shadow-lg border-2 border-indigo-600
                                    @else 
                                        bg-zinc-100 dark:bg-zinc-700 text-zinc-500 dark:text-zinc-400 border-2 border-zinc-200 dark:border-zinc-600
                                    @endif">
                                    @if($i < $currentStep || in_array($i, $completedSteps))
                                        <flux:icon name="check" class="h-5 w-5" />
                                    @else
                                        {{ $i }}
                                    @endif
                                </div>
                                <span class="text-xs mt-3 text-center text-zinc-600 dark:text-zinc-400 font-medium max-w-16 leading-tight">
                                    @switch($i)
                                        @case(1) Basic Info @break
                                        @case(2) Images @break
                                        @case(3) Features @break
                                        @case(4) Attributes @break
                                        @case(5) Variants @break
                                        @case(6) Barcodes @break
                                        @case(7) Review @break
                                    @endswitch
                                </span>
                            </div>
                            
                            <!-- Connector line -->
                            @if($i < $totalSteps)
                                <div class="flex-1 h-1 mx-4 rounded-full
                                    {{ $i < $currentStep || in_array($i, $completedSteps) 
                                        ? 'bg-indigo-600' 
                                        : 'bg-zinc-200 dark:bg-zinc-700' 
                                    }}">
                                </div>
                            @endif
                        </div>
                    @endfor
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
                <div class="space-y-8">
                    <flux:heading size="lg" class="flex items-center gap-3 border-b border-zinc-200 dark:border-zinc-700 pb-4">
                        <div class="w-8 h-8 bg-indigo-100 dark:bg-indigo-900 rounded-lg flex items-center justify-center">
                            <flux:icon name="information-circle" class="w-4 h-4 text-indigo-600" />
                        </div>
                        Basic Product Information
                    </flux:heading>
                    
                    <!-- Primary Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <flux:field>
                            <flux:label>Product Name *</flux:label>
                            <flux:input wire:model.live.debounce.500ms="form.name" 
                                       placeholder="e.g., Premium Window Shade"
                                       class="text-lg" />
                            @error('form.name') <flux:error>{{ $message }}</flux:error> @enderror
                            <flux:description>This will be the main product title visible to customers</flux:description>
                        </flux:field>

                        <flux:field>
                            <flux:label>Status *</flux:label>
                            <flux:select wire:model="form.status">
                                <flux:select.option value="active">Active - Ready for sale</flux:select.option>
                                <flux:select.option value="inactive">Inactive - Hidden from customers</flux:select.option>
                                <flux:select.option value="discontinued">Discontinued - No longer available</flux:select.option>
                            </flux:select>
                            @error('form.status') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>
                    </div>

                    <!-- Technical Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <flux:field>
                            <flux:label class="flex items-center gap-2">
                                Parent SKU *
                                <flux:badge variant="outline" class="text-xs">Auto-generated</flux:badge>
                            </flux:label>
                            <div class="flex gap-3">
                                <flux:input wire:model.live.debounce.500ms="parentSku" 
                                           placeholder="001" 
                                           class="font-mono {{ $skuConflictProduct ? 'border-red-500' : '' }}" />
                                <flux:button wire:click="regenerateParentSku" 
                                            variant="outline" 
                                            size="sm" 
                                            icon="sparkles" 
                                            title="Generate new Parent SKU"
                                            class="shrink-0">
                                </flux:button>
                            </div>
                            @error('parentSku') <flux:error>{{ $message }}</flux:error> @enderror
                            @if($skuConflictProduct)
                                <div class="mt-3 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <flux:icon name="exclamation-triangle" class="h-4 w-4 text-red-500 mr-2 shrink-0" />
                                            <span class="text-sm text-red-700 dark:text-red-300">
                                                SKU conflict with: <strong>{{ $skuConflictProduct }}</strong>
                                            </span>
                                        </div>
                                        <flux:button wire:click="regenerateParentSku" 
                                                    variant="outline" 
                                                    size="sm" 
                                                    class="text-red-600 border-red-300 hover:bg-red-50 shrink-0 ml-3">
                                            Generate New
                                        </flux:button>
                                    </div>
                                </div>
                            @else
                                <flux:description>
                                    Variants will use this as prefix: {{ $parentSku }}-001, {{ $parentSku }}-002, etc.
                                </flux:description>
                            @endif
                        </flux:field>

                        <flux:field>
                            <flux:label>URL Slug</flux:label>
                            <flux:input wire:model="form.slug" 
                                       placeholder="Auto-generated from name"
                                       class="font-mono" />
                            @error('form.slug') <flux:error>{{ $message }}</flux:error> @enderror
                            <flux:description>Leave blank to auto-generate from product name</flux:description>
                        </flux:field>
                    </div>

                    <!-- Description - Full width for better editing -->
                    <flux:field>
                        <flux:label>Product Description</flux:label>
                        <flux:textarea wire:model="form.description" 
                                      rows="5" 
                                      placeholder="Describe your product features, materials, benefits, and any important details customers should know..."
                                      class="resize-none" />
                        @error('form.description') <flux:error>{{ $message }}</flux:error> @enderror
                        <flux:description>Provide a detailed description that will help customers understand your product</flux:description>
                    </flux:field>
                </div>
            @endif

            <!-- Step 2: Product Images -->
            @if($currentStep === 2)
                <div class="space-y-6">
                    <flux:heading size="lg" class="flex items-center gap-3 border-b border-zinc-200 dark:border-zinc-700 pb-4 mb-8">
                        <div class="w-8 h-8 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center">
                            <flux:icon name="photo" class="w-4 h-4 text-green-600" />
                        </div>
                        Product Images
                        <flux:badge variant="outline" class="ml-auto text-xs">
                            Optional but recommended
                        </flux:badge>
                    </flux:heading>
                    
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
                    <flux:heading size="lg" class="flex items-center gap-3 border-b border-zinc-200 dark:border-zinc-700 pb-4 mb-8">
                        <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                            <flux:icon name="list-bullet" class="w-4 h-4 text-blue-600" />
                        </div>
                        Product Features & Details
                        <flux:badge variant="outline" class="ml-auto text-xs">
                            Optional content
                        </flux:badge>
                    </flux:heading>
                    
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
                    <flux:heading size="lg" class="flex items-center gap-3 border-b border-zinc-200 dark:border-zinc-700 pb-4 mb-8">
                        <div class="w-8 h-8 bg-purple-100 dark:bg-purple-900 rounded-lg flex items-center justify-center">
                            <flux:icon name="tag" class="w-4 h-4 text-purple-600" />
                        </div>
                        Product Attributes
                    </flux:heading>
                    
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
                    <flux:heading size="lg" class="flex items-center gap-3 border-b border-zinc-200 dark:border-zinc-700 pb-4 mb-8">
                        <div class="w-8 h-8 bg-gradient-to-r from-indigo-100 to-purple-100 dark:from-indigo-900 dark:to-purple-900 rounded-lg flex items-center justify-center">
                            <flux:icon name="squares-2x2" class="w-4 h-4 text-indigo-600" />
                        </div>
                        Product Variants
                        <flux:badge variant="outline" class="ml-auto text-xs bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-900/50 dark:to-purple-900/50">
                            Smart Generation
                        </flux:badge>
                    </flux:heading>
                    
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
                                
                                <!-- Smart Color Input & Selection 🎨 -->
                                <div class="bg-gradient-to-r from-purple-50 to-pink-50 dark:from-purple-900/10 dark:to-pink-900/10 rounded-xl p-6 border border-purple-200 dark:border-purple-800 space-y-4 group">
                                    <flux:label class="flex items-center gap-3">
                                        <div class="w-6 h-6 bg-gradient-to-r from-purple-500 to-pink-500 rounded-lg flex items-center justify-center">
                                            <flux:icon name="palette" class="w-4 h-4 text-white" />
                                        </div>
                                        <span class="font-semibold">Colors Selection</span>
                                        <flux:badge variant="outline" class="text-xs bg-white/80 dark:bg-zinc-800/80">
                                            Smart Suggestions
                                        </flux:badge>
                                    </flux:label>
                                    
                                    <!-- Color Input with Smart Suggestions -->
                                    <div x-data="{ 
                                        showSuggestions: false,
                                        addColorAndClear(color) {
                                            $wire.addColor(color).then(() => {
                                                $wire.set('colorInput', '');
                                                this.showSuggestions = false;
                                            });
                                        }
                                    }" class="relative">
                                        <flux:input 
                                            wire:model.live="colorInput" 
                                            placeholder="Type to search colors (e.g., 'Bl' for Black/Blue) or add custom..."
                                            x-on:focus="showSuggestions = true"
                                            x-on:click.away="showSuggestions = false"
                                            x-on:keydown.enter.prevent="if ($wire.colorInput.trim()) { addColorAndClear($wire.colorInput.trim()); }"
                                            x-on:keydown.escape="showSuggestions = false" />
                                        
                                        <!-- Color Suggestions Dropdown -->
                                        @if($colorInput || count($this->allColors) > 0)
                                            <div x-show="showSuggestions" 
                                                 x-transition:enter="transition ease-out duration-100"
                                                 x-transition:enter-start="opacity-0 scale-95"
                                                 x-transition:enter-end="opacity-100 scale-100"
                                                 class="absolute z-50 w-full mt-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-lg max-h-64 overflow-y-auto">
                                                
                                                @php
                                                    $filteredColors = $colorInput 
                                                        ? $this->allColors->filter(fn($color) => str_contains(strtolower($color), strtolower($colorInput)))->take(8)
                                                        : $this->allColors->take(8);
                                                @endphp
                                                
                                                @foreach($filteredColors as $color)
                                                    <div class="flex items-center gap-3 px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-700 cursor-pointer border-b border-zinc-100 dark:border-zinc-600 last:border-0"
                                                         x-on:click="addColorAndClear('{{ $color }}')">
                                                        <div class="w-4 h-4 rounded border-2 border-zinc-300 dark:border-zinc-600" 
                                                             style="background-color: {{ strtolower($color) === 'white' ? '#ffffff' : (strtolower($color) === 'grey' ? '#808080' : strtolower($color)) }}"></div>
                                                        <span class="flex-1">{{ $color }}</span>
                                                        @if(!in_array($color, $commonColors))
                                                            <span class="text-xs px-2 py-1 bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300 rounded">Custom</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                                
                                                <!-- Add Custom Color Option -->
                                                @if($colorInput && !$this->allColors->contains(trim($colorInput)))
                                                    <div class="flex items-center gap-3 px-4 py-3 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 cursor-pointer border-t border-zinc-200 dark:border-zinc-600"
                                                         x-on:click="addColorAndClear('{{ trim($colorInput) }}')">
                                                        <flux:icon name="plus" class="w-4 h-4 text-indigo-500" />
                                                        <span class="flex-1">Add "{{ trim($colorInput) }}" as custom color</span>
                                                        <span class="text-xs px-2 py-1 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 rounded">New</span>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                    
                                    <!-- Selected Colors Tags -->
                                    @if(!empty($selectedColors))
                                        <div class="flex flex-wrap gap-2 mt-3" wire:key="selected-colors-{{ count($selectedColors) }}">
                                            @foreach($selectedColors as $index => $color)
                                                <flux:badge variant="outline" 
                                                           wire:key="color-badge-{{ $index }}-{{ $color }}"
                                                           class="flex items-center gap-2 hover:shadow-md transition-all duration-200 hover:scale-105 animate-in fade-in slide-in-from-bottom-2 duration-300"
                                                           style="animation-delay: {{ $index * 50 }}ms">
                                                    <div class="w-3 h-3 rounded border border-zinc-400 ring-2 ring-transparent group-hover:ring-zinc-300 transition-all duration-200" 
                                                         style="background-color: {{ strtolower($color) === 'white' ? '#ffffff' : (strtolower($color) === 'grey' ? '#808080' : strtolower($color)) }}"></div>
                                                    {{ $color }}
                                                    <button wire:click="removeColor({{ $index }})" 
                                                            class="ml-1 hover:text-red-600 hover:bg-red-100 dark:hover:bg-red-900/20 rounded-full p-0.5 transition-all duration-200">
                                                        <flux:icon name="x" class="w-3 h-3" />
                                                    </button>
                                                </flux:badge>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <!-- Smart Width Input & Selection 📏 -->
                                <div class="bg-gradient-to-r from-blue-50 to-cyan-50 dark:from-blue-900/10 dark:to-cyan-900/10 rounded-xl p-6 border border-blue-200 dark:border-blue-800 space-y-4 group">
                                    <flux:label class="flex items-center gap-3">
                                        <div class="w-6 h-6 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-lg flex items-center justify-center">
                                            <flux:icon name="ruler" class="w-4 h-4 text-white" />
                                        </div>
                                        <span class="font-semibold">Width Selection</span>
                                        <flux:badge variant="outline" class="text-xs bg-white/80 dark:bg-zinc-800/80">
                                            Auto-format cm
                                        </flux:badge>
                                    </flux:label>
                                    
                                    <!-- Width Input with Smart Suggestions -->
                                    <div x-data="{ 
                                        showSuggestions: false,
                                        addWidthAndClear(width) {
                                            $wire.addWidth(width).then(() => {
                                                $wire.set('widthInput', '');
                                                this.showSuggestions = false;
                                            });
                                        }
                                    }" class="relative">
                                        <flux:input 
                                            wire:model.live="widthInput" 
                                            placeholder="Type width (e.g., '120' or '120cm') or select from suggestions..."
                                            x-on:focus="showSuggestions = true"
                                            x-on:click.away="showSuggestions = false"
                                            x-on:keydown.enter.prevent="if ($wire.widthInput.trim()) { addWidthAndClear($wire.widthInput.trim()); }"
                                            x-on:keydown.escape="showSuggestions = false" />
                                        
                                        <!-- Width Suggestions Dropdown -->
                                        @if($widthInput || count($this->allWidths) > 0)
                                            <div x-show="showSuggestions" 
                                                 x-transition:enter="transition ease-out duration-100"
                                                 x-transition:enter-start="opacity-0 scale-95"
                                                 x-transition:enter-end="opacity-100 scale-100"
                                                 class="absolute z-50 w-full mt-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-lg max-h-64 overflow-y-auto">
                                                
                                                @php
                                                    $filteredWidths = $widthInput 
                                                        ? $this->allWidths->filter(fn($width) => str_contains(strtolower($width), strtolower($widthInput)))->take(8)
                                                        : $this->allWidths->take(8);
                                                @endphp
                                                
                                                @foreach($filteredWidths as $width)
                                                    <div class="flex items-center justify-between px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-700 cursor-pointer border-b border-zinc-100 dark:border-zinc-600 last:border-0"
                                                         x-on:click="addWidthAndClear('{{ $width }}')">
                                                        <span class="flex-1">{{ $width }}</span>
                                                        @if(!in_array($width, $commonWidths))
                                                            <span class="text-xs px-2 py-1 bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300 rounded">Custom</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                                
                                                <!-- Add Custom Width Option -->
                                                @if($widthInput && !$this->allWidths->contains(trim($widthInput)) && !$this->allWidths->contains(trim($widthInput) . 'cm'))
                                                    <div class="flex items-center gap-3 px-4 py-3 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 cursor-pointer border-t border-zinc-200 dark:border-zinc-600"
                                                         x-on:click="addWidthAndClear('{{ trim($widthInput) }}')">
                                                        <flux:icon name="plus" class="w-4 h-4 text-indigo-500" />
                                                        <span class="flex-1">Add "{{ is_numeric(trim($widthInput)) ? trim($widthInput) . 'cm' : trim($widthInput) }}" as width</span>
                                                        <span class="text-xs px-2 py-1 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 rounded">New</span>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                    
                                    <!-- Selected Widths Tags -->
                                    @if(!empty($selectedWidths))
                                        <div class="flex flex-wrap gap-2 mt-3">
                                            @foreach($selectedWidths as $index => $width)
                                                <flux:badge variant="outline" 
                                                           class="flex items-center gap-2 bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800 hover:shadow-md transition-all duration-200 hover:scale-105 animate-in fade-in slide-in-from-left-2 duration-300"
                                                           style="animation-delay: {{ $index * 50 }}ms">
                                                    <flux:icon name="ruler" class="w-3 h-3 text-blue-600" />
                                                    {{ $width }}
                                                    <button wire:click="removeWidth({{ $index }})" 
                                                            class="ml-1 hover:text-red-600 hover:bg-red-100 dark:hover:bg-red-900/20 rounded-full p-0.5 transition-all duration-200">
                                                        <flux:icon name="x" class="w-3 h-3" />
                                                    </button>
                                                </flux:badge>
                                            @endforeach
                                        </div>
                                    @endif
                                    <flux:description>Window shade widths (auto-formats numbers to cm)</flux:description>
                                </div>

                                <!-- Smart Drop/Length Input & Selection 📐 -->
                                <div class="bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/10 dark:to-emerald-900/10 rounded-xl p-6 border border-green-200 dark:border-green-800 space-y-4 group">
                                    <flux:label class="flex items-center gap-3">
                                        <div class="w-6 h-6 bg-gradient-to-r from-green-500 to-emerald-500 rounded-lg flex items-center justify-center">
                                            <flux:icon name="arrow-down" class="w-4 h-4 text-white" />
                                        </div>
                                        <span class="font-semibold">Drop/Length Selection</span>
                                        <flux:badge variant="outline" class="text-xs bg-white/80 dark:bg-zinc-800/80">
                                            Auto-format cm
                                        </flux:badge>
                                    </flux:label>
                                    
                                    <!-- Drop Input with Smart Suggestions -->
                                    <div x-data="{ 
                                        showSuggestions: false,
                                        addDropAndClear(drop) {
                                            $wire.addDrop(drop).then(() => {
                                                $wire.set('dropInput', '');
                                                this.showSuggestions = false;
                                            });
                                        }
                                    }" class="relative">
                                        <flux:input 
                                            wire:model.live="dropInput" 
                                            placeholder="Type drop length (e.g., '200' or '200cm') or select from suggestions..."
                                            x-on:focus="showSuggestions = true"
                                            x-on:click.away="showSuggestions = false"
                                            x-on:keydown.enter.prevent="if ($wire.dropInput.trim()) { addDropAndClear($wire.dropInput.trim()); }"
                                            x-on:keydown.escape="showSuggestions = false" />
                                        
                                        <!-- Drop Suggestions Dropdown -->
                                        @if($dropInput || count($this->allDrops) > 0)
                                            <div x-show="showSuggestions" 
                                                 x-transition:enter="transition ease-out duration-100"
                                                 x-transition:enter-start="opacity-0 scale-95"
                                                 x-transition:enter-end="opacity-100 scale-100"
                                                 class="absolute z-50 w-full mt-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-lg max-h-64 overflow-y-auto">
                                                
                                                @php
                                                    $filteredDrops = $dropInput 
                                                        ? $this->allDrops->filter(fn($drop) => str_contains(strtolower($drop), strtolower($dropInput)))->take(8)
                                                        : $this->allDrops->take(8);
                                                @endphp
                                                
                                                @foreach($filteredDrops as $drop)
                                                    <div class="flex items-center justify-between px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-700 cursor-pointer border-b border-zinc-100 dark:border-zinc-600 last:border-0"
                                                         x-on:click="addDropAndClear('{{ $drop }}')">
                                                        <span class="flex-1">{{ $drop }}</span>
                                                        @if(!in_array($drop, $commonDrops))
                                                            <span class="text-xs px-2 py-1 bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300 rounded">Custom</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                                
                                                <!-- Add Custom Drop Option -->
                                                @if($dropInput && !$this->allDrops->contains(trim($dropInput)) && !$this->allDrops->contains(trim($dropInput) . 'cm'))
                                                    <div class="flex items-center gap-3 px-4 py-3 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 cursor-pointer border-t border-zinc-200 dark:border-zinc-600"
                                                         x-on:click="addDropAndClear('{{ trim($dropInput) }}')">
                                                        <flux:icon name="plus" class="w-4 h-4 text-indigo-500" />
                                                        <span class="flex-1">Add "{{ is_numeric(trim($dropInput)) ? trim($dropInput) . 'cm' : trim($dropInput) }}" as drop</span>
                                                        <span class="text-xs px-2 py-1 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 rounded">New</span>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                    
                                    <!-- Selected Drops Tags -->
                                    @if(!empty($selectedDrops))
                                        <div class="flex flex-wrap gap-2 mt-3">
                                            @foreach($selectedDrops as $index => $drop)
                                                <flux:badge variant="outline" 
                                                           class="flex items-center gap-2 bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 hover:shadow-md transition-all duration-200 hover:scale-105 animate-in fade-in slide-in-from-right-2 duration-300"
                                                           style="animation-delay: {{ $index * 50 }}ms">
                                                    <flux:icon name="arrow-down" class="w-3 h-3 text-green-600" />
                                                    {{ $drop }}
                                                    <button wire:click="removeDrop({{ $index }})" 
                                                            class="ml-1 hover:text-red-600 hover:bg-red-100 dark:hover:bg-red-900/20 rounded-full p-0.5 transition-all duration-200">
                                                        <flux:icon name="x" class="w-3 h-3" />
                                                    </button>
                                                </flux:badge>
                                            @endforeach
                                        </div>
                                    @endif
                                    <flux:description>Window shade drops/lengths (auto-formats numbers to cm)</flux:description>
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