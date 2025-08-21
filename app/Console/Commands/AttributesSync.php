<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\AttributeDefinition;
use App\Services\AttributeInheritanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 🔄 ATTRIBUTES SYNC COMMAND
 * 
 * Synchronizes attributes from parent products to child variants
 * with comprehensive reporting and recovery options.
 */
class AttributesSync extends Command
{
    protected $signature = 'attributes:sync 
                          {--product-id=* : Specific product IDs to sync}
                          {--variant-id=* : Specific variant IDs to sync}
                          {--attribute=* : Specific attribute keys to sync}
                          {--force : Force re-sync even if already inherited}
                          {--dry-run : Show what would be synced without making changes}
                          {--batch-size=50 : Number of items to process per batch}
                          {--chunk-size=100 : Database chunk size for querying}';

    protected $description = 'Sync attributes from parent products to child variants using inheritance rules';

    protected AttributeInheritanceService $inheritanceService;
    
    protected array $stats = [
        'products_processed' => 0,
        'variants_processed' => 0,
        'attributes_inherited' => 0,
        'attributes_skipped' => 0,
        'errors' => 0,
    ];

    public function __construct(AttributeInheritanceService $inheritanceService)
    {
        parent::__construct();
        $this->inheritanceService = $inheritanceService;
    }

    public function handle(): int
    {
        $this->info('🔄 Starting Attributes Sync');
        $this->newLine();

        $startTime = microtime(true);
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        try {
            // Validate options
            $this->validateOptions();

            // Get target products and variants
            $targets = $this->getTargets();
            
            if (empty($targets['products']) && empty($targets['variants'])) {
                $this->warn('⚠️ No products or variants found to sync');
                return Command::SUCCESS;
            }

            // Display summary
            $this->displaySyncPlan($targets, $isDryRun);

            // Confirm if not dry run and processing many items
            if (!$isDryRun && !$this->confirmLargeOperation($targets)) {
                $this->info('Operation cancelled by user');
                return Command::SUCCESS;
            }

            // Execute sync
            $this->executSync($targets, $isDryRun);

            // Display final results
            $duration = microtime(true) - $startTime;
            $this->displayResults($duration, $isDryRun);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Sync failed: ' . $e->getMessage());
            $this->line('Trace: ' . $e->getFile() . ':' . $e->getLine());
            return Command::FAILURE;
        }
    }

    /**
     * ✅ VALIDATE OPTIONS
     */
    protected function validateOptions(): void
    {
        $productIds = $this->option('product-id');
        $variantIds = $this->option('variant-id');
        $attributes = $this->option('attribute');

        // Validate product IDs exist
        if (!empty($productIds)) {
            $existingIds = Product::whereIn('id', $productIds)->pluck('id')->toArray();
            $missingIds = array_diff($productIds, $existingIds);
            
            if (!empty($missingIds)) {
                throw new \InvalidArgumentException('Product IDs not found: ' . implode(', ', $missingIds));
            }
        }

        // Validate variant IDs exist
        if (!empty($variantIds)) {
            $existingIds = ProductVariant::whereIn('id', $variantIds)->pluck('id')->toArray();
            $missingIds = array_diff($variantIds, $existingIds);
            
            if (!empty($missingIds)) {
                throw new \InvalidArgumentException('Variant IDs not found: ' . implode(', ', $missingIds));
            }
        }

        // Validate attribute keys exist
        if (!empty($attributes)) {
            $existingKeys = AttributeDefinition::whereIn('key', $attributes)->pluck('key')->toArray();
            $missingKeys = array_diff($attributes, $existingKeys);
            
            if (!empty($missingKeys)) {
                throw new \InvalidArgumentException('Attribute keys not found: ' . implode(', ', $missingKeys));
            }
        }
    }

    /**
     * 🎯 GET TARGETS
     * 
     * Get products and variants to sync based on options
     */
    protected function getTargets(): array
    {
        $productIds = $this->option('product-id');
        $variantIds = $this->option('variant-id');
        
        $targets = [
            'products' => collect(),
            'variants' => collect(),
        ];

        // If specific product IDs provided
        if (!empty($productIds)) {
            $targets['products'] = Product::whereIn('id', $productIds)
                ->with(['variants', 'attributes.attributeDefinition'])
                ->get();
        }
        // If specific variant IDs provided
        elseif (!empty($variantIds)) {
            $targets['variants'] = ProductVariant::whereIn('id', $variantIds)
                ->with(['product.attributes.attributeDefinition', 'attributes.attributeDefinition'])
                ->get();
        }
        // Otherwise, get all products with inheritable attributes
        else {
            $inheritableDefinitions = AttributeDefinition::getInheritableAttributes();
            
            if ($inheritableDefinitions->isEmpty()) {
                $this->warn('⚠️ No inheritable attribute definitions found');
                return $targets;
            }

            $targets['products'] = Product::whereHas('attributes', function ($query) use ($inheritableDefinitions) {
                $query->whereIn('attribute_definition_id', $inheritableDefinitions->pluck('id'));
            })->with(['variants', 'attributes.attributeDefinition'])
            ->get();
        }

        return $targets;
    }

    /**
     * 📋 DISPLAY SYNC PLAN
     */
    protected function displaySyncPlan(array $targets, bool $isDryRun): void
    {
        $this->info('📋 Sync Plan:');
        
        if ($targets['products']->isNotEmpty()) {
            $variantCount = $targets['products']->sum(fn($p) => $p->variants->count());
            $this->line("  • Products: {$targets['products']->count()}");
            $this->line("  • Variants: {$variantCount}");
        }
        
        if ($targets['variants']->isNotEmpty()) {
            $this->line("  • Variants: {$targets['variants']->count()}");
        }

        $attributes = $this->option('attribute');
        if (!empty($attributes)) {
            $this->line('  • Attributes: ' . implode(', ', $attributes));
        } else {
            $this->line('  • Attributes: All inheritable attributes');
        }

        $this->line('  • Force: ' . ($this->option('force') ? 'Yes' : 'No'));
        $this->line('  • Batch size: ' . $this->option('batch-size'));
        $this->newLine();
    }

    /**
     * ❓ CONFIRM LARGE OPERATION
     */
    protected function confirmLargeOperation(array $targets): bool
    {
        $totalVariants = $targets['products']->sum(fn($p) => $p->variants->count()) + $targets['variants']->count();
        
        if ($totalVariants > 100) {
            return $this->confirm("You are about to sync {$totalVariants} variants. Continue?", false);
        }
        
        return true;
    }

    /**
     * 🔄 EXECUTE SYNC
     */
    protected function executSync(array $targets, bool $isDryRun): void
    {
        $batchSize = (int) $this->option('batch-size');
        $force = $this->option('force');
        $specificAttributes = $this->option('attribute');

        // Sync product-based variants
        if ($targets['products']->isNotEmpty()) {
            $this->syncProductVariants($targets['products'], $batchSize, $force, $specificAttributes, $isDryRun);
        }

        // Sync specific variants
        if ($targets['variants']->isNotEmpty()) {
            $this->syncSpecificVariants($targets['variants'], $batchSize, $force, $specificAttributes, $isDryRun);
        }
    }

    /**
     * 🔄 SYNC PRODUCT VARIANTS
     */
    protected function syncProductVariants($products, int $batchSize, bool $force, array $specificAttributes, bool $isDryRun): void
    {
        $progressBar = $this->output->createProgressBar($products->count());
        $progressBar->setFormat('verbose');

        foreach ($products as $product) {
            $this->stats['products_processed']++;
            
            $this->line("Processing Product: {$product->name} (ID: {$product->id})");
            
            foreach ($product->variants->chunk($batchSize) as $variantChunk) {
                foreach ($variantChunk as $variant) {
                    $this->syncVariant($variant, $force, $specificAttributes, $isDryRun);
                }
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);
    }

    /**
     * 🔄 SYNC SPECIFIC VARIANTS
     */
    protected function syncSpecificVariants($variants, int $batchSize, bool $force, array $specificAttributes, bool $isDryRun): void
    {
        $progressBar = $this->output->createProgressBar($variants->count());
        $progressBar->setFormat('verbose');

        foreach ($variants->chunk($batchSize) as $variantChunk) {
            foreach ($variantChunk as $variant) {
                $this->syncVariant($variant, $force, $specificAttributes, $isDryRun);
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->newLine(2);
    }

    /**
     * 🔄 SYNC VARIANT
     */
    protected function syncVariant(ProductVariant $variant, bool $force, array $specificAttributes, bool $isDryRun): void
    {
        $this->stats['variants_processed']++;
        
        try {
            $options = [
                'force' => $force,
                'dry_run' => $isDryRun,
            ];

            if (!empty($specificAttributes)) {
                $options['attributes'] = $specificAttributes;
            }

            $result = $this->inheritanceService->inheritAttributesForVariant($variant, $options);
            
            $this->stats['attributes_inherited'] += count($result['inherited']);
            $this->stats['attributes_skipped'] += count($result['skipped']);

            if (!empty($result['errors'])) {
                $this->stats['errors'] += count($result['errors']);
                
                foreach ($result['errors'] as $attributeKey => $error) {
                    $this->warn("  ⚠️ Variant {$variant->sku}: Failed to inherit '{$attributeKey}': {$error}");
                }
            }

            if ($this->output->isVerbose()) {
                if (!empty($result['inherited'])) {
                    $this->line("  ✅ Variant {$variant->sku}: Inherited " . implode(', ', $result['inherited']));
                }
                if (!empty($result['skipped'])) {
                    $this->line("  ⏭️ Variant {$variant->sku}: Skipped " . count($result['skipped']) . ' attributes');
                }
            }

        } catch (\Exception $e) {
            $this->stats['errors']++;
            $this->error("  ❌ Variant {$variant->sku}: " . $e->getMessage());
        }
    }

    /**
     * 📊 DISPLAY RESULTS
     */
    protected function displayResults(float $duration, bool $isDryRun): void
    {
        $this->newLine();
        $this->info('📊 Sync Results:');
        $this->newLine();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Products Processed', number_format($this->stats['products_processed'])],
                ['Variants Processed', number_format($this->stats['variants_processed'])],
                ['Attributes Inherited', number_format($this->stats['attributes_inherited'])],
                ['Attributes Skipped', number_format($this->stats['attributes_skipped'])],
                ['Errors', number_format($this->stats['errors'])],
                ['Duration', round($duration, 2) . 's'],
            ]
        );

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN - No actual changes were made');
        } else {
            if ($this->stats['errors'] === 0) {
                $this->info('✅ Sync completed successfully!');
            } else {
                $this->warn("⚠️ Sync completed with {$this->stats['errors']} errors");
            }
        }

        $this->newLine();
    }

    /**
     * 📈 GET INHERITANCE STATISTICS
     */
    public function getInheritanceStats(): array
    {
        $this->info('📈 Calculating inheritance statistics...');

        $stats = [
            'total_products' => Product::count(),
            'products_with_attributes' => Product::whereHas('attributes')->count(),
            'total_variants' => ProductVariant::count(),
            'variants_with_attributes' => ProductVariant::whereHas('attributes')->count(),
            'inheritable_definitions' => AttributeDefinition::getInheritableAttributes()->count(),
            'inheritance_opportunities' => 0,
            'current_inheritance_rate' => 0,
        ];

        // Calculate inheritance opportunities
        $inheritableDefinitions = AttributeDefinition::getInheritableAttributes();
        $stats['inheritance_opportunities'] = DB::table('products')
            ->join('product_variants', 'products.id', '=', 'product_variants.product_id')
            ->join('product_attributes', 'products.id', '=', 'product_attributes.product_id')
            ->whereIn('product_attributes.attribute_definition_id', $inheritableDefinitions->pluck('id'))
            ->count();

        // Calculate current inheritance rate
        $currentInherited = DB::table('variant_attributes')
            ->where('is_inherited', true)
            ->count();

        $stats['current_inheritance_rate'] = $stats['inheritance_opportunities'] > 0 
            ? round(($currentInherited / $stats['inheritance_opportunities']) * 100, 1)
            : 0;

        return $stats;
    }
}