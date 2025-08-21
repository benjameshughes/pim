{{-- 🚀 BULK PRICING OPERATION - Full-Page Dedicated Interface --}}
<div class="space-y-6" x-data="{ 
    isProcessing: @entangle('isProcessing'),
    progress: @entangle('processingProgress'),
    errorMessage: @entangle('errorMessage'),
    previewPrice: @entangle('previewPrice'),
    updateType: @entangle('pricingData.update_type'),
    averagePrice: @js($this->averageCurrentPrice)
}">
    
    {{-- Breadcrumb Navigation --}}
    <div class="flex items-center gap-2 text-sm text-gray-500">
        <flux:button wire:click="backToBulkOperations" variant="ghost" size="sm" class="hover:text-gray-700">
            <flux:icon name="arrow-left" class="w-4 h-4 mr-1" />
            Bulk Operations
        </flux:button>
        <span>/</span>
        <span class="text-gray-900 dark:text-white font-medium">Update Pricing</span>
    </div>

    {{-- Error Message --}}
    <div x-show="errorMessage" x-transition class="bg-red-50 border border-red-200 rounded-lg p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-600" />
                <span x-text="errorMessage" class="text-red-800 font-medium"></span>
            </div>
            <flux:button wire:click="clearError" variant="ghost" size="sm" class="text-red-600">
                <flux:icon name="x" class="w-4 h-4" />
            </flux:button>
        </div>
    </div>

    {{-- Processing Indicator --}}
    <div x-show="isProcessing" x-transition class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-center gap-3">
            <flux:icon name="arrow-path" class="w-4 h-4 animate-spin" />
            <span>Processing {{ $this->selectedCount }} {{ $this->targetDisplayName }}...</span>
            <div class="bg-gray-200 rounded-full h-3 flex-1">
                <div class="bg-blue-600 h-3 rounded-full transition-all duration-300" 
                     :style="`width: ${progress}%`"></div>
            </div>
            <span x-text="`${progress}%`" class="text-sm font-medium text-blue-600 min-w-12 text-right"></span>
        </div>
    </div>

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                Update Pricing
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">
                Bulk update pricing for {{ $this->selectedCount }} {{ strtolower($this->targetDisplayName) }}
            </p>
        </div>
        
        {{-- Current Average Price --}}
        <div class="text-right">
            <div class="text-sm text-gray-500">Current Average Price</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                ${{ number_format($this->averageCurrentPrice, 2) }}
            </div>
        </div>
    </div>

    {{-- Main Content Cards --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Pricing Configuration --}}
        <flux:card>
            <flux:card.header>
                <flux:card.title>Pricing Configuration</flux:card.title>
                <flux:card.description>Choose how you want to update the pricing</flux:card.description>
            </flux:card.header>
            
            <flux:card.content class="space-y-6">
                {{-- Update Type Selection --}}
                <flux:radio.group wire:model.live="pricingData.update_type">
                    <flux:radio value="fixed">Set fixed price</flux:radio>
                    <flux:radio value="percentage">Adjust by percentage</flux:radio>
                    <flux:radio value="formula">Apply custom formula</flux:radio>
                </flux:radio.group>
                
                {{-- Fixed Price Input --}}
                <template x-if="updateType === 'fixed'">
                    <div class="space-y-3">
                        <flux:input 
                            wire:model.live="pricingData.new_price" 
                            type="number" 
                            label="New Price"
                            prefix="$"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            class="text-lg"
                        />
                        <p class="text-sm text-gray-600">
                            Set the same price for all selected {{ strtolower($this->targetDisplayName) }}
                        </p>
                    </div>
                </template>
                
                {{-- Percentage Adjustment --}}
                <template x-if="updateType === 'percentage'">
                    <div class="space-y-3">
                        <flux:input 
                            wire:model.live="pricingData.percentage" 
                            type="number" 
                            label="Percentage Change"
                            suffix="%"
                            step="0.1"
                            placeholder="10.0"
                            class="text-lg"
                        />
                        <p class="text-sm text-gray-600">
                            Positive values increase price, negative values decrease price.<br>
                            Example: 20% will increase $100 to $120
                        </p>
                    </div>
                </template>
                
                {{-- Formula Input --}}
                <template x-if="updateType === 'formula'">
                    <div class="space-y-3">
                        <flux:input 
                            wire:model.live="pricingData.formula"
                            label="Custom Formula"
                            placeholder="price * 1.2 + 5"
                            class="font-mono"
                        />
                        <div class="text-sm text-gray-600 space-y-1">
                            <p>Use "price" in your formula to reference the current price.</p>
                            <p><strong>Examples:</strong></p>
                            <ul class="list-disc list-inside ml-2 space-y-1">
                                <li><code class="bg-gray-100 px-1 rounded text-xs">price * 1.2</code> - 20% increase</li>
                                <li><code class="bg-gray-100 px-1 rounded text-xs">price + 10</code> - Add $10</li>
                                <li><code class="bg-gray-100 px-1 rounded text-xs">price * 0.9</code> - 10% discount</li>
                            </ul>
                        </div>
                    </div>
                </template>
            </flux:card.content>
        </flux:card>

        {{-- Preview & Summary --}}
        <flux:card>
            <flux:card.header>
                <flux:card.title>Preview & Summary</flux:card.title>
                <flux:card.description>Review your pricing changes before applying</flux:card.description>
            </flux:card.header>
            
            <flux:card.content class="space-y-6">
                {{-- Price Preview --}}
                <template x-if="previewPrice !== null">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="text-sm text-blue-600 font-medium mb-2">Preview Example</div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">$100.00 →</span>
                            <span class="text-2xl font-bold text-blue-600" x-text="`$${parseFloat(previewPrice).toFixed(2)}`"></span>
                        </div>
                    </div>
                </template>

                {{-- Selection Summary --}}
                <div class="border rounded-lg p-4 space-y-3">
                    <h3 class="font-medium text-gray-900 dark:text-white">Selection Summary</h3>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500">Target:</span>
                            <div class="font-medium">{{ $this->targetDisplayName }}</div>
                        </div>
                        <div>
                            <span class="text-gray-500">Count:</span>
                            <div class="font-medium">{{ $this->selectedCount }} items</div>
                        </div>
                    </div>
                </div>

                {{-- Warning --}}
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="flex items-start gap-3">
                        <flux:icon name="exclamation-triangle" class="w-5 h-5 text-yellow-600 mt-0.5" />
                        <div class="text-sm">
                            <div class="font-medium text-yellow-800">Important</div>
                            <div class="text-yellow-700 mt-1">
                                This action will update {{ $this->selectedCount }} {{ strtolower($this->targetDisplayName) }} and cannot be undone. 
                                Please review your settings carefully.
                            </div>
                        </div>
                    </div>
                </div>
            </flux:card.content>
        </flux:card>
    </div>

    {{-- Action Buttons --}}
    <div class="flex justify-between items-center">
        <flux:button wire:click="backToBulkOperations" variant="ghost" ::disabled="isProcessing">
            <flux:icon name="arrow-left" class="w-4 h-4 mr-2" />
            Back to Selection
        </flux:button>
        
        <flux:button 
            wire:click="applyBulkPricing" 
            variant="primary"
            size="base"
            ::disabled="isProcessing"
        >
            <template x-if="!isProcessing">
                <span>Apply Pricing to {{ $this->selectedCount }} {{ $this->targetDisplayName }}</span>
            </template>
            <template x-if="isProcessing">
                <span>Processing...</span>
            </template>
        </flux:button>
    </div>
</div>