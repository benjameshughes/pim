@props([
    'model' => null,
    'data' => null,
    'customList' => null,
    'hideColumns' => [],
    'badgeColumns' => [],
    'title' => null,
    'subtitle' => null,
])

@php
    // Determine which configuration to use
    if ($customList) {
        // Use provided custom list configuration
        $stackedList = $customList->configure();
    } elseif ($model) {
        // Auto-generate from model
        $stackedList = \App\StackedList\DefaultStackedList::makeFor($model)
            ->hideColumns($hideColumns)
            ->badgeColumns($badgeColumns);
            
        // Override title/subtitle if provided
        if ($title) $stackedList->title($title);
        if ($subtitle) $stackedList->subtitle($subtitle);
        
        $stackedList = $stackedList->configure();
    } else {
        throw new InvalidArgumentException('Either model or customList must be provided');
    }

    $config = $stackedList->toArray();
@endphp

<div>
    <!-- Session Messages -->
    @if (session()->has('message'))
        <div class="mb-4 rounded-lg bg-green-100 px-6 py-4 text-green-700 dark:bg-green-900 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif

    @if (session()->has('success'))
        <div class="mb-4 rounded-lg bg-green-100 px-6 py-4 text-green-700 dark:bg-green-900 dark:text-green-300">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-4 rounded-lg bg-red-100 px-6 py-4 text-red-700 dark:bg-red-900 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    <!-- Use the existing stacked-list component -->
    <x-stacked-list 
        :config="$config"
        :data="$data ?? collect()"
        :selected-items="[]"
        :search="''"
        :filters="[]"
        :per-page="15"
        :sort-by="null"
        :sort-direction="'asc'"
        :sort-stack="[]"
        :select-all="false"
    />
</div>