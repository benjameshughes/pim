<?php

namespace App\Services\Pricing\Channel;

use App\Models\SalesChannel;
use App\Models\SyncAccount;

/**
 * ChannelResolver
 *
 * Maps a (channel, account) pair or a sales channel code to the
 * corresponding SalesChannel record (id) used by Pricing records.
 */
class ChannelResolver
{
    /**
     * Resolve channel/account into [code, id].
     * Example: ('shopify','main') -> ['shopify_main', 123]
     */
    public function resolve(string $channel, ?string $account = null): array
    {
        $code = null;
        $id = null;

        if ($account) {
            $sync = SyncAccount::findByChannelAndName($channel, $account);
            if ($sync) {
                $code = $sync->getChannelCode();
                $id = SalesChannel::where('code', $code)->value('id');
            }
        }

        // Fallback: try first active account for channel
        if (!$id) {
            $default = SyncAccount::getDefaultForChannel($channel);
            if ($default) {
                $code = $default->getChannelCode();
                $id = SalesChannel::where('code', $code)->value('id');
            }
        }

        return [$code, $id];
    }

    /**
     * Resolve by explicit sales channel code (e.g., 'shopify_main').
     * Returns the corresponding SalesChannel id or null.
     */
    public function resolveByCode(string $code): ?int
    {
        return SalesChannel::where('code', $code)->value('id');
    }
}

