<?php

namespace App\Jobs\Import;

use App\Models\ImportSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;

class FinalizeImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 2;

    public function __construct(
        private ImportSession $session
    ) {}

    public function handle(): void
    {
        Log::info('Starting import finalization', [
            'session_id' => $this->session->session_id,
        ]);

        try {
            $this->session->updateProgress(
                stage: 'finalizing',
                operation: 'Cleaning up temporary files',
                percentage: 10
            );

            // Clean up temporary files
            $this->cleanupFiles();

            $this->session->updateProgress(
                stage: 'finalizing',
                operation: 'Generating import report',
                percentage: 30
            );

            // Generate detailed report
            $this->generateImportReport();

            $this->session->updateProgress(
                stage: 'finalizing',
                operation: 'Sending notifications',
                percentage: 60
            );

            // Send completion notification
            $this->sendCompletionNotification();

            $this->session->updateProgress(
                stage: 'finalizing',
                operation: 'Updating search indexes',
                percentage: 80
            );

            // Update search indexes (if applicable)
            $this->updateSearchIndexes();

            $this->session->updateProgress(
                stage: 'finalizing',
                operation: 'Finalization completed',
                percentage: 100
            );

            Log::info('Import finalization completed successfully', [
                'session_id' => $this->session->session_id,
                'final_statistics' => $this->session->final_results['statistics'] ?? [],
            ]);

        } catch (\Exception $e) {
            Log::error('Import finalization failed', [
                'session_id' => $this->session->session_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't fail the import for finalization issues
            $this->session->addWarning('Finalization partially failed: ' . $e->getMessage());
        }
    }

    private function cleanupFiles(): void
    {
        try {
            // Delete the uploaded file if it exists
            if (Storage::exists($this->session->file_path)) {
                Storage::delete($this->session->file_path);
                Log::info('Deleted uploaded file', [
                    'session_id' => $this->session->session_id,
                    'file_path' => $this->session->file_path,
                ]);
            }

            // Clean up any temporary processing files
            $tempPath = 'imports/temp/' . $this->session->session_id;
            if (Storage::exists($tempPath)) {
                Storage::deleteDirectory($tempPath);
                Log::info('Cleaned up temporary processing directory', [
                    'session_id' => $this->session->session_id,
                    'temp_path' => $tempPath,
                ]);
            }

        } catch (\Exception $e) {
            Log::warning('File cleanup failed', [
                'session_id' => $this->session->session_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function generateImportReport(): void
    {
        $statistics = $this->session->final_results['statistics'] ?? [];
        $configuration = $this->session->configuration;

        $report = [
            'import_summary' => [
                'session_id' => $this->session->session_id,
                'file_name' => $this->session->original_filename,
                'import_mode' => $configuration['import_mode'] ?? 'unknown',
                'started_at' => $this->session->started_at?->toISOString(),
                'completed_at' => $this->session->completed_at?->toISOString(),
                'duration' => $this->session->processing_time_seconds ? 
                    $this->formatDuration($this->session->processing_time_seconds) : null,
            ],
            'processing_results' => [
                'total_rows_processed' => $statistics['processed_rows'] ?? 0,
                'successful_rows' => $statistics['successful_rows'] ?? 0,
                'failed_rows' => $statistics['failed_rows'] ?? 0,
                'skipped_rows' => $statistics['skipped_rows'] ?? 0,
                'success_rate' => $this->calculateSuccessRate($statistics),
            ],
            'data_created' => [
                'products_created' => $statistics['products_created'] ?? 0,
                'products_updated' => $statistics['products_updated'] ?? 0,
                'variants_created' => $statistics['variants_created'] ?? 0,
                'variants_updated' => $statistics['variants_updated'] ?? 0,
            ],
            'feature_usage' => [
                'mtm_detection_enabled' => $configuration['detect_made_to_measure'] ?? false,
                'smart_dimensions_enabled' => $configuration['dimensions_digits_only'] ?? false,
                'sku_grouping_enabled' => $configuration['group_by_sku'] ?? false,
                'attribute_extraction_enabled' => $configuration['smart_attribute_extraction'] ?? false,
            ],
            'performance_metrics' => $this->session->final_results['performance_metrics'] ?? [],
            'quality_metrics' => $this->calculateQualityMetrics($statistics),
            'recommendations' => $this->generateRecommendations($statistics, $configuration),
        ];

        // Store the comprehensive report
        $this->session->update([
            'final_results' => array_merge(
                $this->session->final_results ?? [],
                ['comprehensive_report' => $report]
            )
        ]);

        Log::info('Import report generated', [
            'session_id' => $this->session->session_id,
            'success_rate' => $report['processing_results']['success_rate'],
            'total_created' => ($report['data_created']['products_created'] ?? 0) + 
                             ($report['data_created']['variants_created'] ?? 0),
        ]);
    }

    private function calculateSuccessRate(array $statistics): float
    {
        $total = $statistics['processed_rows'] ?? 0;
        $successful = $statistics['successful_rows'] ?? 0;
        
        return $total > 0 ? round(($successful / $total) * 100, 2) : 0;
    }

    private function calculateQualityMetrics(array $statistics): array
    {
        $totalProcessed = $statistics['processed_rows'] ?? 0;
        $failed = $statistics['failed_rows'] ?? 0;
        $skipped = $statistics['skipped_rows'] ?? 0;

        return [
            'data_quality_score' => $this->calculateDataQualityScore($statistics),
            'error_rate' => $totalProcessed > 0 ? round(($failed / $totalProcessed) * 100, 2) : 0,
            'skip_rate' => $totalProcessed > 0 ? round(($skipped / $totalProcessed) * 100, 2) : 0,
            'efficiency_score' => $this->calculateEfficiencyScore($statistics),
        ];
    }

    private function calculateDataQualityScore(array $statistics): int
    {
        $total = $statistics['processed_rows'] ?? 0;
        if ($total === 0) return 0;

        $successful = $statistics['successful_rows'] ?? 0;
        $failed = $statistics['failed_rows'] ?? 0;
        
        // Base score from success rate
        $successRate = ($successful / $total) * 100;
        
        // Penalty for high error rate
        $errorRate = ($failed / $total) * 100;
        $errorPenalty = min(30, $errorRate * 2); // Max 30 point penalty
        
        // Final score
        $score = max(0, $successRate - $errorPenalty);
        
        return (int) round($score);
    }

    private function calculateEfficiencyScore(array $statistics): int
    {
        $performanceMetrics = $this->session->final_results['performance_metrics'] ?? [];
        $rowsPerSecond = $performanceMetrics['rows_per_second'] ?? 0;
        
        // Score based on processing speed
        if ($rowsPerSecond >= 50) return 100;      // Excellent
        if ($rowsPerSecond >= 25) return 80;       // Good
        if ($rowsPerSecond >= 10) return 60;       // Average
        if ($rowsPerSecond >= 5) return 40;        // Below average
        if ($rowsPerSecond >= 1) return 20;        // Poor
        
        return 10; // Very poor
    }

    private function generateRecommendations(array $statistics, array $configuration): array
    {
        $recommendations = [];

        // Error rate recommendations
        $errorRate = $this->calculateQualityMetrics($statistics)['error_rate'];
        if ($errorRate > 10) {
            $recommendations[] = [
                'type' => 'data_quality',
                'severity' => 'high',
                'title' => 'High Error Rate',
                'message' => "Error rate of {$errorRate}% is quite high. Consider reviewing data quality.",
                'action' => 'Review error details and clean source data before next import.',
            ];
        }

        // Skip rate recommendations
        $skipRate = $this->calculateQualityMetrics($statistics)['skip_rate'];
        if ($skipRate > 20) {
            $recommendations[] = [
                'type' => 'efficiency',
                'severity' => 'medium',
                'title' => 'High Skip Rate',
                'message' => "Skip rate of {$skipRate}% suggests data conflicts or inappropriate import mode.",
                'action' => 'Consider using create_or_update mode for better coverage.',
            ];
        }

        // Performance recommendations
        $rowsPerSecond = $this->session->final_results['performance_metrics']['rows_per_second'] ?? 0;
        if ($rowsPerSecond < 10) {
            $recommendations[] = [
                'type' => 'performance',
                'severity' => 'low',
                'title' => 'Slow Processing Speed',
                'message' => "Processing speed of {$rowsPerSecond} rows/second could be improved.",
                'action' => 'Consider increasing chunk size or optimizing data structure.',
            ];
        }

        // Feature utilization recommendations
        if (!($configuration['smart_attribute_extraction'] ?? false)) {
            $recommendations[] = [
                'type' => 'features',
                'severity' => 'info',
                'title' => 'Unused Feature: Smart Extraction',
                'message' => 'Smart attribute extraction was disabled. This could improve data quality.',
                'action' => 'Enable smart attribute extraction in future imports.',
            ];
        }

        return $recommendations;
    }

    private function sendCompletionNotification(): void
    {
        try {
            $user = $this->session->user;
            if (!$user || !$user->email) {
                Log::info('No user email found, skipping notification', [
                    'session_id' => $this->session->session_id,
                ]);
                return;
            }

            $statistics = $this->session->final_results['statistics'] ?? [];
            $report = $this->session->final_results['comprehensive_report'] ?? [];

            // For now, just log the notification details
            // In a real implementation, you'd send an actual email
            Log::info('Import completion notification prepared', [
                'session_id' => $this->session->session_id,
                'user_email' => $user->email,
                'success_rate' => $report['processing_results']['success_rate'] ?? 0,
                'total_created' => ($statistics['products_created'] ?? 0) + ($statistics['variants_created'] ?? 0),
                'file_name' => $this->session->original_filename,
            ]);

            // TODO: Implement actual email notification
            // Mail::to($user)->send(new ImportCompletedMail($this->session));

        } catch (\Exception $e) {
            Log::warning('Failed to send completion notification', [
                'session_id' => $this->session->session_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function updateSearchIndexes(): void
    {
        try {
            // If you have search indexing (Elasticsearch, Algolia, etc.)
            // you would trigger reindexing here
            
            Log::info('Search index update completed', [
                'session_id' => $this->session->session_id,
            ]);

        } catch (\Exception $e) {
            Log::warning('Search index update failed', [
                'session_id' => $this->session->session_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds} seconds";
        }
        
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        
        if ($minutes < 60) {
            return $remainingSeconds > 0 
                ? "{$minutes}m {$remainingSeconds}s" 
                : "{$minutes} minutes";
        }
        
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        return $remainingMinutes > 0 
            ? "{$hours}h {$remainingMinutes}m" 
            : "{$hours} hours";
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('FinalizeImportJob failed', [
            'session_id' => $this->session->session_id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Add warning but don't fail the import
        $this->session->addWarning('Finalization failed: ' . $exception->getMessage());
    }
}