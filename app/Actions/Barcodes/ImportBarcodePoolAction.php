<?php

namespace App\Actions\Barcodes;

use App\Actions\Base\BaseAction;
use App\Models\BarcodePool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 📥 IMPORT BARCODE POOL ACTION
 *
 * Import barcodes from CSV data with:
 * - Memory-efficient chunked processing
 * - Quality assessment and scoring
 * - Legacy data preservation
 * - Duplicate handling
 */
class ImportBarcodePoolAction extends BaseAction
{
    /**
     * Import barcodes from CSV data array
     *
     * @param array $csvData Array of CSV rows
     * @param bool $clearExisting Clear existing pool before import
     * @param int $chunkSize Number of records to process per chunk
     * @return array Action result with import statistics
     */
    protected function performAction(...$params): array
    {
        $csvData = $params[0] ?? [];
        $clearExisting = $params[1] ?? false;
        $chunkSize = $params[2] ?? 1000;

        Log::info("Starting barcode pool import", [
            'total_rows' => count($csvData),
            'clear_existing' => $clearExisting,
            'chunk_size' => $chunkSize,
        ]);

        $stats = [
            'total_processed' => 0,
            'legacy_count' => 0,
            'available_count' => 0,
            'skipped_count' => 0,
            'import_batch_id' => 'action_import_' . date('Y_m_d_H_i_s'),
        ];

        return DB::transaction(function () use ($csvData, $clearExisting, $chunkSize, &$stats) {
            if ($clearExisting) {
                Log::info('Clearing existing barcode pool');
                BarcodePool::truncate();
            }

            $chunks = array_chunk($csvData, $chunkSize);
            
            foreach ($chunks as $chunk) {
                $result = $this->processChunk($chunk, $stats['import_batch_id']);
                
                $stats['total_processed'] += $result['processed'];
                $stats['legacy_count'] += $result['legacy'];
                $stats['available_count'] += $result['available'];
                $stats['skipped_count'] += $result['skipped'];
            }

            Log::info("Barcode pool import completed", $stats);

            return [
                'statistics' => $stats,
                'success' => true,
                'message' => $this->buildImportMessage($stats)
            ];
        });
    }

    /**
     * Process a chunk of CSV data
     */
    private function processChunk(array $chunk, string $batchId): array
    {
        $processed = 0;
        $legacy = 0;
        $available = 0;
        $skipped = 0;

        // Extract barcodes for duplicate checking
        $barcodes = array_column($chunk, 0); // Assuming barcode is first column

        // Check for existing barcodes in one query
        $existing = BarcodePool::whereIn('barcode', $barcodes)
            ->pluck('barcode')
            ->toArray();

        $insertData = [];

        foreach ($chunk as $lineNumber => $row) {
            if (empty($row[0])) {
                continue;
            }

            $barcode = trim($row[0]);
            
            // Skip if barcode already exists
            if (in_array($barcode, $existing)) {
                $skipped++;
                continue;
            }

            $isLegacy = $lineNumber < 40000; // Mark rows before 40,000 as legacy
            $qualityScore = $this->assessBarcodeQuality($row, $lineNumber);

            $insertData[] = [
                'barcode' => $barcode,
                'barcode_type' => $row[1] ?? 'EAN13',
                'status' => $this->determineStatus($row, $isLegacy),
                'is_legacy' => $isLegacy,
                'row_number' => $lineNumber,
                'quality_score' => $qualityScore,
                'import_batch_id' => $batchId,
                'legacy_sku' => $row[3] ?? null,
                'legacy_status' => $row[4] ?? null,
                'legacy_product_name' => $row[5] ?? null,
                'legacy_brand' => $row[6] ?? null,
                'legacy_updated' => $row[7] ?? null,
                'legacy_notes' => $this->buildLegacyNotes($row),
                'notes' => $row[9] ?? null,
                'assigned_to_variant_id' => null,
                'assigned_at' => null,
                'date_first_used' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($isLegacy) {
                $legacy++;
            } else {
                $available++;
            }

            $processed++;
        }

        // Bulk insert
        if (!empty($insertData)) {
            BarcodePool::insert($insertData);
        }

        return [
            'processed' => $processed,
            'legacy' => $legacy,
            'available' => $available,
            'skipped' => $skipped,
        ];
    }

    /**
     * Assess the quality of a barcode based on various factors
     */
    private function assessBarcodeQuality(array $row, int $lineNumber): int
    {
        $score = 10; // Start with perfect score

        // Reduce score for legacy data (before row 40,000)
        if ($lineNumber < 40000) {
            $score -= 3;
        }

        // Check barcode format/length (basic validation)
        $barcode = trim($row[0]);
        if (strlen($barcode) < 12) {
            $score -= 2;
        }

        // Check if legacy fields suggest this was problematic data
        $legacyStatus = $row[4] ?? '';
        if (stripos($legacyStatus, 'error') !== false ||
            stripos($legacyStatus, 'invalid') !== false ||
            stripos($legacyStatus, 'problem') !== false) {
            $score -= 3;
        }

        // Check if product name is missing (suggests incomplete data)
        if (empty(trim($row[5] ?? ''))) {
            $score -= 1;
        }

        // Check if brand is missing
        if (empty(trim($row[6] ?? ''))) {
            $score -= 1;
        }

        // Ensure score stays within bounds
        return max(1, min(10, $score));
    }

    /**
     * Determine the status of a barcode based on legacy data and quality
     */
    private function determineStatus(array $row, bool $isLegacy): string
    {
        // If explicitly marked as legacy in CSV, archive it
        $csvStatus = $row[2] ?? 'available';
        if ($csvStatus === 'legacy_archive') {
            return 'legacy_archive';
        }

        // Mark old rows as legacy archive
        if ($isLegacy) {
            return 'legacy_archive';
        }

        // Check for problematic indicators in legacy data
        $legacyStatus = $row[4] ?? '';
        if (stripos($legacyStatus, 'error') !== false ||
            stripos($legacyStatus, 'invalid') !== false ||
            stripos($legacyStatus, 'problem') !== false) {
            return 'problematic';
        }

        // Default to available for good quality, non-legacy data
        return 'available';
    }

    /**
     * Build legacy notes from row data
     */
    private function buildLegacyNotes(array $row): ?string
    {
        $notes = [];

        if (!empty($row[3])) {
            $notes[] = "Legacy SKU: {$row[3]}";
        }

        if (!empty($row[5])) {
            $notes[] = "Product: {$row[5]}";
        }

        if (!empty($row[6])) {
            $notes[] = "Brand: {$row[6]}";
        }

        if (!empty($row[7])) {
            $notes[] = "Last Updated: {$row[7]}";
        }

        if (!empty($row[4])) {
            $notes[] = "Original Status: {$row[4]}";
        }

        return !empty($notes) ? implode(' | ', $notes) : null;
    }

    /**
     * Build import summary message
     */
    private function buildImportMessage(array $stats): string
    {
        return "Imported {$stats['total_processed']} barcodes: " .
               "{$stats['available_count']} available, " .
               "{$stats['legacy_count']} legacy archived, " .
               "{$stats['skipped_count']} skipped (duplicates)";
    }
}