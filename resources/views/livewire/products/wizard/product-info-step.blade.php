<div class="p-4 space-y-4">
    {{-- Step Header --}}
    <div class="border-b border-gray-200 dark:border-gray-700 pb-3">
        <div class="flex items-center gap-3">
            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/20">
                <flux:icon name="clipboard-document-list" class="w-4 h-4 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">
                    Product Information
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Enter the basic details about your product.
                </p>
            </div>
        </div>
    </div>

    {{-- Form --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- Product Name --}}
        <div class="md:col-span-2">
            <flux:field>
                <flux:label>Product Name *</flux:label>
                <flux:input
                    wire:model.live.debounce.300ms="name"
                    placeholder="Enter product name..."
                    invalid="{{ !empty($errors['name']) }}"
                />
                @if(!empty($errors['name']))
                    <flux:error>{{ $errors['name'] }}</flux:error>
                @endif
            </flux:field>
        </div>

        {{-- Parent SKU --}}
        <div>
            <flux:field>
                <flux:label>Parent SKU</flux:label>
                <flux:input
                    wire:model.live.debounce.300ms="parent_sku"
                placeholder="e.g. BLIND-001"
                invalid="{{ !empty($errors['parent_sku']) }}"
            />
            @if(!empty($errors['parent_sku']))
                <flux:error>{{ $errors['parent_sku'] }}</flux:error>
            @endif
            <flux:description>Optional: Used to group related products</flux:description>
        </flux:field>
        </div>

        {{-- Status --}}
        <div>
            <flux:field>
                <flux:label>Product Status *</flux:label>
                <flux:select
                    wire:model.live="status"
                    placeholder="Select status..."
                    invalid="{{ !empty($errors['status']) }}"
                >
                    @foreach($this->getStatusOptions() as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                @if(!empty($errors['status']))
                    <flux:error>{{ $errors['status'] }}</flux:error>
                @endif
            </flux:field>
        </div>

        {{-- Description --}}
        <div class="md:col-span-2">
            <flux:field>
                <flux:label>Description</flux:label>
                <flux:textarea
                    wire:model.live.debounce.500ms="description"
                    rows="4"
                    placeholder="Describe your product..."
                    invalid="{{ !empty($errors['description']) }}"
                />
                @if(!empty($errors['description']))
                    <flux:error>{{ $errors['description'] }}</flux:error>
                @endif
            </flux:field>
        </div>

        {{-- Image URL --}}
        <div class="md:col-span-2">
            <flux:field>
                <flux:label>Product Image URL</flux:label>
                <flux:input
                    type="url"
                    wire:model.live.debounce.300ms="image_url"
                    placeholder="https://example.com/product-image.jpg"
                    invalid="{{ !empty($errors['image_url']) }}"
                />
                @if(!empty($errors['image_url']))
                    <flux:error>{{ $errors['image_url'] }}</flux:error>
                @endif
                <flux:description>Optional: Primary product image URL (you can upload images in Step 3)</flux:description>
            </flux:field>
        </div>
    </div>

    {{-- Preview Card --}}
    @if($name)
        <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-2 mb-3">
                <flux:icon name="eye" class="w-4 h-4 text-gray-600 dark:text-gray-400" />
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                    Preview
                </h3>
            </div>
            <div class="flex items-start gap-4">
                @if($image_url)
                    <img src="{{ $image_url }}" alt="Product preview" class="w-16 h-16 object-cover rounded-lg border border-gray-200 dark:border-gray-700">
                @else
                    <div class="w-16 h-16 bg-gray-200 dark:bg-gray-700 rounded-lg flex items-center justify-center border border-gray-200 dark:border-gray-700">
                        <flux:icon name="photo" class="w-6 h-6 text-gray-400" />
                    </div>
                @endif
                <div class="flex-1">
                    <h4 class="font-semibold text-gray-900 dark:text-white">{{ $name }}</h4>
                    @if($parent_sku)
                        <p class="text-sm text-gray-600 dark:text-gray-400">SKU: {{ $parent_sku }}</p>
                    @endif
                    @if($description)
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ Str::limit($description, 100) }}</p>
                    @endif
                    <flux:badge 
                        :color="match($status) {
                            'active' => 'green',
                            'draft' => 'yellow',
                            'inactive' => 'gray',
                            'archived' => 'red',
                            default => 'gray'
                        }"
                        size="sm"
                        class="mt-2"
                    >
                        {{ $this->getStatusOptions()[$status] ?? $status }}
                    </flux:badge>
                </div>
            </div>
        </div>
    @endif

</div>