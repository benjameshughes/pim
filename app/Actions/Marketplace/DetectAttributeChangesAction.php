<?php

namespace App\Actions\Marketplace;

use App\Actions\Base\BaseAction;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use App\Models\VariantAttribute;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * 🔍 DETECT ATTRIBUTE CHANGES ACTION
 *
 * Identifies attributes that need syncing to marketplaces based on
 * change detection, sync status, and marketplace requirements.
 */
class DetectAttributeChangesAction extends BaseAction
{
    /**
     * 🎯 EXECUTE DETECTION
     *
     * @param  Model  $model  Product or ProductVariant
     * @param  array  $options  Detection options
     */
    public function execute(Model $model, array $options = []): array
    {
        $this->validateInputs($model, $options);

        try {
            $marketplaces = $options['marketplaces'] ?? ['shopify', 'ebay', 'mirakl'];
            $changeThreshold = $options['change_threshold'] ?? null; // Carbon date or null for all

            $results = [
                'model_type' => get_class($model),
                'model_id' => $model->id,
                'detection_timestamp' => now(),
                'marketplaces' => [],
                'summary' => [
                    'total_attributes' => 0,
                    'total_needing_sync' => 0,
                    'by_marketplace' => [],
                    'by_change_type' => [],
                ],
            ];

            foreach ($marketplaces as $marketplace) {
                $marketplaceResults = $this->detectForMarketplace($model, $marketplace, $options);
                $results['marketplaces'][$marketplace] = $marketplaceResults;

                // Update summary
                $results['summary']['by_marketplace'][$marketplace] = [
                    'needs_sync' => count($marketplaceResults['needs_sync']),
                    'up_to_date' => count($marketplaceResults['up_to_date']),
                    'not_applicable' => count($marketplaceResults['not_applicable']),
                ];
            }

            // Calculate overall summary
            $this->calculateSummary($results);

            return $this->success('Attribute change detection completed', $results);

        } catch (\Exception $e) {
            return $this->fail('Change detection failed: '.$e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /**
     * 🔍 DETECT FOR MARKETPLACE
     *
     * Detect changes for a specific marketplace
     */
    protected function detectForMarketplace(Model $model, string $marketplace, array $options): array
    {
        $results = [
            'marketplace' => $marketplace,
            'is_linked' => $this->isLinkedToMarketplace($model, $marketplace),
            'needs_sync' => [],
            'up_to_date' => [],
            'not_applicable' => [],
            'errors' => [],
        ];

        // Get all attributes for this model
        $attributes = $this->getModelAttributes($model);

        foreach ($attributes as $attribute) {
            try {
                $detection = $this->detectAttributeChange($attribute, $marketplace, $options);

                switch ($detection['status']) {
                    case 'needs_sync':
                        $results['needs_sync'][] = $detection;
                        break;
                    case 'up_to_date':
                        $results['up_to_date'][] = $detection;
                        break;
                    case 'not_applicable':
                        $results['not_applicable'][] = $detection;
                        break;
                }
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'attribute_key' => $attribute->getAttributeKey(),
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * 🔍 DETECT ATTRIBUTE CHANGE
     *
     * Detect if a single attribute needs syncing to a marketplace
     */
    protected function detectAttributeChange($attribute, string $marketplace, array $options): array
    {
        $attributeKey = $attribute->getAttributeKey();
        $definition = $attribute->attributeDefinition;

        $detection = [
            'attribute_key' => $attributeKey,
            'attribute_name' => $definition->name,
            'current_value' => $attribute->getTypedValue(),
            'display_value' => $attribute->display_value,
            'last_changed' => $attribute->value_changed_at,
            'status' => 'not_applicable',
            'reasons' => [],
            'sync_priority' => 'normal',
            'estimated_sync_time' => now()->addMinutes(5), // Default estimate
        ];

        // Check if attribute should sync to this marketplace
        if (! $definition->shouldSyncToMarketplace($marketplace)) {
            $detection['status'] = 'not_applicable';
            $detection['reasons'][] = 'Attribute not configured for this marketplace';

            return $detection;
        }

        // Check if attribute is valid
        if (! $attribute->is_valid) {
            $detection['status'] = 'not_applicable';
            $detection['reasons'][] = 'Attribute value is invalid';
            $detection['validation_errors'] = $attribute->validation_errors;

            return $detection;
        }

        // Check various sync conditions
        $syncReasons = $this->getSyncReasons($attribute, $marketplace, $options);

        if (! empty($syncReasons)) {
            $detection['status'] = 'needs_sync';
            $detection['reasons'] = $syncReasons;
            $detection['sync_priority'] = $this->calculateSyncPriority($attribute, $syncReasons);
            $detection['estimated_sync_time'] = $this->estimateSyncTime($attribute, $marketplace);
        } else {
            $detection['status'] = 'up_to_date';
            $detection['reasons'][] = 'Attribute is up to date';
        }

        // Add sync metadata
        $detection['sync_metadata'] = $this->getSyncMetadata($attribute, $marketplace);

        return $detection;
    }

    /**
     * 🔍 GET SYNC REASONS
     *
     * Determine all reasons why an attribute needs syncing
     */
    protected function getSyncReasons($attribute, string $marketplace, array $options): array
    {
        $reasons = [];
        $changeThreshold = $options['change_threshold'] ?? null;

        // Check if never synced
        $syncStatus = $attribute->sync_status[$marketplace] ?? null;
        if (! $syncStatus || $syncStatus !== 'synced') {
            $reasons[] = $syncStatus ? "Previous sync failed ({$syncStatus})" : 'Never synced to this marketplace';
        }

        // Check if value changed since last sync
        if ($attribute->value_changed_at && $attribute->last_synced_at) {
            if ($attribute->value_changed_at > $attribute->last_synced_at) {
                $reasons[] = 'Value changed since last sync';
            }
        } elseif ($attribute->value_changed_at && ! $attribute->last_synced_at) {
            $reasons[] = 'Attribute has value but never synced';
        }

        // Check change threshold
        if ($changeThreshold && $attribute->value_changed_at) {
            if ($attribute->value_changed_at > $changeThreshold) {
                $reasons[] = 'Changed after threshold date';
            }
        }

        // Check if parent product/variant changed (for inherited attributes)
        if ($this->hasInheritanceChanges($attribute)) {
            $reasons[] = 'Parent attribute changed (inheritance)';
        }

        // Check marketplace-specific requirements
        $marketplaceReasons = $this->getMarketplaceSpecificReasons($attribute, $marketplace);
        $reasons = array_merge($reasons, $marketplaceReasons);

        // Force sync option
        if ($options['force'] ?? false) {
            $reasons[] = 'Force sync requested';
        }

        return array_unique($reasons);
    }

    /**
     * 🧬 CHECK INHERITANCE CHANGES
     *
     * Check if inherited attributes need updating due to parent changes
     */
    protected function hasInheritanceChanges($attribute): bool
    {
        if (! $attribute->is_inherited || ! ($attribute instanceof VariantAttribute)) {
            return false;
        }

        $parentAttribute = $attribute->inheritedFromProductAttribute;
        if (! $parentAttribute) {
            return false;
        }

        // Check if parent attribute changed after this was inherited
        return $attribute->inherited_at &&
               $parentAttribute->value_changed_at &&
               $parentAttribute->value_changed_at > $attribute->inherited_at;
    }

    /**
     * 🏪 GET MARKETPLACE SPECIFIC REASONS
     *
     * Get reasons specific to each marketplace
     */
    protected function getMarketplaceSpecificReasons($attribute, string $marketplace): array
    {
        $reasons = [];

        switch ($marketplace) {
            case 'shopify':
                // Check if Shopify product/variant exists
                $model = $attribute instanceof ProductAttribute ? $attribute->product : $attribute->variant;
                if (! $this->isLinkedToMarketplace($model, 'shopify')) {
                    $reasons[] = 'Product/variant not yet linked to Shopify';
                }
                break;

            case 'ebay':
                // eBay-specific checks
                if ($attribute->getAttributeKey() === 'condition') {
                    $reasons[] = 'Condition changes require eBay listing update';
                }
                break;

            case 'mirakl':
                // Mirakl-specific checks
                if (in_array($attribute->getAttributeKey(), ['brand', 'category'])) {
                    $reasons[] = 'Core Mirakl attributes require immediate sync';
                }
                break;
        }

        return $reasons;
    }

    /**
     * ⚡ CALCULATE SYNC PRIORITY
     *
     * Determine sync priority based on reasons and attribute importance
     */
    protected function calculateSyncPriority($attribute, array $reasons): string
    {
        $highPriorityReasons = [
            'Never synced to this marketplace',
            'Previous sync failed',
            'Core Mirakl attributes require immediate sync',
            'Condition changes require eBay listing update',
        ];

        $criticalAttributes = ['brand', 'condition', 'title', 'price'];

        foreach ($reasons as $reason) {
            if (in_array($reason, $highPriorityReasons)) {
                return 'high';
            }
        }

        if (in_array($attribute->getAttributeKey(), $criticalAttributes)) {
            return 'high';
        }

        if (count($reasons) > 2) {
            return 'medium';
        }

        return 'normal';
    }

    /**
     * ⏱️ ESTIMATE SYNC TIME
     *
     * Estimate when the sync should happen based on priority and load
     */
    protected function estimateSyncTime($attribute, string $marketplace): Carbon
    {
        $baseDelay = match ($this->calculateSyncPriority($attribute, [])) {
            'high' => 1,      // 1 minute
            'medium' => 5,    // 5 minutes
            'normal' => 15,   // 15 minutes
            default => 15,
        };

        // Add marketplace-specific delays
        $marketplaceDelay = match ($marketplace) {
            'shopify' => 0,   // Fastest
            'ebay' => 2,      // Moderate
            'mirakl' => 5,    // Slower
            default => 2,
        };

        return now()->addMinutes($baseDelay + $marketplaceDelay);
    }

    /**
     * 📊 GET SYNC METADATA
     *
     * Get current sync metadata for an attribute
     */
    protected function getSyncMetadata($attribute, string $marketplace): array
    {
        return [
            'last_synced_at' => $attribute->last_synced_at,
            'last_sync_status' => $attribute->sync_status[$marketplace] ?? 'never',
            'sync_metadata' => $attribute->sync_metadata[$marketplace] ?? null,
            'version' => $attribute->version,
            'is_inherited' => $attribute->is_inherited ?? false,
            'source' => $attribute->source,
        ];
    }

    /**
     * 🔗 CHECK MARKETPLACE LINK
     *
     * Check if model is linked to marketplace
     */
    protected function isLinkedToMarketplace(Model $model, string $marketplace): bool
    {
        return $model->marketplaceLinks()
            ->where('marketplace', $marketplace)
            ->exists();
    }

    /**
     * 📝 GET MODEL ATTRIBUTES
     *
     * Get all valid attributes for a model
     */
    protected function getModelAttributes(Model $model): Collection
    {
        return $model->validAttributes()->with('attributeDefinition')->get();
    }

    /**
     * 📊 CALCULATE SUMMARY
     *
     * Calculate summary statistics for the results
     */
    protected function calculateSummary(array &$results): void
    {
        $totalNeedingSync = 0;
        $changeTypes = [];

        foreach ($results['marketplaces'] as $marketplace => $marketplaceResults) {
            $needsSync = count($marketplaceResults['needs_sync']);
            $totalNeedingSync += $needsSync;

            // Collect change types
            foreach ($marketplaceResults['needs_sync'] as $change) {
                foreach ($change['reasons'] as $reason) {
                    $changeTypes[$reason] = ($changeTypes[$reason] ?? 0) + 1;
                }
            }
        }

        $results['summary']['total_needing_sync'] = $totalNeedingSync;
        $results['summary']['by_change_type'] = $changeTypes;

        // Get total attributes (from first marketplace to avoid double counting)
        $firstMarketplace = array_key_first($results['marketplaces']);
        if ($firstMarketplace) {
            $firstResults = $results['marketplaces'][$firstMarketplace];
            $results['summary']['total_attributes'] =
                count($firstResults['needs_sync']) +
                count($firstResults['up_to_date']) +
                count($firstResults['not_applicable']);
        }
    }

    /**
     * ✅ VALIDATE INPUTS
     */
    protected function validateInputs(Model $model, array $options): void
    {
        if (! in_array(get_class($model), [Product::class, ProductVariant::class])) {
            throw new \InvalidArgumentException('Model must be Product or ProductVariant');
        }

        if (isset($options['marketplaces'])) {
            $supportedMarketplaces = ['shopify', 'ebay', 'mirakl'];
            $invalidMarketplaces = array_diff($options['marketplaces'], $supportedMarketplaces);
            if (! empty($invalidMarketplaces)) {
                throw new \InvalidArgumentException('Unsupported marketplaces: '.implode(', ', $invalidMarketplaces));
            }
        }

        if (isset($options['change_threshold']) && ! ($options['change_threshold'] instanceof Carbon)) {
            throw new \InvalidArgumentException('change_threshold must be a Carbon instance');
        }
    }

    /**
     * 🔄 BATCH DETECTION
     *
     * Detect changes for multiple models efficiently
     */
    public function batchDetect(array $models, array $options = []): array
    {
        $results = [
            'total_models' => count($models),
            'models_processed' => 0,
            'models_with_changes' => 0,
            'total_changes_detected' => 0,
            'by_marketplace' => [],
            'models' => [],
            'summary' => [],
        ];

        foreach ($models as $model) {
            $detection = $this->execute($model, $options);

            if ($detection['success']) {
                $modelData = $detection['details'];
                $results['models'][] = [
                    'model_type' => $modelData['model_type'],
                    'model_id' => $modelData['model_id'],
                    'has_changes' => $modelData['summary']['total_needing_sync'] > 0,
                    'changes_count' => $modelData['summary']['total_needing_sync'],
                    'marketplaces' => $modelData['marketplaces'],
                ];

                if ($modelData['summary']['total_needing_sync'] > 0) {
                    $results['models_with_changes']++;
                    $results['total_changes_detected'] += $modelData['summary']['total_needing_sync'];
                }
            }

            $results['models_processed']++;
        }

        return $results;
    }

    /**
     * 📈 GET CHANGE TRENDS
     *
     * Analyze change patterns over time
     */
    public function getChangeTrends(array $options = []): array
    {
        $dateFrom = $options['date_from'] ?? now()->subDays(30);
        $dateTo = $options['date_to'] ?? now();
        $marketplaces = $options['marketplaces'] ?? ['shopify', 'ebay', 'mirakl'];

        // This would analyze change patterns from historical data
        // Placeholder implementation
        return [
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'marketplaces_analyzed' => $marketplaces,
            'daily_changes' => [],
            'most_changed_attributes' => [],
            'change_frequency' => [],
            'sync_success_rate' => [],
        ];
    }
}
