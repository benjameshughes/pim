@props(['color', 'shopifyProductId', 'shopifyProducts', 'colorMappings'])

<div class="p-4 bg-gray-50 rounded-lg">
    <div class="flex items-center space-x-3 mb-3">
        <flux:badge color="purple" size="sm">
            {{ $color }}
        </flux:badge>
        <span class="text-sm font-medium text-gray-700">Select Shopify Product</span>
    </div>
    
    <div class="space-y-3">
        {{-- Dropdown Selection --}}
        <div>
            <flux:field>
                <flux:select wire:model.live="colorMappings.{{ $color }}" class="w-full">
                    <flux:select.option value="">Choose a Shopify product...</flux:select.option>
                    @if(!empty($shopifyProducts))
                        @foreach($shopifyProducts as $shopifyProduct)
                            <flux:select.option value="{{ $shopifyProduct['id'] }}">
                                {{ $shopifyProduct['title'] }} (ID: {{ $shopifyProduct['id'] }})
                            </flux:select.option>
                        @endforeach
                    @endif
                    <flux:select.option value="custom">🔧 Custom Product ID...</flux:select.option>
                </flux:select>
                @error("colorMappings.{$color}") 
                    <flux:error>{{ $message }}</flux:error> 
                @enderror
            </flux:field>
        </div>

        {{-- Custom Input Field (shown when "custom" is selected) --}}
        @if($colorMappings[$color] === 'custom')
            <div>
                <flux:field>
                    <flux:label class="text-sm">Custom Shopify Product ID for {{ $color }}</flux:label>
                    <flux:input 
                        wire:model="colorMappings.{{ $color }}_custom"
                        placeholder="Enter custom Shopify Product ID"
                        class="w-full" 
                    />
                </flux:field>
            </div>
        @endif

        {{-- Selected Product Preview --}}
        @if($colorMappings[$color] && $colorMappings[$color] !== 'custom' && $colorMappings[$color] !== '')
            @php
                $selectedProduct = collect($shopifyProducts)->firstWhere('id', $colorMappings[$color]);
            @endphp
            @if($selectedProduct)
                <div class="bg-white border border-green-200 rounded p-3">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="font-medium text-green-800">{{ $selectedProduct['title'] }}</div>
                            <div class="text-sm text-green-600">
                                ID: {{ $selectedProduct['id'] }} • 
                                Status: {{ ucfirst($selectedProduct['status']) }}
                                @if($selectedProduct['variant_count'] > 0)
                                    • {{ $selectedProduct['variant_count'] }} variants
                                @endif
                            </div>
                        </div>
                        <flux:badge color="green" size="xs">Selected</flux:badge>
                    </div>
                </div>
            @endif
        @elseif($colorMappings[$color] === 'custom' && !empty($this->{"colorMappings.{$color}_custom"}))
            <div class="bg-white border border-blue-200 rounded p-3">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="font-medium text-blue-800">Custom Product ID</div>
                        <div class="text-sm text-blue-600">ID: {{ $this->{"colorMappings.{$color}_custom"} }}</div>
                    </div>
                    <flux:badge color="blue" size="xs">Custom</flux:badge>
                </div>
            </div>
        @endif
    </div>
</div>