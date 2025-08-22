<?php

namespace App\Jobs;

use App\Actions\Barcodes\AssignBarcodeToVariantAction;
use App\Actions\Barcodes\BulkAssignBarcodesAction;
use App\Actions\Barcodes\CheckBarcodeAvailabilityAction;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * 🏊‍♂️ ASSIGN BARCODES JOB
 *
 * Queue job for barcode assignment with:
 * - Manual triggering for specific variants/products
 * - Bulk assignment for imports
 * - Auto-assignment on product/variant creation
 * - Pool availability checking
 */
class AssignBarcodesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $assignmentType,
        public array $targets = [],
        public string $barcodeType = 'EAN13',
        public bool $skipExisting = true,
        public ?array $options = null
    ) {
        // Set queue based on assignment type
        $this->onQueue($assignmentType === 'bulk' ? 'barcodes-bulk' : 'barcodes');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting barcode assignment job", [
            'type' => $this->assignmentType,
            'targets' => count($this->targets),
            'barcode_type' => $this->barcodeType,
        ]);

        try {
            match ($this->assignmentType) {
                'single_variant' => $this->handleSingleVariant(),
                'product_variants' => $this->handleProductVariants(),
                'bulk_variants' => $this->handleBulkVariants(),
                'unassigned_scan' => $this->handleUnassignedScan(),
                default => throw new \InvalidArgumentException("Unknown assignment type: {$this->assignmentType}")
            };

        } catch (\Exception $e) {
            Log::error("Barcode assignment job failed", [
                'type' => $this->assignmentType,
                'error' => $e->getMessage(),
                'targets' => $this->targets,
            ]);

            throw $e;
        }
    }

    /**
     * Assign barcode to a single variant
     */
    private function handleSingleVariant(): void
    {
        $variantId = $this->targets[0] ?? null;
        
        if (!$variantId) {
            throw new \InvalidArgumentException("No variant ID provided for single variant assignment");
        }

        $variant = ProductVariant::find($variantId);
        
        if (!$variant) {
            throw new \InvalidArgumentException("Variant not found: {$variantId}");
        }

        $action = new AssignBarcodeToVariantAction();
        $result = $action->execute($variant, $this->barcodeType);

        Log::info("Single variant barcode assignment completed", [
            'variant_id' => $variantId,
            'assigned' => $result['assigned'],
            'message' => $result['message'],
        ]);
    }

    /**
     * Assign barcodes to all variants of specific products
     */
    private function handleProductVariants(): void
    {
        $productIds = $this->targets;
        
        if (empty($productIds)) {
            throw new \InvalidArgumentException("No product IDs provided for product variants assignment");
        }

        $variants = ProductVariant::whereIn('product_id', $productIds)->get();

        if ($variants->isEmpty()) {
            Log::info("No variants found for products", ['product_ids' => $productIds]);
            return;
        }

        $action = new BulkAssignBarcodesAction();
        $result = $action->execute($variants, $this->barcodeType, $this->skipExisting);

        Log::info("Product variants barcode assignment completed", [
            'product_ids' => $productIds,
            'statistics' => $result['statistics'],
        ]);
    }

    /**
     * Bulk assign barcodes to specific variants
     */
    private function handleBulkVariants(): void
    {
        $variantIds = $this->targets;
        
        if (empty($variantIds)) {
            throw new \InvalidArgumentException("No variant IDs provided for bulk assignment");
        }

        $variants = ProductVariant::whereIn('id', $variantIds)->get();

        if ($variants->isEmpty()) {
            Log::info("No variants found for bulk assignment", ['variant_ids' => $variantIds]);
            return;
        }

        $action = new BulkAssignBarcodesAction();
        $result = $action->execute($variants, $this->barcodeType, $this->skipExisting);

        Log::info("Bulk variants barcode assignment completed", [
            'variant_ids' => $variantIds,
            'statistics' => $result['statistics'],
        ]);
    }

    /**
     * Scan for and assign barcodes to unassigned variants
     */
    private function handleUnassignedScan(): void
    {
        Log::info("Starting unassigned variants scan");

        // Check pool availability first
        $availabilityAction = new CheckBarcodeAvailabilityAction();
        $availability = $availabilityAction->execute($this->barcodeType);

        if ($availability['statistics']['ready_for_assignment'] < 10) {
            Log::warning("Low barcode availability, skipping unassigned scan", [
                'available' => $availability['statistics']['ready_for_assignment'],
            ]);
            return;
        }

        // Find variants without barcodes
        $variantsWithoutBarcodes = ProductVariant::whereDoesntHave('barcodes', function ($query) {
            $query->where('type', strtolower($this->barcodeType));
        })->limit($this->options['limit'] ?? 100)->get();

        if ($variantsWithoutBarcodes->isEmpty()) {
            Log::info("No unassigned variants found");
            return;
        }

        Log::info("Found unassigned variants", ['count' => $variantsWithoutBarcodes->count()]);

        $action = new BulkAssignBarcodesAction();
        $result = $action->execute($variantsWithoutBarcodes, $this->barcodeType, true);

        Log::info("Unassigned scan barcode assignment completed", [
            'statistics' => $result['statistics'],
        ]);
    }

    /**
     * Static factory methods for common assignment scenarios
     */
    public static function assignToVariant(ProductVariant $variant, string $type = 'EAN13'): self
    {
        return new self('single_variant', [$variant->id], $type);
    }

    public static function assignToProduct(Product $product, string $type = 'EAN13', bool $skipExisting = true): self
    {
        return new self('product_variants', [$product->id], $type, $skipExisting);
    }

    public static function assignBulkVariants(Collection $variants, string $type = 'EAN13', bool $skipExisting = true): self
    {
        return new self('bulk_variants', $variants->pluck('id')->toArray(), $type, $skipExisting);
    }

    public static function scanUnassigned(string $type = 'EAN13', int $limit = 100): self
    {
        return new self('unassigned_scan', [], $type, true, ['limit' => $limit]);
    }

    /**
     * Failed job handling
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Barcode assignment job failed permanently", [
            'type' => $this->assignmentType,
            'targets' => $this->targets,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}