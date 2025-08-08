<x-page-template 
    title="Barcode Management"
    :breadcrumbs="[
        ['name' => 'Dashboard', 'url' => route('dashboard')],
        ['name' => 'Barcodes']
    ]"
    :actions="[
        [
            'type' => 'link',
            'label' => 'Pool Management',
            'href' => route('barcodes.pool.index'),
            'variant' => 'primary',
            'icon' => 'database'
        ],
        [
            'type' => 'link', 
            'label' => 'Import Pool',
            'href' => route('barcodes.pool.import'),
            'variant' => 'outline',
            'icon' => 'arrow-up-tray'
        ]
    ]"
>
    <x-slot:subtitle>
        Manage GS1 barcodes and assignment to product variants
    </x-slot:subtitle>

    <div class="text-center py-16">
        <div class="mx-auto h-16 w-16 text-zinc-400 mb-4">
            <flux:icon name="qr-code" class="h-16 w-16" />
        </div>
        <h3 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100 mb-2">
            Barcode Management Interface
        </h3>
        <p class="text-zinc-600 dark:text-zinc-400 mb-6 max-w-md mx-auto">
            The barcode management interface is being updated. You can still manage barcode pools and imports.
        </p>
        <div class="flex items-center justify-center gap-4">
            <flux:button 
                href="{{ route('barcodes.pool.index') }}"
                variant="primary"
                icon="database"
                wire:navigate
            >
                Manage Pool
            </flux:button>
            <flux:button 
                href="{{ route('barcodes.pool.import') }}"
                variant="outline"
                icon="arrow-up-tray"
                wire:navigate
            >
                Import Barcodes
            </flux:button>
        </div>
    </div>
</x-page-template>