<?php

namespace App\Livewire\DataExchange\Import;

use App\Actions\Import\AnalyzeImportFileAction;
use App\DTOs\Import\ImportRequest;
use App\Exceptions\Import\FileNotFoundException;
use App\Exceptions\Import\FileSizeException;
use App\Exceptions\Import\InvalidFileFormatException;
use App\Exceptions\Import\SecurityException;
use App\Livewire\Forms\ImportConfigurationForm;
use App\Livewire\Forms\ImportProgressForm;
use App\Services\ImportDataCacheService;
use App\Services\ImportManagerService;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class ImportDataRefactored extends Component
{
    use WithFileUploads;

    // Form Objects
    public ImportConfigurationForm $config;

    public ImportProgressForm $progress;

    public int $step = 1; // 1: Upload, 2: Configure, 3: Validate, 4: Import, 5: Complete

    // Cache keys instead of storing large arrays in component state
    public ?string $worksheetAnalysisCacheKey = null;

    public ?string $sampleDataCacheKey = null;

    public ?string $validationResultsCacheKey = null;

    // Temporary debug property - will be removed once cache issue is resolved
    public array $debugWorksheetAnalysis = [];

    // Direct worksheet data for template access - temporary fix
    public array $worksheetData = [];

    // Direct sample data for template access - temporary fix
    public array $sampleDataDirect = [];

    private ImportManagerService $importManager;

    private ImportDataCacheService $cacheService;

    private AnalyzeImportFileAction $analyzeFileAction;

    public function boot(
        ImportManagerService $importManager,
        ImportDataCacheService $cacheService,
        AnalyzeImportFileAction $analyzeFileAction
    ): void {
        $this->importManager = $importManager;
        $this->cacheService = $cacheService;
        $this->analyzeFileAction = $analyzeFileAction;
    }

    public function mount(): void
    {
        $this->config->resetToDefaults();
        $this->progress->resetProgress();
    }

    // Computed properties for cached data
    public function getWorksheetAnalysisProperty(): array
    {
        $analysis = $this->worksheetAnalysisCacheKey
            ? $this->cacheService->getWorksheetAnalysis($this->worksheetAnalysisCacheKey)
            : [];

        // Fallback to debug property if cache is empty
        if (empty($analysis) && ! empty($this->debugWorksheetAnalysis)) {
            $analysis = $this->debugWorksheetAnalysis;
            Log::warning('Using debug fallback for worksheet analysis', [
                'cache_key' => $this->worksheetAnalysisCacheKey,
                'debug_worksheets' => count($analysis['worksheets'] ?? []),
            ]);
        }

        Log::debug('Worksheet analysis retrieved', [
            'cache_key' => $this->worksheetAnalysisCacheKey,
            'has_worksheets' => ! empty($analysis['worksheets']),
            'worksheet_count' => count($analysis['worksheets'] ?? []),
        ]);

        return $analysis;
    }

    public function getSampleDataProperty(): array
    {
        return $this->sampleDataCacheKey
            ? $this->cacheService->getSampleData($this->sampleDataCacheKey)
            : [];
    }

    public function getValidationResultsProperty(): array
    {
        return $this->validationResultsCacheKey
            ? $this->cacheService->getValidationResults($this->validationResultsCacheKey)
            : [];
    }

    // STEP 1: File Upload and Analysis
    public function analyzeFile(): void
    {
        $this->validate([
            'config.file' => 'required|file|mimes:xlsx,xls,csv|max:102400',
        ]);

        try {
            $this->progress->setStep('analyze');

            // Use the Action for comprehensive file analysis
            $this->worksheetAnalysisCacheKey = $this->analyzeFileAction->execute($this->config->file);

            Log::info('File analysis completed', [
                'cache_key' => $this->worksheetAnalysisCacheKey,
                'filename' => $this->config->file->getClientOriginalName(),
            ]);

            // Immediately test the cache to ensure data is available
            $testAnalysis = $this->cacheService->getWorksheetAnalysis($this->worksheetAnalysisCacheKey);
            Log::info('Cache test after analysis', [
                'cache_key' => $this->worksheetAnalysisCacheKey,
                'cache_contains_data' => ! empty($testAnalysis),
                'worksheets_found' => count($testAnalysis['worksheets'] ?? []),
            ]);

            // Store in debug property as fallback
            $this->debugWorksheetAnalysis = $testAnalysis;

            // Store worksheets directly for template access
            $this->worksheetData = $testAnalysis['worksheets'] ?? [];

            // Force Livewire to recognize the property change
            $this->dispatch('worksheetAnalysisUpdated');

            // Auto-select first worksheet with data
            $analysisArray = $this->getWorksheetAnalysisProperty();
            if (! empty($analysisArray['worksheets'])) {
                // Select first worksheet that has rows > 0
                $firstWorksheetWithData = collect($analysisArray['worksheets'])
                    ->where('rows', '>', 0)
                    ->first();

                if ($firstWorksheetWithData) {
                    $this->config->addWorksheet($firstWorksheetWithData['name']);
                    Log::info('Auto-selected worksheet', [
                        'worksheet' => $firstWorksheetWithData['name'],
                        'rows' => $firstWorksheetWithData['rows'],
                    ]);
                }
            }

            $this->loadSampleData();
            $this->step = 2;

        } catch (FileNotFoundException $e) {
            $this->addError('config.file', $e->getUserMessage());
        } catch (InvalidFileFormatException $e) {
            $this->addError('config.file', $e->getUserMessage());
        } catch (FileSizeException $e) {
            $this->addError('config.file', $e->getUserMessage());
        } catch (SecurityException $e) {
            $this->addError('config.file', $e->getUserMessage());
        } catch (\Exception $e) {
            Log::error('Unexpected file analysis error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'filename' => $this->config->file?->getClientOriginalName(),
            ]);
            $this->addError('config.file', 'An unexpected error occurred. Please try again.');
        }
    }

    // STEP 2: Configuration (Worksheet Selection & Column Mapping)
    public function toggleWorksheet(string $worksheetName): void
    {
        if (in_array($worksheetName, $this->config->selectedWorksheets)) {
            $this->config->removeWorksheet($worksheetName);
            Log::info('Worksheet deselected', ['worksheet' => $worksheetName]);
        } else {
            $this->config->addWorksheet($worksheetName);
            Log::info('Worksheet selected', ['worksheet' => $worksheetName]);
        }

        Log::info('Current selected worksheets', [
            'selected' => $this->config->selectedWorksheets,
            'count' => count($this->config->selectedWorksheets),
        ]);

        if (! empty($this->config->selectedWorksheets)) {
            $this->loadSampleData();
        }
    }

    public function proceedToValidation(): void
    {
        $this->validate([
            'config.selectedWorksheets' => 'required|array|min:1',
            'config.columnMapping' => 'required|array',
        ]);

        if (! $this->config->hasRequiredMappings()) {
            $this->addError('config.columnMapping', 'Please map at least Variant SKU or Product Name');

            return;
        }

        $this->step = 3;
        $this->runValidation();
    }

    // STEP 3: Dry Run Validation
    public function runValidation(): void
    {
        try {
            $this->progress->setStep('validate');

            $importRequest = $this->createImportRequest();
            $validationResult = $this->importManager->runDryRun($importRequest);
            $validationArray = $validationResult->toArray();

            // Store in cache instead of component state
            $this->validationResultsCacheKey = $this->cacheService->storeValidationResults($validationArray);

            Log::info('Dry run completed', $validationArray);

        } catch (\Exception $e) {
            Log::error('Validation failed', ['error' => $e->getMessage()]);
            $this->progress->addImportError('Validation failed: '.$e->getMessage());
        }
    }

    public function proceedToImport(): void
    {
        $validationResults = $this->getValidationResultsProperty();
        if (! empty($validationResults['errors'])) {
            $this->addError('validation', 'Please fix validation errors before proceeding');

            return;
        }

        $this->step = 4;
        $this->startImport();
    }

    // STEP 4: Import Execution
    public function startImport(): void
    {
        try {
            $validationResults = $this->getValidationResultsProperty();
            $this->progress->start($validationResults['total_rows'] ?? 0);

            $importRequest = $this->createImportRequest();
            $result = $this->importManager->executeImport(
                $importRequest,
                [$this, 'updateImportProgress']
            );

            $this->progress->complete($result->toArray());
            $this->step = 5;

            Log::info('Import completed', $result->toArray());

        } catch (\Exception $e) {
            Log::error('Import failed', ['error' => $e->getMessage()]);
            $this->progress->addImportError('Import failed: '.$e->getMessage());
            $this->progress->complete();
        }
    }

    public function updateImportProgress(int $processedRows, array $stats = []): void
    {
        static $lastUpdate = 0;
        $now = time();

        // Always update internal progress data
        $this->progress->updateProgress($processedRows, $stats);

        // Only dispatch DOM updates every 2 seconds or when completed to reduce memory churn
        if ($now - $lastUpdate >= 2 || $this->progress->isCompleted || $processedRows % 50 === 0) {
            $this->dispatch('import-progress-updated');
            $lastUpdate = $now;

            // Force garbage collection for large imports
            if ($processedRows % 1000 === 0 && function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
    }

    // STEP 5: Reset and Start Over
    public function resetImport(): void
    {
        // Clear cached data
        $this->cacheService->clearImportData([
            $this->worksheetAnalysisCacheKey,
            $this->sampleDataCacheKey,
            $this->validationResultsCacheKey,
        ]);

        $this->config->resetToDefaults();
        $this->progress->resetProgress();
        $this->step = 1;
        $this->worksheetAnalysisCacheKey = null;
        $this->sampleDataCacheKey = null;
        $this->validationResultsCacheKey = null;
        $this->debugWorksheetAnalysis = [];
        $this->worksheetData = [];
        $this->sampleDataDirect = [];
        $this->resetValidation();
    }

    // Navigation Methods
    // Debug method - can be removed after fix
    public function debugWorksheetAnalysis(): void
    {
        $analysis = $this->getWorksheetAnalysisProperty();
        Log::info('Debug worksheet analysis called', [
            'cache_key' => $this->worksheetAnalysisCacheKey,
            'analysis_keys' => array_keys($analysis),
            'worksheets_count' => count($analysis['worksheets'] ?? []),
            'debug_worksheets_count' => count($this->debugWorksheetAnalysis['worksheets'] ?? []),
        ]);
    }

    public function goToStep(int $targetStep): void
    {
        // Only allow navigation to completed steps
        if ($targetStep == 1 && $this->step > 1) {
            $this->step = 1;
        } elseif ($targetStep == 2 && $this->step > 2 && $this->worksheetAnalysisCacheKey) {
            $this->step = 2;
        } elseif ($targetStep == 3 && $this->step > 3 && $this->validationResultsCacheKey) {
            $this->step = 3;
        }

        Log::info('User navigated to step', ['target_step' => $targetStep, 'current_step' => $this->step]);
    }

    // Utility Methods
    private function createImportRequest(array $overrides = []): ImportRequest
    {
        $requestData = $this->config->toImportRequest();

        // Get original headers from sample data
        $originalHeaders = $this->getOriginalHeaders();

        return new ImportRequest(
            file: $this->config->file,
            selectedWorksheets: $overrides['selectedWorksheets'] ?? $requestData['selectedWorksheets'],
            columnMapping: $overrides['columnMapping'] ?? $requestData['columnMapping'],
            originalHeaders: $originalHeaders,
            importMode: $overrides['importMode'] ?? $requestData['importMode'],
            autoGenerateParentMode: $requestData['enableAutoParentCreation'],
            smartAttributeExtraction: $requestData['enableSmartAttributeExtraction'],
            autoAssignGS1Barcodes: $requestData['enableAutoBarcodeAssignment'],
            autoCreateParents: $requestData['enableAutoParentCreation']
        );
    }

    private function getOriginalHeaders(): array
    {
        $sampleData = $this->getSampleDataProperty();
        if (empty($sampleData)) {
            return [];
        }

        // Get headers from the first worksheet's sample data
        $firstWorksheetData = reset($sampleData);
        if (empty($firstWorksheetData)) {
            return [];
        }

        return array_keys($firstWorksheetData[0] ?? []);
    }

    private function loadSampleData(): void
    {
        if (empty($this->config->selectedWorksheets)) {
            Log::info('No worksheets selected, skipping sample data load');

            return;
        }

        Log::info('Loading sample data', [
            'selected_worksheets' => $this->config->selectedWorksheets,
            'worksheet_count' => count($this->config->selectedWorksheets),
        ]);

        try {
            $importRequest = $this->createImportRequest([
                'selectedWorksheets' => $this->config->selectedWorksheets,
                'columnMapping' => [],
                'importMode' => 'create_only',
            ]);

            $sampleData = $this->importManager->loadSampleDataForConfiguration($importRequest);

            Log::info('Sample data loaded', [
                'sample_data_count' => count($sampleData),
                'worksheets_in_sample' => array_keys($sampleData),
            ]);

            // Store in cache instead of component state
            $this->sampleDataCacheKey = $this->cacheService->storeSampleData($sampleData);

            // Also store directly for template access
            $this->sampleDataDirect = $sampleData;

            // Auto-guess column mappings
            $this->autoGuessColumnMappings();

        } catch (\Exception $e) {
            Log::error('Failed to load sample data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'selected_worksheets' => $this->config->selectedWorksheets,
            ]);
        }
    }

    private function autoGuessColumnMappings(): void
    {
        $sampleData = $this->getSampleDataProperty();
        if (empty($sampleData)) {
            return;
        }

        $guessedMappings = $this->importManager->guessColumnMappings($sampleData);
        $this->config->setColumnMapping($guessedMappings);
    }

    public function checkImportProgress(): void
    {
        // Called by frontend polling - progress is already updated in real-time
        $this->dispatch('import-progress-checked');
    }

    public function getValidationErrors(): array
    {
        $validationResults = $this->getValidationResultsProperty();

        return collect($validationResults['errors'] ?? [])
            ->map(function ($error) {
                return [
                    'message' => $error,
                    'type' => $this->determineErrorType($error),
                    'severity' => $this->determineErrorSeverity($error),
                ];
            })->toArray();
    }

    private function determineErrorType(string $error): string
    {
        if (str_contains($error, 'required')) {
            return 'validation';
        }
        if (str_contains($error, 'duplicate')) {
            return 'constraint';
        }
        if (str_contains($error, 'format')) {
            return 'format';
        }
        if (str_contains($error, 'barcode')) {
            return 'barcode';
        }

        return 'general';
    }

    private function determineErrorSeverity(string $error): string
    {
        if (str_contains($error, 'required') || str_contains($error, 'duplicate')) {
            return 'high';
        }
        if (str_contains($error, 'format') || str_contains($error, 'barcode')) {
            return 'medium';
        }

        return 'low';
    }

    public function getStepLabel(): string
    {
        return match ($this->step) {
            1 => 'File Upload',
            2 => 'Configuration',
            3 => 'Validation',
            4 => 'Import',
            5 => 'Complete',
            default => 'Unknown'
        };
    }

    public function getProgressPercentage(): int
    {
        return match ($this->step) {
            1 => 0,
            2 => 25,
            3 => 50,
            4 => $this->progress->getProgressPercentage(),
            5 => 100,
            default => 0
        };
    }

    public function render()
    {
        return view('livewire.data-exchange.import.import-data-refactored', [
            'stepLabel' => $this->getStepLabel(),
            'progressPercentage' => $this->getProgressPercentage(),
        ]);
    }
}
