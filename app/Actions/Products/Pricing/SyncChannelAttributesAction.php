<?php

namespace App\Actions\Products\Pricing;

use App\Actions\Base\BaseAction;
use App\Models\AttributeDefinition;
use App\Models\SalesChannel;
use Illuminate\Support\Facades\Log;

/**
 * 🔄 SYNC CHANNEL ATTRIBUTES ACTION
 *
 * Auto-creates attribute definitions for channel pricing based on active sales channels.
 * Creates attributes like 'shopify_price', 'ebay_price', etc. for each active channel.
 */
class SyncChannelAttributesAction extends BaseAction
{
    /**
     * Execute the channel attributes sync
     */
    protected function performAction(...$params): array
    {
        $forceUpdate = $params[0] ?? false;

        Log::info('🔄 Starting channel attributes sync', [
            'force_update' => $forceUpdate,
        ]);

        $startTime = microtime(true);

        try {
            // Get all active sales channels
            $activeChannels = SalesChannel::active()->get();

            if ($activeChannels->isEmpty()) {
                return $this->failure('No active sales channels found');
            }

            $results = [
                'created' => [],
                'updated' => [],
                'skipped' => [],
            ];

            // Create/update attribute definitions for each channel
            foreach ($activeChannels as $channel) {
                $attributeKey = $channel->code.'_price';
                $result = $this->createOrUpdateChannelPriceAttribute($channel, $attributeKey, $forceUpdate);

                $results[$result['action']][] = [
                    'channel' => $channel->code,
                    'attribute_key' => $attributeKey,
                    'details' => $result,
                ];
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('✅ Channel attributes sync completed', [
                'duration_ms' => $duration,
                'created_count' => count($results['created']),
                'updated_count' => count($results['updated']),
                'skipped_count' => count($results['skipped']),
            ]);

            return $this->success('Channel attributes synced successfully', [
                'duration_ms' => $duration,
                'results' => $results,
                'summary' => [
                    'created' => count($results['created']),
                    'updated' => count($results['updated']),
                    'skipped' => count($results['skipped']),
                ],
            ]);

        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('❌ Channel attributes sync failed', [
                'error' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);

            return $this->failure('Failed to sync channel attributes: '.$e->getMessage(), [
                'error_type' => get_class($e),
                'duration_ms' => $duration,
            ]);
        }
    }

    /**
     * Create or update a channel price attribute definition
     */
    protected function createOrUpdateChannelPriceAttribute(SalesChannel $channel, string $attributeKey, bool $forceUpdate): array
    {
        $existingAttribute = AttributeDefinition::where('key', $attributeKey)->first();

        $attributeData = [
            'key' => $attributeKey,
            'name' => ucfirst($channel->name).' Price',
            'description' => "Channel-specific pricing override for {$channel->name}. If set, this price will be used instead of the default retail price for {$channel->name} sales channel.",
            'data_type' => 'decimal',
            'is_inheritable' => false, // Channel pricing is variant-specific
            'is_required_for_variants' => false,
            'is_system_attribute' => false,
            'group' => 'Channel Pricing',
            'icon' => $this->getChannelIcon($channel->code),
            'is_active' => true,
            'validation_rules' => [
                'min' => 0.01,
                'max' => 999999.99,
                'decimal_places' => 2,
            ],
            'ui_options' => [
                'category' => 'Channel Pricing',
                'icon' => $this->getChannelIcon($channel->code),
                'color' => $this->getChannelColor($channel->code),
                'currency_symbol' => '£',
                'show_in_summary' => true,
                'channel_code' => $channel->code,
                'channel_id' => $channel->id,
                'auto_generated' => true,
                'pricing_attribute' => true,
            ],
        ];

        if ($existingAttribute) {
            if ($forceUpdate) {
                $existingAttribute->update($attributeData);

                return [
                    'action' => 'updated',
                    'attribute_id' => $existingAttribute->id,
                    'message' => "Updated existing attribute definition for {$channel->name}",
                ];
            } else {
                return [
                    'action' => 'skipped',
                    'attribute_id' => $existingAttribute->id,
                    'message' => "Attribute already exists for {$channel->name}, skipping",
                ];
            }
        } else {
            $newAttribute = AttributeDefinition::create($attributeData);

            return [
                'action' => 'created',
                'attribute_id' => $newAttribute->id,
                'message' => "Created new channel price attribute for {$channel->name}",
            ];
        }
    }

    /**
     * Get icon for channel
     */
    protected function getChannelIcon(string $channelCode): string
    {
        return match ($channelCode) {
            'shopify' => 'shopping-bag',
            'ebay' => 'store',
            'amazon' => 'shopping-cart',
            'direct' => 'home',
            'retail' => 'store',
            'wholesale' => 'building-2',
            default => 'pound-sterling'
        };
    }

    /**
     * Get color for channel
     */
    protected function getChannelColor(string $channelCode): string
    {
        return match ($channelCode) {
            'shopify' => '#96bf48',
            'ebay' => '#e53238',
            'amazon' => '#ff9900',
            'direct' => '#3b82f6',
            'retail' => '#059669',
            'wholesale' => '#7c3aed',
            default => '#6b7280'
        };
    }

    /**
     * Get all channel price attribute keys
     */
    public static function getChannelPriceAttributeKeys(): array
    {
        return SalesChannel::active()
            ->pluck('code')
            ->map(fn ($code) => $code.'_price')
            ->toArray();
    }

    /**
     * Check if a key is a channel price attribute
     */
    public static function isChannelPriceAttribute(string $key): bool
    {
        return in_array($key, static::getChannelPriceAttributeKeys());
    }
}
