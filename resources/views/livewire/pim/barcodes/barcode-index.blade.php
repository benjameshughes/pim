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

{{-- FilamentPHP-style table rendering --}}
{!! $this->table !!}

{{--    @if (session()->has('success'))--}}
{{--        <div class="mb-4 rounded-lg bg-green-100 px-6 py-4 text-green-700 dark:bg-green-900 dark:text-green-300">--}}
{{--            {{ session('success') }}--}}
{{--        </div>--}}
{{--    @endif--}}

{{--    @if (session()->has('error'))--}}
{{--        <div class="mb-4 rounded-lg bg-red-100 px-6 py-4 text-red-700 dark:bg-red-900 dark:text-red-300">--}}
{{--            {{ session('error') }}--}}
{{--        </div>--}}
{{--    @endif--}}

{{--    <x-stacked-list --}}
{{--        :config="$this->getStackedListConfig()"--}}
{{--        :data="$this->stackedListData"--}}
{{--        :selected-items="$this->stackedListSelectedItems"--}}
{{--        :search="$this->stackedListSearch"--}}
{{--        :filters="$this->stackedListFilters"--}}
{{--        :per-page="$this->stackedListPerPage"--}}
{{--        :sort-by="$this->stackedListSortBy"--}}
{{--        :sort-direction="$this->stackedListSortDirection"--}}
{{--        :sort-stack="$this->stackedListSortStack"--}}
{{--        :select-all="$this->stackedListSelectAll"--}}
{{--        :parent-products-only="$this->parentProductsOnly"--}}
{{--    />--}}

{{--<!-- Generate Barcode Section -->--}}
{{--@if($variants->isNotEmpty())--}}
{{--    <div class="mt-8 bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">--}}
{{--        <flux:heading size="lg" class="mb-4">Generate New Barcodes</flux:heading>--}}
{{--        <flux:subheading class="mb-6">Create barcodes for recent variants without barcodes (showing up to 100)</flux:subheading>--}}
{{--        --}}
{{--        <div class="space-y-4">--}}
{{--            @foreach($variants->take(20) as $variant)--}}
{{--                @if($variant->barcodes->isEmpty())--}}
{{--                    <div class="flex items-center justify-between p-4 border border-zinc-200 dark:border-zinc-700 rounded-lg">--}}
{{--                        <div>--}}
{{--                            <div class="font-medium text-sm">{{ $variant->product->name }}</div>--}}
{{--                            <div class="text-sm text-zinc-500 dark:text-zinc-400">--}}
{{--                                SKU: {{ $variant->sku }}--}}
{{--                                @if($variant->color || $variant->size)--}}
{{--                                    | {{ $variant->color }} {{ $variant->size }}--}}
{{--                                @endif--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="flex items-center space-x-2">--}}
{{--                            @foreach($barcodeTypes as $key => $label)--}}
{{--                                <flux:button --}}
{{--                                    size="sm"--}}
{{--                                    wire:click="generateBarcode({{ $variant->id }}, '{{ $key }}')"--}}
{{--                                >--}}
{{--                                    Generate {{ $label }}--}}
{{--                                </flux:button>--}}
{{--                            @endforeach--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                @endif--}}
{{--            @endforeach--}}
{{--            --}}
{{--            @if($variants->count() >= 20)--}}
{{--                <div class="text-center py-4 text-zinc-500 dark:text-zinc-400 text-sm">--}}
{{--                    Showing first 20 variants without barcodes. Use filters to find specific variants.--}}
{{--                </div>--}}
{{--            @endif--}}
{{--        </div>--}}
{{--    </div>--}}
{{--@endif--}}
</div>