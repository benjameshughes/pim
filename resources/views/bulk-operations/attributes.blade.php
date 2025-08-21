<x-layouts.app>
    <x-slot:title>Bulk Marketplace Attributes</x-slot:title>
    
    <div class="container max-w-7xl mx-auto px-4 py-8">
        <livewire:bulk-operations.bulk-attribute-operation 
            :target-type="$targetType" 
            :selected-items="$selectedItems" 
        />
    </div>
</x-layouts.app>