<?php

namespace App\Livewire\Products\Wizard;

use App\Actions\Import\AnalyzeSkuPatternsAction;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * 🔥✨ VARIANT GENERATION FORM - COLLECTION CROSSJOIN MAGIC ✨🔥
 *
 * Sexy Collection-powered variant generation with crossJoin combinations
 * Integrates with our SKU grouping system for automatic parent creation
 */
class VariantGenerationForm extends Component
{
    public bool $isActive = false;

    /** @var Collection<string, mixed> */
    public Collection $existingData;

    // Collection properties for variant attributes
    /** @var Collection<int, string> */
    public Collection $colors;

    /** @var Collection<int, int> */
    public Collection $widths;

    /** @var Collection<int, int> */
    public Collection $drops;

    // Input fields for adding new options
    public string $newColor = '';

    public string $newWidth = '';

    public string $newDrop = '';

    // SKU generation settings
    public string $parentSku = '';

    public bool $enableSkuGrouping = true;

    public string $skuPattern = '000-000'; // 3 digit parent + 3 digit variant

    // Generated variants Collection
    /** @var Collection<int, array<string, mixed>> */
    public Collection $generatedVariants;

    /**
     * 🎪 MOUNT WITH COLLECTION INITIALIZATION
     */
    /**
     * @param  array<string, mixed>  $stepData
     */
    public function mount(bool $isActive = false, array $stepData = []): void
    {
        $this->isActive = $isActive;
        $this->existingData = collect($stepData);

        // Initialize Collections with defaults or existing data - ensure Collections
        // Initialize Collections with defaults or existing data - ensure Collections
        $colorsArray = $this->existingData->get('colors', []);
        /** @var Collection<int, string> */
        $this->colors = collect($colorsArray);
        if ($this->colors->isEmpty()) {
            /** @var Collection<int, string> */
            $this->colors = collect(); // Start empty, no defaults
        }

        $widthsArray = $this->existingData->get('widths', []);
        /** @var Collection<int, int> */
        $this->widths = collect($widthsArray);
        if ($this->widths->isEmpty()) {
            /** @var Collection<int, int> */
            $this->widths = collect(); // Start empty, no defaults
        }

        $dropsArray = $this->existingData->get('drops', []);
        /** @var Collection<int, int> */
        $this->drops = collect($dropsArray);
        if ($this->drops->isEmpty()) {
            /** @var Collection<int, int> */
            $this->drops = collect(); // Start empty, no defaults
        }

        $this->parentSku = $this->existingData->get('parent_sku', '');
        $this->enableSkuGrouping = $this->existingData->get('enable_sku_grouping', true);

        // Check if we have pre-populated generated variants (edit mode)
        $existingGeneratedVariants = $this->existingData->get('generated_variants', []);
        if (! empty($existingGeneratedVariants)) {
            // EDIT MODE: Use existing variants as-is, no generation
            $this->initializeEditMode($existingGeneratedVariants);
        } else {
            // CREATE MODE: Initialize for new generation
            $this->initializeCreateMode();
        }
    }

    /**
     * 🔧 INITIALIZE EDIT MODE - Display existing variants as-is
     */
    private function initializeEditMode(array $existingGeneratedVariants): void
    {
        // Load existing variants exactly as they are - no regeneration
        $this->generatedVariants = collect($existingGeneratedVariants);

        // In edit mode, we don't need to populate color/width/drop collections
        // because we're not generating new combinations, just displaying existing variants
        // The attributes are already extracted and stored in the collections from mount()
    }

    /**
     * 🚀 INITIALIZE CREATE MODE - Set up for variant generation
     */
    private function initializeCreateMode(): void
    {
        // Initialize empty for new generation
        $this->generatedVariants = collect();

        // Auto-generate if we have attribute data but no pre-generated variants
        if ($this->existingData->isNotEmpty() && ($this->colors->isNotEmpty() || $this->widths->isNotEmpty() || $this->drops->isNotEmpty())) {
            $this->generateVariants();
        }
    }

    /**
     * 🎨 ADD NEW COLOR USING COLLECTIONS
     */
    public function addColor(): void
    {
        if (! empty($this->newColor)) {
            // Normalize color name: Title Case and trim
            $normalizedColor = str($this->newColor)->trim()->title()->toString();

            if (! $this->colors->contains($normalizedColor)) {
                $this->colors->push($normalizedColor);
                $this->newColor = '';

                // Only regenerate variants in create mode
                if (! $this->isEditMode()) {
                    $this->generateVariants();
                }
            }
        }
    }

    /**
     * 📏 ADD NEW WIDTH
     */
    public function addWidth(): void
    {
        $this->validate([
            'newWidth' => 'required|numeric|min:1|max:500',
        ]);

        $width = (int) $this->newWidth;
        if (! $this->widths->contains($width)) {
            $this->widths = $this->widths->push($width)->sort()->values();
            $this->newWidth = '';

            // Only regenerate variants in create mode
            if (! $this->isEditMode()) {
                $this->generateVariants();
            }
        }
    }

    /**
     * 📐 ADD NEW DROP
     */
    public function addDrop(): void
    {
        $this->validate([
            'newDrop' => 'required|numeric|min:1|max:500',
        ]);

        $drop = (int) $this->newDrop;
        if (! $this->drops->contains($drop)) {
            $this->drops = $this->drops->push($drop)->sort()->values();
            $this->newDrop = '';

            // Only regenerate variants in create mode
            if (! $this->isEditMode()) {
                $this->generateVariants();
            }
        }
    }

    /**
     * 🗑️ REMOVE ATTRIBUTE USING COLLECTIONS
     */
    public function removeColor(string $color): void
    {
        $this->colors = $this->colors->reject(fn ($c) => $c === $color)->values();

        // Only regenerate variants in create mode
        if (! $this->isEditMode()) {
            $this->generateVariants();
        }
    }

    public function removeWidth(int $width): void
    {
        $this->widths = $this->widths->reject(fn ($w) => $w === $width)->values();

        // Only regenerate variants in create mode
        if (! $this->isEditMode()) {
            $this->generateVariants();
        }
    }

    public function removeDrop(int $drop): void
    {
        $this->drops = $this->drops->reject(fn ($d) => $d === $drop)->values();

        // Only regenerate variants in create mode
        if (! $this->isEditMode()) {
            $this->generateVariants();
        }
    }

    /**
     * 🎭 PRESET MANAGEMENT
     */
    public function loadPreset(string $preset): void
    {
        match ($preset) {
            'window_treatments' => $this->loadWindowTreatmentPreset(),
            'basic' => $this->loadBasicPreset(),
            default => null, // Handle invalid presets gracefully
        };

        // Only regenerate variants in create mode (presets don't make sense in edit mode anyway)
        if (! $this->isEditMode()) {
            $this->generateVariants();
        }
    }

    private function loadWindowTreatmentPreset(): void
    {
        $this->colors = collect(['White', 'Cream', 'Grey', 'Black', 'Blue']);
        $this->widths = collect([120, 140, 160, 180, 200]);
        $this->drops = collect([160, 180, 200, 220]);
    }

    private function loadBasicPreset(): void
    {
        $this->colors = collect(['Red', 'Blue', 'Green']);
        $this->widths = collect([120, 150, 180]);
        $this->drops = collect([160, 180]);
    }

    public function clearAll(): void
    {
        $this->colors = collect();
        $this->widths = collect();
        $this->drops = collect();
        $this->generatedVariants = collect();
        $this->newColor = '';
        $this->newWidth = '';
        $this->newDrop = '';
    }

    /**
     * 🚀 COLLECTION CROSSJOIN MAGIC - GENERATE ALL COMBINATIONS
     */
    public function generateVariants(): void
    {
        if ($this->colors->isEmpty() || $this->widths->isEmpty() || $this->drops->isEmpty()) {
            $this->generatedVariants = collect();

            return;
        }

        // 🔥 COLLECTION CROSSJOIN MAGIC - FIXED STRUCTURE! 🔥
        /** @var Collection<int, array<string, mixed>> $generatedVariants */
        $generatedVariants = $this->colors
            ->crossJoin($this->widths, $this->drops)
            ->map(function (array $combination, int $index): array {
                // Extract values - crossJoin with multiple params creates flat array
                $color = $combination[0];
                $width = $combination[1];
                $drop = $combination[2];

                // In edit mode, try to preserve existing pricing data for matching variants
                $existingPrice = 0.00;
                $existingStock = 0;
                
                if ($this->isEditMode()) {
                    $existingPrice = $this->findExistingVariantPrice((string) $color, (int) $width, (int) $drop);
                    $existingStock = $this->findExistingVariantStock((string) $color, (int) $width, (int) $drop);
                }

                return [
                    'id' => $index + 1,
                    'color' => (string) $color,
                    'width' => (int) $width,
                    'drop' => (int) $drop,
                    'sku' => $this->generateVariantSku($index + 1),
                    'title' => $this->generateVariantTitle((string) $color, (int) $width, (int) $drop),
                    'price' => $existingPrice, // Preserve existing price in edit mode, default to 0 in create mode
                    'stock' => $existingStock, // Preserve existing stock in edit mode, default to 0 in create mode
                ];
            });

        $this->generatedVariants = $generatedVariants;
    }

    /**
     * 🔖 GENERATE VARIANT SKU USING PATTERN
     */
    private function generateVariantSku(int $variantNumber): string
    {
        if (! $this->enableSkuGrouping || empty($this->parentSku)) {
            return 'VAR-'.str_pad((string) $variantNumber, 3, '0', STR_PAD_LEFT);
        }

        // Use our SKU grouping pattern (000-000)
        $parentPart = str_pad((string) $this->parentSku, 3, '0', STR_PAD_LEFT);
        $variantPart = str_pad((string) $variantNumber, 3, '0', STR_PAD_LEFT);

        return "{$parentPart}-{$variantPart}";
    }

    /**
     * 🏷️ GENERATE VARIANT TITLE
     */
    private function generateVariantTitle(string $color, int $width, int $drop): string
    {
        return "{$color} {$width}cm x {$drop}cm";
    }

    /**
     * 🎯 VALIDATE STEP
     */
    #[On('validate-current-step')]
    public function validateStep(): void
    {
        if (! $this->isActive) {
            return;
        }

        $this->resetErrorBag();

        // Collection-based validation
        $errors = collect();

        if ($this->colors->isEmpty()) {
            $errors->push('At least one color is required');
        }

        if ($this->widths->isEmpty()) {
            $errors->push('At least one width is required');
        }

        if ($this->drops->isEmpty()) {
            $errors->push('At least one drop is required');
        }

        if ($this->enableSkuGrouping && empty($this->parentSku)) {
            $this->addError('parentSku', 'Parent SKU is required when SKU grouping is enabled');
        }

        if ($errors->isNotEmpty()) {
            $errors->each(fn ($error) => $this->addError('variants', $error));

            return;
        }

        // Generate variants if not already done
        if ($this->generatedVariants->isEmpty()) {
            $this->generateVariants();
        }

        // Complete step with all variant data
        $this->completeStep();
    }

    /**
     * ✅ COMPLETE STEP WITH VARIANT DATA
     */
    private function completeStep(): void
    {
        $stepData = [
            'colors' => $this->colors->toArray(),
            'widths' => $this->widths->toArray(),
            'drops' => $this->drops->toArray(),
            'parent_sku' => $this->parentSku,
            'enable_sku_grouping' => $this->enableSkuGrouping,
            'generated_variants' => $this->generatedVariants->toArray(),
            'total_variants' => $this->generatedVariants->count(),
        ];

        $this->dispatch('step-completed', step: 2, data: $stepData);
    }

    /**
     * 📊 VARIANT STATISTICS USING COLLECTIONS
     */
    /**
     * @return Collection<string, mixed>
     */
    #[Computed]
    public function variantStats(): Collection
    {
        return collect([
            'total_variants' => $this->generatedVariants->count(),
            'total_colors' => $this->colors->count(),
            'total_widths' => $this->widths->count(),
            'total_drops' => $this->drops->count(),
            'total_combinations' => $this->colors->count() * $this->widths->count() * $this->drops->count(),
            'most_common_width' => $this->widths->isNotEmpty() ? (collect($this->widths->mode())->first() ?? 'N/A') : 'N/A',
            'width_range' => $this->widths->isEmpty() ? 'N/A' : $this->widths->min().'-'.$this->widths->max().'cm',
            'drop_range' => $this->drops->isEmpty() ? 'N/A' : $this->drops->min().'-'.$this->drops->max().'cm',
        ]);
    }

    /**
     * 🔍 ANALYZE SKU PATTERNS (Integration with our SKU grouping system)
     */
    /**
     * @return Collection<string, mixed>
     */
    public function analyzeSkuPatterns(): Collection
    {
        if ($this->generatedVariants->isEmpty()) {
            return collect(['analysis' => 'No variants to analyze']);
        }

        try {
            // Use our AnalyzeSkuPatternsAction
            $analyzer = new AnalyzeSkuPatternsAction;

            // Convert generated variants to the format expected by the analyzer
            $mockVariants = $this->generatedVariants->map(function ($variant) {
                return (object) [
                    'sku' => $variant['sku'],
                    'color' => $variant['color'],
                    'width' => $variant['width'],
                    'product_id' => 1, // Mock product ID
                ];
            });

            $result = $analyzer->execute($mockVariants);

            /** @var array<string, mixed> $data */
            $data = $result['data'] ?? [];

            return collect($data);

        } catch (\Exception $e) {
            return collect(['error' => $e->getMessage()]);
        }
    }

    /**
     * 📊 GET GENERATION STATISTICS
     */
    /**
     * @return Collection<string, mixed>
     */
    #[Computed]
    public function generationStats(): Collection
    {
        return collect([
            'total_variants' => $this->generatedVariants->count(),
            'total_colors' => $this->colors->count(),
            'total_widths' => $this->widths->count(),
            'total_drops' => $this->drops->count(),
            'total_combinations' => $this->colors->count() * $this->widths->count() * $this->drops->count(),
            'most_common_width' => $this->widths->isNotEmpty() ? (collect($this->widths->mode())->first() ?? 'N/A') : 'N/A',
            'width_range' => $this->widths->isEmpty() ? 'N/A' : $this->widths->min().'-'.$this->widths->max().'cm',
            'drop_range' => $this->drops->isEmpty() ? 'N/A' : $this->drops->min().'-'.$this->drops->max().'cm',
        ]);
    }

    /**
     * 🔍 EDIT MODE STATUS AND INDICATORS
     */
    #[Computed]
    public function isEditMode(): bool
    {
        return $this->generatedVariants->contains('existing', true);
    }

    /**
     * 💰 FIND EXISTING VARIANT PRICE
     * 
     * Look up existing pricing for a variant by attributes in edit mode
     */
    private function findExistingVariantPrice(string $color, int $width, int $drop): float
    {
        if (!$this->isEditMode()) {
            return 0.00;
        }

        // Search through existing generated variants for matching attributes
        $existingVariant = $this->generatedVariants->first(function ($variant) use ($color, $width, $drop) {
            return isset($variant['existing']) && $variant['existing'] === true &&
                   $variant['color'] === $color &&
                   $variant['width'] === $width &&
                   $variant['drop'] === $drop;
        });

        return $existingVariant ? (float) ($existingVariant['price'] ?? 0.00) : 0.00;
    }

    /**
     * 📦 FIND EXISTING VARIANT STOCK
     * 
     * Look up existing stock for a variant by attributes in edit mode
     */
    private function findExistingVariantStock(string $color, int $width, int $drop): int
    {
        if (!$this->isEditMode()) {
            return 0;
        }

        // Search through existing generated variants for matching attributes
        $existingVariant = $this->generatedVariants->first(function ($variant) use ($color, $width, $drop) {
            return isset($variant['existing']) && $variant['existing'] === true &&
                   $variant['color'] === $color &&
                   $variant['width'] === $width &&
                   $variant['drop'] === $drop;
        });

        return $existingVariant ? (int) ($existingVariant['stock'] ?? 0) : 0;
    }

    #[Computed]
    public function existingVariantsCount(): int
    {
        return $this->generatedVariants->where('existing', true)->count();
    }

    #[Computed]
    public function editModeStats(): Collection
    {
        $hasExistingVariants = $this->generatedVariants->contains('existing', true);

        if (! $hasExistingVariants) {
            return collect(['mode' => 'create']);
        }

        $existingVariants = $this->generatedVariants->where('existing', true);
        $newVariants = $this->generatedVariants->where('existing', '!=', true);

        return collect([
            'mode' => 'edit',
            'total_existing' => $existingVariants->count(),
            'total_new' => $newVariants->count(),
            'total_variants' => $this->generatedVariants->count(),
            'message' => "Editing {$existingVariants->count()} existing variants".
                        ($newVariants->isNotEmpty() ? " + {$newVariants->count()} new variants" : ''),
        ]);
    }

    /**
     * 🎨 RENDER THE FORM
     */
    /**
     * @return \Illuminate\View\View
     */
    public function render()
    {
        return view('livewire.products.wizard.variant-generation-form');
    }
}
