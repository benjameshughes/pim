<?php

namespace App\Livewire\Products\Wizard;

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * 🔥✨ PRICING & STOCK FORM - COLLECTION-POWERED STEP 4 ✨🔥
 *
 * Final step: Collection-based pricing management with multi-marketplace support
 * Handles retail prices, marketplace pricing, VAT, and stock levels
 */
class PricingStockForm extends Component
{
    public bool $isActive = false;

    /** @var Collection<string, mixed> */
    public Collection $existingData;

    // Pricing Collections
    /** @var Collection<int, array<string, mixed>> */
    public Collection $variantPricing;

    /** @var Collection<int, array<string, mixed>> */
    public Collection $marketplacePricing;

    /** @var Collection<int, int> */
    public Collection $stockLevels;

    // Default pricing settings
    public float $defaultRetailPrice = 0.00;

    public float $defaultCostPrice = 0.00;

    public int $defaultStockLevel = 0;

    public float $vatPercentage = 20.0;

    public bool $pricesIncludeVat = true;

    // Marketplace settings
    /** @var Collection<int, array<string, mixed>> */
    public Collection $availableMarketplaces;

    /** @var Collection<int, string> */
    public Collection $selectedMarketplaces;

    public bool $enableMarketplacePricing = true;

    // Auto-linking settings
    public bool $enableAutoLinking = true;

    /** @var Collection<int, string> */
    public Collection $autoLinkMarketplaces;

    // Validation using Collections
    /** @var Collection<int, string> */
    public Collection $validationErrors;

    /**
     * 🎪 MOUNT WITH COLLECTION INITIALIZATION
     */
    /**
     * @param  array<string, mixed>  $stepData
     * @param  array<int, array<string, mixed>>  $allStepData
     */
    public function mount(bool $isActive = false, array $stepData = [], array $allStepData = []): void
    {
        $this->isActive = $isActive;
        $this->existingData = collect($stepData);
        $this->validationErrors = collect();

        // Debug: Confirm we're in the right component
        \Log::info('PricingStockForm: Component mounted', [
            'component' => static::class,
            'is_active' => $isActive,
            'step_data_keys' => array_keys($stepData),
            'all_step_data_keys' => array_keys($allStepData),
            'has_step_2_data' => isset($allStepData[2]),
            'step_2_has_variants' => isset($allStepData[2]['generated_variants']),
        ]);

        // ✨ If we have allStepData, use it to access variant information from Step 2
        if (! empty($allStepData) && isset($allStepData[2])) {
            // Merge Step 2 data into our existing data for variant access
            $step2Data = collect($allStepData[2]);
            if ($step2Data->has('generated_variants')) {
                $this->existingData->put('generated_variants', $step2Data->get('generated_variants'));
                $this->existingData->put('total_variants', $step2Data->get('total_variants', 0));
                
                // Also merge any step 2 configuration data
                $this->existingData->put('colors', $step2Data->get('colors', []));
                $this->existingData->put('widths', $step2Data->get('widths', []));
                $this->existingData->put('drops', $step2Data->get('drops', []));
                $this->existingData->put('parent_sku', $step2Data->get('parent_sku', ''));
            }
        }

        // If we have existing step 4 data, prioritize it over step 2 data
        if (! empty($stepData)) {
            // Step 4 data takes precedence for pricing-specific fields
            foreach (['variant_pricing', 'pricing_settings', 'selected_marketplaces'] as $key) {
                if (isset($stepData[$key])) {
                    $this->existingData->put($key, $stepData[$key]);
                }
            }
        }

        // Initialize pricing Collections
        $this->variantPricing = collect();
        $this->marketplacePricing = collect();
        $this->stockLevels = collect();

        // Load existing pricing data
        if ($this->existingData->has('variant_pricing')) {
            $this->variantPricing = collect($this->existingData->get('variant_pricing', []));
        }

        // Initialize marketplaces
        $this->availableMarketplaces = collect([
            ['id' => 'shopify', 'name' => 'Shopify', 'enabled' => true],
            ['id' => 'ebay', 'name' => 'eBay', 'enabled' => true],
            ['id' => 'amazon', 'name' => 'Amazon', 'enabled' => false],
            ['id' => 'mirakl', 'name' => 'Mirakl', 'enabled' => false],
        ]);

        /** @var Collection<int, string> */
        $defaultMarketplaces = collect(['shopify', 'ebay']);
        $this->selectedMarketplaces = $this->existingData->get('selected_marketplaces', $defaultMarketplaces);

        // Initialize auto-linking settings
        $this->autoLinkMarketplaces = collect($this->existingData->get('auto_link_marketplaces', ['shopify', 'ebay']));
        $this->enableAutoLinking = $this->existingData->get('enable_auto_linking', true);

        // Load settings
        $this->defaultRetailPrice = $this->existingData->get('default_retail_price', 0.00);
        $this->defaultCostPrice = $this->existingData->get('default_cost_price', 0.00);
        $this->defaultStockLevel = $this->existingData->get('default_stock_level', 0);
        $this->vatPercentage = $this->existingData->get('vat_percentage', 20.0);
        $this->pricesIncludeVat = $this->existingData->get('prices_include_vat', true);

        // Initialize pricing from generated variants if available
        $this->initializePricingFromVariants();
    }

    /**
     * 💎 INITIALIZE PRICING FROM GENERATED VARIANTS
     */
    private function initializePricingFromVariants(): void
    {
        // Get variants from multiple possible locations with debugging
        $variantData = $this->existingData->get('generated_variants',
            $this->existingData->get('variants', [])
        );

        // Check if we're in edit mode by looking for existing variants with DB IDs
        $isEditMode = $this->detectEditMode($variantData);

        // Debug: Log what we're working with
        \Log::info('PricingStockForm: Initializing variants', [
            'is_edit_mode' => $isEditMode,
            'has_generated_variants' => $this->existingData->has('generated_variants'),
            'has_variants' => $this->existingData->has('variants'),
            'generated_variants_count' => is_array($this->existingData->get('generated_variants')) ? count($this->existingData->get('generated_variants')) : 0,
            'variants_count' => is_array($this->existingData->get('variants')) ? count($this->existingData->get('variants')) : 0,
            'total_variants' => $this->existingData->get('total_variants', 'not_set'),
            'existing_data_keys' => $this->existingData->keys()->toArray(),
            'variant_data_sample' => is_array($variantData) && !empty($variantData) ? array_slice($variantData, 0, 1, true) : 'empty_or_not_array',
        ]);

        if ($variantData && is_array($variantData) && count($variantData) > 0) {
            $variants = collect($variantData);

            /** @var Collection<int, array<string, mixed>> */
            $this->variantPricing = $variants->map(function (array $variant, int $index) use ($isEditMode): array {
                // Handle both new variants (temp IDs) and existing variants (DB IDs)
                $variantId = $variant['id'] ?? ($variant['original_id'] ?? $index);
                $existingVariant = isset($variant['existing']) && $variant['existing'] === true;

                // In edit mode, preserve existing values; in create mode, use defaults
                $retailPrice = $isEditMode ? 
                    (float) ($variant['price'] ?? $this->defaultRetailPrice) : 
                    $this->defaultRetailPrice;
                
                $stockLevel = $isEditMode ? 
                    (int) ($variant['stock'] ?? $this->defaultStockLevel) : 
                    $this->defaultStockLevel;

                return [
                    'variant_id' => $variantId,
                    'sku' => $variant['sku'] ?? '',
                    'color' => $variant['color'] ?? '',
                    'width' => $variant['width'] ?? '',
                    'drop' => $variant['drop'] ?? '',
                    'title' => $variant['title'] ?? $this->generateVariantTitle($variant),
                    'retail_price' => $retailPrice,
                    'cost_price' => (float) $this->defaultCostPrice,
                    'vat_inclusive_price' => $this->calculateVatInclusivePrice($retailPrice),
                    'stock_level' => $stockLevel,
                    'marketplace_pricing' => $this->generateMarketplacePricing($retailPrice),
                    'existing_variant' => $existingVariant, // Track if this is an existing variant
                    'is_edit_mode' => $isEditMode, // Flag for UI differentiation
                ];
            });

            \Log::info('PricingStockForm: Successfully initialized variants', [
                'variant_count' => $this->variantPricing->count(),
                'edit_mode' => $isEditMode,
                'existing_variants' => $this->variantPricing->where('existing_variant', true)->count(),
                'sample_variant' => $this->variantPricing->first(),
            ]);
        } else {
            // Create empty pricing collection
            $this->variantPricing = collect();

            \Log::warning('PricingStockForm: No variants found to initialize', [
                'variant_data_type' => gettype($variantData),
                'variant_data_count' => is_array($variantData) ? count($variantData) : 'not_array',
            ]);
        }
    }

    /**
     * 🔍 DETECT EDIT MODE
     * 
     * Determines if we're editing existing variants based on data structure
     */
    private function detectEditMode(mixed $variantData): bool
    {
        if (!is_array($variantData) || empty($variantData)) {
            return false;
        }

        // Check if any variants have the 'existing' flag set to true
        foreach ($variantData as $variant) {
            if (is_array($variant) && isset($variant['existing']) && $variant['existing'] === true) {
                return true;
            }
            
            // Also check if any variants have numeric IDs (database IDs)
            if (is_array($variant) && isset($variant['id']) && is_numeric($variant['id']) && $variant['id'] > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * 🔄 UPDATE WHEN SELECTED MARKETPLACES CHANGES
     */
    public function updatedSelectedMarketplaces(): void
    {
        // Regenerate marketplace pricing for all variants when marketplaces change
        $this->variantPricing = $this->variantPricing->map(function (array $pricing): array {
            $pricing['marketplace_pricing'] = $this->generateMarketplacePricing($pricing['retail_price']);

            return $pricing;
        });
    }

    /**
     * 🔄 HANDLE VARIANT PRICING UPDATES
     *
     * This method is called automatically when variantPricing property is updated
     * via wire:model bindings. Can be used for custom validation or side effects.
     */
    public function updatedVariantPricing(): void
    {
        // Optional: Add any custom logic when variant pricing changes
        // For now, we'll just log for debugging purposes
        \Log::info('PricingStockForm: Variant pricing updated', [
            'variant_count' => $this->variantPricing->count(),
            'has_valid_prices' => $this->variantPricing->every(fn ($pricing) => $pricing['retail_price'] > 0),
        ]);

        // Could add validation, recalculate totals, etc. here if needed
    }

    /**
     * 🧮 CALCULATE VAT INCLUSIVE PRICE
     */
    private function calculateVatInclusivePrice(float $price): float
    {
        if ($this->pricesIncludeVat) {
            return $price;
        }

        return $price * (1 + ($this->vatPercentage / 100));
    }

    /**
     * 🏪 GENERATE MARKETPLACE PRICING
     *
     * @return array<string, float>
     */
    private function generateMarketplacePricing(float $basePrice): array
    {
        return $this->selectedMarketplaces->mapWithKeys(function (string $marketplace) use ($basePrice): array {
            // Add marketplace-specific markup
            $markup = match ($marketplace) {
                'shopify' => 1.05,  // 5% markup
                'ebay' => 1.08,     // 8% markup
                'amazon' => 1.12,   // 12% markup
                'mirakl' => 1.10,   // 10% markup
                default => 1.05,
            };

            return [$marketplace => round($basePrice * $markup, 2)];
        })->toArray();
    }

    /**
     * 📋 GENERATE VARIANT TITLE
     *
     * @param  array<string, mixed>  $variant
     */
    private function generateVariantTitle(array $variant): string
    {
        $parts = collect([
            $variant['color'] ?? null,
            isset($variant['width']) ? $variant['width'].'cm' : null,
            isset($variant['drop']) ? $variant['drop'].'cm' : null,
        ])->filter()->implode(' × ');

        return $parts ?: 'Variant';
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
        $this->validationErrors = collect();

        // Collection-based validation
        $errors = collect();

        // Check if we have pricing data
        if ($this->variantPricing->isEmpty()) {
            $errors->push('No variants found to price. Please complete the variant generation step.');
        }

        // Validate pricing data
        $this->variantPricing->each(function (array $pricing, int $index) use ($errors): void {
            $variantName = $pricing['title'] ?? 'Variant '.($index + 1);

            if ($pricing['retail_price'] <= 0) {
                $errors->push("Retail price is required for {$variantName}");
            }

            if ($pricing['cost_price'] < 0) {
                $errors->push("Cost price cannot be negative for {$variantName}");
            }

            if ($pricing['stock_level'] < 0) {
                $errors->push("Stock level cannot be negative for {$variantName}");
            }

            // Validate marketplace pricing
            if ($this->enableMarketplacePricing) {
                collect($pricing['marketplace_pricing'])->each(function (float $price, string $marketplace) use ($variantName, $errors): void {
                    if ($price <= 0) {
                        $errors->push("Marketplace price is required for {$variantName} on {$marketplace}");
                    }
                });
            }
        });

        if ($errors->isNotEmpty()) {
            $this->validationErrors = $errors;
            $errors->each(fn ($error) => $this->addError('pricing', $error));

            return;
        }

        // Complete step with all pricing data
        $this->completeStep();
    }

    /**
     * ✅ COMPLETE STEP WITH PRICING DATA
     */
    private function completeStep(): void
    {
        $stepData = [
            'variant_pricing' => $this->variantPricing->toArray(),
            'marketplace_pricing' => $this->marketplacePricing->toArray(),
            'selected_marketplaces' => $this->selectedMarketplaces->toArray(),
            'pricing_settings' => [
                'default_retail_price' => $this->defaultRetailPrice,
                'default_cost_price' => $this->defaultCostPrice,
                'default_stock_level' => $this->defaultStockLevel,
                'vat_percentage' => $this->vatPercentage,
                'prices_include_vat' => $this->pricesIncludeVat,
                'enable_marketplace_pricing' => $this->enableMarketplacePricing,
            ],
            'pricing_statistics' => $this->pricingStats()->toArray(),
            'marketplace_settings' => [
                'enable_auto_linking' => $this->enableAutoLinking,
                'enabled_marketplaces' => $this->autoLinkMarketplaces->toArray(),
                'selected_marketplaces' => $this->selectedMarketplaces->toArray(),
            ],
        ];

        $this->dispatch('step-completed', step: 4, data: $stepData);
    }

    /**
     * 🎲 RESET TO DEFAULTS
     */
    public function resetToDefaults(): void
    {
        $this->initializePricingFromVariants();

        $this->dispatch('notify', [
            'type' => 'info',
            'message' => '🔄 Pricing reset to defaults',
        ]);
    }

    /**
     * 💰 BULK UPDATE PRICING
     * 
     * Apply default pricing to all variants or filtered subset
     */
    public function bulkUpdatePricing(string $target = 'all', string $color = null): void
    {
        if ($this->defaultRetailPrice <= 0) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Please set a valid default retail price first',
            ]);
            return;
        }

        $updatedCount = 0;

        $this->variantPricing = $this->variantPricing->map(function (array $pricing) use ($target, $color, &$updatedCount): array {
            $shouldUpdate = match($target) {
                'all' => true,
                'color' => $pricing['color'] === $color,
                'zero_only' => $pricing['retail_price'] <= 0,
                default => false,
            };

            if ($shouldUpdate) {
                $pricing['retail_price'] = $this->defaultRetailPrice;
                $pricing['vat_inclusive_price'] = $this->calculateVatInclusivePrice($this->defaultRetailPrice);
                $pricing['marketplace_pricing'] = $this->generateMarketplacePricing($this->defaultRetailPrice);
                $updatedCount++;
            }

            return $pricing;
        });

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "💰 Updated pricing for {$updatedCount} variants",
        ]);
    }

    /**
     * 📦 BULK UPDATE STOCK
     * 
     * Apply default stock level to all variants or filtered subset
     */
    public function bulkUpdateStock(string $target = 'all', string $color = null): void
    {
        if ($this->defaultStockLevel < 0) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Stock level cannot be negative',
            ]);
            return;
        }

        $updatedCount = 0;

        $this->variantPricing = $this->variantPricing->map(function (array $pricing) use ($target, $color, &$updatedCount): array {
            $shouldUpdate = match($target) {
                'all' => true,
                'color' => $pricing['color'] === $color,
                'zero_only' => $pricing['stock_level'] <= 0,
                default => false,
            };

            if ($shouldUpdate) {
                $pricing['stock_level'] = $this->defaultStockLevel;
                $updatedCount++;
            }

            return $pricing;
        });

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "📦 Updated stock for {$updatedCount} variants",
        ]);
    }

    /**
     * 🔄 UPDATE INDIVIDUAL VARIANT PRICING
     * 
     * Update pricing for a specific variant
     */
    public function updateVariantPricing(int $index, string $field, mixed $value): void
    {
        if (!$this->variantPricing->has($index)) {
            return;
        }

        $variant = $this->variantPricing->get($index);
        
        // Validate and update the field
        switch ($field) {
            case 'retail_price':
                $value = max(0, (float) $value);
                $variant['retail_price'] = $value;
                $variant['vat_inclusive_price'] = $this->calculateVatInclusivePrice($value);
                $variant['marketplace_pricing'] = $this->generateMarketplacePricing($value);
                break;
            
            case 'stock_level':
                $value = max(0, (int) $value);
                $variant['stock_level'] = $value;
                break;
                
            case 'cost_price':
                $value = max(0, (float) $value);
                $variant['cost_price'] = $value;
                break;
        }

        // Update the variant in the collection
        $this->variantPricing->put($index, $variant);
    }

    /**
     * 📊 PRICING STATISTICS USING COLLECTIONS
     */
    /**
     * @return Collection<string, mixed>
     */
    #[Computed]
    public function pricingStats(): Collection
    {
        if ($this->variantPricing->isEmpty()) {
            return collect([
                'total_variants' => 0,
                'average_retail_price' => 0,
                'total_inventory_value' => 0,
                'total_stock_units' => 0,
            ]);
        }

        $retailPrices = $this->variantPricing->pluck('retail_price');
        $stockLevels = $this->variantPricing->pluck('stock_level');

        return collect([
            'total_variants' => $this->variantPricing->count(),
            'average_retail_price' => $retailPrices->avg(),
            'min_retail_price' => (float) $retailPrices->min(),
            'max_retail_price' => (float) $retailPrices->max(),
            'total_inventory_value' => $this->variantPricing->sum(fn ($p) => $p['retail_price'] * $p['stock_level']),
            'total_stock_units' => $stockLevels->sum(),
            'average_stock_level' => $stockLevels->avg(),
            'variants_in_stock' => $this->variantPricing->where('stock_level', '>', 0)->count(),
            'variants_out_of_stock' => $this->variantPricing->where('stock_level', '<=', 0)->count(),
            'price_range' => $retailPrices->min().' - '.$retailPrices->max(),
        ]);
    }

    /**
     * 🏪 GET MARKETPLACE STATISTICS
     */
    /**
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function marketplaceStats(): Collection
    {
        /** @var Collection<int, array<string, mixed>> $stats */
        $stats = $this->selectedMarketplaces->map(function (string $marketplace): array {
            $marketplacePrices = $this->variantPricing
                ->pluck("marketplace_pricing.{$marketplace}")
                ->filter();

            /** @var array<string, mixed>|null $marketplaceInfo */
            $marketplaceInfo = $this->availableMarketplaces->firstWhere('id', $marketplace);
            $marketplaceName = is_array($marketplaceInfo) ? ($marketplaceInfo['name'] ?? $marketplace) : $marketplace;

            return [
                'marketplace' => $marketplace,
                'name' => $marketplaceName,
                'variant_count' => $marketplacePrices->count(),
                'average_price' => $marketplacePrices->avg(),
                'min_price' => $marketplacePrices->min(),
                'max_price' => $marketplacePrices->max(),
                'total_value' => $marketplacePrices->sum(),
            ];
        });

        return $stats;
    }

    /**
     * 🎨 RENDER THE FORM
     */
    /**
     * @return \Illuminate\View\View
     */
    public function render()
    {
        return view('livewire.products.wizard.pricing-stock-form');
    }
}
