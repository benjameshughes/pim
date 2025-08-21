<?php

namespace App\Services\Marketplace\API\Repositories;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * 📦 MARKETPLACE ORDER REPOSITORY
 *
 * Repository for marketplace order operations.
 * Provides specialized methods for order retrieval, fulfillment,
 * tracking, and order management operations.
 */
class MarketplaceOrderRepository extends AbstractMarketplaceRepository
{
    /**
     * 🔍 Find a single order by ID
     */
    public function find(string $id): ?array
    {
        $result = $this->service->getOrder($id);

        return $result['success'] ? $result['data']['order'] ?? $result['data'] : null;
    }

    /**
     * 📋 Get multiple orders with optional filtering
     */
    public function all(array $filters = []): Collection
    {
        return $this->service->getOrders($filters);
    }

    /**
     * ➕ Create operation not applicable for orders (read-only from marketplace)
     */
    public function create(array $data): array
    {
        throw new \BadMethodCallException('Orders cannot be created through repository - they are managed by the marketplace');
    }

    /**
     * 📝 Update order (limited to fulfillment and tracking)
     */
    public function update(string $id, array $data): array
    {
        // Only allow specific order updates
        $allowedFields = ['fulfillment_status', 'tracking_number', 'tracking_company', 'notes'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));

        if (empty($updateData)) {
            throw new \InvalidArgumentException('Only fulfillment and tracking data can be updated');
        }

        return $this->service->updateOrderFulfillment($id, $updateData);
    }

    /**
     * 🗑️ Delete operation not applicable for orders (use cancel instead)
     */
    public function delete(string $id): bool
    {
        throw new \BadMethodCallException('Orders cannot be deleted - use cancel() instead');
    }

    /**
     * 🚀 Bulk operations not typically supported for orders
     */
    public function bulkCreate(array $records): array
    {
        throw new \BadMethodCallException('Bulk order creation not supported');
    }

    /**
     * 🔄 Bulk update orders (limited to fulfillment)
     */
    public function bulkUpdate(array $orders): array
    {
        $results = [];
        $errors = [];

        foreach ($orders as $orderData) {
            if (! isset($orderData['id'])) {
                $errors[] = 'Order ID is required for bulk update';

                continue;
            }

            try {
                $result = $this->update($orderData['id'], $orderData);
                $results[] = $result;
            } catch (\Exception $e) {
                $errors[] = "Order {$orderData['id']}: {$e->getMessage()}";
            }
        }

        return [
            'success' => empty($errors),
            'results' => $results,
            'errors' => $errors,
            'processed' => count($results),
            'failed' => count($errors),
        ];
    }

    /**
     * 📅 Get orders since specific date
     */
    public function since(Carbon $date): static
    {
        return $this->where('created_at', $date->toISOString(), '>=');
    }

    /**
     * 📅 Get orders until specific date
     */
    public function until(Carbon $date): static
    {
        return $this->where('created_at', $date->toISOString(), '<=');
    }

    /**
     * 📅 Get orders for date range
     */
    public function betweenDates(Carbon $start, Carbon $end): static
    {
        return $this->where('created_at', $start->toISOString(), '>=')
            ->where('created_at', $end->toISOString(), '<=');
    }

    /**
     * 📋 Filter orders by status
     */
    public function byStatus(array $statuses): static
    {
        return $this->where('status', $statuses, 'in');
    }

    /**
     * 🚚 Filter orders by fulfillment status
     */
    public function byFulfillmentStatus(string $status): static
    {
        return $this->where('fulfillment_status', $status);
    }

    /**
     * 💰 Filter orders by total amount range
     */
    public function byAmountRange(float $minAmount, float $maxAmount): static
    {
        return $this->where('total_amount', $minAmount, '>=')
            ->where('total_amount', $maxAmount, '<=');
    }

    /**
     * 👤 Filter orders by customer email
     */
    public function byCustomerEmail(string $email): static
    {
        return $this->where('customer_email', $email);
    }

    /**
     * 📦 Get pending orders (unfulfilled)
     */
    public function pending(): static
    {
        return $this->byStatus(['pending', 'processing', 'open']);
    }

    /**
     * ✅ Get fulfilled orders
     */
    public function fulfilled(): static
    {
        return $this->byFulfillmentStatus('fulfilled');
    }

    /**
     * ❌ Get cancelled orders
     */
    public function cancelled(): static
    {
        return $this->byStatus(['cancelled', 'canceled']);
    }

    /**
     * 🚚 Update order fulfillment
     */
    public function fulfill(string $orderId, array $fulfillmentData): array
    {
        $validation = $this->validateFulfillmentData($fulfillmentData);
        if (! $validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors'],
            ];
        }

        return $this->service->updateOrderFulfillment($orderId, $fulfillmentData);
    }

    /**
     * 📦 Add tracking information to order
     */
    public function addTracking(string $orderId, array $trackingData): array
    {
        $validation = $this->validateTrackingData($trackingData);
        if (! $validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors'],
            ];
        }

        return $this->service->addTrackingToOrder($orderId, $trackingData);
    }

    /**
     * ❌ Cancel an order
     */
    public function cancel(string $orderId, string $reason = ''): array
    {
        return $this->service->cancelOrder($orderId, $reason);
    }

    /**
     * 💳 Process order refund
     */
    public function refund(string $orderId, array $refundData): array
    {
        $validation = $this->validateRefundData($refundData);
        if (! $validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors'],
            ];
        }

        return $this->service->refundOrder($orderId, $refundData);
    }

    /**
     * 🔄 Sync orders from marketplace to local system
     */
    public function syncToLocal(?Carbon $since = null): array
    {
        return $this->service->syncOrdersToLocal($since);
    }

    /**
     * 📄 Get order invoice/receipt
     */
    public function getInvoice(string $orderId): array
    {
        return $this->service->getOrderInvoice($orderId);
    }

    /**
     * 📊 Get order statistics
     */
    public function getStatistics(array $filters = []): array
    {
        $baseFilters = $this->buildQueryFilters();
        $allFilters = array_merge($baseFilters, $filters);

        return $this->service->getOrderStatistics($allFilters);
    }

    /**
     * 🏷️ Get available order statuses
     */
    public function getAvailableStatuses(): array
    {
        return $this->service->getOrderStatuses();
    }

    /**
     * 🚚 Get available shipping methods
     */
    public function getShippingMethods(): array
    {
        return $this->service->getShippingMethods();
    }

    /**
     * 📈 Get daily order summary
     */
    public function getDailySummary(Carbon $date): array
    {
        $startOfDay = $date->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        $orders = $this->betweenDates($startOfDay, $endOfDay)->get();

        return [
            'date' => $date->toDateString(),
            'total_orders' => $orders->count(),
            'total_amount' => $orders->sum('total_amount'),
            'average_order_value' => $orders->avg('total_amount'),
            'pending_orders' => $orders->where('status', 'pending')->count(),
            'fulfilled_orders' => $orders->where('fulfillment_status', 'fulfilled')->count(),
            'cancelled_orders' => $orders->whereIn('status', ['cancelled', 'canceled'])->count(),
        ];
    }

    /**
     * ✅ Validate fulfillment data
     */
    protected function validateFulfillmentData(array $data): array
    {
        $errors = [];

        if (isset($data['tracking_number']) && empty($data['tracking_company'])) {
            $errors[] = 'Tracking company is required when providing tracking number';
        }

        if (isset($data['notify_customer']) && ! is_bool($data['notify_customer'])) {
            $errors[] = 'Notify customer must be a boolean value';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * ✅ Validate tracking data
     */
    protected function validateTrackingData(array $data): array
    {
        $requiredFields = ['tracking_number', 'tracking_company'];

        return $this->validateData($data, $requiredFields);
    }

    /**
     * ✅ Validate refund data
     */
    protected function validateRefundData(array $data): array
    {
        $errors = [];

        if (! isset($data['amount']) || ! is_numeric($data['amount']) || $data['amount'] <= 0) {
            $errors[] = 'Refund amount must be a positive number';
        }

        if (isset($data['reason']) && strlen($data['reason']) > 500) {
            $errors[] = 'Refund reason cannot exceed 500 characters';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
