<?php

namespace App\Services\Pricing\Fluent;

use App\Models\ProductVariant;
use App\Services\Pricing\Actions\BulkUpdatePricingAction;
use App\Services\Pricing\Actions\CopyPricingBetweenChannelsAction;
use App\Services\Pricing\Actions\RecalculateProfitAction;
use App\Services\Pricing\Actions\SyncPricingToMarketplaceAction;
use App\Services\Pricing\Actions\UpdateVariantPriceAction;
use App\Services\Pricing\Calculators\ProfitCalculator;
use App\Services\Pricing\Calculators\Rounding;
use App\Services\Pricing\Calculators\TargetMarginSolver;
use App\Services\Pricing\Channel\ChannelResolver;

/**
 * PricingBuilder
 *
 * Chainable builder capturing a pricing scope (variants/product),
 * channel context and queued operations. Read operations return data;
 * write operations defer to action classes when calling save()/push().
 */
class PricingBuilder
{
    /** @var array<int> */
    protected array $variantIds = [];

    protected ?int $productId = null;

    protected ?int $salesChannelId = null;

    protected ?string $channel = null;

    protected ?string $account = null;

    protected ?string $salesChannelCode = null;

    protected ?string $currency = null;

    /** Queued write operations to apply on save() */
    protected array $pendingUpdates = [
        // variantId => ['price' => ..., 'discount_price' => ..., 'cost_price' => ...]
    ];

    /** Bulk update map (variantId => fields) */
    protected array $bulkMap = [];

    /** Optional rounding strategy name */
    protected ?string $roundingStrategy = null;

    /** Optional copy-from sales channel code */
    protected ?string $copyFromChannelCode = null;

    /** Optional fees/context for profitability */
    protected array $feeContext = [];

    /** Optional target margin for solver */
    protected ?float $targetMargin = null;

    /** Target base price (variant.price) instead of channel-specific pricing */
    protected bool $useBase = false;

    /** Queue of arithmetic adjustments to apply on preview/save */
    protected array $adjustments = [];

    private function __construct() {}

    /**
     * Create a builder for multiple variants.
     */
    public static function forVariants(array $variantIds): self
    {
        $self = new self();
        $self->variantIds = array_values(array_unique(array_map('intval', $variantIds)));
        return $self;
    }

    /**
     * Create a builder for all variants of a product.
     */
    public static function forProduct(int $productId): self
    {
        $self = new self();
        $self->productId = $productId;
        return $self;
    }

    /**
     * Set channel context by marketplace channel + account name
     * (resolves to a sales channel code/id).
     */
    public function channel(string $channel, ?string $account = null): self
    {
        $this->channel = $channel;
        $this->account = $account;

        [$code, $id] = app(ChannelResolver::class)->resolve($channel, $account);
        $this->salesChannelCode = $code;
        $this->salesChannelId = $id;
        return $this;
    }

    /**
     * Set channel context by explicit sales channel code (e.g., shopify_main).
     */
    public function salesChannel(string $code): self
    {
        $this->salesChannelCode = $code;
        $this->salesChannelId = app(ChannelResolver::class)->resolveByCode($code);
        return $this;
    }

    /**
     * Target the variant base price column (product_variants.price) instead of channel.
     */
    public function base(): self
    {
        $this->useBase = true;
        return $this;
    }

    /**
     * Set desired working currency for calculations (optional override).
     */
    public function currency(string $iso3): self
    {
        $this->currency = strtoupper($iso3);
        return $this;
    }

    /**
     * Queue a price update for the (single) variant scope.
     * Use bulkUpdate() for multiple variants.
     */
    public function price(float $price): self
    {
        $this->ensureSingleVariantScope();
        $this->pendingUpdates[$this->variantIds[0]]['price'] = $price;
        return $this;
    }

    /** Queue a discount price update. */
    public function discount(float $discountPrice): self
    {
        $this->ensureSingleVariantScope();
        $this->pendingUpdates[$this->variantIds[0]]['discount_price'] = $discountPrice;
        return $this;
    }

    /** Queue a discount based on percent off the current/target price. */
    public function discountPercent(float $percent): self
    {
        // Queue adjustment: reduce price by percent
        $this->adjustments[] = ['type' => 'discount_percent', 'value' => (float) $percent];
        return $this;
    }

    /** Queue a cost price update. */
    public function cost(float $costPrice): self
    {
        $this->ensureSingleVariantScope();
        $this->pendingUpdates[$this->variantIds[0]]['cost_price'] = $costPrice;
        return $this;
    }

    /** Clear any discount for the scoped variant(s). */
    public function clearDiscount(): self
    {
        $ids = $this->resolveVariantIds();
        foreach ($ids as $id) {
            $this->pendingUpdates[$id]['discount_price'] = null;
        }
        return $this;
    }

    /** Reduce price by a fixed amount across scope. */
    public function discountAmount(float $amount): self
    {
        $this->adjustments[] = ['type' => 'discount_amount', 'value' => (float) $amount];
        return $this;
    }

    /** Increase price by a percentage across scope. */
    public function markupPercent(float $percent): self
    {
        $this->adjustments[] = ['type' => 'markup_percent', 'value' => (float) $percent];
        return $this;
    }

    /** Increase price by a fixed amount across scope. */
    public function markupAmount(float $amount): self
    {
        $this->adjustments[] = ['type' => 'markup_amount', 'value' => (float) $amount];
        return $this;
    }

    /** Generic additive adjustment: positive (markup) or negative (discount). */
    public function adjustAmount(float $delta): self
    {
        $this->adjustments[] = ['type' => 'adjust_amount', 'value' => (float) $delta];
        return $this;
    }

    /** Provide a bulk updates map: [variantId => ['price'=>.., ...], ...] */
    public function bulkUpdate(array $map): self
    {
        $this->bulkMap = $map;
        return $this;
    }

    /** Copy pricing from another channel code (e.g., direct_main). */
    public function copyFrom(string $sourceChannelCode): self
    {
        $this->copyFromChannelCode = $sourceChannelCode;
        return $this;
    }

    /** Choose a rounding strategy to apply before saving. */
    public function round(string $strategy = 'nearest_0_99'): self
    {
        $this->roundingStrategy = $strategy;
        return $this;
    }

    /** Provide fee context for profitability calculations. */
    public function withFees(array $fees): self
    {
        $this->feeContext = $fees;
        return $this;
    }

    /** Target a margin percentage; call solvePrice() to compute price. */
    public function targetMargin(float $percent): self
    {
        $this->targetMargin = $percent;
        return $this;
    }

    /** Compute a price from target margin and queue it for save(). */
    public function solvePrice(): self
    {
        $this->ensureSingleVariantScope();
        $variant = ProductVariant::find($this->variantIds[0]);
        if ($variant) {
            $price = app(TargetMarginSolver::class)->solve(
                cost: (float) ($this->pendingUpdates[$variant->id]['cost_price'] ?? $variant->pricing?->cost_price ?? 0.0),
                targetMargin: (float) ($this->targetMargin ?? 0.0),
                fees: $this->feeContext
            );
            $this->pendingUpdates[$variant->id]['price'] = $price;
        }
        return $this;
    }

    // ---------- Read operations ----------

    /**
     * Get normalized pricing data for the current scope and channel.
     */
    public function get(): array
    {
        $ids = $this->resolveVariantIds();

        // Load base prices
        $variants = ProductVariant::whereIn('id', $ids)->get(['id','price','product_id']);
        $baseMap = $variants->keyBy('id')->map(fn($v) => (float) ($v->price ?? 0.0));

        // Load channel pricing if applicable
        $channelMap = collect();
        $discountMap = collect();
        if (! $this->useBase && $this->salesChannelId) {
            $rows = \App\Models\Pricing::whereIn('product_variant_id', $ids)
                ->where('sales_channel_id', $this->salesChannelId)
                ->get(['product_variant_id','price','discount_price','currency']);
            $channelMap = $rows->keyBy('product_variant_id')->map(fn($r) => (float) ($r->price ?? 0.0));
            $discountMap = $rows->keyBy('product_variant_id')->map(fn($r) => $r->discount_price);
        }

        $data = [];
        foreach ($ids as $id) {
            $base = $baseMap[$id] ?? 0.0;
            $chan = $channelMap[$id] ?? null;
            $effective = $this->useBase ? $base : ($chan ?? $base);
            $data[] = [
                'variant_id' => $id,
                'product_id' => $variants->firstWhere('id',$id)?->product_id,
                'base_price' => $base,
                'channel_price' => $chan,
                'effective_price' => $effective,
                'discount_price' => $discountMap[$id] ?? null,
            ];
        }

        return [
            'scope' => $this->scopeDescriptor(),
            'channel' => $this->useBase ? 'base' : $this->salesChannelCode,
            'currency' => $this->currency,
            'data' => $data,
        ];
    }

    /**
     * Preview pricing changes: returns before/after per variant without saving.
     */
    public function preview(): array
    {
        $ids = $this->resolveVariantIds();
        $changes = [];

        foreach ($ids as $id) {
            $current = $this->currentPriceForVariant($id);
            $proposed = $this->applyQueuedAdjustments($current);

            // If an explicit price override was queued, apply last
            if (isset($this->pendingUpdates[$id]['price'])) {
                $proposed = (float) $this->pendingUpdates[$id]['price'];
            }

            // Apply rounding strategy if configured
            if ($this->roundingStrategy) {
                $proposed = app(Rounding::class)->roundPrice($proposed, $this->roundingStrategy, $this->currency);
            }

            $changes[$id] = [
                'before' => $current,
                'after' => $proposed,
                'channel' => $this->useBase ? 'base' : $this->salesChannelCode,
            ];
        }

        return [
            'success' => true,
            'scope' => $this->scopeDescriptor(),
            'changes' => $changes,
        ];
    }

    /**
     * Get profitability breakdown for the scope using optional fee context.
     */
    public function profits(): array
    {
        return [
            'scope' => $this->scopeDescriptor(),
            'channel' => $this->salesChannelCode,
            'currency' => $this->currency,
            'profit' => app(ProfitCalculator::class)->example(),
        ];
    }

    /**
     * Get aggregate stats (avg/min/max, on-sale counts) for the scope.
     */
    public function stats(): array
    {
        $result = $this->get();
        $prices = collect($result['data'])->pluck('effective_price')->filter(fn($v) => $v !== null)->values();
        $onSale = collect($result['data'])->filter(fn($row) => !is_null($row['discount_price']))->count();

        $stats = [
            'avg_price' => $prices->isNotEmpty() ? round($prices->average(), 2) : null,
            'min_price' => $prices->isNotEmpty() ? $prices->min() : null,
            'max_price' => $prices->isNotEmpty() ? $prices->max() : null,
            'on_sale' => $onSale,
            'count' => $prices->count(),
        ];

        return [
            'scope' => $result['scope'],
            'channel' => $result['channel'],
            'currency' => $result['currency'],
            'stats' => $stats,
        ];
    }

    // ---------- Write operations ----------

    /**
     * Persist queued updates (single or bulk) and apply rounding/copy logic.
     * Delegates to actions and returns a summary array.
     */
    public function save(): array
    {
        $ids = $this->resolveVariantIds();

        // Build effective price updates by applying queued adjustments
        foreach ($ids as $id) {
            $base = $this->currentPriceForVariant($id);
            $price = $this->applyQueuedAdjustments($base);

            if (isset($this->pendingUpdates[$id]['price'])) {
                $price = (float) $this->pendingUpdates[$id]['price'];
            }

            $this->pendingUpdates[$id]['price'] = $price;
        }

        // Apply rounding strategy if requested
        if ($this->roundingStrategy) {
            app(Rounding::class)->apply($this->roundingStrategy, $this->pendingUpdates, $this->currency);
        }

        // Copy-from logic
        if ($this->copyFromChannelCode) {
            app(CopyPricingBetweenChannelsAction::class)
                ->execute($ids, $this->copyFromChannelCode, $this->salesChannelCode);
        }

        // Bulk vs single and target destination (base or channel)
        if (!empty($this->bulkMap)) {
            if ($this->useBase) {
                // Translate bulk map to base updates
                foreach ($this->bulkMap as $variantId => $fields) {
                    app(\App\Services\Pricing\Actions\UpdateVariantBasePriceAction::class)
                        ->update($variantId, $fields['price'] ?? null, $fields['discount_price'] ?? null);
                }
            } else {
                app(BulkUpdatePricingAction::class)->execute($ids, $this->salesChannelId, $this->bulkMap);
            }
        } else {
            foreach ($this->pendingUpdates as $variantId => $fields) {
                if ($this->useBase) {
                    app(\App\Services\Pricing\Actions\UpdateVariantBasePriceAction::class)
                        ->update($variantId, $fields['price'] ?? null, $fields['discount_price'] ?? null);
                } else {
                    app(UpdateVariantPriceAction::class)->execute($variantId, $this->salesChannelId, $fields);
                }
            }
        }

        // Optional profit recalculation
        app(RecalculateProfitAction::class)->execute($ids, $this->salesChannelId);

        return [
            'success' => true,
            'updated' => count($ids),
            'channel' => $this->useBase ? 'base' : $this->salesChannelCode,
        ];
    }

    /**
     * Push the current scope's pricing to the external marketplace for
     * the configured channel. Delegates to integration adapters/jobs.
     */
    public function push(): array
    {
        return app(SyncPricingToMarketplaceAction::class)
            ->execute($this->resolveVariantIds(), $this->salesChannelCode);
    }

    /**
     * Provide a dry-run diff of what would be pushed.
     */
    public function previewPush(): array
    {
        return [
            'success' => true,
            'channel' => $this->salesChannelCode,
            'diff' => [],
        ];
    }

    // ---------- Internals ----------

    /** Resolve variant IDs for the current scope (variants or product). */
    protected function resolveVariantIds(): array
    {
        if ($this->productId) {
            return ProductVariant::where('product_id', $this->productId)->pluck('id')->all();
        }

        return $this->variantIds;
    }

    /** Ensure operations are only applied to a single variant. */
    protected function ensureSingleVariantScope(): void
    {
        $ids = $this->resolveVariantIds();
        if (count($ids) !== 1) {
            throw new \InvalidArgumentException('This operation requires a single variant scope.');
        }
        $this->variantIds = $ids; // normalize lazy product scope resolution
    }

    /** Describe the current scope for debugging/telemetry. */
    protected function scopeDescriptor(): array
    {
        return [
            'product_id' => $this->productId,
            'variant_ids' => $this->variantIds,
            'sales_channel_id' => $this->salesChannelId,
            'sales_channel_code' => $this->salesChannelCode,
            'target' => $this->useBase ? 'base' : 'channel',
        ];
    }

    /** Get current effective price for a variant based on base/channel target. */
    protected function currentPriceForVariant(int $variantId): float
    {
        $variant = ProductVariant::find($variantId);
        if (! $variant) {
            return 0.0;
        }

        if ($this->useBase || ! $this->salesChannelId) {
            return (float) ($variant->price ?? 0.0);
        }

        // Channel-specific price, fallback to base
        $record = \App\Models\Pricing::where('product_variant_id', $variantId)
            ->where('sales_channel_id', $this->salesChannelId)
            ->first();

        return (float) ($record->price ?? $variant->price ?? 0.0);
    }

    /** Apply queued arithmetic adjustments to a starting price. */
    protected function applyQueuedAdjustments(float $basePrice): float
    {
        $price = $basePrice;
        foreach ($this->adjustments as $adj) {
            $v = (float) $adj['value'];
            $price = match ($adj['type']) {
                'discount_percent' => $price * (1 - $v / 100),
                'discount_amount' => max(0, $price - $v),
                'markup_percent' => $price * (1 + $v / 100),
                'markup_amount' => $price + $v,
                'adjust_amount' => $price + $v,
                default => $price,
            };
        }
        return round($price, 2);
    }
}
