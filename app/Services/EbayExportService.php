<?php

namespace App\Services;

use App\Models\MarketplaceBarcode;
use App\Models\MarketplaceVariant;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EbayExportService
{
    private EbayConnectService $ebayService;

    public function __construct(EbayConnectService $ebayService)
    {
        $this->ebayService = $ebayService;
    }

    /**
     * Export products to eBay
     */
    public function exportProducts(?Collection $products = null): array
    {
        if ($products === null) {
            $products = Product::with(['variants.attributes', 'variants.pricing', 'variants.barcodes', 'attributes'])->get();
        }

        $results = [
            'total_products' => 0,
            'total_variants' => 0,
            'successful_exports' => 0,
            'failed_exports' => 0,
            'errors' => [],
            'exported_items' => [],
        ];

        foreach ($products as $product) {
            $results['total_products']++;

            foreach ($product->variants as $variant) {
                $results['total_variants']++;
                $exportResult = $this->exportVariant($product, $variant);

                if ($exportResult['success']) {
                    $results['successful_exports']++;
                    $results['exported_items'][] = [
                        'sku' => $variant->sku,
                        'product' => $product->name,
                        'offer_id' => $exportResult['offer_id'] ?? null,
                        'listing_id' => $exportResult['listing_id'] ?? null,
                    ];
                } else {
                    $results['failed_exports']++;
                    $results['errors'][] = [
                        'sku' => $variant->sku,
                        'product' => $product->name,
                        'error' => $exportResult['error'],
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Export a single variant to eBay
     */
    public function exportVariant(Product $product, ProductVariant $variant): array
    {
        DB::beginTransaction();

        try {
            // Step 1: Create inventory item
            $inventoryData = $this->buildInventoryItemData($product, $variant);
            $inventoryResult = $this->ebayService->createInventoryItem($variant->sku, $inventoryData);

            if (! $inventoryResult['success']) {
                DB::rollback();

                return [
                    'success' => false,
                    'error' => 'Failed to create inventory item: '.$inventoryResult['error'],
                ];
            }

            // Step 2: Create offer
            $offerData = $this->buildOfferData($product, $variant);
            $offerResult = $this->ebayService->createOffer($offerData);

            if (! $offerResult['success']) {
                DB::rollback();

                return [
                    'success' => false,
                    'error' => 'Failed to create offer: '.$offerResult['error'],
                ];
            }

            $offerId = $offerResult['offer_id'];

            // Step 3: Publish offer
            $publishResult = $this->ebayService->publishOffer($offerId);

            if (! $publishResult['success']) {
                DB::rollback();

                return [
                    'success' => false,
                    'error' => 'Failed to publish offer: '.$publishResult['error'],
                ];
            }

            // Step 4: Store marketplace data
            $this->storeMarketplaceData($variant, $offerId, $publishResult['listing_id']);

            DB::commit();

            Log::info('Successfully exported variant to eBay', [
                'sku' => $variant->sku,
                'offer_id' => $offerId,
                'listing_id' => $publishResult['listing_id'],
            ]);

            return [
                'success' => true,
                'offer_id' => $offerId,
                'listing_id' => $publishResult['listing_id'],
            ];

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('eBay variant export failed', [
                'sku' => $variant->sku,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build inventory item data for eBay
     */
    private function buildInventoryItemData(Product $product, ProductVariant $variant): array
    {
        $pricing = $variant->pricing->first();
        $primaryBarcode = $variant->primaryBarcode();

        return [
            'availability' => [
                'shipToLocationAvailability' => [
                    'quantity' => max(0, $variant->stock_level ?? 0),
                ],
            ],
            'condition' => 'NEW',
            'product' => [
                'title' => $this->buildProductTitle($product, $variant),
                'description' => $this->buildProductDescription($product, $variant),
                'imageUrls' => $this->getProductImages($product, $variant),
                'aspects' => $this->buildProductAspects($product, $variant),
                'upc' => $primaryBarcode ? $primaryBarcode->barcode : null,
            ],
            'packageWeightAndSize' => $this->buildPackageInfo($variant),
        ];
    }

    /**
     * Build offer data for eBay
     */
    private function buildOfferData(Product $product, ProductVariant $variant): array
    {
        $pricing = $variant->pricing->first();
        $price = $pricing ? $pricing->sale_price : '0.00';

        return [
            'sku' => $variant->sku,
            'marketplaceId' => 'EBAY_US', // Default to US, can be configurable
            'format' => 'FIXED_PRICE',
            'availableQuantity' => max(0, $variant->stock_level ?? 0),
            'categoryId' => $this->getEbayCategoryId($product),
            'pricingSummary' => [
                'price' => [
                    'currency' => 'USD',
                    'value' => number_format((float) $price, 2, '.', ''),
                ],
            ],
            'listingDescription' => $this->buildListingDescription($product, $variant),
            'listingPolicies' => [
                'fulfillmentPolicyId' => config('services.ebay.fulfillment_policy_id'),
                'paymentPolicyId' => config('services.ebay.payment_policy_id'),
                'returnPolicyId' => config('services.ebay.return_policy_id'),
            ],
            'merchantLocationKey' => config('services.ebay.location_key', 'default_location'),
        ];
    }

    /**
     * Build product title with variant details
     */
    private function buildProductTitle(Product $product, ProductVariant $variant): string
    {
        $title = $product->name;

        if ($variant->color) {
            $title .= " - {$variant->color}";
        }

        if ($variant->dimensions) {
            $title .= " ({$variant->dimensions})";
        }

        // eBay has a title limit of 80 characters
        return substr($title, 0, 80);
    }

    /**
     * Build comprehensive product description
     */
    private function buildProductDescription(Product $product, ProductVariant $variant): string
    {
        $description = $product->description ?: '';

        // Add variant-specific details
        $details = [];
        if ($variant->color) {
            $details[] = "Color: {$variant->color}";
        }
        if ($variant->dimensions) {
            $details[] = "Size: {$variant->dimensions}";
        }

        if (! empty($details)) {
            $description .= "\n\nProduct Details:\n".implode("\n", $details);
        }

        // Add features from product
        $features = [];
        for ($i = 1; $i <= 5; $i++) {
            $feature = $product->{"product_features_{$i}"};
            if ($feature) {
                $features[] = $feature;
            }
        }

        if (! empty($features)) {
            $description .= "\n\nKey Features:\n".implode("\n", array_map(fn ($f) => "• {$f}", $features));
        }

        return $description;
    }

    /**
     * Build listing description (shorter version for offer)
     */
    private function buildListingDescription(Product $product, ProductVariant $variant): string
    {
        return $this->buildProductDescription($product, $variant);
    }

    /**
     * Get product images
     */
    private function getProductImages(Product $product, ProductVariant $variant): array
    {
        $images = [];

        // Try variant images first
        if ($variant->variantImages()->exists()) {
            foreach ($variant->variantImages as $image) {
                if ($image->image_url) {
                    $images[] = $image->image_url;
                }
            }
        }

        // Fall back to product images
        if (empty($images) && ! empty($product->images)) {
            $images = is_array($product->images) ? $product->images : [$product->images];
        }

        // eBay allows up to 12 images
        return array_slice($images, 0, 12);
    }

    /**
     * Build product aspects (item specifics)
     */
    private function buildProductAspects(Product $product, ProductVariant $variant): array
    {
        $aspects = [];

        // Add brand
        $brand = $product->attributes()->where('attribute_key', 'brand')->first();
        if ($brand) {
            $aspects['Brand'] = [$brand->attribute_value];
        } else {
            $aspects['Brand'] = ['Unbranded']; // eBay often requires brand
        }

        // Add color
        if ($variant->color) {
            $aspects['Color'] = [$variant->color];
        }

        // Add material
        $material = $product->attributes()->where('attribute_key', 'material')->first();
        if ($material) {
            $aspects['Material'] = [$material->attribute_value];
        }

        // Add variant attributes
        foreach ($variant->attributes as $attr) {
            $key = ucfirst(str_replace('_', ' ', $attr->attribute_key));
            if (! isset($aspects[$key])) {
                $aspects[$key] = [$attr->attribute_value];
            }
        }

        // Add product attributes
        foreach ($product->attributes as $attr) {
            $key = ucfirst(str_replace('_', ' ', $attr->attribute_key));
            if (! isset($aspects[$key])) {
                $aspects[$key] = [$attr->attribute_value];
            }
        }

        return $aspects;
    }

    /**
     * Build package information
     */
    private function buildPackageInfo(ProductVariant $variant): ?array
    {
        $package = [];

        if ($variant->package_weight) {
            $package['weight'] = [
                'unit' => 'POUND',
                'value' => (string) $variant->package_weight,
            ];
        }

        if ($variant->package_length || $variant->package_width || $variant->package_height) {
            $package['dimensions'] = [
                'unit' => 'INCH',
                'length' => (string) ($variant->package_length ?? 0),
                'width' => (string) ($variant->package_width ?? 0),
                'height' => (string) ($variant->package_height ?? 0),
            ];
        }

        return ! empty($package) ? $package : null;
    }

    /**
     * Get eBay category ID for product
     */
    private function getEbayCategoryId(Product $product): string
    {
        // Try to get from product attributes
        $categoryAttr = $product->attributes()->where('attribute_key', 'ebay_category_id')->first();
        if ($categoryAttr) {
            return $categoryAttr->attribute_value;
        }

        // Default categories based on product type
        if (str_contains(strtolower($product->name), 'blind')) {
            return '11700'; // Home & Garden > Window Treatments & Hardware > Blinds & Shades
        }

        return '11700'; // Default to Home & Garden
    }

    /**
     * Store marketplace data in database
     */
    private function storeMarketplaceData(ProductVariant $variant, string $offerId, ?string $listingId): void
    {
        // Get eBay marketplace
        $ebayMarketplace = \App\Models\Marketplace::where('platform', 'ebay')->first();

        if (! $ebayMarketplace) {
            Log::warning('eBay marketplace not found in database');

            return;
        }

        // Store marketplace variant
        MarketplaceVariant::updateOrCreate(
            [
                'variant_id' => $variant->id,
                'marketplace_id' => $ebayMarketplace->id,
            ],
            [
                'title' => $this->buildProductTitle($variant->product, $variant),
                'description' => $this->buildProductDescription($variant->product, $variant),
                'status' => 'active',
                'marketplace_data' => json_encode([
                    'offer_id' => $offerId,
                    'listing_id' => $listingId,
                    'exported_at' => now()->toISOString(),
                    'exported_by' => 'ebay_export_service',
                ]),
            ]
        );

        // Store marketplace barcode if listing ID exists
        if ($listingId) {
            MarketplaceBarcode::updateOrCreate(
                [
                    'variant_id' => $variant->id,
                    'marketplace_id' => $ebayMarketplace->id,
                    'identifier_type' => 'listing_id',
                ],
                [
                    'identifier_value' => $listingId,
                ]
            );
        }
    }
}
