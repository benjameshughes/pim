<?php

namespace App\Actions\Import;

use App\Models\Marketplace;
use App\Models\MarketplaceVariant;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Log;

class HandleMarketplaceVariants
{
    public function execute(ProductVariant $variant, array $data): void
    {
        $marketplaceFields = [
            'amazon_asin', 'amazon_title', 'amazon_description', 'amazon_price',
            'ebay_item_id', 'ebay_title', 'ebay_description', 'ebay_price',
            'shopify_product_id', 'shopify_variant_id', 'shopify_title', 'shopify_price',
        ];

        foreach ($marketplaceFields as $field) {
            if (! empty($data[$field])) {
                [$marketplaceName, $fieldType] = explode('_', $field, 2);

                $marketplace = Marketplace::where('name', $marketplaceName)->first();
                if (! $marketplace) {
                    continue;
                }

                $marketplaceVariant = MarketplaceVariant::firstOrCreate([
                    'variant_id' => $variant->id,
                    'marketplace_id' => $marketplace->id,
                ]);

                $this->updateMarketplaceVariantField($marketplaceVariant, $fieldType, $data[$field]);
            }
        }
    }

    private function updateMarketplaceVariantField(MarketplaceVariant $marketplaceVariant, string $fieldType, $value): void
    {
        switch ($fieldType) {
            case 'asin':
            case 'item_id':
            case 'product_id':
            case 'variant_id':
                $marketplaceVariant->marketplace_product_id = $value;
                break;

            case 'title':
                $marketplaceVariant->marketplace_title = $value;
                break;

            case 'description':
                $marketplaceVariant->marketplace_description = $value;
                break;

            case 'price':
                $marketplaceVariant->marketplace_price = (float) $value;
                break;
        }

        $marketplaceVariant->save();

        Log::info('Updated marketplace variant', [
            'variant_id' => $marketplaceVariant->variant_id,
            'marketplace_id' => $marketplaceVariant->marketplace_id,
            'field' => $fieldType,
            'value' => $value,
        ]);
    }
}
