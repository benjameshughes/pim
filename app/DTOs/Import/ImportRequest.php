<?php

namespace App\DTOs\Import;

class ImportRequest
{
    public function __construct(
        public $file,
        public array $selectedWorksheets,
        public array $columnMapping,
        public array $originalHeaders,
        public string $importMode,
        public bool $autoGenerateParentMode = true,
        public bool $smartAttributeExtraction = true,
        public bool $autoAssignGS1Barcodes = false,
        public bool $autoCreateParents = false
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            file: $data['file'],
            selectedWorksheets: $data['selectedWorksheets'] ?? [],
            columnMapping: $data['columnMapping'] ?? [],
            originalHeaders: $data['originalHeaders'] ?? [],
            importMode: $data['importMode'] ?? 'create_or_update',
            autoGenerateParentMode: $data['autoGenerateParentMode'] ?? true,
            smartAttributeExtraction: $data['smartAttributeExtraction'] ?? true,
            autoAssignGS1Barcodes: $data['autoAssignGS1Barcodes'] ?? false,
            autoCreateParents: $data['autoCreateParents'] ?? false
        );
    }

    public function toArray(): array
    {
        return [
            'selectedWorksheets' => $this->selectedWorksheets,
            'columnMapping' => $this->columnMapping,
            'originalHeaders' => $this->originalHeaders,
            'importMode' => $this->importMode,
            'autoGenerateParentMode' => $this->autoGenerateParentMode,
            'smartAttributeExtraction' => $this->smartAttributeExtraction,
            'autoAssignGS1Barcodes' => $this->autoAssignGS1Barcodes,
            'autoCreateParents' => $this->autoCreateParents,
        ];
    }
}