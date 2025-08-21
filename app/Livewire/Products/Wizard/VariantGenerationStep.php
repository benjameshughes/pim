<?php

namespace App\Livewire\Products\Wizard;

use App\Actions\Products\Wizard\ValidateProductWizardStepAction;
use App\Models\Product;
use Livewire\Component;

/**
 * 💎 VARIANT GENERATION STEP - Clean & Focused
 *
 * Handles the second step of product creation: variant generation.
 * Simplified variant creation with event-driven communication.
 */
class VariantGenerationStep extends Component
{
    // Form fields
    public array $colors = [];

    public array $widths = [];

    public array $drops = [];

    public string $parent_sku = '';

    public bool $enable_sku_grouping = true;

    // Generated variants
    public array $generated_variants = [];

    public int $total_variants = 0;

    // Component state
    public array $stepData = [];

    public bool $isActive = false;

    public int $currentStep = 2;

    public bool $isEditMode = false;

    public ?Product $product = null;

    // UI state
    public string $new_color = '';

    public string $new_width = '';

    public string $new_drop = '';

    /**
     * 🎪 MOUNT - Initialize with existing data
     */
    public function mount(
        array $stepData = [],
        bool $isActive = false,
        int $currentStep = 2,
        bool $isEditMode = false,
        ?Product $product = null
    ): void {
        $this->stepData = $stepData;
        $this->isActive = $isActive;
        $this->currentStep = $currentStep;
        $this->isEditMode = $isEditMode;
        $this->product = $product;

        // Populate form with existing data
        if (! empty($stepData)) {
            $this->colors = $stepData['colors'] ?? [];
            $this->widths = $stepData['widths'] ?? [];
            $this->drops = $stepData['drops'] ?? [];
            $this->parent_sku = $stepData['parent_sku'] ?? '';
            $this->enable_sku_grouping = $stepData['enable_sku_grouping'] ?? true;
            $this->generated_variants = $stepData['generated_variants'] ?? [];
            $this->total_variants = count($this->generated_variants);
        }
    }

    /**
     * ➕ ADD COLOR
     */
    public function addColor(): void
    {
        if ($this->new_color && ! in_array($this->new_color, $this->colors)) {
            $this->colors[] = trim($this->new_color);
            $this->new_color = '';
            $this->generateVariants();
        }
    }

    /**
     * ➕ ADD WIDTH
     */
    public function addWidth(): void
    {
        if ($this->new_width && ! in_array((int) $this->new_width, $this->widths)) {
            $this->widths[] = (int) $this->new_width;
            $this->new_width = '';
            $this->generateVariants();
        }
    }

    /**
     * ➕ ADD DROP
     */
    public function addDrop(): void
    {
        if ($this->new_drop && ! in_array((int) $this->new_drop, $this->drops)) {
            $this->drops[] = (int) $this->new_drop;
            $this->new_drop = '';
            $this->generateVariants();
        }
    }

    /**
     * 🗑️ REMOVE COLOR
     */
    public function removeColor(string $color): void
    {
        $this->colors = array_values(array_filter($this->colors, fn ($c) => $c !== $color));
        $this->generateVariants();
    }

    /**
     * 🗑️ REMOVE WIDTH
     */
    public function removeWidth(int $width): void
    {
        $this->widths = array_values(array_filter($this->widths, fn ($w) => $w !== $width));
        $this->generateVariants();
    }

    /**
     * 🗑️ REMOVE DROP
     */
    public function removeDrop(int $drop): void
    {
        $this->drops = array_values(array_filter($this->drops, fn ($d) => $d !== $drop));
        $this->generateVariants();
    }

    /**
     * ⚡ QUICK ADD COLOR - Direct auto-add without input field
     */
    public function quickAddColor(string $color): void
    {
        $normalizedColor = trim($color);
        if ($normalizedColor && ! in_array($normalizedColor, $this->colors)) {
            $this->colors[] = $normalizedColor;
            $this->generateVariants();
        }
    }

    /**
     * ⚡ QUICK ADD WIDTH - Direct auto-add without input field
     */
    public function quickAddWidth(int $width): void
    {
        if ($width > 0 && ! in_array($width, $this->widths)) {
            $this->widths[] = $width;
            sort($this->widths); // Keep widths sorted
            $this->generateVariants();
        }
    }

    /**
     * ⚡ QUICK ADD DROP - Direct auto-add without input field
     */
    public function quickAddDrop(int $drop): void
    {
        if ($drop > 0 && ! in_array($drop, $this->drops)) {
            $this->drops[] = $drop;
            sort($this->drops); // Keep drops sorted
            $this->generateVariants();
        }
    }

    /**
     * 🎭 LOAD PRESET CONFIGURATIONS
     */
    public function loadPreset(string $preset): void
    {
        match ($preset) {
            'roller_blinds' => $this->loadRollerBlindsPreset(),
            'venetian_blinds' => $this->loadVenetianBlindsPreset(),
            default => null,
        };

        $this->generateVariants();
    }

    /**
     * 🎢 ROLLER BLINDS PRESET
     */
    protected function loadRollerBlindsPreset(): void
    {
        $this->colors = ['White', 'Cream', 'Grey', 'Black'];
        $this->widths = [60, 90, 120, 150, 180, 210, 240];
        $this->drops = [140, 160, 210];
    }

    /**
     * 🎯 VENETIAN BLINDS PRESET - 45cm to 240cm in 15cm increments
     */
    protected function loadVenetianBlindsPreset(): void
    {
        $this->colors = ['White', 'Cream', 'Grey', 'Black', 'Natural'];
        // Generate 45cm to 240cm in 15cm increments
        $this->widths = range(45, 240, 15);
        $this->drops = [140, 160, 210];
    }

    /**
     * ⚡ GENERATE VARIANTS
     */
    public function generateVariants(): void
    {
        $this->generated_variants = [];
        $variantIndex = 1;

        // Generate all combinations
        if (empty($this->colors) && empty($this->widths) && empty($this->drops)) {
            return;
        }

        $colors = empty($this->colors) ? [''] : $this->colors;
        $widths = empty($this->widths) ? [0] : $this->widths;
        $drops = empty($this->drops) ? [0] : $this->drops;

        foreach ($colors as $color) {
            foreach ($widths as $width) {
                foreach ($drops as $drop) {
                    $sku = $this->generateVariantSku($variantIndex, $color, $width, $drop);

                    $this->generated_variants[] = [
                        'sku' => $sku,
                        'color' => $color,
                        'width' => $width,
                        'drop' => $drop,
                        'price' => 0,
                        'stock' => 0,
                    ];

                    $variantIndex++;
                }
            }
        }

        $this->total_variants = count($this->generated_variants);
        $this->emitDataUpdate();
    }

    /**
     * 🏷️ GENERATE VARIANT SKU
     */
    protected function generateVariantSku(int $index, string $color, int $width, int $drop): string
    {
        if ($this->enable_sku_grouping && $this->parent_sku) {
            return $this->parent_sku.'-'.str_pad($index, 3, '0', STR_PAD_LEFT);
        }

        $parts = ['VAR'];
        if ($color) {
            $parts[] = strtoupper(substr($color, 0, 3));
        }
        if ($width) {
            $parts[] = $width.'W';
        }
        if ($drop) {
            $parts[] = $drop.'D';
        }

        return implode('-', $parts).'-'.str_pad($index, 2, '0', STR_PAD_LEFT);
    }

    /**
     * 🎯 VALIDATE AND COMPLETE STEP
     */
    public function completeStep(): void
    {
        $validateAction = new ValidateProductWizardStepAction;
        $result = $validateAction->execute(2, $this->getFormData());

        if (! $result['data']['valid']) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Please fix the validation errors: '.implode(', ', array_values($result['data']['errors'])),
            ]);

            return;
        }

        $this->dispatch('step-completed', 2, $this->getFormData());
    }

    /**
     * 📡 EMIT DATA UPDATE TO PARENT
     */
    protected function emitDataUpdate(): void
    {
        $this->dispatch('variant-data-updated', $this->getFormData());
    }

    /**
     * 📊 GET FORM DATA
     */
    protected function getFormData(): array
    {
        return [
            'colors' => $this->colors,
            'widths' => $this->widths,
            'drops' => $this->drops,
            'parent_sku' => $this->parent_sku,
            'enable_sku_grouping' => $this->enable_sku_grouping,
            'generated_variants' => $this->generated_variants,
            'total_variants' => $this->total_variants,
        ];
    }

    /**
     * 🎨 RENDER
     */
    public function render()
    {
        return view('livewire.products.wizard.variant-generation-step');
    }
}
