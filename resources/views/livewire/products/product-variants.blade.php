{{-- DEBUG PRODUCT VARIANTS --}}
<div class="p-4 bg-yellow-50 border border-yellow-200">
    <h3 class="font-bold text-red-600">DEBUG INFO</h3>
    <p><strong>Product ID:</strong> {{ $product->id ?? 'NULL' }}</p>
    <p><strong>Product Name:</strong> {{ $product->name ?? 'NULL' }}</p>
    <p><strong>Variants Count (DB):</strong> {{ $product->variants()->count() }}</p>
    <p><strong>Variants Count (Computed):</strong> {{ $this->variants->count() }}</p>
    
    @if($this->variants->count() > 0)
        <div class="mt-2">
            <strong>First Variant:</strong> {{ $this->variants->first()->sku ?? 'NO SKU' }}
        </div>
    @endif
    
    <strong>Stats:</strong> {{ json_encode($this->stats) }}
</div>

<div class="pl-12 py-2 space-y-1 bg-gray-50/50">
    {{-- Quick Stats Header --}}
    <div class="flex items-center gap-4 text-xs text-gray-600 mb-3 px-4">
        <span>{{ $this->stats['total'] }} variants</span>
        <span>•</span>
        <span>{{ $this->stats['active'] }} active</span>
        <span>•</span>
        <span>{{ $this->stats['colors'] }} colors</span>
        <span>•</span>
        <span>Avg: £{{ number_format($this->stats['avg_price'], 2) }}</span>
        <span>•</span>
        <span>Stock: {{ $this->stats['total_stock'] }}</span>
    </div>

    {{-- Variant Rows --}}
    @forelse($this->variants as $variantModel)
        <div class="p-2 bg-white border border-green-200 mb-2">
            <p><strong>Variant:</strong> {{ $variantModel->sku }} - {{ $variantModel->color }}</p>
        </div>
    @empty
        <div class="text-center py-6 bg-red-100 border border-red-200">
            <p class="font-bold text-red-600">NO VARIANTS FOUND!</p>
            <p>This means the @empty block is being hit</p>
        </div>
    @endforelse
</div>