<x-layouts.app>
    <x-slot:title>{{ isset($product) ? 'Edit Product' : 'Create Product' }}</x-slot:title>
    
    <div class="container max-w-7xl mx-auto px-4 py-8">
        <div class="max-w-7xl mx-auto">
            @if(isset($product))
                <livewire:products.product-wizard-clean :product="$product" />
            @else
                <livewire:products.product-wizard-clean />
            @endif
        </div>
    </div>
</x-layouts.app>