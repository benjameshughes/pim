<?php

namespace App\Console\Commands;

use App\Actions\Barcodes\CheckBarcodeAvailabilityAction;
use App\Jobs\AssignBarcodesJob;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;

class AssignBarcodesCommand extends Command
{
    protected $signature = 'barcodes:assign 
                            {type=scan : Assignment type: scan, product, variant, bulk}
                            {--ids= : Comma-separated IDs for product/variant/bulk assignment}
                            {--barcode-type=EAN13 : Type of barcode to assign}
                            {--skip-existing : Skip items that already have barcodes}
                            {--limit=100 : Limit for scan operations}
                            {--queue : Run in queue instead of synchronously}
                            {--check : Just check availability without assigning}';

    protected $description = 'Assign barcodes to products/variants using the GS1 barcode pool';

    public function handle()
    {
        $type = $this->argument('type');
        $barcodeType = $this->option('barcode-type');
        
        // Check availability first
        $checkAction = new CheckBarcodeAvailabilityAction();
        $availability = $checkAction->execute($barcodeType);
        
        $this->displayAvailability($availability);
        
        if ($this->option('check')) {
            return self::SUCCESS;
        }

        // Warn if low availability
        if ($availability['availability_status'] === 'critical') {
            if (!$this->confirm('Critical low barcode supply. Continue anyway?')) {
                $this->info('Assignment cancelled.');
                return self::SUCCESS;
            }
        }

        return match ($type) {
            'scan' => $this->handleScan(),
            'product' => $this->handleProduct(),
            'variant' => $this->handleVariant(),
            'bulk' => $this->handleBulk(),
            default => $this->handleUnknownType($type)
        };
    }

    private function displayAvailability($availability): void
    {
        $stats = $availability['statistics'];
        
        $this->info("🏊‍♂️ Barcode Pool Status ({$stats['barcode_type']})");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        $this->line("Ready for Assignment: <info>{$stats['ready_for_assignment']}</info>");
        $this->line("Total Available: <info>{$stats['available_total']}</info>");
        $this->line("Currently Assigned: <comment>{$stats['assigned_total']}</comment>");
        $this->line("Legacy Archived: <comment>{$stats['legacy_archive_total']}</comment>");
        
        if ($stats['next_available']) {
            $next = $stats['next_available'];
            $this->line("Next Available: <info>{$next['barcode']}</info> (Quality: {$next['quality_score']}/10)");
        }
        
        $this->line($availability['message']);
        $this->newLine();
    }

    private function handleScan(): int
    {
        $limit = (int) $this->option('limit');
        
        $this->info("🔍 Scanning for unassigned variants (limit: {$limit})");
        
        $job = AssignBarcodesJob::scanUnassigned($this->option('barcode-type'), $limit);
        
        if ($this->option('queue')) {
            $job->dispatch();
            $this->info('✅ Scan job queued successfully');
        } else {
            $job->handle();
            $this->info('✅ Scan completed');
        }
        
        return self::SUCCESS;
    }

    private function handleProduct(): int
    {
        $ids = $this->parseIds();
        
        if (empty($ids)) {
            $this->error('Product IDs required. Use --ids=1,2,3');
            return self::FAILURE;
        }
        
        $products = Product::whereIn('id', $ids)->get();
        
        if ($products->isEmpty()) {
            $this->error('No products found with those IDs');
            return self::FAILURE;
        }
        
        $this->info("📦 Assigning barcodes to variants of {$products->count()} products");
        
        foreach ($products as $product) {
            $job = AssignBarcodesJob::assignToProduct(
                $product, 
                $this->option('barcode-type'), 
                $this->option('skip-existing')
            );
            
            if ($this->option('queue')) {
                $job->dispatch();
                $this->line("✅ Queued assignment for product: {$product->name}");
            } else {
                $job->handle();
                $this->line("✅ Processed product: {$product->name}");
            }
        }
        
        return self::SUCCESS;
    }

    private function handleVariant(): int
    {
        $ids = $this->parseIds();
        
        if (empty($ids)) {
            $this->error('Variant IDs required. Use --ids=1,2,3');
            return self::FAILURE;
        }
        
        $variants = ProductVariant::whereIn('id', $ids)->get();
        
        if ($variants->isEmpty()) {
            $this->error('No variants found with those IDs');
            return self::FAILURE;
        }
        
        $this->info("🎨 Assigning barcodes to {$variants->count()} variants");
        
        foreach ($variants as $variant) {
            $job = AssignBarcodesJob::assignToVariant($variant, $this->option('barcode-type'));
            
            if ($this->option('queue')) {
                $job->dispatch();
                $this->line("✅ Queued assignment for variant: {$variant->sku}");
            } else {
                $job->handle();
                $this->line("✅ Processed variant: {$variant->sku}");
            }
        }
        
        return self::SUCCESS;
    }

    private function handleBulk(): int
    {
        $ids = $this->parseIds();
        
        if (empty($ids)) {
            $this->error('Variant IDs required for bulk assignment. Use --ids=1,2,3');
            return self::FAILURE;
        }
        
        $variants = ProductVariant::whereIn('id', $ids)->get();
        
        if ($variants->isEmpty()) {
            $this->error('No variants found with those IDs');
            return self::FAILURE;
        }
        
        $this->info("🚀 Bulk assigning barcodes to {$variants->count()} variants");
        
        $job = AssignBarcodesJob::assignBulkVariants(
            $variants,
            $this->option('barcode-type'),
            $this->option('skip-existing')
        );
        
        if ($this->option('queue')) {
            $job->dispatch();
            $this->info('✅ Bulk assignment job queued successfully');
        } else {
            $job->handle();
            $this->info('✅ Bulk assignment completed');
        }
        
        return self::SUCCESS;
    }

    private function handleUnknownType(string $type): int
    {
        $this->error("Unknown assignment type: {$type}");
        $this->line('Available types: scan, product, variant, bulk');
        return self::FAILURE;
    }

    private function parseIds(): array
    {
        $idsOption = $this->option('ids');
        
        if (!$idsOption) {
            return [];
        }
        
        return array_map('intval', explode(',', $idsOption));
    }
}