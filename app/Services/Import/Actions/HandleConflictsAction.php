<?php

namespace App\Services\Import\Actions;

use App\Services\Import\Conflicts\ConflictResolver;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class HandleConflictsAction extends ImportAction
{
    private ConflictResolver $conflictResolver;
    private int $maxRetries;
    private bool $haltOnUnresolvable;

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        
        $this->conflictResolver = ConflictResolver::create($config['conflict_resolution'] ?? []);
        $this->maxRetries = $config['max_retries'] ?? 3;
        $this->haltOnUnresolvable = $config['halt_on_unresolvable'] ?? false;
    }

    public function execute(ActionContext $context): ActionResult
    {
        $this->logAction('Starting conflict-aware data processing', [
            'row_number' => $context->getRowNumber(),
            'max_retries' => $this->maxRetries,
        ]);

        $attempts = 0;
        $lastException = null;
        $resolutionHistory = [];

        while ($attempts < $this->maxRetries) {
            try {
                // Attempt to process the data
                $result = $this->processData($context);
                
                if ($attempts > 0) {
                    $this->logAction('Succeeded after conflict resolution', [
                        'row_number' => $context->getRowNumber(),
                        'attempts' => $attempts + 1,
                        'resolutions_applied' => count($resolutionHistory),
                    ]);
                }

                return $result->withData([
                    'conflict_resolution_attempts' => $attempts,
                    'resolutions_applied' => $resolutionHistory,
                    'conflict_resolver_stats' => $this->conflictResolver->getStatistics(),
                ]);

            } catch (QueryException $e) {
                $lastException = $e;
                $attempts++;

                $this->logAction('Database conflict detected', [
                    'row_number' => $context->getRowNumber(),
                    'attempt' => $attempts,
                    'error_code' => $e->getCode(),
                    'error_message' => $e->getMessage(),
                ]);

                // Attempt to resolve the conflict
                $resolution = $this->conflictResolver->resolve($e, $context->getData());
                $resolutionHistory[] = $resolution->toArray();

                if (!$resolution->isResolved()) {
                    $this->logAction('Conflict resolution failed', [
                        'row_number' => $context->getRowNumber(),
                        'resolution' => $resolution->toArray(),
                    ]);

                    if ($this->haltOnUnresolvable) {
                        return ActionResult::failed(
                            'Unresolvable conflict: ' . $resolution->getReason(),
                            [
                                'attempts' => $attempts,
                                'resolution_history' => $resolutionHistory,
                                'original_exception' => $e->getMessage(),
                            ]
                        );
                    }

                    continue; // Try again without modifications
                }

                // Apply resolution
                $this->applyResolution($resolution, $context);

                $this->logAction('Applied conflict resolution', [
                    'row_number' => $context->getRowNumber(),
                    'strategy' => $resolution->getStrategy(),
                    'action' => $resolution->getAction(),
                ]);

                // If resolution says to skip or fail, do so
                if ($resolution->shouldSkip()) {
                    return ActionResult::success([
                        'action_taken' => 'skipped',
                        'reason' => $resolution->getReason(),
                        'resolution_history' => $resolutionHistory,
                    ]);
                }

                if ($resolution->shouldFail()) {
                    return ActionResult::failed(
                        $resolution->getReason(),
                        ['resolution_history' => $resolutionHistory]
                    );
                }

                // Continue loop for retry
            }
        }

        // All retries exhausted
        $this->logAction('All conflict resolution attempts exhausted', [
            'row_number' => $context->getRowNumber(),
            'max_attempts' => $this->maxRetries,
            'final_error' => $lastException?->getMessage(),
        ]);

        return ActionResult::failed(
            'Failed after ' . $this->maxRetries . ' conflict resolution attempts: ' . 
            ($lastException?->getMessage() ?? 'Unknown error'),
            [
                'exhausted_retries' => true,
                'attempts' => $attempts,
                'resolution_history' => $resolutionHistory,
            ]
        );
    }

    private function processData(ActionContext $context): ActionResult
    {
        $data = $context->getData();
        $importMode = $context->getConfig('import_mode', 'create_or_update');

        // Get or create product
        $product = $context->get('product');
        if (!$product) {
            return ActionResult::failed('No product available in context for conflict handling');
        }

        // Create or update variant with conflict-prone operations
        $variant = $this->handleVariant($product, $data, $importMode);
        if (!$variant) {
            return ActionResult::failed('Variant creation/update failed');
        }

        // Handle additional conflict-prone operations
        $this->handleBarcodes($variant, $data);
        $this->handlePricing($variant, $data);

        return ActionResult::success([
            'variant_id' => $variant->id,
            'variant_sku' => $variant->sku,
            'created' => $variant->wasRecentlyCreated,
        ])->withContextUpdate('variant', $variant);
    }

    private function handleVariant(Product $product, array $data, string $importMode): ?ProductVariant
    {
        $variantSku = $data['variant_sku'] ?? null;
        if (!$variantSku) {
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
                return ProductVariant::create($variantData);

            case 'update_existing':
                $existing = ProductVariant::where('sku', $variantSku)->firstOrFail();
                $existing->update($variantData);
                return $existing;

            case 'create_or_update':
            default:
                return ProductVariant::updateOrCreate(
                    ['sku' => $variantSku],
                    $variantData
                );
        }
    }

    private function handleBarcodes(ProductVariant $variant, array $data): void
    {
        if (!empty($data['barcode'])) {
            $barcodeType = $data['barcode_type'] ?? $this->detectBarcodeType($data['barcode']);
            
            // This might throw QueryException for duplicate barcodes
            $variant->barcodes()->create([
                'barcode' => $data['barcode'],
                'type' => $barcodeType,
            ]);
        }
    }

    private function handlePricing(ProductVariant $variant, array $data): void
    {
        if (!empty($data['retail_price'])) {
            // This might throw QueryException for unique constraints
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

    private function applyResolution($resolution, ActionContext $context): void
    {
        if ($resolution->hasModifiedData()) {
            // Update context with modified data
            $context->mergeData($resolution->getModifiedData());
            
            $this->logAction('Applied data modifications', [
                'row_number' => $context->getRowNumber(),
                'modified_fields' => array_keys($resolution->getModifiedData()),
            ]);
        }

        // Handle special resolution metadata
        $metadata = $resolution->getMetadata();
        
        if (isset($metadata['reassign_barcode'])) {
            $this->handleBarcodeReassignment($metadata, $context);
        }

        if (isset($metadata['update_existing_variant'])) {
            $this->handleExistingVariantUpdate($metadata, $context);
        }
    }

    private function handleBarcodeReassignment(array $metadata, ActionContext $context): void
    {
        // Implementation would handle reassigning barcodes between variants
        Log::info('Barcode reassignment requested', [
            'barcode' => $metadata['barcode'],
            'from_variant' => $metadata['from_variant_id'],
            'to_variant_sku' => $metadata['to_variant_sku'],
        ]);
    }

    private function handleExistingVariantUpdate(array $metadata, ActionContext $context): void
    {
        // Implementation would handle updating existing variants
        Log::info('Existing variant update requested', [
            'variant_id' => $metadata['variant_id'],
            'update_fields' => $metadata['fields'] ?? [],
        ]);
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
}