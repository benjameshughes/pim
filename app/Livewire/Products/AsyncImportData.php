<?php

namespace App\Livewire\Products;

use App\Services\AsyncExcelProcessingService;
use App\Services\ImportManagerService;
use App\Livewire\Forms\ImportConfigurationForm;
use App\DTOs\Import\ImportRequest;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Log;

#[Layout('components.layouts.app')]
class AsyncImportData extends Component
{
    use WithFileUploads;

    // Form Objects
    public ImportConfigurationForm $config;
    
    public int $step = 1; // 1: Upload, 2: Configure, 3: Validate, 4: Import, 5: Complete
    public array $worksheetAnalysis = [];
    public array $sampleData = [];
    public array $validationResults = [];
    
    // Progress tracking
    public ?string $currentJobId = null;
    public array $jobProgress = [];
    public bool $isProcessing = false;
    
    private AsyncExcelProcessingService $asyncExcelService;
    private ImportManagerService $importManager;
    
    public function boot(
        AsyncExcelProcessingService $asyncExcelService,
        ImportManagerService $importManager
    ): void {
        $this->asyncExcelService = $asyncExcelService;
        $this->importManager = $importManager;
    }
    
    public function mount(): void
    {
        $this->config->resetToDefaults();
        $this->resetProgress();
    }
    
    // STEP 1: File Upload and Async Analysis
    public function analyzeFile(): void
    {
        $this->validate([
            'config.file' => 'required|file|mimes:xlsx,xls,csv|max:102400'
        ]);
        
        try {
            $this->isProcessing = true;
            
            // Start async file analysis
            $this->currentJobId = $this->asyncExcelService->analyzeFileAsync($this->config->file);
            
            Log::info('File analysis started', [
                'job_id' => $this->currentJobId,
                'file' => $this->config->file->getClientOriginalName()
            ]);
            
            // Start polling for progress
            $this->dispatch('start-progress-polling', ['jobId' => $this->currentJobId]);
            
        } catch (\Exception $e) {
            Log::error('File analysis failed to start', ['error' => $e->getMessage()]);
            $this->addError('config.file', 'Failed to start file analysis: ' . $e->getMessage());
            $this->isProcessing = false;
        }
    }
    
    // Called by frontend polling to check progress
    public function checkProgress(): void
    {
        if (!$this->currentJobId) {
            return;
        }
        
        $progress = $this->asyncExcelService->getProgress($this->currentJobId);
        
        if (!$progress) {
            $this->handleJobError('Progress tracking lost');
            return;
        }
        
        $this->jobProgress = $progress;
        
        // Handle job completion based on current step
        if ($progress['is_completed']) {
            $this->handleJobCompletion();
        } elseif ($progress['has_failed']) {
            $this->handleJobError($progress['error_message'] ?? 'Job failed');
        }
        
        // Dispatch progress update event for frontend
        $this->dispatch('progress-updated', ['progress' => $progress]);
    }
    
    private function handleJobCompletion(): void
    {
        switch ($this->step) {
            case 1: // File analysis completed
                $this->handleAnalysisCompletion();
                break;
            case 2: // Sample data loading completed
                $this->handleSampleDataCompletion();
                break;
            case 3: // Dry run completed
                $this->handleDryRunCompletion();
                break;
            case 4: // Full import completed
                $this->handleImportCompletion();
                break;
        }
    }
    
    private function handleAnalysisCompletion(): void
    {
        $analysis = $this->asyncExcelService->getAnalysisResults($this->currentJobId);
        
        if ($analysis) {
            $this->worksheetAnalysis = $analysis->toArray();
            
            // Auto-select first worksheet with data
            if (!empty($this->worksheetAnalysis['worksheets'])) {
                $firstWorksheetWithData = collect($this->worksheetAnalysis['worksheets'])
                    ->first(fn($ws) => $ws['rows'] > 0);
                
                if ($firstWorksheetWithData) {
                    $this->config->addWorksheet($firstWorksheetWithData['name']);
                    $this->loadSampleDataAsync();
                } else {
                    $this->step = 2;
                    $this->resetProgress();
                }
            } else {
                $this->step = 2;
                $this->resetProgress();
            }
        } else {
            $this->handleJobError('Failed to retrieve analysis results');
        }
    }
    
    private function handleSampleDataCompletion(): void
    {
        $sampleData = $this->asyncExcelService->getDataResults($this->currentJobId);
        
        if ($sampleData) {
            $this->sampleData = $sampleData;
            $this->autoGuessColumnMappings();
            $this->step = 2;
            $this->resetProgress();
        } else {
            $this->handleJobError('Failed to retrieve sample data');
        }
    }
    
    private function handleDryRunCompletion(): void
    {
        // Get the processed data and run validation
        $validationData = $this->asyncExcelService->getDataResults($this->currentJobId);
        
        if ($validationData) {
            $this->runValidationOnData($validationData);
        } else {
            $this->handleJobError('Failed to retrieve validation data');
        }
    }
    
    private function handleImportCompletion(): void
    {
        $this->step = 5;
        $this->resetProgress();
        
        Log::info('Import process completed', [
            'job_id' => $this->currentJobId
        ]);
    }
    
    private function handleJobError(string $errorMessage): void
    {
        $this->addError('processing', $errorMessage);
        $this->isProcessing = false;
        $this->resetProgress();
        
        Log::error('Job processing error', [
            'job_id' => $this->currentJobId,
            'error' => $errorMessage,
            'step' => $this->step
        ]);
    }
    
    // STEP 2: Configuration (Worksheet Selection & Column Mapping)
    public function toggleWorksheet(string $worksheetName): void
    {
        if (in_array($worksheetName, $this->config->selectedWorksheets)) {
            $this->config->removeWorksheet($worksheetName);
        } else {
            $this->config->addWorksheet($worksheetName);
        }
        
        if (!empty($this->config->selectedWorksheets)) {
            $this->loadSampleDataAsync();
        }
    }
    
    private function loadSampleDataAsync(): void
    {
        if (empty($this->config->selectedWorksheets)) {
            return;
        }
        
        try {
            $this->isProcessing = true;
            
            // Convert worksheet names to indices for the service
            $selectedIndices = [];
            foreach ($this->config->selectedWorksheets as $worksheetName) {
                $worksheet = collect($this->worksheetAnalysis['worksheets'])
                    ->firstWhere('name', $worksheetName);
                if ($worksheet) {
                    $selectedIndices[] = $worksheet['index'];
                }
            }
            
            $this->currentJobId = $this->asyncExcelService->loadSampleDataAsync(
                $this->config->file,
                $selectedIndices
            );
            
            $this->dispatch('start-progress-polling', ['jobId' => $this->currentJobId]);
            
        } catch (\Exception $e) {
            Log::error('Sample data loading failed to start', ['error' => $e->getMessage()]);
            $this->addError('processing', 'Failed to start sample data loading: ' . $e->getMessage());
            $this->isProcessing = false;
        }
    }
    
    public function proceedToValidation(): void
    {
        $this->validate([
            'config.selectedWorksheets' => 'required|array|min:1',
            'config.columnMapping' => 'required|array'
        ]);
        
        if (!$this->config->hasRequiredMappings()) {
            $this->addError('config.columnMapping', 'Please map at least Variant SKU or Product Name');
            return;
        }
        
        $this->step = 3;
        $this->runAsyncValidation();
    }
    
    // STEP 3: Async Dry Run Validation
    private function runAsyncValidation(): void
    {
        try {
            $this->isProcessing = true;
            
            // Convert worksheet names to indices
            $selectedIndices = [];
            foreach ($this->config->selectedWorksheets as $worksheetName) {
                $worksheet = collect($this->worksheetAnalysis['worksheets'])
                    ->firstWhere('name', $worksheetName);
                if ($worksheet) {
                    $selectedIndices[] = $worksheet['index'];
                }
            }
            
            $this->currentJobId = $this->asyncExcelService->loadDataForDryRunAsync(
                $this->config->file,
                $selectedIndices,
                1000 // Limit for dry run
            );
            
            $this->dispatch('start-progress-polling', ['jobId' => $this->currentJobId]);
            
        } catch (\Exception $e) {
            Log::error('Dry run validation failed to start', ['error' => $e->getMessage()]);
            $this->addError('processing', 'Failed to start validation: ' . $e->getMessage());
            $this->isProcessing = false;
        }
    }
    
    private function runValidationOnData(array $validationData): void
    {
        try {
            // Apply column mappings to the loaded data
            $mappedData = $this->importManager->mapAllRows($validationData, $this->config->columnMapping);
            
            // Create import request for validation
            $requestData = $this->config->toImportRequest();
            $importRequest = new ImportRequest(
                file: $this->config->file,
                selectedWorksheets: $requestData['selectedWorksheets'],
                columnMapping: $requestData['columnMapping'],
                originalHeaders: [],
                importMode: $requestData['importMode'],
                autoGenerateParentMode: $requestData['enableAutoParentCreation'],
                smartAttributeExtraction: $requestData['enableSmartAttributeExtraction'],
                autoAssignGS1Barcodes: $requestData['enableAutoBarcodeAssignment'],
                autoCreateParents: $requestData['enableAutoParentCreation']
            );
            
            // Run validation
            $validationResult = $this->importManager->validateImportData($mappedData, $importRequest);
            $this->validationResults = $validationResult->toArray();
            
            $this->resetProgress();
            
            Log::info('Dry run validation completed', $this->validationResults);
            
        } catch (\Exception $e) {
            $this->handleJobError('Validation failed: ' . $e->getMessage());
        }
    }
    
    public function proceedToImport(): void
    {
        if (!empty($this->validationResults['errors'])) {
            $this->addError('validation', 'Please fix validation errors before proceeding');
            return;
        }
        
        $this->step = 4;
        $this->startAsyncImport();
    }
    
    // STEP 4: Async Import Execution
    private function startAsyncImport(): void
    {
        try {
            $this->isProcessing = true;
            
            // Convert worksheet names to indices
            $selectedIndices = [];
            foreach ($this->config->selectedWorksheets as $worksheetName) {
                $worksheet = collect($this->worksheetAnalysis['worksheets'])
                    ->firstWhere('name', $worksheetName);
                if ($worksheet) {
                    $selectedIndices[] = $worksheet['index'];
                }
            }
            
            $this->currentJobId = $this->asyncExcelService->loadAllDataForImportAsync(
                $this->config->file,
                $selectedIndices
            );
            
            $this->dispatch('start-progress-polling', ['jobId' => $this->currentJobId]);
            
        } catch (\Exception $e) {
            Log::error('Full import failed to start', ['error' => $e->getMessage()]);
            $this->addError('processing', 'Failed to start import: ' . $e->getMessage());
            $this->isProcessing = false;
        }
    }
    
    // STEP 5: Reset and Start Over
    public function resetImport(): void
    {
        // Cancel any active job
        if ($this->currentJobId && $this->isProcessing) {
            $this->asyncExcelService->cancelJob($this->currentJobId);
        }
        
        $this->config->resetToDefaults();
        $this->resetProgress();
        $this->step = 1;
        $this->worksheetAnalysis = [];
        $this->sampleData = [];
        $this->validationResults = [];
        $this->resetValidation();
        
        $this->dispatch('stop-progress-polling');
    }
    
    // Utility Methods
    private function resetProgress(): void
    {
        $this->currentJobId = null;
        $this->jobProgress = [];
        $this->isProcessing = false;
    }
    
    private function autoGuessColumnMappings(): void
    {
        if (empty($this->sampleData)) {
            return;
        }
        
        $guessedMappings = $this->importManager->guessColumnMappings($this->sampleData);
        $this->config->setColumnMapping($guessedMappings);
    }
    
    public function cancelCurrentJob(): void
    {
        if ($this->currentJobId && $this->isProcessing) {
            $success = $this->asyncExcelService->cancelJob($this->currentJobId);
            
            if ($success) {
                $this->resetProgress();
                $this->dispatch('stop-progress-polling');
                $this->addError('processing', 'Job cancelled by user');
            }
        }
    }
    
    public function getStepLabel(): string
    {
        return match($this->step) {
            1 => 'File Upload & Analysis',
            2 => 'Configuration',
            3 => 'Validation',
            4 => 'Import',
            5 => 'Complete',
            default => 'Unknown'
        };
    }
    
    public function getProgressPercentage(): int
    {
        if ($this->isProcessing && !empty($this->jobProgress)) {
            return $this->jobProgress['progress_percent'] ?? 0;
        }
        
        return match($this->step) {
            1 => 0,
            2 => 25,
            3 => 50,
            4 => 75,
            5 => 100,
            default => 0
        };
    }
    
    public function getActiveJobs(): array
    {
        return $this->asyncExcelService->getActiveJobs();
    }
    
    public function render()
    {
        return view('livewire.products.async-import-data', [
            'stepLabel' => $this->getStepLabel(),
            'progressPercentage' => $this->getProgressPercentage(),
            'activeJobs' => $this->getActiveJobs(),
        ]);
    }
}