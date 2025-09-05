<?php

namespace App\Actions\Marketplace\Identifiers;

use App\Models\SyncAccount;
use App\Services\Marketplace\Facades\Sync;

class SetupEbayIdentifiers
{
    public function execute(SyncAccount $account): array
    {
        $result = Sync::marketplace('ebay')
            ->account($account->name)
            ->info();

        if (!($result['success'] ?? false)) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Failed to fetch eBay account info',
            ];
        }

        $account->updateMarketplaceIdentifiers($result['marketplace_details']);

        return [
            'success' => true,
            'marketplace_details' => $result['marketplace_details'],
            'summary' => $result['summary'] ?? 'eBay identifiers retrieved',
        ];
    }
}

