{{-- 📋 PRODUCT INFO FORM - COLLECTION-POWERED STEP 1 --}}
<div class="space-y-6" wire:ignore.self>

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-foreground">Product Information</h3>
            <p class="text-sm text-muted-foreground">Enter the basic details for your product</p>
        </div>
        
        {{-- Form Statistics --}}
        @if($this->formStats->get('filled_fields') > 0)
            <div class="flex items-center gap-4 text-sm">
                <div class="flex items-center gap-2">
                    <flux:icon name="check-circle" class="w-4 h-4 {{ $this->formStats->get('is_valid') ? 'text-green-500' : 'text-muted-foreground' }}" />
                    <span class="font-medium">{{ $this->formStats->get('completion_percentage') }}%</span>
                    <span class="text-muted-foreground">Complete</span>
                </div>
            </div>
        @endif
    </div>

    {{-- Main Form --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Product Name --}}
        <flux:field class="md:col-span-2">
            <flux:label>Product Name *</flux:label>
            <flux:input 
                wire:model.live="name" 
                placeholder="Enter product name..." 
                required
            />
            <flux:error name="name" />
        </flux:field>

        {{-- Parent SKU --}}
        <flux:field>
            <flux:label>Parent SKU *</flux:label>
            <div class="flex gap-2">
                <flux:input 
                    wire:model.live="parent_sku" 
                    placeholder="e.g., WIN-001" 
                    required
                />
                <flux:button 
                    wire:click="generateSkuSuggestion" 
                    variant="outline" 
                    size="sm"
                    :disabled="empty($name)"
                >
                    <flux:icon name="sparkles" class="w-4 h-4" />
                </flux:button>
            </div>
            <flux:error name="parent_sku" />
            <flux:description>Unique identifier for this product family</flux:description>
        </flux:field>

        {{-- Status --}}
        <flux:field>
            <flux:label>Status *</flux:label>
            <flux:select wire:model.live="status">
                @foreach($this->statusOptions as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="status" />
        </flux:field>
    </div>

    {{-- Description --}}
    <flux:field>
        <flux:label>Description</flux:label>
        <flux:textarea 
            wire:model.live="description" 
            placeholder="Describe your product..."
            rows="4"
        />
        <flux:error name="description" />
    </flux:field>

    {{-- Image URL (Optional) --}}
    <flux:field>
        <flux:label>Primary Image URL</flux:label>
        <flux:input 
            wire:model.live="image_url" 
            type="url"
            placeholder="https://example.com/image.jpg" 
        />
        <flux:error name="image_url" />
        <flux:description>Optional: Add a URL to your main product image</flux:description>
    </flux:field>

    {{-- Validation Errors --}}
    @if($validationErrors->isNotEmpty())
        <div class="bg-destructive/10 border border-destructive/20 rounded-lg p-4 space-y-2">
            <div class="flex items-center gap-2">
                <flux:icon name="alert-circle" class="w-5 h-5 text-destructive" />
                <h4 class="font-semibold text-destructive">Please fix the following errors:</h4>
            </div>
            <ul class="list-disc list-inside space-y-1 text-sm text-destructive">
                @foreach($validationErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Form Statistics Panel --}}
    @if($this->formStats->get('filled_fields') > 0)
        <div class="bg-muted/30 rounded-lg p-4">
            <div class="flex items-center gap-2 mb-3">
                <flux:icon name="chart-bar" class="w-4 h-4 text-muted-foreground" />
                <h4 class="font-medium text-foreground">Form Progress</h4>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <div class="font-medium text-foreground">{{ $this->formStats->get('filled_fields') }}/{{ $this->formStats->get('total_fields') }}</div>
                    <div class="text-muted-foreground">Fields filled</div>
                </div>
                <div>
                    <div class="font-medium text-foreground">{{ $this->formStats->get('completion_percentage') }}%</div>
                    <div class="text-muted-foreground">Complete</div>
                </div>
                <div>
                    <div class="font-medium {{ $this->formStats->get('required_complete') ? 'text-green-600' : 'text-amber-600' }}">
                        {{ $this->formStats->get('required_complete') ? 'Yes' : 'No' }}
                    </div>
                    <div class="text-muted-foreground">Required complete</div>
                </div>
                <div>
                    <div class="font-medium {{ $this->formStats->get('is_valid') ? 'text-green-600' : 'text-red-600' }}">
                        {{ $this->formStats->get('is_valid') ? 'Valid' : 'Invalid' }}
                    </div>
                    <div class="text-muted-foreground">Validation</div>
                </div>
            </div>
        </div>
    @endif

    {{-- Actions --}}
    <div class="flex items-center justify-between pt-4 border-t border-border">
        <flux:button 
            wire:click="resetForm" 
            variant="ghost" 
            size="sm"
            wire:confirm="Reset form to defaults?"
        >
            <flux:icon name="refresh-cw" class="w-4 h-4 mr-2" />
            Reset Form
        </flux:button>
        
        {{-- Completion Status --}}
        @if($this->formStats->get('is_valid'))
            <div class="flex items-center gap-2 text-sm text-green-600">
                <flux:icon name="check-circle" class="w-4 h-4" />
                <span class="font-medium">Ready to continue</span>
            </div>
        @endif
    </div>

</div>