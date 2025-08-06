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

<!-- Delete Confirmation Modal -->
@if($showDeleteModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex min-h-screen items-end justify-center px-4 pb-20 pt-4 text-center sm:block sm:p-0">
            <div wire:click="$set('showDeleteModal', false)" class="fixed inset-0 bg-zinc-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

            <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

            <div class="inline-block transform overflow-hidden rounded-lg bg-white text-left align-bottom shadow-xl transition-all dark:bg-zinc-800 sm:my-8 sm:w-full sm:max-w-lg sm:align-middle">
                <div class="bg-white px-4 pb-4 pt-5 dark:bg-zinc-800 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                            <flux:heading size="lg" class="mb-2">Delete Variant</flux:heading>
                            <flux:text>Are you sure you want to delete this variant? This action cannot be undone and will also delete all associated barcodes and pricing.</flux:text>
                        </div>
                    </div>
                </div>
                <div class="bg-zinc-50 px-4 py-3 dark:bg-zinc-900 sm:flex sm:flex-row-reverse sm:px-6">
                    <flux:button variant="danger" wire:click="deleteVariant" class="sm:ml-3">
                        Delete
                    </flux:button>
                    <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">
                        Cancel
                    </flux:button>
                </div>
            </div>
        </div>
    </div>
@endif