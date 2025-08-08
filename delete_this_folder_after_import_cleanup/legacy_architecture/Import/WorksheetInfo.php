<?php

namespace App\DTOs\Import;

class WorksheetInfo
{
    public function __construct(
        public int $index,
        public string $name,
        public array $headers,
        public int $rowCount,
        public string $preview
    ) {}

    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'name' => $this->name,
            'headers' => count($this->headers),
            'rows' => $this->rowCount,
            'preview' => $this->preview,
        ];
    }
}
