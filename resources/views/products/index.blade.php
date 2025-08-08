<x-layouts.app.sidebar>
    <x-page-template 
        title="Products"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Products', 'current' => true]
        ]"
        :actions="[
            [
                'type' => 'link',
                'label' => 'New Product',
                'href' => route('products.create'),
                'variant' => 'primary',
                'icon' => 'plus',
                'wire:navigate' => true
            ],
            [
                'type' => 'link', 
                'label' => 'Import Data',
                'href' => route('import'),
                'variant' => 'outline',
                'icon' => 'arrow-up-tray',
                'wire:navigate' => true
            ]
        ]"
    >
        <x-slot:icon>
            <flux:icon name="cube" class="h-6 w-6 text-white" />
        </x-slot:icon>

        <x-slot:subtitle>
            Manage your product catalog and variants
        </x-slot:subtitle>

        <x-slot:stats>
            <x-stats-grid>
                <x-stats-card 
                    title="Total Products"
                    :value="$products->total()"
                    icon="cube"
                    trend="up"
                    trend-value="12%"
                    trend-label="from last month"
                />
                <x-stats-card 
                    title="Active Products"
                    :value="$products->where('status', 'active')->count()"
                    icon="check-circle"
                    color="green"
                />
                <x-stats-card 
                    title="Draft Products"
                    :value="$products->where('status', 'draft')->count()"
                    icon="document"
                    color="yellow"
                />
                <x-stats-card 
                    title="With Variants"
                    :value="$products->where('variants_count', '>', 0)->count()"
                    icon="squares-2x2"
                    color="purple"
                />
            </x-stats-grid>
        </x-slot:stats>

        <x-data-table 
            :data="$products"
            :columns="[
                [
                    'key' => 'name',
                    'label' => 'Product Name',
                    'type' => 'custom',
                    'render' => function($item) {
                        $image = $item->productImages->first();
                        return '
                            <div class=\"flex items-center gap-3\">
                                ' . ($image ? 
                                    '<img src=\"' . \Storage::url($image->path) . '\" alt=\"' . $item->name . '\" class=\"w-10 h-10 rounded-lg object-cover\">' :
                                    '<div class=\"w-10 h-10 bg-zinc-200 dark:bg-zinc-700 rounded-lg flex items-center justify-center\">
                                        <flux:icon name=\"cube\" class=\"h-5 w-5 text-zinc-500\" />
                                    </div>'
                                ) . '
                                <div>
                                    <div class=\"font-medium text-zinc-900 dark:text-zinc-100\">' . $item->name . '</div>
                                    ' . ($item->parent_sku ? '<div class=\"text-sm text-zinc-500\">SKU: ' . $item->parent_sku . '</div>' : '') . '
                                </div>
                            </div>';
                    }
                ],
                [
                    'key' => 'status',
                    'label' => 'Status', 
                    'type' => 'badge',
                    'badges' => [
                        'draft' => ['variant' => 'neutral', 'label' => 'Draft'],
                        'active' => ['variant' => 'positive', 'label' => 'Active'],
                        'inactive' => ['variant' => 'warning', 'label' => 'Inactive'],
                        'archived' => ['variant' => 'negative', 'label' => 'Archived']
                    ]
                ],
                [
                    'key' => 'variants_count',
                    'label' => 'Variants',
                    'type' => 'text',
                    'textClass' => 'text-sm font-medium text-zinc-900 dark:text-zinc-100'
                ],
                [
                    'key' => 'created_at',
                    'label' => 'Created',
                    'type' => 'date',
                    'dateFormat' => 'M j, Y'
                ],
                [
                    'key' => 'updated_at',
                    'label' => 'Updated',
                    'type' => 'date',
                    'dateFormat' => 'M j, Y g:i A'
                ]
            ]"
            :actions="[
                [
                    'type' => 'link',
                    'label' => 'View Product',
                    'icon' => 'eye',
                    'href' => fn($item) => route('products.show', $item),
                    'navigate' => true
                ],
                [
                    'type' => 'link',
                    'label' => 'Edit Product',
                    'icon' => 'pencil',
                    'href' => fn($item) => route('products.edit', $item),
                    'navigate' => true
                ]
            ]"
            :filters="[
                [
                    'key' => 'status',
                    'type' => 'select',
                    'label' => 'Status',
                    'placeholder' => 'All Statuses',
                    'options' => [
                        'draft' => 'Draft',
                        'active' => 'Active', 
                        'inactive' => 'Inactive',
                        'archived' => 'Archived'
                    ]
                ]
            ]"
            :header-actions="[
                [
                    'type' => 'button',
                    'label' => 'Refresh',
                    'action' => 'refreshProducts',
                    'variant' => 'ghost',
                    'icon' => 'arrow-clockwise',
                    'loading' => 'refreshProducts'
                ]
            ]"
            searchable
            search-placeholder="Search products by name, SKU, or description..."
            selectable
            :bulk-actions="[
                [
                    'key' => 'bulk-activate',
                    'label' => 'Activate Selected',
                    'icon' => 'check-circle',
                    'variant' => 'positive'
                ],
                [
                    'key' => 'bulk-deactivate', 
                    'label' => 'Deactivate Selected',
                    'icon' => 'x-circle',
                    'variant' => 'outline'
                ],
                [
                    'key' => 'bulk-delete',
                    'label' => 'Delete Selected',
                    'icon' => 'trash',
                    'variant' => 'negative',
                    'danger' => true
                ]
            ]"
        />

        <x-slot:emptyState>
            <div class="text-center py-12">
                <div class="mx-auto h-16 w-16 text-zinc-400 mb-4">
                    <flux:icon name="cube" class="h-16 w-16" />
                </div>
                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-2">
                    No products found
                </h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-6">
                    Start building your catalog by creating your first product.
                </p>
                <flux:button 
                    href="{{ route('products.create') }}"
                    variant="primary"
                    icon="plus"
                    wire:navigate
                >
                    Create Product
                </flux:button>
            </div>
        </x-slot:emptyState>
    </x-page-template>
</x-layouts.app.sidebar>