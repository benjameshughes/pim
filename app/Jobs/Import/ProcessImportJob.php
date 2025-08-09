<?php

namespace App\Jobs\Import;

use App\Models\ImportSession;
use App\Services\Import\Extraction\MadeToMeasureExtractor;
use App\Services\Import\Extraction\SmartDimensionExtractor;
use App\Services\Import\SkuPatternAnalyzer;
use App\Services\Import\Actions\PipelineBuilder;
use App\Services\Import\Actions\ActionContext;
use App\Services\Import\Actions\ActionPipeline;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Services\Import\Performance\ImportPerformanceBuilder;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes - shorter timeout
    public $tries = 2;
    public $maxExceptions = 3;

    private array $statistics = [
        'processed_rows' => 0,
        'successful_rows' => 0,
        'failed_rows' => 0,
        'skipped_rows' => 0,
        'products_created' => 0,
        'products_updated' => 0,
        'variants_created' => 0,
        'variants_updated' => 0,
        'errors' => [],
        'warnings' => [],
    ];

    private array $extractors = [];
    private ?SkuPatternAnalyzer $skuAnalyzer = null;
    private ?ActionPipeline $actionsPipeline = null;

    public function __construct(
        private ImportSession $session
    ) {}

    public function handle(): void
    {
        Log::info('Starting import processing', [
            'session_id' => $this->session->session_id,
            'total_rows' => $this->session->total_rows,
        ]);

        try {
            $this->initializeExtractors();
            $this->initializeActionsPipeline();
            $this->initializeProcessing();

            $this->session->markAsStarted();
            $this->session->updateProgress(
                stage: 'processing',
                operation: 'Starting import processing',
                percentage: 5
            );

            // Process file in chunks (each chunk has its own transaction)
            $this->processFileInChunks();

            // Compile final results
            $this->compileFinalResults();

            $this->session->markAsCompleted();
            $this->session->updateProgress(
                stage: 'processing',
                operation: 'Import completed successfully',
                percentage: 100
            );

            // Dispatch finalization job
            FinalizeImportJob::dispatch($this->session)
                ->onQueue('imports')
                ->delay(now()->addSeconds(2));

            Log::info('Import processing completed successfully', [
                'session_id' => $this->session->session_id,
                'statistics' => $this->statistics,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Import processing failed', [
                'session_id' => $this->session->session_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'statistics' => $this->statistics,
            ]);

            $this->session->markAsFailed('Import processing failed: ' . $e->getMessage());
        }
    }

    private function initializeExtractors(): void
    {
        $configuration = $this->session->configuration;

        // Initialize Made-to-Measure extractor
        if ($configuration['detect_made_to_measure'] ?? false) {
            $this->extractors['mtm'] = app(MadeToMeasureExtractor::class);
            Log::info('MTM extractor initialized');
        }

        // Initialize dimension extractor
        if ($configuration['dimensions_digits_only'] ?? false) {
            $this->extractors['dimensions'] = app(SmartDimensionExtractor::class);
            Log::info('Smart dimension extractor initialized');
        }

        // Initialize SKU analyzer
        if ($configuration['group_by_sku'] ?? false) {
            $this->skuAnalyzer = app(SkuPatternAnalyzer::class);
            Log::info('SKU pattern analyzer initialized');
        }
    }

    private function initializeActionsPipeline(): void
    {
        $configuration = $this->session->configuration;
        
        // Build the actions pipeline based on configuration
        $this->actionsPipeline = PipelineBuilder::importPipeline([
            'import_mode' => $configuration['import_mode'] ?? 'create_or_update',
            'extract_mtm' => $configuration['detect_made_to_measure'] ?? false,
            'extract_dimensions' => $configuration['dimensions_digits_only'] ?? false,
            'use_sku_grouping' => $configuration['group_by_sku'] ?? false,
            'validate_rows' => $configuration['validate_rows'] ?? true,
            'validation_optional' => true, // Don't fail import on validation errors
            'attribute_extraction_optional' => true, // Don't fail on extraction errors
            'timeout_seconds' => 60.0, // Per-row timeout
            'max_retries' => 1, // Retry database errors once
            'log_successful' => false, // Only log failures to reduce noise
            'log_failed' => true,
            'log_context' => false,
        ])->build();

        Log::info('Actions pipeline initialized', [
            'session_id' => $this->session->session_id,
            'configuration' => array_keys($configuration),
        ]);
    }

    private function initializeProcessing(): void
    {
        $this->statistics = [
            'processed_rows' => 0,
            'successful_rows' => 0,
            'failed_rows' => 0,
            'skipped_rows' => 0,
            'products_created' => 0,
            'products_updated' => 0,
            'variants_created' => 0,
            'variants_updated' => 0,
            'errors' => [],
            'warnings' => [],
            'start_time' => now(),
        ];
    }

    private function processFileInChunks(): void
    {
        $filePath = Storage::path($this->session->file_path);
        
        Log::info('🚀 Initializing HIGH-PERFORMANCE import processing', [
            'session_id' => $this->session->session_id,
            'file_name' => $this->session->original_filename,
            'file_size' => number_format($this->session->file_size / 1024, 2) . ' KB',
            'total_rows' => $this->session->total_rows,
        ]);

        // 🔥 ULTRA PERFORMANCE BUILDER - ACTIVATED!
        $performanceBuilder = ImportPerformanceBuilder::forSession($this->session)
            ->maximize() // Enable ALL optimizations
            ->withDetailedLogging();

        // 🚀 Process file with MAXIMUM PERFORMANCE
        foreach ($performanceBuilder->processFile($filePath, function($chunk) {
            return $this->processChunk($chunk, $this->session->column_mapping ?? []);
        }) as $result) {
            // Each chunk result is yielded here
            $this->updateProgress();
        }

        // 📊 Log performance statistics
        $stats = $performanceBuilder->getPerformanceStats();
        Log::info('🎯 HIGH-PERFORMANCE import processing completed', [
            'session_id' => $this->session->session_id,
            'performance_stats' => $stats,
        ]);
    }


    private function processChunk(array $chunk, array $columnMapping): void
    {
        // Process each chunk in its own transaction for better performance
        DB::transaction(function () use ($chunk, $columnMapping) {
            foreach ($chunk as $rowInfo) {
                try {
                    $this->processRow($rowInfo, $columnMapping);
                } catch (\Exception $e) {
                    $this->handleRowError($rowInfo['row_number'], $e);
                }
            }
        });
    }

    private function processRow(array $rowInfo, array $columnMapping): void
    {
        $rowNumber = $rowInfo['row_number'];
        $rowData = $rowInfo['data'];
        $headers = $rowInfo['headers'];

        // Map row data to fields
        $mappedData = $this->mapRowData($rowData, $headers, $columnMapping);

        // Skip rows without essential data
        if (empty($mappedData['product_name']) && empty($mappedData['variant_sku'])) {
            $this->statistics['skipped_rows']++;
            return;
        }

        // Process the row through the Actions pipeline
        $result = $this->executeActionsPipeline($mappedData, $rowNumber);

        // Update statistics based on result
        $this->updateStatistics($result);
        $this->statistics['processed_rows']++;
    }

    private function mapRowData(array $rowData, array $headers, array $columnMapping): array
    {
        $mapped = [];

        foreach ($columnMapping as $columnIndex => $fieldName) {
            if (!empty($fieldName) && isset($rowData[$columnIndex])) {
                $mapped[$fieldName] = trim($rowData[$columnIndex]);
            }
        }

        return $mapped;
    }

    private function executeActionsPipeline(array $data, int $rowNumber): array
    {
        if (!$this->actionsPipeline) {
            throw new \RuntimeException('Actions pipeline not initialized');
        }

        try {
            // Create action context
            $context = new ActionContext($data, $rowNumber, $this->session->configuration);

            // Execute the actions pipeline
            $result = $this->actionsPipeline->execute($context);

            if ($result->isSuccess()) {
                // Extract product information from context
                $product = $context->get('product');
                
                if ($product) {
                    // Handle variant creation and additional data using existing methods
                    $variant = $this->handleVariantCreation($product, $context->getData(), $this->session->configuration['import_mode'] ?? 'create_or_update');
                    
                    if ($variant) {
                        $this->handleAdditionalData($variant, $context->getData());
                        
                        return [
                            'action' => 'success',
                            'product' => $product,
                            'variant' => $variant,
                            'created_product' => $product->wasRecentlyCreated,
                            'created_variant' => $variant->wasRecentlyCreated,
                            'pipeline_data' => $result->getData(),
                        ];
                    }
                }
                
                return ['action' => 'skip', 'reason' => 'Product resolved but variant creation failed'];
            }

            return [
                'action' => 'error',
                'error' => $result->getError(),
                'row_number' => $rowNumber,
                'pipeline_data' => $result->getData(),
            ];

        } catch (\Exception $e) {
            Log::error('Actions pipeline execution failed', [
                'row_number' => $rowNumber,
                'error' => $e->getMessage(),
                'data_sample' => array_slice($data, 0, 3),
            ]);

            return [
                'action' => 'error',
                'error' => $e->getMessage(),
                'row_number' => $rowNumber,
            ];
        }
    }

    private function handleProductCreation(array $data, string $importMode): ?Product
    {
        $productName = $data['enhanced_product_name'] ?? $data['product_name'] ?? '';
        
        if (empty($productName)) {
            return null;
        }

        $productData = [
            'name' => $productName,
            'description' => $data['description'] ?? '',
            'status' => $data['status'] ?? 'active',
        ];

        // Extract parent SKU from variant SKU if using SKU-based grouping
        if ($this->skuAnalyzer && !empty($data['variant_sku'])) {
            $parentSku = $this->skuAnalyzer->extractParentSku($data['variant_sku']);
            if ($parentSku) {
                $productData['parent_sku'] = $parentSku;
            }
        }

        switch ($importMode) {
            case 'create_only':
                $existing = Product::where('name', $productName)->first();
                if ($existing) {
                    return $existing; // Return existing, don't create
                }
                return Product::create(array_merge($productData, [
                    'slug' => $this->generateUniqueSlug($productName)
                ]));

            case 'update_existing':
                $existing = Product::where('name', $productName)->first();
                if (!$existing) {
                    return null; // Don't create, return null
                }
                $existing->update($productData);
                return $existing;

            case 'create_or_update':
            default:
                return Product::updateOrCreate(
                    ['name' => $productName],
                    array_merge($productData, [
                        'slug' => $this->generateUniqueSlug($productName)
                    ])
                );
        }
    }

    private function handleVariantCreation(Product $product, array $data, string $importMode): ?ProductVariant
    {
        $variantSku = $data['variant_sku'] ?? '';
        
        if (empty($variantSku)) {
            return null;
        }

        $variantData = [
            'product_id' => $product->id,
            'sku' => $variantSku,
            'stock_level' => $data['stock_level'] ?? 0,
            'package_length' => $data['package_length'] ?? null,
            'package_width' => $data['package_width'] ?? null,
            'package_height' => $data['package_height'] ?? null,
            'package_weight' => $data['package_weight'] ?? null,
        ];

        switch ($importMode) {
            case 'create_only':
                $existing = ProductVariant::where('sku', $variantSku)->first();
                if ($existing) {
                    return $existing; // Return existing, don't create
                }
                $variant = ProductVariant::create($variantData);
                break;

            case 'update_existing':
                $existing = ProductVariant::where('sku', $variantSku)->first();
                if (!$existing) {
                    return null; // Don't create, return null
                }
                $existing->update($variantData);
                $variant = $existing;
                break;

            case 'create_or_update':
            default:
                $variant = ProductVariant::updateOrCreate(
                    ['sku' => $variantSku],
                    $variantData
                );
                break;
        }

        // Set variant attributes using the new attribute system
        $this->setVariantAttributes($variant, $data);

        return $variant;
    }

    private function setVariantAttributes(ProductVariant $variant, array $data): void
    {
        // Set color if available
        $color = $data['variant_color'] ?? null;
        if ($color) {
            $variant->setVariantAttributeValue('color', $color, 'string', 'core');
        }

        // Set dimensions if extracted
        if (isset($data['extracted_width'])) {
            $variant->setVariantAttributeValue('width', $data['extracted_width'], 'number', 'core');
        }

        if (isset($data['extracted_drop'])) {
            $variant->setVariantAttributeValue('drop', $data['extracted_drop'], 'number', 'core');
        }

        // Set size if available
        $size = $data['extracted_size'] ?? $data['variant_size'] ?? null;
        if ($size) {
            $variant->setVariantAttributeValue('size', $size, 'string', 'core');
        }

        // Set MTM status if detected
        if (isset($data['made_to_measure'])) {
            $variant->setVariantAttributeValue('made_to_measure', $data['made_to_measure'], 'boolean', 'core');
        }
    }

    private function handleAdditionalData(ProductVariant $variant, array $data): void
    {
        // Handle barcodes
        if (!empty($data['barcode'])) {
            $existingBarcode = $variant->barcodes()->where('barcode', $data['barcode'])->first();
            if (!$existingBarcode) {
                $barcodeType = $data['barcode_type'] ?? $this->detectBarcodeType($data['barcode']);
                $variant->barcodes()->create([
                    'barcode' => $data['barcode'],
                    'type' => $barcodeType,
                ]);
            }
        }

        // Handle pricing
        if (!empty($data['retail_price'])) {
            $variant->pricing()->updateOrCreate(
                ['marketplace' => 'website'],
                [
                    'retail_price' => $data['retail_price'],
                    'cost_price' => $data['cost_price'] ?? null,
                    'vat_percentage' => 20.00,
                    'vat_inclusive' => true,
                ]
            );
        }
    }

    private function detectBarcodeType(string $barcode): string
    {
        $length = strlen(preg_replace('/[^0-9]/', '', $barcode));
        
        return match ($length) {
            8 => 'EAN8',
            12 => 'UPC',
            13 => 'EAN13',
            14 => 'GTIN14',
            default => 'UNKNOWN',
        };
    }

    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = \Illuminate\Support\Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (Product::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function updateStatistics(array $result): void
    {
        switch ($result['action']) {
            case 'success':
                $this->statistics['successful_rows']++;
                
                if (isset($result['created_product']) && $result['created_product']) {
                    $this->statistics['products_created']++;
                } else {
                    $this->statistics['products_updated']++;
                }

                if (isset($result['created_variant']) && $result['created_variant']) {
                    $this->statistics['variants_created']++;
                } else {
                    $this->statistics['variants_updated']++;
                }
                break;

            case 'skip':
                $this->statistics['skipped_rows']++;
                break;

            case 'error':
                $this->statistics['failed_rows']++;
                $this->statistics['errors'][] = [
                    'row' => $result['row_number'] ?? 'unknown',
                    'message' => $result['error'] ?? 'Unknown error',
                    'timestamp' => now()->toISOString(),
                ];
                break;
        }
    }

    private function updateProgress(): void
    {
        $totalProcessed = $this->statistics['processed_rows'];
        $totalRows = $this->session->total_rows;
        
        if ($totalRows > 0) {
            $percentage = min(95, (int) (($totalProcessed / $totalRows) * 100));
            
            $this->session->updateProgress(
                stage: 'processing',
                operation: "Processed {$totalProcessed} of {$totalRows} rows",
                percentage: $percentage
            );
        }

        // Update row counts
        $this->session->update([
            'processed_rows' => $totalProcessed,
            'successful_rows' => $this->statistics['successful_rows'],
            'failed_rows' => $this->statistics['failed_rows'],
            'skipped_rows' => $this->statistics['skipped_rows'],
        ]);
    }

    private function handleRowError(int $rowNumber, \Exception $e): void
    {
        $this->statistics['failed_rows']++;
        $this->statistics['processed_rows']++;
        
        $this->statistics['errors'][] = [
            'row' => $rowNumber,
            'message' => $e->getMessage(),
            'timestamp' => now()->toISOString(),
        ];

        Log::warning('Row processing failed', [
            'session_id' => $this->session->session_id,
            'row_number' => $rowNumber,
            'error' => $e->getMessage(),
        ]);
    }

    private function compileFinalResults(): void
    {
        $finalResults = [
            'processing_completed_at' => now()->toISOString(),
            'processing_duration_seconds' => $this->statistics['start_time']->diffInSeconds(now()),
            'statistics' => $this->statistics,
            'performance_metrics' => [
                'rows_per_second' => $this->calculateProcessingSpeed(),
                'memory_peak_mb' => memory_get_peak_usage(true) / 1024 / 1024,
            ],
        ];

        $this->session->update([
            'final_results' => $finalResults,
            'errors' => $this->statistics['errors'],
            'warnings' => $this->statistics['warnings'],
        ]);
    }

    private function calculateProcessingSpeed(): float
    {
        $duration = $this->statistics['start_time']->diffInSeconds(now());
        return $duration > 0 ? round($this->statistics['processed_rows'] / $duration, 2) : 0;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessImportJob failed', [
            'session_id' => $this->session->session_id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'statistics' => $this->statistics,
        ]);

        $this->session->markAsFailed('Import processing failed: ' . $exception->getMessage());
    }
}