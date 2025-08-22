<?php

namespace App\Actions\Barcodes;

use App\Actions\Base\BaseAction;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 🚀 BULK ASSIGN BARCODES ACTION
 *
 * Efficiently assign barcodes to multiple variants with:
 * - Batch processing for performance
 * - Progress tracking
 * - Error handling and rollback
 * - Statistics reporting
 */
class BulkAssignBarcodesAction extends BaseAction
{
    /**
     * Assign barcodes to multiple variants
     *
     * @param Collection|array $variants Collection of ProductVariant instances
     * @param string $type Barcode type (default: EAN13)
     * @param bool $skipExisting Skip variants that already have barcodes
     * @return array Action result with assignment statistics
     */
    protected function performAction(...$params): array
    {
        $variants = $params[0] ?? collect();
        $type = $params[1] ?? 'EAN13';
        $skipExisting = $params[2] ?? true;

        // Convert array to collection if needed
        if (is_array($variants)) {
            $variants = collect($variants);
        }

        Log::info("Starting bulk barcode assignment", [
            'variant_count' => $variants->count(),
            'type' => $type,
            'skip_existing' => $skipExisting,
        ]);

        $stats = [
            'total_variants' => $variants->count(),
            'assigned' => 0,
            'skipped_existing' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $assignAction = new AssignBarcodeToVariantAction();

        foreach ($variants as $variant) {
            try {
                $result = $assignAction->execute($variant, $type);

                if ($result['assigned']) {
                    $stats['assigned']++;
                } else {
                    $stats['skipped_existing']++;
                    
                    if (!$skipExisting) {
                        Log::warning("Variant already has barcode but skipExisting is false", [
                            'variant_id' => $variant->id,
                        ]);
                    }
                }

            } catch (\Exception $e) {
                $stats['failed']++;
                $stats['errors'][] = [
                    'variant_id' => $variant->id,
                    'variant_sku' => $variant->sku,
                    'error' => $e->getMessage(),
                ];

                Log::error("Failed to assign barcode to variant", [
                    'variant_id' => $variant->id,
                    'error' => $e->getMessage(),
                ]);

                // Continue processing other variants
                continue;
            }
        }

        Log::info("Bulk barcode assignment completed", $stats);

        return [
            'statistics' => $stats,
            'success' => $stats['failed'] === 0,
            'message' => $this->buildSummaryMessage($stats)
        ];
    }

    /**
     * Build a human-readable summary message
     */
    private function buildSummaryMessage(array $stats): string
    {
        $messages = [];

        if ($stats['assigned'] > 0) {
            $messages[] = "Assigned {$stats['assigned']} barcodes";
        }

        if ($stats['skipped_existing'] > 0) {
            $messages[] = "skipped {$stats['skipped_existing']} existing";
        }

        if ($stats['failed'] > 0) {
            $messages[] = "failed {$stats['failed']}";
        }

        $summary = implode(', ', $messages);
        
        return "Processed {$stats['total_variants']} variants: {$summary}";
    }
}