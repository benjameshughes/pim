<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class ImportProgressForm extends Form
{
    public string $currentStep = 'upload';
    public int $totalRows = 0;
    public int $processedRows = 0;
    public int $productsCreated = 0;
    public int $productsUpdated = 0;
    public int $variantsCreated = 0;
    public int $variantsUpdated = 0;
    public int $variantsSkipped = 0;
    public array $errors = [];
    public array $warnings = [];
    public bool $isProcessing = false;
    public bool $isCompleted = false;
    public bool $hasErrors = false;
    public float $startTime = 0;
    public float $estimatedTimeRemaining = 0;
    
    public array $steps = [
        'upload' => 'File Upload',
        'analyze' => 'File Analysis',
        'configure' => 'Configuration',
        'validate' => 'Validation',
        'import' => 'Import',
        'complete' => 'Complete'
    ];
    
    public function start(int $totalRows): void
    {
        $this->isProcessing = true;
        $this->isCompleted = false;
        $this->hasErrors = false;
        $this->totalRows = $totalRows;
        $this->processedRows = 0;
        $this->errors = [];
        $this->warnings = [];
        $this->startTime = microtime(true);
        $this->currentStep = 'import';
    }
    
    public function updateProgress(int $processedRows, array $stats = []): void
    {
        $this->processedRows = $processedRows;
        
        if (!empty($stats)) {
            $this->productsCreated = $stats['products_created'] ?? $this->productsCreated;
            $this->productsUpdated = $stats['products_updated'] ?? $this->productsUpdated;
            $this->variantsCreated = $stats['variants_created'] ?? $this->variantsCreated;
            $this->variantsUpdated = $stats['variants_updated'] ?? $this->variantsUpdated;
            $this->variantsSkipped = $stats['variants_skipped'] ?? $this->variantsSkipped;
        }
        
        $this->calculateEstimatedTime();
    }
    
    public function addImportError(string $error): void
    {
        $this->errors[] = $error;
        $this->hasErrors = true;
    }
    
    public function addImportWarning(string $warning): void
    {
        $this->warnings[] = $warning;
    }
    
    public function complete(array $finalStats = []): void
    {
        $this->isProcessing = false;
        $this->isCompleted = true;
        $this->currentStep = 'complete';
        
        if (!empty($finalStats)) {
            $this->productsCreated = $finalStats['products_created'] ?? $this->productsCreated;
            $this->productsUpdated = $finalStats['products_updated'] ?? $this->productsUpdated;
            $this->variantsCreated = $finalStats['variants_created'] ?? $this->variantsCreated;
            $this->variantsUpdated = $finalStats['variants_updated'] ?? $this->variantsUpdated;
            $this->variantsSkipped = $finalStats['variants_skipped'] ?? $this->variantsSkipped;
            
            if (!empty($finalStats['errors'])) {
                foreach ($finalStats['errors'] as $error) {
                    $this->addImportError($error);
                }
            }
            
            if (!empty($finalStats['warnings'])) {
                foreach ($finalStats['warnings'] as $warning) {
                    $this->addImportWarning($warning);
                }
            }
        }
    }
    
    public function resetProgress(): void
    {
        $this->currentStep = 'upload';
        $this->totalRows = 0;
        $this->processedRows = 0;
        $this->productsCreated = 0;
        $this->productsUpdated = 0;
        $this->variantsCreated = 0;
        $this->variantsUpdated = 0;
        $this->variantsSkipped = 0;
        $this->errors = [];
        $this->warnings = [];
        $this->isProcessing = false;
        $this->isCompleted = false;
        $this->hasErrors = false;
        $this->startTime = 0;
        $this->estimatedTimeRemaining = 0;
    }
    
    public function setStep(string $step): void
    {
        if (array_key_exists($step, $this->steps)) {
            $this->currentStep = $step;
        }
    }
    
    public function getProgressPercentage(): int
    {
        if ($this->totalRows === 0) {
            return 0;
        }
        
        return min(100, (int) round(($this->processedRows / $this->totalRows) * 100));
    }
    
    public function getCurrentStepLabel(): string
    {
        return $this->steps[$this->currentStep] ?? 'Unknown';
    }
    
    public function getElapsedTime(): float
    {
        if ($this->startTime === 0) {
            return 0;
        }
        
        return microtime(true) - $this->startTime;
    }
    
    public function getFormattedElapsedTime(): string
    {
        $elapsed = $this->getElapsedTime();
        
        if ($elapsed < 60) {
            return sprintf('%.1fs', $elapsed);
        }
        
        $minutes = floor($elapsed / 60);
        $seconds = $elapsed % 60;
        
        return sprintf('%dm %.1fs', $minutes, $seconds);
    }
    
    public function getFormattedEstimatedTime(): string
    {
        if ($this->estimatedTimeRemaining < 60) {
            return sprintf('%.1fs remaining', $this->estimatedTimeRemaining);
        }
        
        $minutes = floor($this->estimatedTimeRemaining / 60);
        $seconds = $this->estimatedTimeRemaining % 60;
        
        return sprintf('%dm %.1fs remaining', $minutes, $seconds);
    }
    
    public function getProcessingRate(): float
    {
        $elapsed = $this->getElapsedTime();
        
        if ($elapsed === 0 || $this->processedRows === 0) {
            return 0;
        }
        
        return $this->processedRows / $elapsed;
    }
    
    public function getFormattedProcessingRate(): string
    {
        $rate = $this->getProcessingRate();
        
        if ($rate === 0) {
            return '0 rows/sec';
        }
        
        return sprintf('%.1f rows/sec', $rate);
    }
    
    public function getTotalProcessed(): int
    {
        return $this->productsCreated + $this->productsUpdated + $this->variantsCreated + $this->variantsUpdated;
    }
    
    public function getSummaryStats(): array
    {
        return [
            'products_created' => $this->productsCreated,
            'products_updated' => $this->productsUpdated,
            'variants_created' => $this->variantsCreated,
            'variants_updated' => $this->variantsUpdated,
            'variants_skipped' => $this->variantsSkipped,
            'total_processed' => $this->getTotalProcessed(),
            'errors_count' => count($this->errors),
            'warnings_count' => count($this->warnings),
            'processing_rate' => $this->getFormattedProcessingRate(),
            'elapsed_time' => $this->getFormattedElapsedTime(),
        ];
    }
    
    private function calculateEstimatedTime(): void
    {
        if ($this->processedRows === 0 || $this->totalRows === 0) {
            $this->estimatedTimeRemaining = 0;
            return;
        }
        
        $elapsed = $this->getElapsedTime();
        $rate = $this->processedRows / $elapsed;
        $remaining = $this->totalRows - $this->processedRows;
        
        $this->estimatedTimeRemaining = $remaining / $rate;
    }
}