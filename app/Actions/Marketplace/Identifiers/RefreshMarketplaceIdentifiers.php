<?php

namespace App\Actions\Marketplace\Identifiers;

use App\Models\SyncAccount;

class RefreshMarketplaceIdentifiers
{
    public function __construct(private readonly SetupMarketplaceIdentifiers $setup) {}

    public function execute(SyncAccount $account): array
    {
        return $this->setup->execute($account);
    }
}

