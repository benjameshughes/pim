<?php

namespace App\Console\Commands;

use App\Models\BarcodePool;
use Illuminate\Console\Command;

class ImportBarcodePoolCommand extends Command
{
    protected $signature = 'barcode:import 
                            {file : Path to the enhanced CSV file}
                            {--clear : Clear existing pool before import}
                            {--chunk=1000 : Number of records to process per chunk}';

    protected $description = 'Import enhanced barcode CSV with memory-efficient processing';

    public function handle()
    {
        $filePath = $this->argument('file');
        $chunkSize = (int) $this->option('chunk');
        $clearExisting = $this->option('clear');

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return self::FAILURE;
        }

        $this->info('Starting barcode pool import...');
        $this->info("File: {$filePath}");
        $this->info("Chunk size: {$chunkSize}");

        if ($clearExisting) {
            $this->info('Clearing existing barcode pool...');
            BarcodePool::truncate();
            $this->info('Pool cleared.');
        }

        $startTime = microtime(true);
        $totalProcessed = 0;
        $legacyCount = 0;
        $availableCount = 0;
        $skippedCount = 0;

        // Open file handle for memory-efficient reading
        $handle = fopen($filePath, 'r');

        if (! $handle) {
            $this->error("Could not open file: {$filePath}");

            return self::FAILURE;
        }

        // Skip header row
        fgetcsv($handle);

        $chunk = [];
        $lineNumber = 1;

        $this->info('Processing file in chunks...');
        $progressBar = $this->output->createProgressBar();

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;

            if (! empty($row[0])) {
                $barcode = trim($row[0]);
                $isLegacy = $lineNumber < 40000; // Mark rows before 40,000 as legacy
                $qualityScore = $this->assessBarcodeQuality($row, $lineNumber);

                $chunk[] = [
                    'barcode' => $barcode,
                    'barcode_type' => $row[1] ?? 'EAN13',
                    'status' => $this->determineStatus($row, $isLegacy),
                    'is_legacy' => $isLegacy,
                    'row_number' => $lineNumber,
                    'quality_score' => $qualityScore,
                    'legacy_sku' => $row[3] ?? '',
                    'legacy_status' => $row[4] ?? '',
                    'legacy_product_name' => $row[5] ?? '',
                    'legacy_brand' => $row[6] ?? '',
                    'legacy_updated' => $row[7] ?? '',
                    'import_batch_id' => $row[8] ?? 'cmd_import_'.date('Y_m_d_H_i_s'),
                    'notes' => $row[9] ?? '',
                ];
            }

            // Process chunk when it reaches the specified size
            if (count($chunk) >= $chunkSize) {
                $result = $this->processChunk($chunk);
                $totalProcessed += $result['processed'];
                $legacyCount += $result['legacy'];
                $availableCount += $result['available'];
                $skippedCount += $result['skipped'];

                $progressBar->advance($result['processed']);
                $chunk = [];
            }
        }

        // Process remaining records
        if (! empty($chunk)) {
            $result = $this->processChunk($chunk);
            $totalProcessed += $result['processed'];
            $legacyCount += $result['legacy'];
            $availableCount += $result['available'];
            $skippedCount += $result['skipped'];

            $progressBar->advance($result['processed']);
        }

        fclose($handle);
        $progressBar->finish();

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        $this->newLine(2);
        $this->info('Import completed successfully!');
        $this->info("Duration: {$duration} seconds");
        $this->info("Total processed: {$totalProcessed}");
        $this->info("Legacy archived: {$legacyCount}");
        $this->info("Available: {$availableCount}");
        $this->info("Skipped (duplicates): {$skippedCount}");

        return self::SUCCESS;
    }

    private function processChunk(array $chunk): array
    {
        $processed = 0;
        $legacy = 0;
        $available = 0;
        $skipped = 0;

        // Extract barcodes for duplicate checking
        $barcodes = array_column($chunk, 'barcode');

        // Check for existing barcodes in one query
        $existing = BarcodePool::whereIn('barcode', $barcodes)
            ->pluck('barcode')
            ->toArray();

        $insertData = [];

        foreach ($chunk as $item) {
            // Skip if barcode already exists
            if (in_array($item['barcode'], $existing)) {
                $skipped++;

                continue;
            }

            $isLegacy = $item['is_legacy'];

            $insertData[] = [
                'barcode' => $item['barcode'],
                'barcode_type' => $item['barcode_type'],
                'status' => $item['status'],
                'is_legacy' => $isLegacy,
                'row_number' => $item['row_number'],
                'quality_score' => $item['quality_score'],
                'import_batch_id' => $item['import_batch_id'],
                'legacy_sku' => $item['legacy_sku'] ?: null,
                'legacy_status' => $item['legacy_status'] ?: null,
                'legacy_product_name' => $item['legacy_product_name'] ?: null,
                'legacy_brand' => $item['legacy_brand'] ?: null,
                'legacy_updated' => $item['legacy_updated'] ?: null,
                'legacy_notes' => $this->buildLegacyNotes($item),
                'notes' => $item['notes'] ?: null,
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

        // Bulk insert with ignore duplicates
        if (! empty($insertData)) {
            try {
                BarcodePool::insert($insertData);
            } catch (\Exception $e) {
                // If there's still a duplicate error, process individually
                foreach ($insertData as $record) {
                    try {
                        BarcodePool::create($record);
                    } catch (\Exception $individualError) {
                        $skipped++;
                        $processed--;
                        if ($record['is_legacy']) {
                            $legacy--;
                        } else {
                            $available--;
                        }
                    }
                }
            }
        }

        return [
            'processed' => $processed,
            'legacy' => $legacy,
            'available' => $available,
            'skipped' => $skipped,
        ];
    }

    private function buildLegacyNotes(array $item): ?string
    {
        $notes = [];

        if (! empty($item['legacy_sku'])) {
            $notes[] = "Legacy SKU: {$item['legacy_sku']}";
        }

        if (! empty($item['legacy_product_name'])) {
            $notes[] = "Product: {$item['legacy_product_name']}";
        }

        if (! empty($item['legacy_brand'])) {
            $notes[] = "Brand: {$item['legacy_brand']}";
        }

        if (! empty($item['legacy_updated'])) {
            $notes[] = "Last Updated: {$item['legacy_updated']}";
        }

        if (! empty($item['legacy_status'])) {
            $notes[] = "Original Status: {$item['legacy_status']}";
        }

        return ! empty($notes) ? implode(' | ', $notes) : null;
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
}
