<x-layouts.app>
    <x-slot:title>{{ isset($product) ? 'Edit Product' : 'Create Product' }}</x-slot:title>
    
    @if(isset($product))
        <livewire:product-wizard :product="$product" />
    @else
        <livewire:product-wizard />
    @endif
</x-layouts.app>