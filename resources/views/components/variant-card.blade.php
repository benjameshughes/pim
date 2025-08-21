@props(['productVariant' => null, 'parent' => null])

<div class="flex items-center justify-between py-2 px-4 bg-white rounded-md border-l-4 border-blue-200 hover:bg-blue-50/50 transition-colors group">
    <div class="flex items-center gap-4">
        {{-- SKU --}}
        <div class="min-w-0">
            <span class="font-mono text-sm font-medium text-gray-900">{{ $productVariant->sku }}</span>
            @if($productVariant->external_sku && $productVariant->external_sku !== $productVariant->sku)
                <span class="text-xs text-gray-400 block">{{ $productVariant->external_sku }}</span>
            @endif
        </div>
        
        {{-- Interactive Color Picker --}}
        <div class="flex items-center gap-2 bg-blue-50 border border-blue-200 rounded-full px-3 py-1">
            <input 
                type="color" 
                value="{{ $parent->getColorHex($productVariant->color) }}"
                wire:change="updateColor({{ $productVariant->id }}, $event.target.value)"
                wire:target="updateColor({{ $productVariant->id }})"
                class="w-5 h-5 rounded-full border-0 cursor-pointer bg-transparent"
                title="Click to change color"
            />
            <span class="text-sm font-medium text-blue-700">{{ $productVariant->color }}</span>
            <div wire:loading wire:target="updateColor({{ $productVariant->id }})" class="ml-2">
                <flux:icon name="loader" class="w-3 h-3 animate-spin text-blue-500" />
            </div>
        </div>
        
        {{-- Dimensions --}}
        <div class="text-sm text-gray-600">
            @if($productVariant->width)
                <span>{{ $productVariant->width }}cm</span>
                @if($productVariant->drop)
                    <span class="text-gray-400">×</span>
                    <span>{{ $productVariant->drop }}cm</span>
                @endif
            @else
                <span class="text-gray-400">No size</span>
            @endif
        </div>
        
        {{-- Price --}}
        <div class="font-semibold text-green-700">
            {{ $productVariant->price ? '£' . number_format($productVariant->price, 2) : 'No price' }}
        </div>
        
        {{-- Stock --}}
        <div class="text-sm">
            <span class="text-gray-600">Stock:</span>
            <span class="@if(($productVariant->stock_level ?? 0) > 10) text-green-600 @elseif($productVariant->stock_level > 0) text-yellow-600 @else text-red-600 @endif font-medium">
                {{ $productVariant->stock_level ?? 0 }}
            </span>
        </div>
        
        {{-- Status --}}
        <flux:badge 
            size="sm" 
            color="{{ $productVariant->status === 'active' ? 'green' : 'zinc' }}"
            inset="top bottom"
        >
            {{ ucfirst($productVariant->status ?? 'unknown') }}
        </flux:badge>
        
        {{-- Barcodes Count --}}
        @if($productVariant->barcodes_count > 0)
            <div class="text-xs text-gray-500">
                <flux:icon name="scan-barcode" class="w-3 h-3 inline mr-1" />
                {{ $productVariant->barcodes_count }}
            </div>
        @endif
    </div>
    
    {{-- Action Buttons --}}
    <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
        <flux:button 
            href="{{ route('variants.edit', $productVariant) }}" 
            size="sm" 
            variant="ghost" 
            icon="pencil"
        >
            Edit
        </flux:button>
        
        <flux:button 
            href="{{ route('variants.show', $productVariant) }}" 
            size="sm" 
            variant="ghost" 
            icon="eye"
        >
            View
        </flux:button>
    </div>
</div>