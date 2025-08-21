<?php

namespace App\Actions\Products\Wizard;

use App\Actions\Base\BaseAction;
use App\Actions\Products\CreateProductAction;
use App\Actions\Products\UpdateProductAction;
use App\Models\Product;
use InvalidArgumentException;

/**
 * Save Product Wizard Data Action
 *
 * Main orchestrator for saving product wizard data.
 * Handles both create and update operations based on product existence.
 */
class SaveProductWizardDataAction extends BaseAction
{
    /**
     * Execute the save operation
     *
     * @param  array  $wizardData  Complete wizard data from all steps
     * @param  Product|null  $existingProduct  Existing product for updates
     * @return array Action result with created/updated product
     */
    protected function performAction(...$params): array
    {
        $wizardData = $params[0] ?? [];
        $existingProduct = $params[1] ?? null;

        $this->validateWizardData($wizardData);

        if ($existingProduct && $existingProduct->exists) {
            return $this->updateExistingProduct($existingProduct, $wizardData);
        } else {
            return $this->createNewProduct($wizardData);
        }
    }

    /**
     * Validate wizard data structure
     */
    protected function validateWizardData(array $wizardData): void
    {
        if (empty($wizardData['product_info'])) {
            throw new InvalidArgumentException('Product information is required');
        }

        if (empty($wizardData['product_info']['name'])) {
            throw new InvalidArgumentException('Product name is required');
        }

        // Validate variants if provided
        if (! empty($wizardData['variants']['generated_variants'])) {
            $this->validateVariants($wizardData['variants']['generated_variants']);
        }
    }

    /**
     * Validate variants data
     */
    protected function validateVariants(array $variants): void
    {
        foreach ($variants as $index => $variant) {
            if (empty($variant['sku'])) {
                throw new InvalidArgumentException("Variant at index {$index} is missing SKU");
            }
        }
    }

    /**
     * Create new product from wizard data
     */
    protected function createNewProduct(array $wizardData): array
    {
        $createAction = new CreateProductAction;

        // Extract product data from wizard
        $productData = $this->extractProductData($wizardData);

        $result = $createAction->execute($productData);

        if (! $result['success']) {
            throw new \Exception('Failed to create product: '.$result['message']);
        }

        $product = $result['data']['product'];

        // Handle variants if provided
        if (! empty($wizardData['variants']['generated_variants'])) {
            $this->createVariants($product, $wizardData['variants']['generated_variants']);
        }

        // Handle images if provided
        if (! empty($wizardData['images'])) {
            $this->handleImages($product, $wizardData['images']);
        }

        // Handle pricing if provided (step 4 data)
        if (! empty($wizardData['pricing']) && is_array($wizardData['pricing'])) {
            $this->handlePricing($product, $wizardData['pricing']);
        }
        
        // Also check for direct variant pricing data structure
        if (! empty($wizardData['variant_pricing']) && is_array($wizardData['variant_pricing'])) {
            $this->handlePricing($product, ['variant_pricing' => $wizardData['variant_pricing']]);
        }

        // Handle automatic marketplace linking
        if (! empty($wizardData['marketplace_settings'])) {
            $this->handleMarketplaceLinks($product, $wizardData['marketplace_settings']);
        }

        return $this->success("Product '{$product->name}' created successfully", [
            'product' => $product->fresh(),
            'action' => 'created',
        ]);
    }

    /**
     * Update existing product from wizard data
     */
    protected function updateExistingProduct(Product $product, array $wizardData): array
    {
        $updateAction = new UpdateProductAction;

        // Extract product data from wizard
        $productData = $this->extractProductData($wizardData);

        $result = $updateAction->execute($product, $productData);

        if (! $result['success']) {
            throw new \Exception('Failed to update product: '.$result['message']);
        }

        $updatedProduct = $result['data']['product'];

        // Handle variants update
        if (! empty($wizardData['variants']['generated_variants'])) {
            $this->updateVariants($updatedProduct, $wizardData['variants']['generated_variants']);
        }

        // Handle images update
        if (! empty($wizardData['images'])) {
            $this->handleImages($updatedProduct, $wizardData['images']);
        }

        // Handle pricing update (step 4 data)
        if (! empty($wizardData['pricing']) && is_array($wizardData['pricing'])) {
            $this->handlePricing($updatedProduct, $wizardData['pricing']);
        }
        
        // Also check for direct variant pricing data structure
        if (! empty($wizardData['variant_pricing']) && is_array($wizardData['variant_pricing'])) {
            $this->handlePricing($updatedProduct, ['variant_pricing' => $wizardData['variant_pricing']]);
        }

        // Handle automatic marketplace linking (for new marketplaces)
        if (! empty($wizardData['marketplace_settings'])) {
            $this->handleMarketplaceLinks($updatedProduct, $wizardData['marketplace_settings']);
        }

        return $this->success("Product '{$updatedProduct->name}' updated successfully", [
            'product' => $updatedProduct->fresh(),
            'action' => 'updated',
        ]);
    }

    /**
     * Extract product data from wizard data
     */
    protected function extractProductData(array $wizardData): array
    {
        $productInfo = $wizardData['product_info'];

        // Generate parent_sku if not provided
        $parentSku = $productInfo['parent_sku'] ?? null;
        if (empty($parentSku)) {
            $parentSku = $this->generateParentSku($productInfo['name']);
        }

        return [
            'name' => $productInfo['name'],
            'parent_sku' => $parentSku,
            'description' => $productInfo['description'] ?? null,
            'status' => $productInfo['status'] ?? 'active',
            'image_url' => $productInfo['image_url'] ?? null,
        ];
    }

    /**
     * Generate a unique parent SKU from product name
     */
    protected function generateParentSku(string $productName): string
    {
        // Start with first 3 letters of each word, uppercase
        $words = explode(' ', $productName);
        $sku = '';

        foreach (array_slice($words, 0, 3) as $word) {
            $sku .= strtoupper(substr($word, 0, 3));
        }

        // Add random number to ensure uniqueness
        $counter = 1;
        $baseSku = $sku;

        while (Product::where('parent_sku', $sku)->exists()) {
            $sku = $baseSku.str_pad($counter, 3, '0', STR_PAD_LEFT);
            $counter++;
        }

        return $sku;
    }

    /**
     * Create variants for the product
     */
    protected function createVariants(Product $product, array $variants): void
    {
        foreach ($variants as $variantData) {
            // Generate title from variant attributes
            $title = $this->generateVariantTitle($variantData);

            $product->variants()->create([
                'sku' => $variantData['sku'],
                'title' => $title,
                'color' => $variantData['color'] ?? 'Default',
                'width' => $variantData['width'] ?? 0,
                'drop' => $variantData['drop'] ?? 0,
                'max_drop' => $variantData['max_drop'] ?? ($variantData['drop'] ?? 0),
                'price' => $variantData['price'] ?? 0,
                'stock_level' => $variantData['stock'] ?? 0,
                'status' => $variantData['status'] ?? 'active',
            ]);
        }
    }

    /**
     * Generate a descriptive title for a variant
     */
    protected function generateVariantTitle(array $variantData): string
    {
        $parts = [];

        if (! empty($variantData['color'])) {
            $parts[] = $variantData['color'];
        }

        if (! empty($variantData['width']) && ! empty($variantData['drop'])) {
            $parts[] = $variantData['width'].'x'.$variantData['drop'];
        } elseif (! empty($variantData['size'])) {
            $parts[] = $variantData['size'];
        }

        return ! empty($parts) ? implode(' - ', $parts) : $variantData['sku'];
    }

    /**
     * Update variants for the product
     */
    protected function updateVariants(Product $product, array $variants): void
    {
        // For simplicity, we'll delete existing variants and recreate
        // In a production environment, you might want to update existing ones
        $product->variants()->delete();
        $this->createVariants($product, $variants);
    }

    /**
     * Handle images for the product
     */
    protected function handleImages(Product $product, array $imageData): void
    {
        // TODO: Implement image handling
        // This will be implemented when we get to the image component
    }

    /**
     * Handle pricing for the product
     */
    protected function handlePricing(Product $product, array $pricingData): void
    {
        // Check if we have variant pricing data
        if (empty($pricingData['variant_pricing'])) {
            \Log::info('SaveProductWizardDataAction: No variant pricing data found', [
                'product_id' => $product->id,
                'pricing_data_keys' => array_keys($pricingData),
            ]);
            return;
        }

        $variantPricingData = $pricingData['variant_pricing'];
        
        \Log::info('SaveProductWizardDataAction: Processing variant pricing', [
            'product_id' => $product->id,
            'variant_count' => count($variantPricingData),
            'sample_variant' => array_keys($variantPricingData)[0] ?? 'none',
        ]);

        // Process each variant's pricing
        foreach ($variantPricingData as $pricingInfo) {
            if (empty($pricingInfo['sku'])) {
                continue; // Skip variants without SKUs
            }

            // Find variant by SKU
            $variant = $product->variants()->where('sku', $pricingInfo['sku'])->first();
            
            if (!$variant) {
                // If variant doesn't exist by SKU, try by variant_id if it's a numeric DB ID
                if (isset($pricingInfo['variant_id']) && is_numeric($pricingInfo['variant_id'])) {
                    $variant = $product->variants()->where('id', $pricingInfo['variant_id'])->first();
                }
            }

            if ($variant) {
                // Update the variant with pricing data
                $updateData = [];

                // Update retail price
                if (isset($pricingInfo['retail_price'])) {
                    $updateData['price'] = (float) $pricingInfo['retail_price'];
                }

                // Update stock level
                if (isset($pricingInfo['stock_level'])) {
                    $updateData['stock_level'] = (int) $pricingInfo['stock_level'];
                }

                // Update cost price if available
                if (isset($pricingInfo['cost_price'])) {
                    $updateData['cost_price'] = (float) $pricingInfo['cost_price'];
                }

                // Apply the updates
                if (!empty($updateData)) {
                    $variant->update($updateData);
                }

                // Handle marketplace pricing if present
                if (isset($pricingInfo['marketplace_pricing']) && !empty($pricingInfo['marketplace_pricing'])) {
                    $this->handleMarketplacePricing($variant, $pricingInfo['marketplace_pricing']);
                }
            }
        }

        // Handle pricing settings if provided
        if (isset($pricingData['pricing_settings'])) {
            $this->storePricingSettings($product, $pricingData['pricing_settings']);
        }
    }

    /**
     * Handle marketplace pricing for a variant
     */
    protected function handleMarketplacePricing($variant, array $marketplacePricing): void
    {
        // For now, we'll store marketplace pricing as JSON in a field
        // In the future, this could be expanded to a separate table/system
        
        if (!empty($marketplacePricing)) {
            $variant->update([
                'marketplace_pricing' => json_encode($marketplacePricing)
            ]);
        }
    }

    /**
     * Store pricing settings for the product
     */
    protected function storePricingSettings(Product $product, array $pricingSettings): void
    {
        // Store pricing configuration as product metadata
        $metadata = [
            'pricing_settings' => $pricingSettings,
            'updated_at' => now()->toISOString(),
        ];

        // Store in product metadata field or separate table
        $product->update([
            'pricing_metadata' => json_encode($metadata)
        ]);
    }

    /**
     * Handle automatic marketplace linking
     */
    protected function handleMarketplaceLinks(Product $product, array $marketplaceSettings): void
    {
        if (empty($marketplaceSettings['enabled_marketplaces'])) {
            return;
        }

        $enabledMarketplaces = $marketplaceSettings['enabled_marketplaces'];

        // Get all available sync accounts
        $syncAccounts = \App\Models\SyncAccount::whereIn('channel', $enabledMarketplaces)
            ->where('is_active', true)
            ->get();

        foreach ($syncAccounts as $syncAccount) {
            // Check if link already exists
            $existingLink = $product->marketplaceLinks()
                ->where('sync_account_id', $syncAccount->id)
                ->first();

            if (!$existingLink) {
                // Create pending marketplace link
                \App\Models\MarketplaceLink::create([
                    'linkable_type' => get_class($product),
                    'linkable_id' => $product->id,
                    'sync_account_id' => $syncAccount->id,
                    'internal_sku' => $product->parent_sku ?? 'NO-SKU',
                    'external_sku' => $product->parent_sku ?? 'NO-SKU',
                    'link_status' => 'pending',
                    'link_level' => 'product',
                    'marketplace_data' => [
                        'auto_created' => true,
                        'created_from' => 'product_wizard',
                        'created_at' => now()->toISOString(),
                    ],
                    'linked_by' => auth()->user()?->name ?? 'system',
                ]);

                \Log::info('Auto-created MarketplaceLink for product', [
                    'product_id' => $product->id,
                    'sync_account_id' => $syncAccount->id,
                    'marketplace' => $syncAccount->channel,
                ]);
            }

            // Also create/update SyncStatus for backward compatibility
            $syncStatus = \App\Models\SyncStatus::firstOrCreate([
                'product_id' => $product->id,
                'sync_account_id' => $syncAccount->id,
            ], [
                'sync_status' => 'pending',
                'metadata' => [
                    'auto_created' => true,
                    'created_from' => 'product_wizard',
                ],
            ]);
        }
    }
}
