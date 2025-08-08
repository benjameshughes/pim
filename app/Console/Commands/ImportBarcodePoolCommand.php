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
                $chunk[] = [
                    'barcode' => trim($row[0]),
                    'barcode_type' => $row[1] ?? 'EAN13',
                    'status' => $row[2] ?? 'available',
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

            $isLegacy = $item['status'] === 'legacy_archive';

            $insertData[] = [
                'barcode' => $item['barcode'],
                'barcode_type' => $item['barcode_type'],
                'status' => $item['status'],
                'is_legacy' => $isLegacy,
                'import_batch_id' => $item['import_batch_id'],
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
}
