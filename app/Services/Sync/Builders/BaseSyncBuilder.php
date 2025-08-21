<?php

namespace App\Services\Sync\Builders;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncAccount;
use App\Models\SyncLog;
use App\Models\SyncStatus;
use Illuminate\Support\Collection;

/**
 * 🏗️ BASE SYNC BUILDER
 *
 * Foundation for all sync builders with common fluent methods.
 * Provides the building blocks for beautiful sync APIs.
 */
abstract class BaseSyncBuilder
{
    protected ?Product $product = null;

    protected ?ProductVariant $variant = null;

    protected ?SyncAccount $account = null;

    protected Collection $products;

    protected array $selectedColors = [];

    protected array $metadata = [];

    protected bool $dryRun = false;

    protected bool $force = false;

    public function __construct()
    {
        $this->products = collect();
    }

    /**
     * 📦 Set single product for sync
     */
    public function product(Product $product): self
    {
        $this->product = $product;

        return $this;
    }

    /**
     * 📦 Set multiple products for sync
     */
    public function products(Collection|array $products): self
    {
        $this->products = collect($products);

        return $this;
    }

    /**
     * 🎨 Set specific variant for sync
     */
    public function variant(ProductVariant $variant): self
    {
        $this->variant = $variant;

        return $this;
    }

    /**
     * 🏢 Set specific account for sync
     */
    public function account(string $accountName): self
    {
        $channelName = $this->getChannelName();
        $this->account = SyncAccount::findByChannelAndName($channelName, $accountName);

        if (! $this->account) {
            throw new \InvalidArgumentException("Sync account '{$accountName}' not found for channel '{$channelName}'");
        }

        return $this;
    }

    /**
     * 🎨 Set specific colors for sync (Shopify color separation)
     */
    public function colors(array $colors): self
    {
        $this->selectedColors = $colors;

        return $this;
    }

    /**
     * 🎨 Set single color for sync
     */
    public function color(string $color): self
    {
        $this->selectedColors = [$color];

        return $this;
    }

    /**
     * 📋 Set metadata for sync operation
     */
    public function with(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    /**
     * 🧪 Enable dry run mode (preview only)
     */
    public function dryRun(bool $enabled = true): self
    {
        $this->dryRun = $enabled;

        return $this;
    }

    /**
     * 💪 Enable force mode (ignore warnings)
     */
    public function force(bool $enabled = true): self
    {
        $this->force = $enabled;

        return $this;
    }

    /**
     * 🔍 Get or create sync account for channel
     */
    protected function getSyncAccount(): SyncAccount
    {
        if ($this->account) {
            return $this->account;
        }

        $channelName = $this->getChannelName();
        $account = SyncAccount::getDefaultForChannel($channelName);

        if (! $account) {
            throw new \RuntimeException("No active sync account found for channel '{$channelName}'");
        }

        return $account;
    }

    /**
     * 📝 Create sync log entry
     */
    protected function createSyncLog(string $action, ?Product $product = null): SyncLog
    {
        return SyncLog::createEntry(
            $this->getSyncAccount(),
            $action,
            $product ?: $this->product
        );
    }

    /**
     * 📊 Find or create sync status
     */
    protected function findOrCreateSyncStatus(Product $product, ?string $color = null): SyncStatus
    {
        return SyncStatus::findOrCreateFor(
            $product,
            $this->getSyncAccount(),
            $this->variant,
            $color
        );
    }

    /**
     * ✅ Validate builder state
     */
    protected function validate(): void
    {
        if (! $this->product && $this->products->isEmpty()) {
            throw new \InvalidArgumentException('No product(s) specified for sync operation');
        }

        $account = $this->getSyncAccount();
        if (! $account->isConfigured()) {
            throw new \RuntimeException("Sync account '{$account->name}' is not properly configured");
        }
    }

    /**
     * 🎯 Get channel name (must be implemented by subclasses)
     */
    abstract protected function getChannelName(): string;

    /**
     * 🚀 Push operation (must be implemented by subclasses)
     */
    abstract public function push(): array;

    /**
     * 🔽 Pull operation (must be implemented by subclasses)
     */
    abstract public function pull(): array;
}
