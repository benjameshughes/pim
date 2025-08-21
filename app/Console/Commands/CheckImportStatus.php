<?php

namespace App\Console\Commands;

use App\Services\Mirakl\ProductCsvUploader;
use Illuminate\Console\Command;

/**
 * 📊 CHECK IMPORT STATUS
 *
 * Quick utility to check the status of a specific import
 */
class CheckImportStatus extends Command
{
    protected $signature = 'check:import-status {import_id} {marketplace=freemans}';

    protected $description = 'Check the status of a specific Mirakl import';

    public function handle(): int
    {
        $importId = (int) $this->argument('import_id');
        $marketplace = $this->argument('marketplace');

        echo "📊 Checking Import Status\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "Import ID: {$importId}\n";
        echo "Marketplace: {$marketplace}\n\n";

        try {
            $uploader = ProductCsvUploader::forMarketplace($marketplace);

            // Get import status
            $statusResult = $uploader->checkImportStatus($importId);

            echo '🔍 Import Status: '.($statusResult['status'] ?? 'unknown')."\n";

            if (isset($statusResult['import_data'])) {
                $importData = $statusResult['import_data'];
                echo '📅 Created: '.($importData['date_created'] ?? 'N/A')."\n";
                echo '📊 Status: '.($importData['import_status'] ?? 'N/A')."\n";
                echo '❌ Has Errors: '.(($importData['has_error_report'] ?? false) ? 'Yes' : 'No')."\n";

                if ($importData['has_error_report'] ?? false) {
                    echo "\n📋 Downloading error report...\n";
                    $errorReport = $uploader->downloadErrorReport($importId);
                    if ($errorReport['success']) {
                        echo "Error details:\n";
                        echo substr($errorReport['error_report'], 0, 1000)."...\n";
                    }
                }

                // Try to download new product report for successful imports
                if (($importData['import_status'] ?? '') === 'COMPLETE') {
                    echo "\n📊 Attempting to download new product report...\n";
                    $newProductReport = $uploader->downloadNewProductReport($importId);
                    if ($newProductReport['success']) {
                        echo "✅ New product report downloaded!\n";
                        echo 'Report size: '.strlen($newProductReport['new_product_report'])." bytes\n";
                        echo "First 1000 characters:\n";
                        echo substr($newProductReport['new_product_report'], 0, 1000)."...\n";
                    } else {
                        echo '❌ No new product report available: '.($newProductReport['error'] ?? 'Unknown error')."\n";
                    }
                }
            }

            // Get recent import history for context
            echo "\n📋 Recent Import History:\n";
            $history = $uploader->getImportHistory(20);

            if ($history['success']) {
                foreach ($history['imports'] as $import) {
                    $marker = $import['import_id'] == $importId ? '👉' : '  ';
                    echo "{$marker} Import {$import['import_id']}: {$import['status']} ({$import['date_created']})\n";
                }
            }

        } catch (\Exception $e) {
            echo "❌ Error checking import status: {$e->getMessage()}\n";

            return 1;
        }

        return 0;
    }
}
