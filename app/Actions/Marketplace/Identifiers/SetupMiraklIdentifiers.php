<?php

namespace App\Actions\Marketplace\Identifiers;

use App\Models\SyncAccount;
use App\Services\Marketplace\Facades\Sync;

class SetupMiraklIdentifiers
{
    public function execute(SyncAccount $account): array
    {
        $result = Sync::marketplace('mirakl')
            ->account($account->name)
            ->info();

        if (!($result['success'] ?? false)) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Failed to fetch Mirakl operator info',
            ];
        }

        $account->updateMarketplaceIdentifiers($result['marketplace_details']);

        return [
            'success' => true,
            'marketplace_details' => $result['marketplace_details'],
            'summary' => $result['summary'] ?? 'Mirakl identifiers retrieved',
        ];
    }
}

