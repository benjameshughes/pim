<div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
    <flux:heading size="lg" class="mb-4">Pricing for {{ $variant->product->name }} - {{ $variant->sku }}</flux:heading>
    
    <form wire:submit="savePricing" class="space-y-6">
        <!-- Channel Selection -->
        <flux:field>
            <flux:label>Sales Channel</flux:label>
            <flux:select wire:model.live="selectedChannel">
                @foreach($channels as $channel)
                    <option value="{{ $channel->slug }}">{{ $channel->name }}</option>
                @endforeach
            </flux:select>
        </flux:field>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Basic Pricing -->
            <div class="space-y-4">
                <flux:heading size="sm">Basic Pricing</flux:heading>
                
                <flux:input 
                    wire:model.live="retail_price"
                    label="Retail Price (£)" 
                    type="number" 
                    step="0.01"
                    placeholder="49.99"
                    required 
                />
                
                <flux:input 
                    wire:model.live="cost_price"
                    label="Cost Price (£)" 
                    type="number" 
                    step="0.01"
                    placeholder="25.00"
                />

                <div class="grid grid-cols-2 gap-4">
                    <flux:input 
                        wire:model.live="vat_percentage"
                        label="VAT (%)" 
                        type="number" 
                        step="0.01"
                        placeholder="20.00"
                        required 
                    />
                    
                    <div class="flex items-center mt-6">
                        <input 
                            type="checkbox" 
                            wire:model.live="vat_inclusive" 
                            id="vat_inclusive"
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        >
                        <label for="vat_inclusive" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                            VAT Inclusive
                        </label>
                    </div>
                </div>
            </div>

            <!-- Costs & Fees -->
            <div class="space-y-4">
                <flux:heading size="sm">Costs & Fees</flux:heading>
                
                <flux:input 
                    wire:model.live="shipping_cost"
                    label="Shipping Cost (£)" 
                    type="number" 
                    step="0.01"
                    placeholder="4.95"
                />
                
                <flux:input 
                    wire:model.live="channel_fee_percentage"
                    label="Channel Fee (%)" 
                    type="number" 
                    step="0.01"
                    placeholder="15.00"
                />
            </div>
        </div>

        <!-- Live Preview -->
        @if($preview)
            <div class="border-t pt-6">
                <flux:heading size="sm" class="mb-4">Profit Calculation Preview</flux:heading>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded-lg">
                        <div class="font-medium text-blue-800 dark:text-blue-200">Net Price</div>
                        <div class="text-lg font-bold">£{{ number_format($preview->net_price, 2) }}</div>
                    </div>
                    
                    <div class="bg-orange-50 dark:bg-orange-900/20 p-3 rounded-lg">
                        <div class="font-medium text-orange-800 dark:text-orange-200">Total Costs</div>
                        <div class="text-lg font-bold">£{{ number_format($preview->total_cost, 2) }}</div>
                    </div>
                    
                    <div class="bg-{{ $preview->isProfitable() ? 'green' : 'red' }}-50 dark:bg-{{ $preview->isProfitable() ? 'green' : 'red' }}-900/20 p-3 rounded-lg">
                        <div class="font-medium text-{{ $preview->isProfitable() ? 'green' : 'red' }}-800 dark:text-{{ $preview->isProfitable() ? 'green' : 'red' }}-200">Profit</div>
                        <div class="text-lg font-bold">£{{ number_format($preview->profit_amount, 2) }}</div>
                    </div>
                    
                    <div class="bg-purple-50 dark:bg-purple-900/20 p-3 rounded-lg">
                        <div class="font-medium text-purple-800 dark:text-purple-200">Margin</div>
                        <div class="text-lg font-bold">{{ $preview->getFormattedProfitMargin() }}</div>
                    </div>
                </div>
                
                <!-- Detailed Breakdown -->
                <details class="mt-4">
                    <summary class="cursor-pointer font-medium text-zinc-700 dark:text-zinc-300">View Detailed Breakdown</summary>
                    <div class="mt-3 grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <h4 class="font-medium mb-2">Revenue</h4>
                            <div class="space-y-1">
                                <div class="flex justify-between">
                                    <span>Retail Price:</span>
                                    <span>£{{ number_format($preview->retail_price, 2) }}</span>
                                </div>
                                @if($preview->vat_amount > 0)
                                    <div class="flex justify-between text-zinc-600">
                                        <span>VAT ({{ $preview->vat_percentage }}%):</span>
                                        <span>£{{ number_format($preview->vat_amount, 2) }}</span>
                                    </div>
                                @endif
                                <div class="flex justify-between font-medium border-t pt-1">
                                    <span>Final Price:</span>
                                    <span>£{{ number_format($preview->final_price, 2) }}</span>
                                </div>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-medium mb-2">Costs</h4>
                            <div class="space-y-1">
                                @if($preview->cost_price > 0)
                                    <div class="flex justify-between">
                                        <span>Cost Price:</span>
                                        <span>£{{ number_format($preview->cost_price, 2) }}</span>
                                    </div>
                                @endif
                                @if($preview->shipping_cost > 0)
                                    <div class="flex justify-between">
                                        <span>Shipping:</span>
                                        <span>£{{ number_format($preview->shipping_cost, 2) }}</span>
                                    </div>
                                @endif
                                @if($preview->channel_fee_amount > 0)
                                    <div class="flex justify-between">
                                        <span>Channel Fee:</span>
                                        <span>£{{ number_format($preview->channel_fee_amount, 2) }}</span>
                                    </div>
                                @endif
                                <div class="flex justify-between font-medium border-t pt-1">
                                    <span>Total Costs:</span>
                                    <span>£{{ number_format($preview->total_cost, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </details>
            </div>
        @endif

        <!-- Actions -->
        <div class="flex justify-between items-center pt-6 border-t">
            <div>
                @if($currentPricing)
                    <flux:button 
                        type="button"
                        variant="danger" 
                        wire:click="deletePricing"
                        wire:confirm="Are you sure you want to delete this pricing?"
                    >
                        Delete Pricing
                    </flux:button>
                @endif
            </div>
            
            <div class="flex gap-4">
                <flux:button type="submit" variant="primary">
                    {{ $currentPricing ? 'Update' : 'Save' }} Pricing
                </flux:button>
            </div>
        </div>
    </form>
</div>
