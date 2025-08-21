<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Services\Shopify\API\WebhooksApi;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * 🪝 SHOPIFY WEBHOOK CONTROLLER
 *
 * Handles incoming Shopify webhooks with HMAC verification.
 * Processes webhook events and broadcasts to Livewire components.
 */
class WebhookController extends Controller
{
    /**
     * 🛡️ WEBHOOK MIDDLEWARE
     *
     * Verify webhook authenticity before processing
     */
    public function __construct()
    {
        $this->middleware('throttle:shopify-webhooks');
    }

    /**
     * 🛍️ HANDLE PRODUCT WEBHOOKS
     *
     * Handle product-related webhook events
     */
    public function handleProductWebhooks(Request $request): Response
    {
        // Verify webhook signature
        if (! $this->verifyWebhookSignature($request)) {
            Log::warning('Invalid Shopify webhook signature for product webhook', [
                'headers' => $request->headers->all(),
                'ip' => $request->ip(),
            ]);

            return response('Unauthorized', 401);
        }

        $topic = $request->header('X-Shopify-Topic');
        $shopDomain = $request->header('X-Shopify-Shop-Domain');
        $payload = $request->json()->all();

        Log::info('Shopify product webhook received', [
            'topic' => $topic,
            'shop_domain' => $shopDomain,
            'product_id' => $payload['id'] ?? null,
        ]);

        try {
            $result = match ($topic) {
                'products/create' => $this->handleProductCreate($payload, $shopDomain),
                'products/update' => $this->handleProductUpdate($payload, $shopDomain),
                'products/delete' => $this->handleProductDelete($payload, $shopDomain),
                'product_listings/add' => $this->handleProductListingAdd($payload, $shopDomain),
                'product_listings/remove' => $this->handleProductListingRemove($payload, $shopDomain),
                'product_listings/update' => $this->handleProductListingUpdate($payload, $shopDomain),
                'inventory_levels/connect' => $this->handleInventoryConnect($payload, $shopDomain),
                'inventory_levels/update' => $this->handleInventoryUpdate($payload, $shopDomain),
                'inventory_levels/disconnect' => $this->handleInventoryDisconnect($payload, $shopDomain),
                default => $this->handleUnsupportedTopic($topic, $payload, $shopDomain),
            };

            // Broadcast real-time update to Livewire components
            $this->broadcastWebhookEvent($topic, $payload, $shopDomain, $result);

            return response('OK', 200);

        } catch (\Exception $e) {
            Log::error('Error processing Shopify product webhook', [
                'topic' => $topic,
                'shop_domain' => $shopDomain,
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return response('Internal Server Error', 500);
        }
    }

    /**
     * 📦 HANDLE ORDER WEBHOOKS
     *
     * Handle order-related webhook events
     */
    public function handleOrderWebhooks(Request $request): Response
    {
        // Verify webhook signature
        if (! $this->verifyWebhookSignature($request)) {
            Log::warning('Invalid Shopify webhook signature for order webhook', [
                'headers' => $request->headers->all(),
                'ip' => $request->ip(),
            ]);

            return response('Unauthorized', 401);
        }

        $topic = $request->header('X-Shopify-Topic');
        $shopDomain = $request->header('X-Shopify-Shop-Domain');
        $payload = $request->json()->all();

        Log::info('Shopify order webhook received', [
            'topic' => $topic,
            'shop_domain' => $shopDomain,
            'order_id' => $payload['id'] ?? null,
            'order_number' => $payload['order_number'] ?? null,
        ]);

        try {
            $result = match ($topic) {
                'orders/create' => $this->handleOrderCreate($payload, $shopDomain),
                'orders/update' => $this->handleOrderUpdate($payload, $shopDomain),
                'orders/paid' => $this->handleOrderPaid($payload, $shopDomain),
                'orders/cancelled' => $this->handleOrderCancelled($payload, $shopDomain),
                'orders/fulfilled' => $this->handleOrderFulfilled($payload, $shopDomain),
                'orders/partially_fulfilled' => $this->handleOrderPartiallyFulfilled($payload, $shopDomain),
                'order_transactions/create' => $this->handleOrderTransactionCreate($payload, $shopDomain),
                default => $this->handleUnsupportedTopic($topic, $payload, $shopDomain),
            };

            // Broadcast real-time update
            $this->broadcastWebhookEvent($topic, $payload, $shopDomain, $result);

            return response('OK', 200);

        } catch (\Exception $e) {
            Log::error('Error processing Shopify order webhook', [
                'topic' => $topic,
                'shop_domain' => $shopDomain,
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return response('Internal Server Error', 500);
        }
    }

    /**
     * 🔧 HANDLE OTHER WEBHOOKS
     *
     * Handle miscellaneous webhook events
     */
    public function handleOtherWebhooks(Request $request): Response
    {
        // Verify webhook signature
        if (! $this->verifyWebhookSignature($request)) {
            Log::warning('Invalid Shopify webhook signature for other webhook', [
                'headers' => $request->headers->all(),
                'ip' => $request->ip(),
            ]);

            return response('Unauthorized', 401);
        }

        $topic = $request->header('X-Shopify-Topic');
        $shopDomain = $request->header('X-Shopify-Shop-Domain');
        $payload = $request->json()->all();

        Log::info('Shopify other webhook received', [
            'topic' => $topic,
            'shop_domain' => $shopDomain,
        ]);

        try {
            $result = match ($topic) {
                'app/uninstalled' => $this->handleAppUninstalled($payload, $shopDomain),
                'shop/update' => $this->handleShopUpdate($payload, $shopDomain),
                'customers/create' => $this->handleCustomerCreate($payload, $shopDomain),
                'customers/update' => $this->handleCustomerUpdate($payload, $shopDomain),
                'customers/delete' => $this->handleCustomerDelete($payload, $shopDomain),
                'collections/create' => $this->handleCollectionCreate($payload, $shopDomain),
                'collections/update' => $this->handleCollectionUpdate($payload, $shopDomain),
                'collections/delete' => $this->handleCollectionDelete($payload, $shopDomain),
                default => $this->handleUnsupportedTopic($topic, $payload, $shopDomain),
            };

            // Broadcast real-time update
            $this->broadcastWebhookEvent($topic, $payload, $shopDomain, $result);

            return response('OK', 200);

        } catch (\Exception $e) {
            Log::error('Error processing Shopify other webhook', [
                'topic' => $topic,
                'shop_domain' => $shopDomain,
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return response('Internal Server Error', 500);
        }
    }

    /**
     * 🛡️ VERIFY WEBHOOK SIGNATURE
     *
     * Verify HMAC signature of incoming webhook
     */
    protected function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->header('X-Shopify-Hmac-Sha256');
        $payload = $request->getContent();

        if (! $signature || ! $payload) {
            return false;
        }

        return WebhooksApi::verifyWebhookSignature($payload, $signature);
    }

    // Product webhook handlers

    /**
     * 🛍️ HANDLE PRODUCT CREATE
     */
    protected function handleProductCreate(array $payload, string $shopDomain): array
    {
        // Check if this product has PIM metafields
        $pimProductId = $this->extractPimProductId($payload);

        if ($pimProductId) {
            // This is a product created by our PIM system - log sync success
            Log::info('PIM-created product confirmed in Shopify', [
                'shop_domain' => $shopDomain,
                'shopify_product_id' => $payload['id'],
                'pim_product_id' => $pimProductId,
            ]);
        } else {
            // This is a product created directly in Shopify - might need sync to PIM
            Log::info('External product created in Shopify', [
                'shop_domain' => $shopDomain,
                'shopify_product_id' => $payload['id'],
                'title' => $payload['title'] ?? '',
            ]);
        }

        return [
            'action' => 'product_created',
            'pim_managed' => ! is_null($pimProductId),
            'product_id' => $payload['id'],
        ];
    }

    /**
     * 🔄 HANDLE PRODUCT UPDATE
     */
    protected function handleProductUpdate(array $payload, string $shopDomain): array
    {
        $pimProductId = $this->extractPimProductId($payload);

        Log::info('Product updated in Shopify', [
            'shop_domain' => $shopDomain,
            'shopify_product_id' => $payload['id'],
            'pim_product_id' => $pimProductId,
            'title' => $payload['title'] ?? '',
        ]);

        // If this is a PIM-managed product, we might need to sync changes back
        if ($pimProductId) {
            // Queue job to sync changes back to PIM if needed
            // This could update inventory, pricing, or other fields
        }

        return [
            'action' => 'product_updated',
            'pim_managed' => ! is_null($pimProductId),
            'product_id' => $payload['id'],
        ];
    }

    /**
     * 🗑️ HANDLE PRODUCT DELETE
     */
    protected function handleProductDelete(array $payload, string $shopDomain): array
    {
        Log::info('Product deleted in Shopify', [
            'shop_domain' => $shopDomain,
            'shopify_product_id' => $payload['id'],
        ]);

        return [
            'action' => 'product_deleted',
            'product_id' => $payload['id'],
        ];
    }

    /**
     * 📋 HANDLE INVENTORY UPDATE
     */
    protected function handleInventoryUpdate(array $payload, string $shopDomain): array
    {
        Log::info('Inventory updated in Shopify', [
            'shop_domain' => $shopDomain,
            'inventory_item_id' => $payload['inventory_item_id'] ?? null,
            'available' => $payload['available'] ?? null,
        ]);

        // This is crucial for keeping PIM inventory in sync
        return [
            'action' => 'inventory_updated',
            'inventory_item_id' => $payload['inventory_item_id'] ?? null,
            'available' => $payload['available'] ?? null,
        ];
    }

    // Order webhook handlers

    /**
     * 📦 HANDLE ORDER CREATE
     */
    protected function handleOrderCreate(array $payload, string $shopDomain): array
    {
        Log::info('New order created in Shopify', [
            'shop_domain' => $shopDomain,
            'order_id' => $payload['id'],
            'order_number' => $payload['order_number'] ?? null,
            'total_price' => $payload['total_price'] ?? null,
        ]);

        // Extract line items for inventory management
        $lineItems = $payload['line_items'] ?? [];
        $pimManagedItems = [];

        foreach ($lineItems as $item) {
            $pimVariantId = $this->extractPimVariantId($item);
            if ($pimVariantId) {
                $pimManagedItems[] = [
                    'pim_variant_id' => $pimVariantId,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ];
            }
        }

        return [
            'action' => 'order_created',
            'order_id' => $payload['id'],
            'pim_managed_items' => $pimManagedItems,
        ];
    }

    /**
     * 💰 HANDLE ORDER PAID
     */
    protected function handleOrderPaid(array $payload, string $shopDomain): array
    {
        Log::info('Order paid in Shopify', [
            'shop_domain' => $shopDomain,
            'order_id' => $payload['id'],
            'order_number' => $payload['order_number'] ?? null,
        ]);

        return [
            'action' => 'order_paid',
            'order_id' => $payload['id'],
        ];
    }

    // Utility methods

    /**
     * 🏷️ EXTRACT PIM PRODUCT ID
     */
    protected function extractPimProductId(array $payload): ?string
    {
        $metafields = $payload['metafields'] ?? [];

        foreach ($metafields as $metafield) {
            if ($metafield['namespace'] === 'pim' && $metafield['key'] === 'product_id') {
                return $metafield['value'];
            }
        }

        return null;
    }

    /**
     * 🏷️ EXTRACT PIM VARIANT ID
     */
    protected function extractPimVariantId(array $lineItem): ?string
    {
        $properties = $lineItem['properties'] ?? [];

        foreach ($properties as $property) {
            if ($property['name'] === 'pim_variant_id') {
                return $property['value'];
            }
        }

        return null;
    }

    /**
     * ❓ HANDLE UNSUPPORTED TOPIC
     */
    protected function handleUnsupportedTopic(string $topic, array $payload, string $shopDomain): array
    {
        Log::info('Unsupported webhook topic received', [
            'topic' => $topic,
            'shop_domain' => $shopDomain,
        ]);

        return [
            'action' => 'unsupported_topic',
            'topic' => $topic,
        ];
    }

    /**
     * 📡 BROADCAST WEBHOOK EVENT
     *
     * Broadcast webhook events to Livewire components for real-time updates
     */
    protected function broadcastWebhookEvent(string $topic, array $payload, string $shopDomain, array $result): void
    {
        try {
            $eventData = [
                'topic' => $topic,
                'shop_domain' => $shopDomain,
                'timestamp' => now()->toISOString(),
                'result' => $result,
                'summary' => $this->buildEventSummary($topic, $payload, $result),
            ];

            // Broadcast to shop-specific channel
            Broadcast::channel("shopify.{$shopDomain}")
                ->send('webhook-received', $eventData);

            // Broadcast to general Shopify channel for global updates
            Broadcast::channel('shopify.global')
                ->send('webhook-received', $eventData);

            Log::debug('Webhook event broadcasted', [
                'topic' => $topic,
                'shop_domain' => $shopDomain,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to broadcast webhook event', [
                'topic' => $topic,
                'shop_domain' => $shopDomain,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 📋 BUILD EVENT SUMMARY
     */
    protected function buildEventSummary(string $topic, array $payload, array $result): string
    {
        return match ($topic) {
            'products/create' => "Product '{$payload['title']}' was created",
            'products/update' => "Product '{$payload['title']}' was updated",
            'products/delete' => 'Product was deleted',
            'orders/create' => "New order #{$payload['order_number']} created",
            'orders/paid' => "Order #{$payload['order_number']} was paid",
            'inventory_levels/update' => 'Inventory updated',
            default => "Webhook {$topic} processed",
        };
    }

    // Additional handler stubs for other webhook topics

    protected function handleProductListingAdd(array $payload, string $shopDomain): array
    {
        return ['action' => 'product_listing_added'];
    }

    protected function handleProductListingRemove(array $payload, string $shopDomain): array
    {
        return ['action' => 'product_listing_removed'];
    }

    protected function handleProductListingUpdate(array $payload, string $shopDomain): array
    {
        return ['action' => 'product_listing_updated'];
    }

    protected function handleInventoryConnect(array $payload, string $shopDomain): array
    {
        return ['action' => 'inventory_connected'];
    }

    protected function handleInventoryDisconnect(array $payload, string $shopDomain): array
    {
        return ['action' => 'inventory_disconnected'];
    }

    protected function handleOrderUpdate(array $payload, string $shopDomain): array
    {
        return ['action' => 'order_updated'];
    }

    protected function handleOrderCancelled(array $payload, string $shopDomain): array
    {
        return ['action' => 'order_cancelled'];
    }

    protected function handleOrderFulfilled(array $payload, string $shopDomain): array
    {
        return ['action' => 'order_fulfilled'];
    }

    protected function handleOrderPartiallyFulfilled(array $payload, string $shopDomain): array
    {
        return ['action' => 'order_partially_fulfilled'];
    }

    protected function handleOrderTransactionCreate(array $payload, string $shopDomain): array
    {
        return ['action' => 'order_transaction_created'];
    }

    protected function handleAppUninstalled(array $payload, string $shopDomain): array
    {
        return ['action' => 'app_uninstalled'];
    }

    protected function handleShopUpdate(array $payload, string $shopDomain): array
    {
        return ['action' => 'shop_updated'];
    }

    protected function handleCustomerCreate(array $payload, string $shopDomain): array
    {
        return ['action' => 'customer_created'];
    }

    protected function handleCustomerUpdate(array $payload, string $shopDomain): array
    {
        return ['action' => 'customer_updated'];
    }

    protected function handleCustomerDelete(array $payload, string $shopDomain): array
    {
        return ['action' => 'customer_deleted'];
    }

    protected function handleCollectionCreate(array $payload, string $shopDomain): array
    {
        return ['action' => 'collection_created'];
    }

    protected function handleCollectionUpdate(array $payload, string $shopDomain): array
    {
        return ['action' => 'collection_updated'];
    }

    protected function handleCollectionDelete(array $payload, string $shopDomain): array
    {
        return ['action' => 'collection_deleted'];
    }
}
