<?php

namespace App\Services\Marketplace\API\Builders;

use App\Services\Marketplace\API\AbstractMarketplaceService;
use Carbon\Carbon;

/**
 * 📦 ORDER OPERATION BUILDER
 *
 * Fluent API builder for marketplace order operations.
 * Provides chainable methods for configuring order operations.
 *
 * Usage:
 * $orders = $service->orders()
 *     ->since(Carbon::yesterday())
 *     ->withStatus(['PENDING', 'PROCESSING'])
 *     ->withLimit(100)
 *     ->pull();
 */
class OrderOperationBuilder
{
    protected AbstractMarketplaceService $service;

    protected array $filters = [];

    protected ?Carbon $sinceDate = null;

    protected ?Carbon $untilDate = null;

    protected array $statuses = [];

    protected int $limit = 50;

    protected int $offset = 0;

    protected string $sortBy = 'created_at';

    protected string $sortDirection = 'desc';

    protected bool $includeDetails = false;

    protected bool $includeLineItems = false;

    protected bool $includeCustomer = false;

    protected bool $includeShipping = false;

    public function __construct(AbstractMarketplaceService $service)
    {
        $this->service = $service;
    }

    /**
     * 📅 Filter orders since specific date
     */
    public function since(Carbon $date): static
    {
        $this->sinceDate = $date;

        return $this;
    }

    /**
     * 📅 Filter orders until specific date
     */
    public function until(Carbon $date): static
    {
        $this->untilDate = $date;

        return $this;
    }

    /**
     * 📋 Filter by order statuses
     */
    public function withStatus(array $statuses): static
    {
        $this->statuses = $statuses;

        return $this;
    }

    /**
     * 🔢 Set result limit
     */
    public function withLimit(int $limit): static
    {
        $this->limit = max(1, min(500, $limit)); // Limit between 1-500

        return $this;
    }

    /**
     * ⏭️ Set result offset for pagination
     */
    public function withOffset(int $offset): static
    {
        $this->offset = max(0, $offset);

        return $this;
    }

    /**
     * 📊 Set sorting options
     */
    public function sortBy(string $field, string $direction = 'desc'): static
    {
        $this->sortBy = $field;
        $this->sortDirection = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        return $this;
    }

    /**
     * 📋 Include order details in response
     */
    public function includeDetails(): static
    {
        $this->includeDetails = true;

        return $this;
    }

    /**
     * 🛒 Include line items in response
     */
    public function includeLineItems(): static
    {
        $this->includeLineItems = true;

        return $this;
    }

    /**
     * 👤 Include customer information in response
     */
    public function includeCustomer(): static
    {
        $this->includeCustomer = true;

        return $this;
    }

    /**
     * 🚚 Include shipping information in response
     */
    public function includeShipping(): static
    {
        $this->includeShipping = true;

        return $this;
    }

    /**
     * 📦 Include all available information
     */
    public function includeAll(): static
    {
        return $this->includeDetails()
            ->includeLineItems()
            ->includeCustomer()
            ->includeShipping();
    }

    /**
     * ⬇️ Pull orders from marketplace
     */
    public function pull(): array
    {
        $filters = $this->buildFilters();
        $orders = $this->service->getOrders($filters);

        return [
            'success' => true,
            'data' => $orders,
            'count' => $orders->count(),
            'filters_applied' => $filters,
        ];
    }

    /**
     * 🔍 Get a specific order by ID
     */
    public function getOrder(string $orderId): array
    {
        return $this->service->getOrder($orderId);
    }

    /**
     * 🚚 Update fulfillment status
     */
    public function fulfill(string $orderId, array $fulfillmentData): array
    {
        return $this->service->updateOrderFulfillment($orderId, $fulfillmentData);
    }

    /**
     * 📦 Add tracking information
     */
    public function addTracking(string $orderId, array $trackingData): array
    {
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
     * 💳 Process refund
     */
    public function refund(string $orderId, array $refundData): array
    {
        return $this->service->refundOrder($orderId, $refundData);
    }

    /**
     * 🔄 Sync orders to local system
     */
    public function syncToLocal(?Carbon $since = null): array
    {
        return $this->service->syncOrdersToLocal($since ?? $this->sinceDate);
    }

    /**
     * 📊 Get order statistics
     */
    public function getStatistics(): array
    {
        $filters = $this->buildFilters();

        return $this->service->getOrderStatistics($filters);
    }

    /**
     * 🏗️ Build filters array from configured options
     */
    protected function buildFilters(): array
    {
        $filters = [];

        if ($this->sinceDate) {
            $filters['since'] = $this->sinceDate->toISOString();
        }

        if ($this->untilDate) {
            $filters['until'] = $this->untilDate->toISOString();
        }

        if (! empty($this->statuses)) {
            $filters['status'] = $this->statuses;
        }

        $filters['limit'] = $this->limit;
        $filters['offset'] = $this->offset;
        $filters['sort_by'] = $this->sortBy;
        $filters['sort_direction'] = $this->sortDirection;

        // Include options
        $filters['include'] = [];
        if ($this->includeDetails) {
            $filters['include'][] = 'details';
        }
        if ($this->includeLineItems) {
            $filters['include'][] = 'line_items';
        }
        if ($this->includeCustomer) {
            $filters['include'][] = 'customer';
        }
        if ($this->includeShipping) {
            $filters['include'][] = 'shipping';
        }

        // Merge any additional filters
        $filters = array_merge($filters, $this->filters);

        return array_filter($filters); // Remove empty values
    }

    /**
     * ⚙️ Add custom filter
     */
    public function withFilter(string $key, $value): static
    {
        $this->filters[$key] = $value;

        return $this;
    }

    /**
     * 📊 Get builder summary
     */
    public function getSummary(): array
    {
        return [
            'since_date' => $this->sinceDate?->toDateString(),
            'until_date' => $this->untilDate?->toDateString(),
            'statuses' => $this->statuses,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
            'include_details' => $this->includeDetails,
            'include_line_items' => $this->includeLineItems,
            'include_customer' => $this->includeCustomer,
            'include_shipping' => $this->includeShipping,
            'custom_filters' => array_keys($this->filters),
        ];
    }
}
