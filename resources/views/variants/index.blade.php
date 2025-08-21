<x-layouts.app>
    <x-slot:title>Product Variants</x-slot:title>
    
    <div class="container max-w-7xl mx-auto px-4 py-8">
        <div class="max-w-7xl mx-auto">
            
            {{-- Page Header --}}
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                            💎 Product Variants
                        </h1>
                        <p class="mt-2 text-gray-600 dark:text-gray-400">
                            Manage individual product variants and their specifications
                        </p>
                    </div>
                    
                    <div class="flex gap-3">
                        <flux:button 
                            href="{{ route('variants.create') }}" 
                            variant="primary" 
                            icon="plus"
                        >
                            New Variant
                        </flux:button>
                    </div>
                </div>
            </div>

            {{-- Variants Component --}}
            <livewire:variants.variant-index />
            
        </div>
    </div>
</x-layouts.app>