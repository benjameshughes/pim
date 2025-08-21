<x-layouts.app>
    <x-slot:title>Bulk Pricing Operations</x-slot:title>
    
    <div class="container max-w-7xl mx-auto px-4 py-8">
        <livewire:bulk-operations.bulk-pricing-operation 
            :target-type="$targetType" 
            :selected-items="$selectedItems" 
        />
    </div>
</x-layouts.app>