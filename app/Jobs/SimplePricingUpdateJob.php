<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\SyncAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 💰 SIMPLE PRICING UPDATE JOB
 */
class SimplePricingUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Product $product;
    public SyncAccount $syncAccount;

    public function __construct(Product $product, SyncAccount $syncAccount)
    {
        $this->product = $product;
        $this->syncAccount = $syncAccount;
        $this->onQueue("pricing-{$syncAccount->channel}");
    }

    public function handle(): void
    {
        Log::info('🎯 Starting FRESH pricing job', [
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
        ]);

        // Get variants and their pricing
        $variants = $this->product->variants()->get();
        
        Log::info('Found variants for pricing', [
            'count' => $variants->count(),
            'variant_ids' => $variants->pluck('id')->toArray(),
        ]);

        foreach ($variants as $variant) {
            Log::info('Processing variant', [
                'variant_id' => $variant->id,
                'sku' => $variant->sku,
                'color' => $variant->color,
                'size' => $variant->size,
            ]);
        }

        Log::info('✅ Fresh pricing job completed successfully');
    }
}