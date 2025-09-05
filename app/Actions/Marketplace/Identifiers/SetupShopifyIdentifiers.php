<?php

namespace App\Actions\Marketplace\Identifiers;

use App\Models\SyncAccount;
use App\Services\Marketplace\Facades\Sync;

class SetupShopifyIdentifiers
{
    public function execute(SyncAccount $account): array
    {
        $result = Sync::marketplace('shopify')
            ->account($account->name)
            ->info();

        if (!($result['success'] ?? false)) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Failed to fetch Shopify account info',
            ];
        }

        // Persist into settings via model helper
        $account->updateMarketplaceIdentifiers($result['marketplace_details']);

        return [
            'success' => true,
            'marketplace_details' => $result['marketplace_details'],
            'summary' => $result['summary'] ?? 'Shopify identifiers retrieved',
        ];
    }
}

