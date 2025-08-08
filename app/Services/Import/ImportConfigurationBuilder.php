<?php

namespace App\Services\Import;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class ImportConfigurationBuilder
{
    protected ?UploadedFile $file = null;
    protected array $configuration = [];

    public function fromFile(UploadedFile $file): self
    {
        $this->validateFile($file);
        $this->file = $file;
        
        return $this;
    }

    public function withMode(string $mode): self
    {
        $allowedModes = ['create_only', 'update_existing', 'create_or_update'];
        
        if (!in_array($mode, $allowedModes)) {
            throw new InvalidArgumentException('Invalid import mode: ' . $mode);
        }

        $this->configuration['import_mode'] = $mode;
        
        return $this;
    }

    public function extractAttributes(bool $enabled = true): self
    {
        $this->configuration['smart_attribute_extraction'] = $enabled;
        
        return $this;
    }

    public function detectMadeToMeasure(bool $enabled = true): self
    {
        $this->configuration['detect_made_to_measure'] = $enabled;
        
        return $this;
    }

    public function dimensionsDigitsOnly(bool $enabled = true): self
    {
        $this->configuration['dimensions_digits_only'] = $enabled;
        
        return $this;
    }

    public function groupBySku(bool $enabled = true): self
    {
        $this->configuration['group_by_sku'] = $enabled;
        
        return $this;
    }

    public function autoGenerateParents(bool $enabled = true): self
    {
        $this->configuration['auto_generate_parents'] = $enabled;
        
        return $this;
    }

    public function assignBarcodes(bool $enabled = true): self
    {
        $this->configuration['assign_barcodes'] = $enabled;
        
        return $this;
    }

    public function processInBackground(bool $enabled = true): self
    {
        $this->configuration['process_in_background'] = $enabled;
        
        return $this;
    }

    public function withChunkSize(int $size): self
    {
        if ($size < 10 || $size > 500) {
            throw new InvalidArgumentException('Chunk size must be between 10 and 500');
        }

        $this->configuration['chunk_size'] = $size;
        
        return $this;
    }

    public function withMaxProcessingTime(int $minutes): self
    {
        $this->configuration['max_processing_time'] = $minutes;
        
        return $this;
    }

    public function withExtractionSettings(array $settings): self
    {
        $this->configuration['extraction_settings'] = $settings;
        
        return $this;
    }

    public function withValidationRules(array $rules): self
    {
        $this->configuration['validation_rules'] = $rules;
        
        return $this;
    }

    public function build(): ImportConfiguration
    {
        if (!$this->file) {
            throw new InvalidArgumentException('No file provided for import');
        }

        // Set defaults
        $defaultConfig = [
            'import_mode' => 'create_or_update',
            'chunk_size' => 50,
            'smart_attribute_extraction' => true,
            'detect_made_to_measure' => false,
            'dimensions_digits_only' => false,
            'group_by_sku' => false,
            'auto_generate_parents' => true,
            'assign_barcodes' => true,
            'process_in_background' => true,
        ];

        $finalConfig = array_merge($defaultConfig, $this->configuration);

        return new ImportConfiguration($this->file, $finalConfig);
    }

    protected function validateFile(UploadedFile $file): void
    {
        // Check file size (max 10MB)
        if ($file->getSize() > 10 * 1024 * 1024) {
            throw new InvalidArgumentException('File too large. Maximum size is 10MB');
        }

        // Check file extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['csv', 'xlsx', 'xls'])) {
            throw new InvalidArgumentException('Unsupported file type: ' . $extension);
        }

        // Check MIME type
        $allowedMimeTypes = [
            'text/csv', 
            'application/vnd.ms-excel', 
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
        
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, $allowedMimeTypes)) {
            throw new InvalidArgumentException('Unsupported file type');
        }
    }
}

class ImportConfiguration
{
    public function __construct(
        protected UploadedFile $file,
        protected array $configuration
    ) {}

    public function getFile(): UploadedFile
    {
        return $this->file;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->configuration[$key] ?? $default;
    }

    public function toArray(): array
    {
        return $this->configuration;
    }
}