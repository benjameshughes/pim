<?php

namespace App\Services\Import\Actions;

use App\Models\Product;
use App\Services\Import\SkuPatternAnalyzer;
use Illuminate\Support\Str;

class ResolveProductAction extends ImportAction
{
    private string $importMode;
    private bool $useSkuGrouping;
    private ?SkuPatternAnalyzer $skuAnalyzer = null;

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        
        $this->importMode = $config['import_mode'] ?? 'create_or_update';
        $this->useSkuGrouping = $config['use_sku_grouping'] ?? false;

        if ($this->useSkuGrouping) {
            $this->skuAnalyzer = app(SkuPatternAnalyzer::class);
        }
    }

    public function execute(ActionContext $context): ActionResult
    {
        $data = $context->getData();
        
        $productName = $data['enhanced_product_name'] ?? $data['product_name'] ?? '';
        
        if (empty($productName)) {
            return ActionResult::failed('Product name is required', [
                'field' => 'product_name',
                'provided_value' => $productName,
            ]);
        }

        $this->logAction('Resolving product', [
            'row_number' => $context->getRowNumber(),
            'product_name' => $productName,
            'import_mode' => $this->importMode,
            'use_sku_grouping' => $this->useSkuGrouping,
        ]);

        try {
            $product = $this->handleProductResolution($data, $productName);

            if (!$product) {
                return ActionResult::failed('Product resolution returned null', [
                    'import_mode' => $this->importMode,
                    'product_name' => $productName,
                ]);
            }

            $this->logAction('Product resolved successfully', [
                'row_number' => $context->getRowNumber(),
                'product_id' => $product->id,
                'was_created' => $product->wasRecentlyCreated,
            ]);

            return ActionResult::success($context, 'Product resolved successfully')->withData([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'was_created' => $product->wasRecentlyCreated,
            ])->withContextUpdate('product', $product);

        } catch (\Exception $e) {
            $this->logAction('Product resolution failed', [
                'row_number' => $context->getRowNumber(),
                'error' => $e->getMessage(),
                'product_name' => $productName,
            ]);

            return ActionResult::failed(
                'Product resolution failed: ' . $e->getMessage(),
                [
                    'product_name' => $productName,
                    'import_mode' => $this->importMode,
                ]
            );
        }
    }

    private function handleProductResolution(array $data, string $productName): ?Product
    {
        $productData = [
            'name' => $productName,
            'description' => $data['description'] ?? '',
            'status' => $data['status'] ?? 'active',
        ];

        // Extract parent SKU from variant SKU if using SKU-based grouping
        if ($this->useSkuGrouping && $this->skuAnalyzer && !empty($data['variant_sku'])) {
            $parentSku = $this->skuAnalyzer->extractParentSku($data['variant_sku']);
            if ($parentSku) {
                $productData['parent_sku'] = $parentSku;
            }
        }

        switch ($this->importMode) {
            case 'create_only':
                $existing = Product::where('name', $productName)->first();
                if ($existing) {
                    return $existing; // Return existing, don't create
                }
                return Product::create(array_merge($productData, [
                    'slug' => $this->generateUniqueSlug($productName)
                ]));

            case 'update_existing':
                $existing = Product::where('name', $productName)->first();
                if (!$existing) {
                    return null; // Don't create, return null
                }
                $existing->update($productData);
                return $existing;

            case 'create_or_update':
            default:
                return Product::updateOrCreate(
                    ['name' => $productName],
                    array_merge($productData, [
                        'slug' => $this->generateUniqueSlug($productName)
                    ])
                );
        }
    }

    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (Product::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}