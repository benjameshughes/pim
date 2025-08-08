<?php

namespace App\Console\Commands;

use App\Services\AsyncExcelProcessingService;
use Illuminate\Console\Command;

class CleanupFileProcessingData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'file-processing:cleanup {--days=1 : Number of days old to consider for cleanup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old file processing progress records and temporary files';

    /**
     * Execute the console command.
     */
    public function handle(AsyncExcelProcessingService $service)
    {
        $days = (int) $this->option('days');

        $this->info("Cleaning up file processing data older than {$days} days...");

        $service->cleanup($days);

        $this->info('Cleanup completed successfully.');
    }
}
