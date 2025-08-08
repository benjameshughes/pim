<?php

namespace App\Services\Import;

use Illuminate\Http\UploadedFile;

class ImportConfiguration
{
    public function __construct(
        private UploadedFile $file,
        private string $importMode = 'create_or_update',
        private bool $smartAttributeExtraction = true,
        private bool $autoGenerateParentMode = true,
        private bool $autoAssignGS1Barcodes = true,
        private bool $detectMadeToMeasure = true,
        private bool $dimensionsDigitsOnly = true,
        private bool $groupBySku = true,
        private bool $processInBackground = true,
        private array $selectedWorksheets = [],
        private array $columnMapping = [],
        private array $extractionSettings = [],
        private array $validationRules = [],
        private int $chunkSize = 50,
        private int $maxProcessingTimeMinutes = 30,
    ) {}

    public static function create(): ImportConfigurationBuilder
    {
        return new ImportConfigurationBuilder();
    }

    public function getFile(): UploadedFile
    {
        return $this->file;
    }

    public function getImportMode(): string
    {
        return $this->importMode;
    }

    public function shouldExtractAttributes(): bool
    {
        return $this->smartAttributeExtraction;
    }

    public function shouldAutoGenerateParents(): bool
    {
        return $this->autoGenerateParentMode;
    }

    public function shouldAssignBarcodes(): bool
    {
        return $this->autoAssignGS1Barcodes;
    }

    public function shouldDetectMadeToMeasure(): bool
    {
        return $this->detectMadeToMeasure;
    }

    public function shouldUseDimensionsDigitsOnly(): bool
    {
        return $this->dimensionsDigitsOnly;
    }

    public function shouldGroupBySku(): bool
    {
        return $this->groupBySku;
    }

    public function shouldProcessInBackground(): bool
    {
        return $this->processInBackground;
    }

    public function getSelectedWorksheets(): array
    {
        return $this->selectedWorksheets;
    }

    public function getColumnMapping(): array
    {
        return $this->columnMapping;
    }

    public function getExtractionSettings(): array
    {
        return array_merge([
            'mtm_detection' => $this->detectMadeToMeasure,
            'dimensions_digits_only' => $this->dimensionsDigitsOnly,
            'context_aware' => true,
            'confidence_threshold' => 0.8,
        ], $this->extractionSettings);
    }

    public function getValidationRules(): array
    {
        return $this->validationRules;
    }

    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    public function getMaxProcessingTimeMinutes(): int
    {
        return $this->maxProcessingTimeMinutes;
    }

    public function toArray(): array
    {
        return [
            'file_info' => [
                'original_name' => $this->file->getClientOriginalName(),
                'size' => $this->file->getSize(),
                'type' => $this->file->getClientOriginalExtension(),
                'hash' => hash_file('sha256', $this->file->getRealPath()),
            ],
            'import_mode' => $this->importMode,
            'smart_attribute_extraction' => $this->smartAttributeExtraction,
            'auto_generate_parent_mode' => $this->autoGenerateParentMode,
            'auto_assign_gs1_barcodes' => $this->autoAssignGS1Barcodes,
            'detect_made_to_measure' => $this->detectMadeToMeasure,
            'dimensions_digits_only' => $this->dimensionsDigitsOnly,
            'group_by_sku' => $this->groupBySku,
            'process_in_background' => $this->processInBackground,
            'selected_worksheets' => $this->selectedWorksheets,
            'column_mapping' => $this->columnMapping,
            'extraction_settings' => $this->getExtractionSettings(),
            'validation_rules' => $this->validationRules,
            'chunk_size' => $this->chunkSize,
            'max_processing_time_minutes' => $this->maxProcessingTimeMinutes,
            'created_at' => now()->toISOString(),
        ];
    }

    public function withColumnMapping(array $mapping): self
    {
        $clone = clone $this;
        $clone->columnMapping = $mapping;
        return $clone;
    }

    public function withSelectedWorksheets(array $worksheets): self
    {
        $clone = clone $this;
        $clone->selectedWorksheets = $worksheets;
        return $clone;
    }
}

class ImportConfigurationBuilder
{
    private ?UploadedFile $file = null;
    private string $importMode = 'create_or_update';
    private bool $smartAttributeExtraction = true;
    private bool $autoGenerateParentMode = true;
    private bool $autoAssignGS1Barcodes = true;
    private bool $detectMadeToMeasure = true;
    private bool $dimensionsDigitsOnly = true;
    private bool $groupBySku = true;
    private bool $processInBackground = true;
    private array $selectedWorksheets = [];
    private array $columnMapping = [];
    private array $extractionSettings = [];
    private array $validationRules = [];
    private int $chunkSize = 50;
    private int $maxProcessingTimeMinutes = 30;

    public function fromFile(UploadedFile $file): self
    {
        $this->file = $file;
        return $this;
    }

    public function withMode(string $mode): self
    {
        if (!in_array($mode, ['create_only', 'update_existing', 'create_or_update'])) {
            throw new \InvalidArgumentException("Invalid import mode: {$mode}");
        }
        
        $this->importMode = $mode;
        return $this;
    }

    public function extractAttributes(bool $enabled = true): self
    {
        $this->smartAttributeExtraction = $enabled;
        return $this;
    }

    public function detectMadeToMeasure(bool $enabled = true): self
    {
        $this->detectMadeToMeasure = $enabled;
        return $this;
    }

    public function dimensionsDigitsOnly(bool $enabled = true): self
    {
        $this->dimensionsDigitsOnly = $enabled;
        return $this;
    }

    public function groupBySku(bool $enabled = true): self
    {
        $this->groupBySku = $enabled;
        return $this;
    }

    public function autoGenerateParents(bool $enabled = true): self
    {
        $this->autoGenerateParentMode = $enabled;
        return $this;
    }

    public function assignBarcodes(bool $enabled = true): self
    {
        $this->autoAssignGS1Barcodes = $enabled;
        return $this;
    }

    public function processInBackground(bool $enabled = true): self
    {
        $this->processInBackground = $enabled;
        return $this;
    }

    public function withChunkSize(int $size): self
    {
        if ($size < 1 || $size > 1000) {
            throw new \InvalidArgumentException("Chunk size must be between 1 and 1000");
        }
        
        $this->chunkSize = $size;
        return $this;
    }

    public function withMaxProcessingTime(int $minutes): self
    {
        if ($minutes < 1 || $minutes > 120) {
            throw new \InvalidArgumentException("Max processing time must be between 1 and 120 minutes");
        }
        
        $this->maxProcessingTimeMinutes = $minutes;
        return $this;
    }

    public function withExtractionSettings(array $settings): self
    {
        $this->extractionSettings = array_merge($this->extractionSettings, $settings);
        return $this;
    }

    public function withValidationRules(array $rules): self
    {
        $this->validationRules = array_merge($this->validationRules, $rules);
        return $this;
    }

    public function build(): ImportConfiguration
    {
        if (!$this->file) {
            throw new \InvalidArgumentException("File is required");
        }

        return new ImportConfiguration(
            file: $this->file,
            importMode: $this->importMode,
            smartAttributeExtraction: $this->smartAttributeExtraction,
            autoGenerateParentMode: $this->autoGenerateParentMode,
            autoAssignGS1Barcodes: $this->autoAssignGS1Barcodes,
            detectMadeToMeasure: $this->detectMadeToMeasure,
            dimensionsDigitsOnly: $this->dimensionsDigitsOnly,
            groupBySku: $this->groupBySku,
            processInBackground: $this->processInBackground,
            selectedWorksheets: $this->selectedWorksheets,
            columnMapping: $this->columnMapping,
            extractionSettings: $this->extractionSettings,
            validationRules: $this->validationRules,
            chunkSize: $this->chunkSize,
            maxProcessingTimeMinutes: $this->maxProcessingTimeMinutes,
        );
    }
}