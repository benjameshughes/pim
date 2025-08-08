<?php

namespace App\Services\Import\Actions;

use App\Models\ImportSession;

class ActionContext
{
    public array $data;
    public array $metadata = [];
    public array $configuration = [];
    private ?int $rowNumber = null;
    public ?ImportSession $session = null;

    public function __construct($sessionOrData, $rowNumberOrData = null, array $configuration = [])
    {
        // Support both constructor signatures for backward compatibility
        if ($sessionOrData instanceof ImportSession) {
            // New signature: (ImportSession $session, array $data = [])
            $this->session = $sessionOrData;
            $this->data = $rowNumberOrData ?? [];
            $this->configuration = $configuration;
        } else {
            // Legacy signature: (array $data, int $rowNumber, array $configuration = [])
            $this->data = $sessionOrData;
            $this->rowNumber = $rowNumberOrData;
            $this->configuration = $configuration;
        }
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function set(string $key, $value): void
    {
        $this->data[$key] = $value;
    }

    public function mergeData(array $data): void
    {
        $this->data = array_merge($this->data, $data);
    }

    public function getRowNumber(): ?int
    {
        return $this->rowNumber;
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    public function getConfig(string $key, $default = null)
    {
        return $this->configuration[$key] ?? $default;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(string $key, $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function getMetadataValue(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    // Alias for compatibility with new test expectations
    public function updateData(array $newData): void
    {
        $this->mergeData($newData);
    }

    public function addConfiguration(string $key, mixed $value): void
    {
        $this->configuration[$key] = $value;
    }


    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'row_number' => $this->rowNumber,
            'configuration' => $this->configuration,
            'metadata' => $this->metadata,
        ];
    }
}