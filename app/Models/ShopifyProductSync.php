<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * 💎 ENHANCED SHOPIFY PRODUCT SYNC MODEL 💎
 * 
 * Now supports BOTH legacy color-based syncing AND comprehensive sync monitoring!
 * Because we're FABULOUS and support backward compatibility with STYLE! 💅
 */
class ShopifyProductSync extends Model
{
    protected $fillable = [
        'product_id',
        'color',
        'shopify_product_id',
        'shopify_handle',
        'sync_status',
        'last_sync_data',
        'last_synced_at',
        'sync_method',
        'variants_synced',
        'sync_duration',
        'error_details',
        'data_drift_score',
        'health_score',
    ];

    protected $casts = [
        'last_sync_data' => 'array',
        'last_synced_at' => 'datetime',
        'error_details' => 'array',
        'variants_synced' => 'integer',
        'sync_duration' => 'integer', // in milliseconds
        'data_drift_score' => 'float',
        'health_score' => 'integer', // 0-100
    ];

    /**
     * Get the Laravel product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Check if a product/color combination is already synced
     */
    public static function isAlreadySynced(int $productId, string $color): bool
    {
        return static::where('product_id', $productId)
            ->where('color', $color)
            ->where('sync_status', 'synced')
            ->exists();
    }

    /**
     * Get sync record for a product/color combination
     */
    public static function getSyncRecord(int $productId, string $color): ?self
    {
        return static::where('product_id', $productId)
            ->where('color', $color)
            ->first();
    }

    /**
     * Create or update sync record
     */
    public static function updateSyncRecord(
        int $productId,
        string $color,
        string $shopifyProductId,
        array $syncData,
        string $status = 'synced',
        ?string $handle = null
    ): self {
        return static::updateOrCreate(
            [
                'product_id' => $productId,
                'color' => $color,
            ],
            [
                'shopify_product_id' => $shopifyProductId,
                'shopify_handle' => $handle,
                'sync_status' => $status,
                'last_sync_data' => $syncData,
                'last_synced_at' => now(),
            ]
        );
    }

    /**
     * Check if sync data has changed (needs update)
     */
    public function hasDataChanged(array $newData): bool
    {
        if (empty($this->last_sync_data)) {
            return true;
        }

        // Compare key fields that would require an update
        $compareFields = ['title', 'body_html', 'variants_count', 'price_range'];

        foreach ($compareFields as $field) {
            if (($this->last_sync_data[$field] ?? null) !== ($newData[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all synced products for a Laravel product
     */
    public static function getSyncedProductsFor(int $productId): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('product_id', $productId)
            ->where('sync_status', 'synced')
            ->get();
    }

    // ===== 💅 NEW COMPREHENSIVE SYNC STATUS METHODS 💅 ===== //

    /**
     * Get main sync record for a product (regardless of color)
     * For comprehensive product sync status monitoring
     */
    public static function getMainSyncRecord(int $productId): ?self
    {
        return static::where('product_id', $productId)
            ->orderBy('last_synced_at', 'desc')
            ->first();
    }

    /**
     * Update comprehensive sync status with all the juicy details
     */
    public static function updateComprehensiveSync(
        int $productId,
        string $color,
        string $shopifyProductId,
        array $syncData,
        array $options = []
    ): self {
        return static::updateOrCreate(
            [
                'product_id' => $productId,
                'color' => $color,
            ],
            [
                'shopify_product_id' => $shopifyProductId,
                'shopify_handle' => $options['handle'] ?? null,
                'sync_status' => $options['status'] ?? 'synced',
                'sync_method' => $options['method'] ?? 'manual',
                'variants_synced' => $options['variants_synced'] ?? 0,
                'sync_duration' => $options['duration'] ?? null,
                'data_drift_score' => $options['drift_score'] ?? 0.0,
                'health_score' => $options['health_score'] ?? 100,
                'error_details' => $options['errors'] ?? null,
                'last_sync_data' => $syncData,
                'last_synced_at' => now(),
            ]
        );
    }

    /**
     * Calculate sync health percentage based on various factors
     */
    public function calculateSyncHealth(): int
    {
        $health = 100;
        
        // Deduct points for staleness
        $hoursSinceSync = $this->last_synced_at->diffInHours(now());
        if ($hoursSinceSync > 24) {
            $health -= 30;
        } elseif ($hoursSinceSync > 6) {
            $health -= 15;
        }
        
        // Deduct points for data drift
        if ($this->data_drift_score > 5) {
            $health -= 25;
        } elseif ($this->data_drift_score > 2) {
            $health -= 10;
        }
        
        // Deduct points for sync errors
        if ($this->sync_status === 'failed') {
            $health -= 40;
        } elseif ($this->sync_status === 'pending') {
            $health -= 20;
        }
        
        return max(0, $health);
    }

    /**
     * Get sync status summary for dashboard display
     */
    public function getSyncSummary(): array
    {
        $health = $this->calculateSyncHealth();
        
        return [
            'status' => $this->sync_status,
            'health_percentage' => $health,
            'health_grade' => $this->getHealthGrade($health),
            'last_synced' => $this->last_synced_at,
            'drift_score' => $this->data_drift_score ?? 0,
            'variants_count' => $this->variants_synced ?? 0,
            'needs_attention' => $health < 80 || $this->sync_status !== 'synced',
            'recommendations' => $this->generateRecommendations($health)
        ];
    }

    /**
     * Convert health percentage to letter grade (because we're CLASSY!)
     */
    private function getHealthGrade(int $health): string
    {
        return match(true) {
            $health >= 95 => 'A+',
            $health >= 90 => 'A',
            $health >= 85 => 'A-',
            $health >= 80 => 'B+',
            $health >= 75 => 'B',
            $health >= 70 => 'B-',
            $health >= 65 => 'C+',
            $health >= 60 => 'C',
            $health >= 55 => 'C-',
            $health >= 50 => 'D',
            default => 'F'
        };
    }

    /**
     * Generate actionable recommendations based on sync health
     */
    private function generateRecommendations(int $health): array
    {
        $recommendations = [];
        
        if ($this->sync_status === 'failed') {
            $recommendations[] = 'Resolve sync errors and retry synchronization';
        }
        
        if ($this->data_drift_score > 5) {
            $recommendations[] = 'Significant data differences detected - sync required';
        }
        
        if ($this->last_synced_at->diffInHours(now()) > 24) {
            $recommendations[] = 'Data is stale - consider automatic syncing';
        }
        
        if (empty($recommendations)) {
            $recommendations[] = 'Sync status is healthy - no action required';
        }
        
        return $recommendations;
    }

    /**
     * Scope for products that need attention (health issues)
     */
    public function scopeNeedsAttention($query)
    {
        return $query->where(function ($q) {
            $q->where('sync_status', '!=', 'synced')
              ->orWhere('data_drift_score', '>', 2)
              ->orWhere('last_synced_at', '<', Carbon::now()->subHours(24));
        });
    }

    /**
     * Scope for healthy synced products
     */
    public function scopeHealthy($query)
    {
        return $query->where('sync_status', 'synced')
                    ->where('data_drift_score', '<=', 2)
                    ->where('last_synced_at', '>=', Carbon::now()->subHours(6));
    }

    /**
     * Get Shopify admin URL for this product
     */
    public function getShopifyAdminUrl(): ?string
    {
        if (!$this->shopify_product_id) {
            return null;
        }
        
        $numericId = $this->extractNumericId($this->shopify_product_id);
        $storeUrl = config('services.shopify.store_url');
        
        return $numericId ? "https://{$storeUrl}/admin/products/{$numericId}" : null;
    }

    /**
     * Extract numeric ID from Shopify GID format
     */
    private function extractNumericId(string $gid): ?int
    {
        if (preg_match('/\/Product\/(\d+)$/', $gid, $matches)) {
            return (int) $matches[1];
        }
        
        if (is_numeric($gid)) {
            return (int) $gid;
        }
        
        return null;
    }
}
