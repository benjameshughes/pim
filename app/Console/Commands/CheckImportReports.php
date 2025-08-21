<?php

namespace App\Console\Commands;

use App\Services\Mirakl\ProductCsvUploader;
use Illuminate\Console\Command;

/**
 * 📋 CHECK IMPORT REPORTS
 *
 * Downloads and displays both error reports and new product reports for imports
 */
class CheckImportReports extends Command
{
    protected $signature = 'check:import-reports {import_id} {marketplace=freemans}';

    protected $description = 'Check both error and new product reports for an import';

    public function handle(): int
    {
        $importId = (int) $this->argument('import_id');
        $marketplace = $this->argument('marketplace');

        echo "📋 Checking Import Reports\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "Import ID: {$importId}\n";
        echo "Marketplace: {$marketplace}\n\n";

        try {
            $uploader = ProductCsvUploader::forMarketplace($marketplace);

            // First check the import status
            $statusResult = $uploader->checkImportStatus($importId);

            echo '🔍 Import Status: '.($statusResult['status'] ?? 'unknown')."\n";

            if (isset($statusResult['import_data'])) {
                $importData = $statusResult['import_data'];
                echo '📅 Created: '.($importData['date_created'] ?? 'N/A')."\n";
                echo '📊 Status: '.($importData['import_status'] ?? 'N/A')."\n";
                echo '❌ Has Errors: '.(($importData['has_error_report'] ?? false) ? 'Yes' : 'No')."\n\n";
            }

            // Download new product report
            echo "📊 New Product Report:\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $newProductReport = $uploader->downloadNewProductReport($importId);

            if ($newProductReport['success']) {
                $report = $newProductReport['new_product_report'];
                if (! empty(trim($report))) {
                    echo $report."\n";
                } else {
                    echo "✅ Report is empty (no new products created)\n";
                }
            } else {
                echo '⚠️  Could not download new product report: '.($newProductReport['error'] ?? 'Unknown error')."\n";
            }

            echo "\n";

            // Download error report if available
            if (isset($statusResult['import_data']['has_error_report']) && $statusResult['import_data']['has_error_report']) {
                echo "❌ Error Report:\n";
                echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                $errorReport = $uploader->downloadErrorReport($importId);

                if ($errorReport['success']) {
                    echo $errorReport['error_report']."\n";
                } else {
                    echo '⚠️  Could not download error report: '.($errorReport['error'] ?? 'Unknown error')."\n";
                }
            } else {
                echo "✅ No error report (import successful)\n";
            }

        } catch (\Exception $e) {
            echo "❌ Error checking import reports: {$e->getMessage()}\n";

            return 1;
        }

        return 0;
    }
}
