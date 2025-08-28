<?php

namespace App\Jobs;

use App\Actions\Import\SimpleImportAction;
use App\Events\Products\ProductImportProgress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessProductImport implements ShouldQueue
{
    use Queueable;
    
    public $timeout = 0; // No timeout for long-running imports

    public function __construct(
        private string $filePath,
        private string $importId,
        private array $mappings
    ) {}

    public function handle(): void
    {
        Log::info('🚀 Starting queued product import', [
            'importId' => $this->importId,
            'filePath' => $this->filePath,
            'mappings' => $this->mappings
        ]);

        try {
            $action = new SimpleImportAction();
            
            $result = $action->execute([
                'file' => $this->filePath,
                'mappings' => $this->mappings,
                'importId' => $this->importId,
            ]);

            Log::info('✅ Queued product import completed successfully', [
                'importId' => $this->importId,
                'result' => $result
            ]);

            // Clean up the temporary file
            if (file_exists($this->filePath)) {
                unlink($this->filePath);
            }

        } catch (\Exception $e) {
            Log::error('💥 Queued product import failed', [
                'importId' => $this->importId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Broadcast error
            ProductImportProgress::dispatch(
                $this->importId,
                0,
                0,
                'error',
                'Import failed',
                $e->getMessage()
            );

            // Clean up the temporary file
            if (file_exists($this->filePath)) {
                unlink($this->filePath);
            }

            throw $e;
        }
    }
}