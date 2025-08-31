<?php

namespace App\Actions\Marketplace\Shopify;

use App\Models\SyncAccount;
use App\Services\Marketplace\ValueObjects\SyncResult;

/**
 * ⬇️ PULL FROM SHOPIFY ACTION
 *
 * Pulls product data from Shopify for discovery and sync purposes.
 * Uses GraphQL queries to efficiently retrieve product information.
 */
class PullFromShopifyAction
{
    /**
     * Pull products from Shopify using official SDK
     *
     * @param  SyncAccount  $syncAccount  Shopify account to pull from
     * @param  array  $filters  Optional filters for the pull operation
     * @return SyncResult Pull operation result
     */
    public function execute(SyncAccount $syncAccount, array $filters = []): SyncResult
    {
        $client = new \App\Services\Marketplace\Shopify\ShopifyGraphQLClient($syncAccount);

        $limit = $filters['limit'] ?? 50;
        $after = $filters['after'] ?? null;

        $result = $client->getProducts($limit, $after);
        $products = $result['products'] ?? [];
        $edges = $products['edges'] ?? [];
        $pageInfo = $products['pageInfo'] ?? [];

        return SyncResult::success(
            message: 'Successfully pulled '.count($edges).' products from Shopify',
            data: [
                'products' => array_map(fn ($edge) => $edge['node'], $edges),
                'total_count' => count($edges),
                'has_next_page' => $pageInfo['hasNextPage'] ?? false,
                'end_cursor' => $pageInfo['endCursor'] ?? null,
            ],
            metadata: [
                'shop_domain' => $syncAccount->credentials['shop_domain'],
                'filters' => $filters,
                'pull_time' => now()->toISOString(),
                'sdk' => 'shopify/shopify-api',
            ]
        );
    }
}
