<?php

namespace App\Observers;

use App\Models\SalesChannel;
use App\Models\SyncAccount;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 🎯 SYNC ACCOUNT OBSERVER - KISS Channel Auto-Creation
 *
 * Automatically creates corresponding SalesChannels when SyncAccounts are created/updated.
 * Format: {channel}_{account_name} → ebay_blindsoutlet, shopify_main, etc.
 *
 * This enables channel-specific pricing: ebay_blindsoutlet_price, shopify_main_price
 */
class SyncAccountObserver
{
    /**
     * Handle the SyncAccount "created" event
     */
    public function created(SyncAccount $syncAccount): void
    {
        $this->createCorrespondingSalesChannel($syncAccount);
    }

    /**
     * Handle the SyncAccount "updated" event
     */
    public function updated(SyncAccount $syncAccount): void
    {
        // Only handle updates if channel or name changed
        if ($syncAccount->wasChanged(['channel', 'name', 'display_name'])) {
            $this->updateCorrespondingSalesChannel($syncAccount);
        }
    }

    /**
     * Handle the SyncAccount "deleting" event
     */
    public function deleting(SyncAccount $syncAccount): void
    {
        $this->deleteCorrespondingSalesChannel($syncAccount);
    }

    /**
     * Create corresponding sales channel for sync account
     */
    protected function createCorrespondingSalesChannel(SyncAccount $syncAccount): void
    {
        if (! $syncAccount->channel || ! $syncAccount->name) {
            Log::warning('🚫 Cannot create SalesChannel: missing channel or name', [
                'sync_account_id' => $syncAccount->id,
                'channel' => $syncAccount->channel,
                'name' => $syncAccount->name,
            ]);

            return;
        }

        $channelCode = $this->generateChannelCode($syncAccount);

        // Check if channel already exists
        if (SalesChannel::where('code', $channelCode)->exists()) {
            Log::info('⚠️ SalesChannel already exists, skipping creation', [
                'channel_code' => $channelCode,
                'sync_account_id' => $syncAccount->id,
            ]);

            return;
        }

        $salesChannel = SalesChannel::create([
            'name' => $this->generateChannelName($syncAccount),
            'code' => $channelCode,
            'description' => $this->generateChannelDescription($syncAccount),
            'status' => 'active',
            'config' => [
                'sync_account_id' => $syncAccount->id,
                'auto_generated' => true,
                'marketplace_type' => $syncAccount->channel,
                'account_name' => $syncAccount->name,
                'created_by_observer' => true,
                'priority' => $this->getChannelPriority($syncAccount->channel),
                'auto_sync' => true,
            ],
        ]);

        Log::info('✅ Auto-created SalesChannel for SyncAccount', [
            'sync_account_id' => $syncAccount->id,
            'sync_account_name' => $syncAccount->name,
            'channel_code' => $channelCode,
            'sales_channel_id' => $salesChannel->id,
        ]);

        // Trigger attribute sync for new channel
        $this->syncChannelAttributes();
    }

    /**
     * Update corresponding sales channel when sync account changes
     */
    protected function updateCorrespondingSalesChannel(SyncAccount $syncAccount): void
    {
        $oldChannelCode = $this->generateChannelCodeFromOriginal($syncAccount);
        $newChannelCode = $this->generateChannelCode($syncAccount);

        $salesChannel = SalesChannel::where('config->sync_account_id', $syncAccount->id)->first();

        if (! $salesChannel) {
            Log::info('🔄 No existing SalesChannel found, creating new one', [
                'sync_account_id' => $syncAccount->id,
            ]);
            $this->createCorrespondingSalesChannel($syncAccount);

            return;
        }

        // Update sales channel
        $salesChannel->update([
            'name' => $this->generateChannelName($syncAccount),
            'code' => $newChannelCode,
            'description' => $this->generateChannelDescription($syncAccount),
            'config' => array_merge($salesChannel->config ?? [], [
                'marketplace_type' => $syncAccount->channel,
                'account_name' => $syncAccount->name,
                'updated_by_observer' => true,
            ]),
        ]);

        Log::info('🔄 Updated SalesChannel for SyncAccount', [
            'sync_account_id' => $syncAccount->id,
            'old_channel_code' => $oldChannelCode,
            'new_channel_code' => $newChannelCode,
            'sales_channel_id' => $salesChannel->id,
        ]);

        // Re-sync attributes if channel code changed
        if ($oldChannelCode !== $newChannelCode) {
            $this->syncChannelAttributes();
        }
    }

    /**
     * Delete corresponding sales channel when sync account is deleted
     */
    protected function deleteCorrespondingSalesChannel(SyncAccount $syncAccount): void
    {
        $salesChannel = SalesChannel::where('config->sync_account_id', $syncAccount->id)->first();

        if ($salesChannel) {
            $channelCode = $salesChannel->code;
            $salesChannel->delete();

            Log::info('🗑️ Deleted SalesChannel for SyncAccount', [
                'sync_account_id' => $syncAccount->id,
                'channel_code' => $channelCode,
                'sales_channel_id' => $salesChannel->id,
            ]);

            // Clean up attributes after deletion
            $this->syncChannelAttributes();
        }
    }

    /**
     * Generate channel code: {channel}_{account_name}
     */
    protected function generateChannelCode(SyncAccount $syncAccount): string
    {
        $channel = Str::slug($syncAccount->channel);
        $name = Str::slug($syncAccount->name);

        return "{$channel}_{$name}";
    }

    /**
     * Generate channel code from original values (for updates)
     */
    protected function generateChannelCodeFromOriginal(SyncAccount $syncAccount): string
    {
        $originalChannel = $syncAccount->getOriginal('channel') ?? $syncAccount->channel;
        $originalName = $syncAccount->getOriginal('name') ?? $syncAccount->name;

        return Str::slug($originalChannel).'_'.Str::slug($originalName);
    }

    /**
     * Generate human-readable channel name
     */
    protected function generateChannelName(SyncAccount $syncAccount): string
    {
        $channelName = ucfirst($syncAccount->channel);
        $accountName = $syncAccount->display_name ?: ucfirst($syncAccount->name);

        return "{$channelName} - {$accountName}";
    }

    /**
     * Generate channel description
     */
    protected function generateChannelDescription(SyncAccount $syncAccount): string
    {
        return "Auto-generated sales channel for {$syncAccount->channel} account '{$syncAccount->name}'. ".
               "Channel-specific pricing available via {$this->generateChannelCode($syncAccount)}_price attribute.";
    }

    /**
     * Get priority for channel type
     */
    protected function getChannelPriority(string $channel): int
    {
        return match ($channel) {
            'shopify' => 100,
            'ebay' => 90,
            'amazon' => 80,
            'direct' => 70,
            'wholesale' => 60,
            default => 50,
        };
    }

    /**
     * Trigger channel attributes sync
     */
    protected function syncChannelAttributes(): void
    {
        try {
            app(\App\Services\Pricing\ChannelPricingService::class)
                ->syncChannelAttributes();
        } catch (\Exception $e) {
            Log::error('❌ Failed to sync channel attributes after SyncAccount change', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
