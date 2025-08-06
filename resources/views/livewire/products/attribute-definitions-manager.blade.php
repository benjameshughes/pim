<div class="max-w-7xl mx-auto space-y-6">
    <!-- Header -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
        <div class="p-6">
            <x-breadcrumb :items="[
                ['name' => 'Products', 'url' => route('products.index')],
                ['name' => 'Attribute Definitions']
            ]" class="mb-4" />
            
            <div class="flex items-start justify-between">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-4 mb-3">
                        <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center">
                            <flux:icon name="settings" class="h-6 w-6 text-white" />
                        </div>
                        <div>
                            <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-100 font-semibold">
                                Attribute Definitions
                            </flux:heading>
                            <flux:subheading class="text-zinc-600 dark:text-zinc-400">
                                Define and manage custom attributes for products and variants
                            </flux:subheading>
                        </div>
                    </div>
                </div>
                
                <flux:button wire:click="createAttribute" variant="primary" icon="plus">
                    Create Attribute
                </flux:button>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
            <div class="text-2xl font-bold text-blue-600">{{ number_format($stats['total']) }}</div>
            <div class="text-sm text-zinc-600 dark:text-zinc-400">Total Attributes</div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
            <div class="text-2xl font-bold text-green-600">{{ number_format($stats['active']) }}</div>
            <div class="text-sm text-zinc-600 dark:text-zinc-400">Active</div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
            <div class="text-2xl font-bold text-purple-600">{{ number_format($stats['product_only']) }}</div>
            <div class="text-sm text-zinc-600 dark:text-zinc-400">Product Only</div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
            <div class="text-2xl font-bold text-orange-600">{{ number_format($stats['variant_only']) }}</div>
            <div class="text-sm text-zinc-600 dark:text-zinc-400">Variant Only</div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
            <div class="text-2xl font-bold text-indigo-600">{{ number_format($stats['both']) }}</div>
            <div class="text-sm text-zinc-600 dark:text-zinc-400">Both Types</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <flux:field>
                <flux:label>Search</flux:label>
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Search attributes..." icon="search" />
            </flux:field>

            <flux:field>
                <flux:label>Category</flux:label>
                <flux:select wire:model.live="categoryFilter">
                    <flux:select.option value="">All Categories</flux:select.option>
                    @foreach($categories as $category)
                        <flux:select.option value="{{ $category }}">{{ ucfirst($category) }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>Applies To</flux:label>
                <flux:select wire:model.live="appliesFilter">
                    <flux:select.option value="">All Types</flux:select.option>
                    <flux:select.option value="product">Products Only</flux:select.option>
                    <flux:select.option value="variant">Variants Only</flux:select.option>
                    <flux:select.option value="both">Both</flux:select.option>
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>Status</flux:label>
                <flux:select wire:model.live="activeFilter">
                    <flux:select.option value="">All Status</flux:select.option>
                    <flux:select.option value="active">Active Only</flux:select.option>
                    <flux:select.option value="inactive">Inactive Only</flux:select.option>
                </flux:select>
            </flux:field>
        </div>
    </div>

    <!-- Attributes Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
        <div class="p-6 border-b border-zinc-200 dark:border-zinc-700">
            <flux:heading size="lg">Attribute Definitions</flux:heading>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            Attribute
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            Data Type
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            Category
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            Applies To
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            Rules
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($attributes as $attribute)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                            <td class="px-6 py-4">
                                <div>
                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $attribute->label }}
                                    </div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400 font-mono">
                                        {{ $attribute->key }}
                                    </div>
                                    @if($attribute->description)
                                        <div class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">
                                            {{ Str::limit($attribute->description, 50) }}
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <flux:badge variant="outline" class="text-xs">
                                    {{ ucfirst($attribute->data_type) }}
                                </flux:badge>
                                @if($attribute->is_required)
                                    <flux:badge variant="outline" class="text-xs ml-1 text-red-600 border-red-200">
                                        Required
                                    </flux:badge>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm text-zinc-900 dark:text-zinc-100">
                                    {{ ucfirst($attribute->category) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                @if($attribute->applies_to === 'both')
                                    <flux:badge variant="outline" class="text-xs text-purple-600 border-purple-200">
                                        Both
                                    </flux:badge>
                                @elseif($attribute->applies_to === 'product')
                                    <flux:badge variant="outline" class="text-xs text-blue-600 border-blue-200">
                                        Product
                                    </flux:badge>
                                @else
                                    <flux:badge variant="outline" class="text-xs text-green-600 border-green-200">
                                        Variant
                                    </flux:badge>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($attribute->validation_rules)
                                    <div class="text-xs text-zinc-600 dark:text-zinc-400">
                                        @php $rules = $attribute->validation_rules; @endphp
                                        @if(isset($rules['min']) || isset($rules['max']))
                                            Range: {{ $rules['min'] ?? '∞' }} - {{ $rules['max'] ?? '∞' }}
                                        @endif
                                        @if(isset($rules['options']))
                                            Options: {{ count($rules['options']) }} choices
                                        @endif
                                    </div>
                                @else
                                    <span class="text-xs text-zinc-400">No rules</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($attribute->is_active)
                                    <flux:badge variant="outline" class="text-xs text-green-600 border-green-200">
                                        Active
                                    </flux:badge>
                                @else
                                    <flux:badge variant="outline" class="text-xs text-zinc-600 border-zinc-200">
                                        Inactive
                                    </flux:badge>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button size="sm" variant="ghost" wire:click="editAttribute({{ $attribute->id }})" icon="pencil">
                                        Edit
                                    </flux:button>
                                    <flux:button size="sm" variant="ghost" wire:click="toggleStatus({{ $attribute->id }})" 
                                        icon="{{ $attribute->is_active ? 'pause' : 'play' }}">
                                        {{ $attribute->is_active ? 'Disable' : 'Enable' }}
                                    </flux:button>
                                    <flux:button size="sm" variant="ghost" wire:click="deleteAttribute({{ $attribute->id }})" 
                                        wire:confirm="Are you sure you want to delete this attribute definition?" icon="trash-2" class="text-red-600">
                                        Delete
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <flux:icon name="settings" class="h-12 w-12 text-zinc-400 mb-4" />
                                    <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400 mb-2">
                                        No attribute definitions found
                                    </flux:heading>
                                    <p class="text-zinc-600 dark:text-zinc-400 mb-4">
                                        Create your first attribute definition to get started.
                                    </p>
                                    <flux:button wire:click="createAttribute" variant="primary" icon="plus">
                                        Create Attribute
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($attributes->hasPages())
            <div class="px-6 py-4 border-t border-zinc-200 dark:border-zinc-700">
                {{ $attributes->links() }}
            </div>
        @endif
    </div>

    <!-- Create/Edit Modal -->
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex min-h-screen items-center justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div wire:click="closeModal" class="fixed inset-0 bg-zinc-500 bg-opacity-75 transition-opacity"></div>

                <div class="inline-block transform overflow-hidden rounded-lg bg-white dark:bg-zinc-800 text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl sm:align-middle">
                    <div class="bg-white dark:bg-zinc-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <flux:heading size="lg" class="mb-6">{{ $modalTitle }}</flux:heading>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <flux:field>
                                <flux:label>Key *</flux:label>
                                <flux:input wire:model="key" placeholder="e.g., material_type" />
                                @error('key') <flux:description class="text-red-600">{{ $message }}</flux:description> @enderror
                            </flux:field>

                            <flux:field>
                                <flux:label>Label *</flux:label>
                                <flux:input wire:model="label" placeholder="e.g., Material Type" />
                                @error('label') <flux:description class="text-red-600">{{ $message }}</flux:description> @enderror
                            </flux:field>

                            <flux:field>
                                <flux:label>Data Type *</flux:label>
                                <flux:select wire:model.live="data_type">
                                    <flux:select.option value="string">Text</flux:select.option>
                                    <flux:select.option value="number">Number</flux:select.option>
                                    <flux:select.option value="boolean">Boolean</flux:select.option>
                                    <flux:select.option value="json">JSON</flux:select.option>
                                </flux:select>
                                @error('data_type') <flux:description class="text-red-600">{{ $message }}</flux:description> @enderror
                            </flux:field>

                            <flux:field>
                                <flux:label>Category *</flux:label>
                                <flux:input wire:model="category" placeholder="e.g., physical, technical, marketing" />
                                @error('category') <flux:description class="text-red-600">{{ $message }}</flux:description> @enderror
                            </flux:field>

                            <flux:field>
                                <flux:label>Applies To *</flux:label>
                                <flux:select wire:model="applies_to">
                                    <flux:select.option value="product">Products Only</flux:select.option>
                                    <flux:select.option value="variant">Variants Only</flux:select.option>
                                    <flux:select.option value="both">Both Products & Variants</flux:select.option>
                                </flux:select>
                                @error('applies_to') <flux:description class="text-red-600">{{ $message }}</flux:description> @enderror
                            </flux:field>

                            <flux:field>
                                <flux:label>Sort Order</flux:label>
                                <flux:input wire:model="sort_order" type="number" min="0" />
                                @error('sort_order') <flux:description class="text-red-600">{{ $message }}</flux:description> @enderror
                            </flux:field>
                        </div>

                        <div class="mt-4">
                            <flux:field>
                                <flux:label>Description</flux:label>
                                <textarea wire:model="description" class="w-full px-3 py-2 border border-zinc-300 dark:border-zinc-600 rounded-md bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 placeholder-zinc-500 dark:placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" rows="3" placeholder="Optional description for this attribute..."></textarea>
                                @error('description') <flux:description class="text-red-600">{{ $message }}</flux:description> @enderror
                            </flux:field>
                        </div>

                        @if($data_type === 'number')
                            <div class="mt-4 grid grid-cols-2 gap-4">
                                <flux:field>
                                    <flux:label>Minimum Value</flux:label>
                                    <flux:input wire:model="min_value" type="number" step="any" placeholder="Optional" />
                                </flux:field>
                                <flux:field>
                                    <flux:label>Maximum Value</flux:label>
                                    <flux:input wire:model="max_value" type="number" step="any" placeholder="Optional" />
                                </flux:field>
                            </div>
                        @endif

                        <div class="mt-4">
                            <flux:field>
                                <flux:label>Valid Options</flux:label>
                                <flux:input wire:model="options" placeholder="Option 1, Option 2, Option 3" />
                                <flux:description>Comma-separated list of valid options (optional)</flux:description>
                            </flux:field>
                        </div>

                        <div class="mt-4 space-y-3">
                            <flux:checkbox wire:model="is_required">
                                Required field
                            </flux:checkbox>
                            <flux:checkbox wire:model="is_active">
                                Active
                            </flux:checkbox>
                        </div>
                    </div>

                    <div class="bg-zinc-50 dark:bg-zinc-900 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                        <flux:button wire:click="saveAttribute" variant="primary" class="sm:ml-3">
                            {{ $editingAttribute ? 'Update' : 'Create' }}
                        </flux:button>
                        <flux:button wire:click="closeModal" variant="outline">
                            Cancel
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>