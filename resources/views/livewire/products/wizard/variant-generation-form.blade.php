{{-- 💎 VARIANT GENERATION FORM - COLLECTION CROSSJOIN MAGIC --}}
<div class="space-y-6" wire:ignore.self>

    {{-- Header with Statistics --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            @if($this->isEditMode)
                <h3 class="text-lg font-semibold text-foreground">Edit Product Variants</h3>
                <p class="text-sm text-muted-foreground">Modify existing variants or add new combinations</p>
            @else
                <h3 class="text-lg font-semibold text-foreground">Generate Variants</h3>
                <p class="text-sm text-muted-foreground">Create all possible combinations of colors, widths, and drops</p>
            @endif
        </div>
        
        {{-- Variant Statistics --}}
        @if($this->variantStats->get('total_variants') > 0)
            <div class="flex flex-wrap items-center gap-4 text-sm">
                <div class="flex items-center gap-2">
                    <flux:icon name="layers" class="w-4 h-4 text-muted-foreground" />
                    <span class="font-medium">{{ $this->variantStats->get('total_variants') }}</span>
                    <span class="text-muted-foreground">Variants</span>
                </div>
                
                <div class="flex items-center gap-2">
                    <flux:icon name="palette" class="w-4 h-4 text-muted-foreground" />
                    <span class="font-medium">{{ $this->variantStats->get('total_colors') }}</span>
                    <span class="text-muted-foreground">Colors</span>
                </div>
                
                <div class="flex items-center gap-2">
                    <flux:icon name="move-horizontal" class="w-4 h-4 text-muted-foreground" />
                    <span class="font-medium">{{ $this->variantStats->get('total_widths') }}</span>
                    <span class="text-muted-foreground">×</span>
                    <span class="font-medium">{{ $this->variantStats->get('total_drops') }}</span>
                    <span class="text-muted-foreground">Sizes</span>
                </div>
            </div>
        @endif
    </div>

    {{-- Edit Mode Info or Preset Buttons --}}
    @if($this->isEditMode)
        {{-- Edit Mode Information --}}
        <div class="bg-blue-50 dark:bg-blue-950/50 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <div class="flex items-center gap-3">
                <flux:icon name="pencil" class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                <div class="flex-1">
                    <h4 class="text-sm font-semibold text-blue-900 dark:text-blue-100">
                        {{ $this->editModeStats->get('message') }}
                    </h4>
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                        You can modify existing variants or add new attribute combinations below.
                    </p>
                </div>
                <span class="text-xs px-2 py-1 bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 rounded-full">
                    Edit Mode
                </span>
            </div>
        </div>
    @else
        {{-- Preset Buttons for Create Mode --}}
        <div class="flex flex-wrap gap-2">
            <flux:button wire:click="loadPreset('blinds_basic')" variant="outline" size="sm">
                <flux:icon name="home" class="w-4 h-4 mr-2" />
                Basic Blinds
            </flux:button>
            <flux:button wire:click="loadPreset('blinds_premium')" variant="outline" size="sm">
                <flux:icon name="star" class="w-4 h-4 mr-2" />
                Premium Blinds
            </flux:button>
            <flux:button wire:click="loadPreset('curtains')" variant="outline" size="sm">
                <flux:icon name="waves" class="w-4 h-4 mr-2" />
                Curtains
            </flux:button>
        </div>
    @endif

    {{-- SKU Settings --}}
    <div class="bg-muted/30 rounded-lg p-4 space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <flux:label class="font-medium">SKU Grouping</flux:label>
                <p class="text-sm text-muted-foreground">Enable automatic parent-child SKU generation</p>
            </div>
            <flux:switch wire:model.live="enableSkuGrouping" />
        </div>
        
        @if($enableSkuGrouping)
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Parent SKU</flux:label>
                    <flux:input wire:model.live="parentSku" placeholder="e.g., 001" />
                    <flux:description>Base SKU for this product family</flux:description>
                </flux:field>
                
                <flux:field>
                    <flux:label>SKU Pattern</flux:label>
                    <flux:input wire:model.live="skuPattern" readonly />
                    <flux:description>Format: Parent-Variant (000-000)</flux:description>
                </flux:field>
            </div>
        @endif
    </div>

    {{-- Attribute Management Grid - Only show in create mode --}}
    @if(!$this->isEditMode)
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Colors --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:label class="text-base font-semibold">Colors ({{ $colors->count() }})</flux:label>
            </div>
            
            {{-- Add New Color --}}
            <div class="flex gap-2">
                <div class="flex-1 flex gap-2">
                    <input 
                        type="color" 
                        wire:model="newColorHex"
                        class="w-10 h-10 rounded border border-border cursor-pointer"
                        title="Pick a color"
                    />
                    <flux:input 
                        wire:model="newColor" 
                        placeholder="Color name..."
                        wire:keydown.enter="addColor"
                        class="flex-1"
                    />
                </div>
                <flux:button 
                    wire:click="addColor" 
                    variant="outline" 
                    size="sm"
                    :disabled="empty($newColor)"
                >
                    <flux:icon name="plus" class="w-4 h-4" />
                </flux:button>
            </div>
            
            {{-- Color List --}}
            <div class="space-y-2 max-h-40 overflow-y-auto">
                @foreach($colors as $index => $color)
                    <div class="flex items-center justify-between bg-card border border-border rounded px-3 py-2">
                        <div class="flex items-center gap-2">
                            <div class="w-4 h-4 rounded-full border border-border" 
                                 style="background-color: {{ strtolower($color) === 'white' ? '#ffffff' : (strtolower($color) === 'black' ? '#000000' : strtolower($color)) }};">
                            </div>
                            <span class="text-sm font-medium">{{ $color }}</span>
                        </div>
                        <flux:button 
                            wire:click="removeColor('{{ $color }}')" 
                            variant="ghost" 
                            size="sm"
                        >
                            <flux:icon name="x" class="w-3 h-3" />
                        </flux:button>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Widths --}}
        <div class="space-y-4">
            <flux:label class="text-base font-semibold">Widths ({{ $widths->count() }})</flux:label>
            
            {{-- Add New Width --}}
            <div class="flex gap-2">
                <flux:input 
                    wire:model="newWidth" 
                    type="number"
                    placeholder="Width in cm..."
                    wire:keydown.enter="addWidth"
                />
                <flux:button 
                    wire:click="addWidth" 
                    variant="outline" 
                    size="sm"
                    :disabled="empty($newWidth)"
                >
                    <flux:icon name="plus" class="w-4 h-4" />
                </flux:button>
            </div>
            
            {{-- Width List --}}
            <div class="space-y-2 max-h-40 overflow-y-auto">
                @foreach($widths->sort() as $width)
                    <div class="flex items-center justify-between bg-card border border-border rounded px-3 py-2">
                        <span class="text-sm font-medium">{{ $width }}cm</span>
                        <flux:button 
                            wire:click="removeWidth({{ $width }})" 
                            variant="ghost" 
                            size="sm"
                        >
                            <flux:icon name="x" class="w-3 h-3" />
                        </flux:button>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Drops --}}
        <div class="space-y-4">
            <flux:label class="text-base font-semibold">Drops ({{ $drops->count() }})</flux:label>
            
            {{-- Add New Drop --}}
            <div class="flex gap-2">
                <flux:input 
                    wire:model="newDrop" 
                    type="number"
                    placeholder="Drop in cm..."
                    wire:keydown.enter="addDrop"
                />
                <flux:button 
                    wire:click="addDrop" 
                    variant="outline" 
                    size="sm"
                    :disabled="empty($newDrop)"
                >
                    <flux:icon name="plus" class="w-4 h-4" />
                </flux:button>
            </div>
            
            {{-- Drop List --}}
            <div class="space-y-2 max-h-40 overflow-y-auto">
                @foreach($drops->sort() as $drop)
                    <div class="flex items-center justify-between bg-card border border-border rounded px-3 py-2">
                        <span class="text-sm font-medium">{{ $drop }}cm</span>
                        <flux:button 
                            wire:click="removeDrop({{ $drop }})" 
                            variant="ghost" 
                            size="sm"
                        >
                            <flux:icon name="x" class="w-3 h-3" />
                        </flux:button>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Generate/Update Button --}}
    @if(!$this->isEditMode)
        <div class="flex items-center justify-center py-4">
            <flux:button 
                wire:click="generateVariants" 
                variant="primary"
                :disabled="$colors->isEmpty() || $widths->isEmpty() || $drops->isEmpty()"
            >
                <flux:icon name="refresh-cw" class="w-4 h-4 mr-2" />
                Generate {{ $this->variantStats->get('total_combinations') }} Variants
            </flux:button>
        </div>
    @endif

    {{-- Generated Variants Preview --}}
    @if($generatedVariants->isNotEmpty())
        <div class="bg-card border border-border rounded-lg overflow-hidden">
            <div class="p-4 border-b border-border">
                <div class="flex items-center justify-between">
                    @if($this->isEditMode)
                        <h4 class="font-semibold text-foreground">Product Variants ({{ $generatedVariants->count() }})</h4>
                    @else
                        <h4 class="font-semibold text-foreground">Generated Variants ({{ $generatedVariants->count() }})</h4>
                    @endif
                    
                    {{-- SKU Analysis --}}
                    @if($enableSkuGrouping)
                        <flux:button 
                            wire:click="analyzeSkuPatterns" 
                            variant="ghost" 
                            size="sm"
                        >
                            <flux:icon name="search" class="w-4 h-4 mr-2" />
                            Analyze SKUs
                        </flux:button>
                    @endif
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-muted/50">
                        <tr class="text-left">
                            <th class="px-4 py-3 text-sm font-medium text-muted-foreground">SKU</th>
                            <th class="px-4 py-3 text-sm font-medium text-muted-foreground">Title</th>
                            <th class="px-4 py-3 text-sm font-medium text-muted-foreground">Color</th>
                            <th class="px-4 py-3 text-sm font-medium text-muted-foreground">Width</th>
                            <th class="px-4 py-3 text-sm font-medium text-muted-foreground">Drop</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($generatedVariants->take(10) as $variant)
                            <tr class="hover:bg-muted/30 transition-colors">
                                <td class="px-4 py-3">
                                    <code class="text-xs bg-muted px-2 py-1 rounded">{{ $variant['sku'] }}</code>
                                </td>
                                <td class="px-4 py-3 text-sm font-medium">{{ $variant['title'] }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-4 h-4 rounded border border-border" 
                                             style="background-color: {{ strtolower($variant['color']) === 'white' ? '#ffffff' : (strtolower($variant['color']) === 'black' ? '#000000' : strtolower($variant['color'])) }};">
                                        </div>
                                        <span class="text-sm">{{ $variant['color'] }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm">{{ $variant['width'] }}cm</td>
                                <td class="px-4 py-3 text-sm">{{ $variant['drop'] }}cm</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                
                @if($generatedVariants->count() > 10)
                    <div class="p-4 border-t border-border bg-muted/30 text-center text-sm text-muted-foreground">
                        @if($this->isEditMode)
                            @php
                                $existingCount = $this->existingVariantsCount;
                                $newCount = $generatedVariants->count() - $existingCount;
                                $remainingExisting = max(0, $existingCount - 10);
                                $remainingNew = max(0, $newCount - max(0, 10 - $existingCount));
                            @endphp
                            Showing first 10 variants.
                            @if($remainingExisting > 0 && $remainingNew > 0)
                                {{ $remainingExisting }} more existing + {{ $remainingNew }} new variants.
                            @elseif($remainingExisting > 0)
                                {{ $remainingExisting }} more existing variants.
                            @elseif($remainingNew > 0)
                                {{ $remainingNew }} more variants will be created.
                            @endif
                        @else
                            Showing first 10 variants. {{ $generatedVariants->count() - 10 }} more will be created.
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Validation Errors --}}
    @error('variants')
        <div class="bg-destructive/10 border border-destructive/20 rounded-lg p-4">
            <div class="flex items-center gap-2">
                <flux:icon name="alert-circle" class="w-5 h-5 text-destructive" />
                <h4 class="font-semibold text-destructive">Validation Error</h4>
            </div>
            <p class="text-sm text-destructive mt-2">{{ $message }}</p>
        </div>
    @enderror

    {{-- Completion Summary --}}
    @if($generatedVariants->isNotEmpty())
        <div class="bg-primary/10 border border-primary/20 rounded-lg p-4">
            <div class="flex items-center gap-2 mb-3">
                <flux:icon name="check-circle" class="w-5 h-5 text-primary" />
                @if($this->isEditMode)
                    <h4 class="font-semibold text-primary">Variants Loaded Successfully</h4>
                @else
                    <h4 class="font-semibold text-primary">Variants Generated Successfully</h4>
                @endif
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <div class="font-medium">{{ $generatedVariants->count() }} Variants</div>
                    @if($this->isEditMode)
                        <div class="text-muted-foreground">Total loaded</div>
                    @else
                        <div class="text-muted-foreground">Total combinations</div>
                    @endif
                </div>
                <div>
                    <div class="font-medium">{{ $this->variantStats->get('width_range') }}</div>
                    <div class="text-muted-foreground">Width range</div>
                </div>
                <div>
                    <div class="font-medium">{{ $this->variantStats->get('drop_range') }}</div>
                    <div class="text-muted-foreground">Drop range</div>
                </div>
                <div>
                    <div class="font-medium">{{ $colors->count() }}</div>
                    <div class="text-muted-foreground">Color options</div>
                </div>
            </div>
        </div>
    @endif

</div>