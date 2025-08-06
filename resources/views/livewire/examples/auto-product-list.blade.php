{{-- Example of using the auto-stacked-list component --}}

{{-- Method 1: Using the auto-stacked-list component with model auto-generation --}}
<x-auto-stacked-list 
    model="{{ App\Models\Product::class }}"
    :data="$products"
    :hide-columns="['description', 'features', 'slug', 'metadata']"
    :badge-columns="['featured', 'is_active']"
    title="Auto-Generated Products"
    subtitle="This list was automatically generated from the Product model"
/>

{{-- 
Method 2: You could also use the trait in your component and pass the config:

<x-stacked-list 
    :config="$config"
    :data="$products"
    :selected-items="[]"
    :search="''"
    :filters="[]"
    :per-page="15"
    :sort-by="null"
    :sort-direction="'asc'"
    :sort-stack="[]"
    :select-all="false"
/>
--}}