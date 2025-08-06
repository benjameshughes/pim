{{-- Ultra-simple example - just specify the model and data --}}

<x-auto-stacked-list 
    model="{{ App\Models\ProductVariant::class }}"
    :data="App\Models\ProductVariant::with(['product', 'barcodes', 'pricing'])->withCount(['barcodes', 'pricing'])->paginate(15)"
/>

{{-- That's it! The component will automatically:
     - Generate columns based on the database schema
     - Create appropriate column types (text, badges, etc.)
     - Add search/sort functionality where appropriate  
     - Include default bulk actions
     - Create a proper title and subtitle
     - Handle relationships (product.name, etc.)
     - Skip common hidden fields (id, timestamps, etc.)
--}}