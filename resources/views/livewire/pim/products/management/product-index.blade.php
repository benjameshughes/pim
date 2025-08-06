{{-- Original stacked-list configuration --}}
{{--
@if (session()->has('message'))
    <div class="mb-4 rounded-lg bg-green-100 px-6 py-4 text-green-700 dark:bg-green-900 dark:text-green-300">
        {{ session('message') }}
    </div>
@endif

<x-stacked-list 
    :config="$this->getStackedListConfig()"
    :data="$this->stackedListData"
    :selected-items="$this->stackedListSelectedItems"
    :search="$this->stackedListSearch"
    :filters="$this->stackedListFilters"
    :per-page="$this->stackedListPerPage"
    :sort-by="$this->stackedListSortBy"
    :sort-direction="$this->stackedListSortDirection"
    :sort-stack="$this->stackedListSortStack"
    :select-all="$this->stackedListSelectAll"
/>
--}}

{{-- Ultra-simple auto-generation with custom actions using trait --}}
@php
    $customList = \App\StackedList\DefaultStackedList::makeFor(\App\Models\Product::class)
        ->hideColumns(['description', 'features', 'slug', 'metadata'])
        ->title('Products')
        ->subtitle('Auto-generated product listing with trait-powered actions')
        ->configure();
    
    // Get the configuration and override actions using the trait
    $config = $customList->toArray();
    
    // Use trait method to override row actions with custom method-based view action
    $customActions = [
        [
            'method' => 'viewProduct',
            'label' => 'View',
            'icon' => 'eye'
        ],
        [
            'route' => 'products.product.edit',
            'label' => 'Edit', 
            'icon' => 'pencil'
        ]
    ];
    
    // Override using simpler approach
    foreach ($config['columns'] as &$column) {
        if ($column['type'] === 'actions') {
            $column['actions'] = $customActions;
            break;
        }
    }
@endphp

<x-stacked-list 
    :config="$config"
    :data="$this->stackedListData"
    :selected-items="$this->stackedListSelectedItems"
    :search="$this->stackedListSearch"
    :filters="$this->stackedListFilters"
    :per-page="$this->stackedListPerPage"
    :sort-by="$this->stackedListSortBy"
    :sort-direction="$this->stackedListSortDirection"
    :sort-stack="$this->stackedListSortStack"
    :select-all="$this->stackedListSelectAll"
/>