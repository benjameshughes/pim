<?php

namespace App\Livewire\Barcodes;

use App\Services\BarcodeAssignmentService;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * 🏊‍♂️ BARCODE POOL DASHBOARD - GS1 MANAGEMENT CENTER
 *
 * Comprehensive dashboard for managing the GS1 barcode pool with:
 * - Real-time pool statistics and health monitoring
 * - Assignment history and analytics
 * - Quality assessment and pool management tools
 * - Import and assignment operations
 */
#[Layout('components.layouts.app')]
#[Title('Barcode Pool Dashboard')]
class BarcodePoolDashboard extends Component
{
    /**
     * Current barcode type filter
     */
    public string $selectedType = 'EAN13';

    /**
     * Available barcode types
     */
    public array $availableTypes = [
        'EAN13' => 'EAN-13',
        'UPC' => 'UPC',
        'CODE128' => 'Code 128',
    ];

    /**
     * Pool health refresh interval (seconds)
     */
    public int $refreshInterval = 30;

    /**
     * Loading states for UI feedback
     */
    public array $loading = [];

    /**
     * 📊 POOL STATISTICS
     *
     * Get comprehensive statistics for the selected barcode type
     */
    #[Computed]
    public function poolStats(): array
    {
        $cacheKey = "barcode_pool_stats_{$this->selectedType}";

        return Cache::remember($cacheKey, 300, function () {
            $service = app(BarcodeAssignmentService::class);

            return $service->getPoolStatistics($this->selectedType);
        });
    }

    /**
     * 🏥 POOL HEALTH CHECK
     *
     * Assess the health of the current barcode pool
     */
    #[Computed]
    public function poolHealth(): array
    {
        $cacheKey = "barcode_pool_health_{$this->selectedType}";

        return Cache::remember($cacheKey, 300, function () {
            $service = app(BarcodeAssignmentService::class);

            return $service->checkPoolHealth($this->selectedType);
        });
    }

    /**
     * 📈 RECENT ASSIGNMENTS
     *
     * Get recent barcode assignments for monitoring
     */
    #[Computed]
    public function recentAssignments()
    {
        $cacheKey = "recent_assignments_{$this->selectedType}";

        return Cache::remember($cacheKey, 60, function () {
            $service = app(BarcodeAssignmentService::class);

            return $service->getRecentAssignments($this->selectedType, 7);
        });
    }

    /**
     * 📊 QUALITY DISTRIBUTION
     *
     * Get quality score distribution for visualization
     */
    #[Computed]
    public function qualityDistribution(): array
    {
        $stats = $this->poolStats;
        $distribution = $stats['quality_distribution'] ?? [];

        // Convert to chart-friendly format
        $chartData = [];
        for ($i = 1; $i <= 10; $i++) {
            $chartData[] = [
                'score' => $i,
                'count' => $distribution[$i] ?? 0,
                'percentage' => $stats['total'] > 0 ? round((($distribution[$i] ?? 0) / $stats['total']) * 100, 1) : 0,
            ];
        }

        return $chartData;
    }

    /**
     * 🔄 REFRESH POOL DATA
     *
     * Manually refresh all pool statistics
     */
    public function refreshPoolData(): void
    {
        $this->loading['refresh'] = true;

        // Clear all cached data for this type
        Cache::forget("barcode_pool_stats_{$this->selectedType}");
        Cache::forget("barcode_pool_health_{$this->selectedType}");
        Cache::forget("recent_assignments_{$this->selectedType}");

        // Force refresh of computed properties
        unset($this->poolStats);
        unset($this->poolHealth);
        unset($this->recentAssignments);

        $this->loading['refresh'] = false;

        $this->dispatch('pool-data-refreshed');

        session()->flash('success', 'Pool data refreshed successfully!');
    }

    /**
     * 🏊‍♂️ RESERVE BARCODE RANGE
     *
     * Reserve a range of barcodes for specific use
     */
    public function reserveRange(int $count, ?string $reason = null): void
    {
        $this->loading['reserve'] = true;

        try {
            $service = app(BarcodeAssignmentService::class);
            $reserved = $service->reserveRange($count, $this->selectedType, $reason);

            if ($reserved < $count) {
                session()->flash('warning', "Only {$reserved} of {$count} requested barcodes were reserved.");
            } else {
                session()->flash('success', "Successfully reserved {$reserved} {$this->selectedType} barcodes.");
            }

            // Refresh data after reservation
            $this->refreshPoolData();

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to reserve barcodes: '.$e->getMessage());
        }

        $this->loading['reserve'] = false;
    }

    /**
     * 🔄 CHANGE BARCODE TYPE
     *
     * Switch to a different barcode type
     */
    public function selectType(string $type): void
    {
        if (array_key_exists($type, $this->availableTypes)) {
            $this->selectedType = $type;

            // Clear computed properties to force refresh
            unset($this->poolStats);
            unset($this->poolHealth);
            unset($this->recentAssignments);
            unset($this->qualityDistribution);

            $this->dispatch('barcode-type-changed', type: $type);
        }
    }

    /**
     * 📤 IMPORT POOL DATA
     *
     * Trigger the import process (placeholder - would integrate with file upload)
     */
    public function importPoolData(): void
    {
        $this->loading['import'] = true;

        try {
            // In a real implementation, this would handle file upload
            // For now, just show that the import process is available
            session()->flash('info', 'Import functionality available. Use: php artisan barcode:import {file}');

        } catch (\Exception $e) {
            session()->flash('error', 'Import failed: '.$e->getMessage());
        }

        $this->loading['import'] = false;
    }

    /**
     * 🎯 GET ASSIGNMENT RATE TREND
     *
     * Calculate assignment rate trend for display
     */
    public function getAssignmentRateTrend(): string
    {
        $stats = $this->poolStats;
        $assignmentRate = $stats['assignment_rate'] ?? 0;

        if ($assignmentRate >= 90) {
            return 'critical';
        } elseif ($assignmentRate >= 70) {
            return 'warning';
        } elseif ($assignmentRate >= 30) {
            return 'normal';
        } else {
            return 'low';
        }
    }

    /**
     * 🎨 GET STATUS COLOR FOR BADGES
     */
    public function getStatusColor(string $status): string
    {
        return match ($status) {
            'available' => 'green',
            'assigned' => 'blue',
            'reserved' => 'yellow',
            'legacy_archive' => 'gray',
            'problematic' => 'red',
            default => 'gray'
        };
    }

    /**
     * Mount component with initial setup
     */
    public function mount(): void
    {
        // Pre-warm the cache with pool statistics
        $this->poolStats;
        $this->poolHealth;
    }

    /**
     * Render the dashboard
     */
    public function render()
    {
        return view('livewire.barcodes.barcode-pool-dashboard', [
            'assignmentRateTrend' => $this->getAssignmentRateTrend(),
        ]);
    }
}
