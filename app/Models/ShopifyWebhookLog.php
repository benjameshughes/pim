<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * 📡 SHOPIFY WEBHOOK LOG MODEL 📡
 * 
 * Tracks all incoming webhook events from Shopify like a NOTIFICATION HISTORIAN!
 * Perfect for debugging, monitoring, and sync event auditing! 💅
 */
class ShopifyWebhookLog extends Model
{
    protected $fillable = [
        'topic',
        'shopify_product_id',
        'shopify_variant_id', 
        'payload',
        'headers',
        'processed_at',
        'processing_status',
        'processing_result',
        'error_message',
        'related_product_id',
        'event_timestamp',
        'webhook_id',
        'signature_verified',
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array', 
        'processing_result' => 'array',
        'processed_at' => 'datetime',
        'event_timestamp' => 'datetime',
        'signature_verified' => 'boolean',
    ];

    /**
     * Get related Laravel product if found
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'related_product_id');
    }

    /**
     * Scope for unprocessed webhooks
     */
    public function scopeUnprocessed($query)
    {
        return $query->whereNull('processed_at');
    }

    /**
     * Scope for failed processing
     */
    public function scopeFailed($query)
    {
        return $query->where('processing_status', 'failed');
    }

    /**
     * Scope for sync-related topics only
     */
    public function scopeSyncRelated($query)
    {
        return $query->whereIn('topic', [
            'products/create',
            'products/update',
            'products/delete',
            'inventory_levels/update',
            'inventory_levels/connect',
            'inventory_levels/disconnect'
        ]);
    }

    /**
     * Scope for recent events (last 24 hours)
     */
    public function scopeRecent($query)
    {
        return $query->where('created_at', '>=', Carbon::now()->subDay());
    }

    /**
     * Mark webhook as processed
     */
    public function markProcessed(string $status = 'success', array $result = [], ?string $error = null): void
    {
        $this->update([
            'processed_at' => now(),
            'processing_status' => $status,
            'processing_result' => $result,
            'error_message' => $error,
        ]);
    }

    /**
     * Extract Shopify product ID from payload
     */
    public function extractShopifyProductId(): ?string
    {
        $payload = $this->payload;
        
        // Try different payload structures based on webhook topic
        return match($this->topic) {
            'products/create', 'products/update', 'products/delete' 
                => $payload['id'] ?? null,
            'inventory_levels/update', 'inventory_levels/connect', 'inventory_levels/disconnect'
                => $payload['inventory_item_id'] ?? null,
            default => null
        };
    }

    /**
     * Find related local product by matching SKUs or other identifiers
     */
    public function findRelatedProduct(): ?Product
    {
        $payload = $this->payload;
        
        // Try to match by variant SKUs first
        if (isset($payload['variants'])) {
            foreach ($payload['variants'] as $variant) {
                if ($sku = $variant['sku'] ?? null) {
                    $product = Product::whereHas('variants', function($query) use ($sku) {
                        $query->where('sku', $sku);
                    })->first();
                    
                    if ($product) {
                        return $product;
                    }
                }
            }
        }
        
        // Try to match by single variant SKU (for variant-specific events)
        if ($sku = $payload['sku'] ?? null) {
            return Product::whereHas('variants', function($query) use ($sku) {
                $query->where('sku', $sku);
            })->first();
        }
        
        return null;
    }

    /**
     * Get webhook event summary for dashboard display
     */
    public function getEventSummary(): array
    {
        return [
            'topic' => $this->topic,
            'timestamp' => $this->event_timestamp ?? $this->created_at,
            'status' => $this->processing_status ?? 'pending',
            'product_name' => $this->product?->name ?? 'Unknown Product',
            'shopify_id' => $this->shopify_product_id,
            'signature_verified' => $this->signature_verified,
            'needs_attention' => in_array($this->processing_status, ['failed', 'error'])
        ];
    }

    /**
     * Create webhook log from incoming request
     */
    public static function createFromWebhook(string $topic, array $payload, array $headers = []): self
    {
        return static::create([
            'topic' => $topic,
            'payload' => $payload,
            'headers' => $headers,
            'shopify_product_id' => $payload['id'] ?? null,
            'shopify_variant_id' => $payload['variant_id'] ?? null,
            'event_timestamp' => isset($payload['updated_at']) 
                ? Carbon::parse($payload['updated_at']) 
                : now(),
            'webhook_id' => $headers['X-Shopify-Webhook-Id'] ?? null,
            'signature_verified' => false, // Will be updated after verification
        ]);
    }
}