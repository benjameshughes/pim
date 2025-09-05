<?php

namespace App\Actions\Marketplace\Identifiers;

use App\Models\SyncAccount;

class SetupMarketplaceIdentifiers
{
    public function __construct(
        private readonly SetupShopifyIdentifiers $shopify,
        private readonly SetupEbayIdentifiers $ebay,
        private readonly SetupMiraklIdentifiers $mirakl,
    ) {}

    public function execute(SyncAccount $account): array
    {
        return match ($account->channel) {
            'shopify' => $this->shopify->execute($account),
            'ebay' => $this->ebay->execute($account),
            'mirakl' => $this->mirakl->execute($account),
            'amazon' => [
                'success' => false,
                'error' => 'Amazon identifier setup not implemented yet',
            ],
            default => [
                'success' => false,
                'error' => "Unsupported channel: {$account->channel}",
            ],
        };
    }
}

