<?php

namespace App\Console\Commands;

use App\Models\Image;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImageStatsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'images:stats {--refresh : Refresh the display every 2 seconds}';

    /**
     * The console command description.
     */
    protected $description = 'Display image storage and usage statistics';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('refresh')) {
            $this->watchStats();
        } else {
            $this->showStats();
        }

        return self::SUCCESS;
    }

    private function watchStats(): void
    {
        $this->info('📊 Image Storage Statistics (Press Ctrl+C to exit)');
        $this->newLine();

        while (true) {
            system('clear'); // Clear screen on Unix-like systems
            $this->info('📊 Image Storage Statistics (Refreshing every 2 seconds)');
            $this->info('Press Ctrl+C to exit');
            $this->newLine();

            $this->showStats();

            sleep(2);
        }
    }

    private function showStats(): void
    {
        $stats = $this->getImageStats();

        // Main statistics table
        $this->table(
            ['Metric', 'Count', 'Percentage'],
            [
                [
                    'Total Images',
                    $stats['total'],
                    '100%',
                ],
                [
                    '🔗 Attached to Products',
                    $stats['attached_products'],
                    $stats['total'] > 0 ? round(($stats['attached_products'] / $stats['total']) * 100, 1).'%' : '0%',
                ],
                [
                    '🎯 Attached to Variants',
                    $stats['attached_variants'],
                    $stats['total'] > 0 ? round(($stats['attached_variants'] / $stats['total']) * 100, 1).'%' : '0%',
                ],
                [
                    '🆓 Unattached (DAM)',
                    $stats['unattached'],
                    $stats['total'] > 0 ? round(($stats['unattached'] / $stats['total']) * 100, 1).'%' : '0%',
                ],
            ]
        );

        // Storage information
        $this->newLine();
        $this->info('💾 Storage Information:');
        
        $this->table(
            ['Storage Disk', 'Count', 'Total Size'],
            [
                ['R2 (images)', $stats['total'], $this->formatBytes($stats['total_size'])]
            ]
        );

        // Folder distribution
        if (!empty($stats['folders'])) {
            $this->newLine();
            $this->info('📁 Folder Distribution:');

            $folderTable = [];
            foreach ($stats['folders'] as $folder => $count) {
                $folderTable[] = [
                    $folder ?: '(No folder)',
                    $count,
                    $stats['total'] > 0 ? round(($count / $stats['total']) * 100, 1).'%' : '0%',
                ];
            }

            $this->table(['Folder', 'Count', 'Percentage'], $folderTable);
        }

        // Recent uploads
        if (!empty($stats['recent'])) {
            $this->newLine();
            $this->info('📸 Recent Uploads (Latest 5):');

            $recentTable = [];
            foreach ($stats['recent'] as $image) {
                $recentTable[] = [
                    $image->id,
                    substr($image->display_title, 0, 30) . (strlen($image->display_title) > 30 ? '...' : ''),
                    $this->formatBytes($image->size),
                    $image->created_at->diffForHumans(),
                ];
            }

            $this->table(['ID', 'Title', 'Size', 'Uploaded'], $recentTable);
        }
    }

    /**
     * Get image statistics
     */
    private function getImageStats(): array
    {
        $total = Image::count();
        
        return [
            'total' => $total,
            'attached_products' => Image::whereHas('products')->count(),
            'attached_variants' => Image::whereHas('variants')->count(),
            'unattached' => Image::unattached()->count(),
            'total_size' => Image::sum('size') ?: 0,
            'folders' => Image::selectRaw('folder, count(*) as count')
                ->groupBy('folder')
                ->pluck('count', 'folder')
                ->toArray(),
            'recent' => Image::orderBy('created_at', 'desc')->limit(5)->get(),
        ];
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
        } elseif ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' bytes';
    }
}
