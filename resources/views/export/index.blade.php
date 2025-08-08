<x-layouts.app.sidebar>
    <x-page-template 
        title="Export Data"
        :breadcrumbs="[
            ['name' => 'Dashboard', 'url' => route('dashboard')],
            ['name' => 'Export Data']
        ]"
        :actions="[
            [
                'type' => 'link',
                'label' => 'Import Data',
                'href' => route('import.index'),
                'variant' => 'outline',
                'icon' => 'arrow-down-tray'
            ]
        ]"
    >
        <x-slot:subtitle>
            Export your product data to various formats and marketplaces
        </x-slot:subtitle>

        <div class="text-center py-16">
            <div class="mx-auto h-16 w-16 text-zinc-400 mb-4">
                <flux:icon name="arrow-up-tray" class="h-16 w-16" />
            </div>
            <h3 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100 mb-2">
                Export Features Coming Soon
            </h3>
            <p class="text-zinc-600 dark:text-zinc-400 mb-6 max-w-md mx-auto">
                We're working on comprehensive export functionality. In the meantime, you can export to Shopify from the products section.
            </p>
            <div class="flex items-center justify-center gap-4">
                <flux:button 
                    href="{{ route('products.export.shopify') }}"
                    variant="primary"
                    icon="shopping-bag"
                    wire:navigate
                >
                    Export to Shopify
                </flux:button>
                <flux:button 
                    href="{{ route('import.index') }}"
                    variant="outline"
                    icon="arrow-down-tray"
                    wire:navigate
                >
                    Import Data Instead
                </flux:button>
            </div>
        </div>
    </x-page-template>
</x-layouts.app.sidebar>