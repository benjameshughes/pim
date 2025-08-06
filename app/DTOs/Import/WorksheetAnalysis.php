<?php

namespace App\DTOs\Import;

class WorksheetAnalysis
{
    public function __construct(
        public array $worksheets = []
    ) {}

    public function getWorksheetCount(): int
    {
        return count($this->worksheets);
    }

    public function hasWorksheets(): bool
    {
        return !empty($this->worksheets);
    }

    public function toArray(): array
    {
        return [
            'worksheets' => array_map(fn($worksheet) => $worksheet->toArray(), $this->worksheets),
            'total_count' => $this->getWorksheetCount()
        ];
    }
}