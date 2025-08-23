<x-layouts.app>
    <x-slot:title>Create Product</x-slot:title>
    
    <div class="p-4">
        <h1>About to load ProductWizard...</h1>
        <p>This line shows before the component</p>
        
        <livewire:product-wizard />
        
        <p>This line shows after the component (if it loads)</p>
    </div>
</x-layouts.app>