<!-- Overview Tab Content -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Content -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Description -->
        @if($product->description)
            <div class="border-b border-zinc-100 dark:border-zinc-800 pb-6">
                <flux:heading size="lg" class="mb-4">Description</flux:heading>
                <div class="prose prose-sm dark:prose-invert max-w-none">
                    {{ $product->description }}
                </div>
            </div>
        @endif

        <!-- Product Features -->
        @if(collect([$product->product_features_1, $product->product_features_2, $product->product_features_3, $product->product_features_4, $product->product_features_5])->filter()->isNotEmpty())
            <div class="border-b border-zinc-100 dark:border-zinc-800 pb-6">
                <flux:heading size="lg" class="mb-4">Features</flux:heading>
                <div class="space-y-3">
                    @foreach(['product_features_1', 'product_features_2', 'product_features_3', 'product_features_4', 'product_features_5'] as $feature)
                        @if($product->$feature)
                            <div class="flex items-start gap-3">
                                <flux:icon name="check" class="h-4 w-4 text-emerald-500 mt-0.5 flex-shrink-0" />
                                <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $product->$feature }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Product Details -->
        @if(collect([$product->product_details_1, $product->product_details_2, $product->product_details_3, $product->product_details_4, $product->product_details_5])->filter()->isNotEmpty())
            <div class="border-b border-zinc-100 dark:border-zinc-800 pb-6">
                <flux:heading size="lg" class="mb-4">Details</flux:heading>
                <div class="space-y-3">
                    @foreach(['product_details_1', 'product_details_2', 'product_details_3', 'product_details_4', 'product_details_5'] as $detail)
                        @if($product->$detail)
                            <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $product->$detail }}
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
    </div>
    
    <!-- Sidebar -->
    <div class="space-y-6">

        <!-- Quick Stats -->
        <div class="border-b border-zinc-100 dark:border-zinc-800 pb-6">
            <flux:heading size="lg" class="mb-4">Quick Stats</flux:heading>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Total Variants</span>
                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $product->variants->count() }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Total Stock</span>
                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $product->variants->sum('stock_level') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Active Variants</span>
                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $product->variants->where('status', 'active')->count() }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Images</span>
                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $product->productImages->count() }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Created</span>
                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $product->created_at->format('M j, Y') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>