<?php

namespace App\Console\Commands;

use App\Models\ProductImage;
use App\Services\ImageProcessingService;
use Illuminate\Console\Command;

class ImageStatsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'images:stats {--refresh : Refresh the display every 2 seconds}';

    /**
     * The console command description.
     */
    protected $description = 'Display image processing statistics';

    /**
     * Execute the console command.
     */
    public function handle(ImageProcessingService $processingService): int
    {
        if ($this->option('refresh')) {
            $this->watchStats($processingService);
        } else {
            $this->showStats($processingService);
        }

        return self::SUCCESS;
    }

    private function watchStats(ImageProcessingService $processingService): void
    {
        $this->info('📊 Image Processing Statistics (Press Ctrl+C to exit)');
        $this->newLine();

        while (true) {
            system('clear'); // Clear screen on Unix-like systems
            $this->info('📊 Image Processing Statistics (Refreshing every 2 seconds)');
            $this->info('Press Ctrl+C to exit');
            $this->newLine();
            
            $this->showStats($processingService);
            
            sleep(2);
        }
    }

    private function showStats(ImageProcessingService $processingService): void
    {
        $stats = $processingService->getProcessingStats();

        // Main statistics table
        $this->table(
            ['Status', 'Count', 'Percentage'],
            [
                [
                    'Total Images', 
                    $stats['total'], 
                    '100%'
                ],
                [
                    '⏳ Pending', 
                    $stats['pending'], 
                    $stats['total'] > 0 ? round(($stats['pending'] / $stats['total']) * 100, 1) . '%' : '0%'
                ],
                [
                    '🔄 Processing', 
                    $stats['processing'], 
                    $stats['total'] > 0 ? round(($stats['processing'] / $stats['total']) * 100, 1) . '%' : '0%'
                ],
                [
                    '✅ Completed', 
                    $stats['completed'], 
                    $stats['total'] > 0 ? round(($stats['completed'] / $stats['total']) * 100, 1) . '%' : '0%'
                ],
                [
                    '❌ Failed', 
                    $stats['failed'], 
                    $stats['total'] > 0 ? round(($stats['failed'] / $stats['total']) * 100, 1) . '%' : '0%'
                ],
            ]
        );

        // Storage distribution
        $storageStats = ProductImage::selectRaw('storage_disk, processing_status, count(*) as count')
            ->groupBy('storage_disk', 'processing_status')
            ->get();

        if ($storageStats->isNotEmpty()) {
            $this->newLine();
            $this->info('💾 Storage Distribution:');
            
            $storageTable = [];
            foreach ($storageStats as $stat) {
                $storageTable[] = [
                    $stat->storage_disk ?: 'public',
                    $stat->processing_status,
                    $stat->count
                ];
            }
            
            $this->table(['Storage Disk', 'Status', 'Count'], $storageTable);
        }

        // Recent failures
        $recentFailures = ProductImage::where('processing_status', ProductImage::PROCESSING_FAILED)
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        if ($recentFailures->isNotEmpty()) {
            $this->newLine();
            $this->error('❌ Recent Failures (Latest 5):');
            
            $failureTable = [];
            foreach ($recentFailures as $failure) {
                $error = $failure->metadata['processing_error'] ?? 'Unknown error';
                $failureTable[] = [
                    $failure->id,
                    basename($failure->image_path),
                    $failure->image_type,
                    substr($error, 0, 50) . (strlen($error) > 50 ? '...' : ''),
                    $failure->updated_at->diffForHumans()
                ];
            }
            
            $this->table(['ID', 'File', 'Type', 'Error', 'When'], $failureTable);
        }

        // Progress summary
        if ($stats['total'] > 0) {
            $processed = $stats['completed'] + $stats['failed'];
            $remaining = $stats['pending'] + $stats['processing'];
            $progressPercent = round(($processed / $stats['total']) * 100, 1);
            
            $this->newLine();
            $this->info("🎯 Progress: {$processed}/{$stats['total']} images processed ({$progressPercent}%)");
            
            if ($remaining > 0) {
                $this->info("⏱️  Remaining: {$remaining} images");
            } else {
                $this->info("🎉 All images processed!");
            }
        }
    }
}