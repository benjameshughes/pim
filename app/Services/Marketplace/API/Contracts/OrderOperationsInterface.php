<?php

namespace App\Services\Marketplace\API\Contracts;

use App\Services\Marketplace\API\Builders\OrderOperationBuilder;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * 📦 ORDER OPERATIONS INTERFACE
 *
 * Defines contract for marketplace order operations.
 * Covers order retrieval, fulfillment, and management.
 */
interface OrderOperationsInterface
{
    /**
     * 🏗️ Create order operation builder
     */
    public function orders(): OrderOperationBuilder;

    /**
     * 📋 Get orders with filters
     */
    public function getOrders(array $filters = []): Collection;

    /**
     * 🔍 Get a single order
     */
    public function getOrder(string $orderId): array;

    /**
     * 📅 Get orders since specific date
     */
    public function getOrdersSince(Carbon $since): Collection;

    /**
     * 🚚 Update order fulfillment status
     */
    public function updateOrderFulfillment(string $orderId, array $fulfillmentData): array;

    /**
     * 📝 Add tracking information to order
     */
    public function addTrackingToOrder(string $orderId, array $trackingData): array;

    /**
     * ❌ Cancel an order
     */
    public function cancelOrder(string $orderId, string $reason = ''): array;

    /**
     * 💳 Process order refund
     */
    public function refundOrder(string $orderId, array $refundData): array;

    /**
     * 📊 Get order statistics
     */
    public function getOrderStatistics(array $filters = []): array;

    /**
     * 🔄 Sync orders to local system
     */
    public function syncOrdersToLocal(?Carbon $since = null): array;

    /**
     * 📄 Get order invoice/receipt
     */
    public function getOrderInvoice(string $orderId): array;

    /**
     * 🏷️ Get available order statuses
     */
    public function getOrderStatuses(): array;

    /**
     * 🚚 Get available shipping methods
     */
    public function getShippingMethods(): array;
}
