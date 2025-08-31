<?php

namespace App\Jobs;

use App\Events\Barcodes\BarcodeImportProgress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessBarcodeImport implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private string $filePath,
        private string $importId,
        private array $columnMapping
    ) {}

    public function handle(): void
    {
        $data = [];
        $processed = 0;
        $now = now();

        $handle = fopen($this->filePath, 'r');
        fgets($handle); // skip header line

        while (($line = fgets($handle)) !== false) {
            $row = str_getcsv($line);
            $barcodeValue = $row[$this->columnMapping['barcode']] ?? null;

            if ($barcodeValue) {
                $data[] = [
                    'barcode' => $barcodeValue,
                    'sku' => $row[$this->columnMapping['sku']] ?? null,
                    'title' => $row[$this->columnMapping['title']] ?? null,
                    'product_variant_id' => null,
                    'is_assigned' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($data) === 1000) {
                    \DB::table('barcodes')->upsert($data, ['barcode'], ['sku', 'title', 'updated_at']);
                    $processed += 1000;

                    // Check heartbeat before continuing
                    $lastHeartbeat = \Cache::get("import_heartbeat_{$this->importId}");
                    if (! $lastHeartbeat || now()->diffInSeconds($lastHeartbeat) > 30) {
                        $this->fail('Import cancelled due to client disconnection');

                        return;
                    }

                    // Broadcast progress event
                    broadcast(new BarcodeImportProgress($this->importId, $processed, 'processing'));

                    $data = [];
                }
            }
        }

        if (! empty($data)) {
            \DB::table('barcodes')->upsert($data, ['barcode'], ['sku', 'title', 'updated_at']);
            $processed += count($data);
        }

        // Broadcast completion event
        broadcast(new BarcodeImportProgress($this->importId, $processed, 'completed'));

        fclose($handle);
    }
}
