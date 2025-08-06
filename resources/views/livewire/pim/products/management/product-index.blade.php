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