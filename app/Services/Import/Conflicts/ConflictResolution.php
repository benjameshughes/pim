<?php

namespace App\Services\Import\Conflicts;

class ConflictResolution
{
    public const ACTION_SKIP = 'skip';
    public const ACTION_UPDATE = 'update';
    public const ACTION_RETRY = 'retry';
    public const ACTION_MODIFY = 'modify';
    public const ACTION_FAIL = 'fail';

    public const STRATEGY_SKIP_ROW = 'skip_row';
    public const STRATEGY_UPDATE_EXISTING = 'update_existing';
    public const STRATEGY_GENERATE_UNIQUE = 'generate_unique';
    public const STRATEGY_MERGE_DATA = 'merge_data';
    public const STRATEGY_USE_EXISTING = 'use_existing';

    private bool $resolved;
    private string $action;
    private string $strategy;
    private string $reason;
    private array $modifiedData = [];
    private array $metadata = [];

    public function __construct(
        bool $resolved,
        string $action,
        string $strategy = '',
        string $reason = '',
        array $modifiedData = [],
        array $metadata = []
    ) {
        $this->resolved = $resolved;
        $this->action = $action;
        $this->strategy = $strategy;
        $this->reason = $reason;
        $this->modifiedData = $modifiedData;
        $this->metadata = $metadata;
    }

    public static function resolved(
        string $action,
        string $strategy,
        string $reason = '',
        array $modifiedData = [],
        array $metadata = []
    ): self {
        return new self(true, $action, $strategy, $reason, $modifiedData, $metadata);
    }

    public static function failed(string $reason, array $metadata = []): self
    {
        return new self(false, self::ACTION_FAIL, '', $reason, [], $metadata);
    }

    public static function skip(string $reason, array $metadata = []): self
    {
        return new self(true, self::ACTION_SKIP, self::STRATEGY_SKIP_ROW, $reason, [], $metadata);
    }

    public static function updateExisting(array $modifiedData, string $reason = '', array $metadata = []): self
    {
        return new self(
            true,
            self::ACTION_UPDATE,
            self::STRATEGY_UPDATE_EXISTING,
            $reason ?: 'Update existing record with new data',
            $modifiedData,
            $metadata
        );
    }

    public static function retryWithModifiedData(array $modifiedData, string $reason = '', array $metadata = []): self
    {
        return new self(
            true,
            self::ACTION_RETRY,
            self::STRATEGY_MODIFY,
            $reason ?: 'Retry with modified data',
            $modifiedData,
            $metadata
        );
    }

    public static function useExisting(string $reason = '', array $metadata = []): self
    {
        return new self(
            true,
            self::ACTION_SKIP,
            self::STRATEGY_USE_EXISTING,
            $reason ?: 'Use existing record without changes',
            [],
            $metadata
        );
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getStrategy(): string
    {
        return $this->strategy;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function hasModifiedData(): bool
    {
        return !empty($this->modifiedData);
    }

    public function getModifiedData(): array
    {
        return $this->modifiedData;
    }

    public function getModifiedValue(string $key, $default = null)
    {
        return $this->modifiedData[$key] ?? $default;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getMetadataValue(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    public function withMetadata(string $key, $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    public function shouldSkip(): bool
    {
        return $this->action === self::ACTION_SKIP;
    }

    public function shouldUpdate(): bool
    {
        return $this->action === self::ACTION_UPDATE;
    }

    public function shouldRetry(): bool
    {
        return $this->action === self::ACTION_RETRY;
    }

    public function shouldFail(): bool
    {
        return $this->action === self::ACTION_FAIL;
    }

    public function toArray(): array
    {
        return [
            'resolved' => $this->resolved,
            'action' => $this->action,
            'strategy' => $this->strategy,
            'reason' => $this->reason,
            'has_modified_data' => $this->hasModifiedData(),
            'modified_data' => $this->modifiedData,
            'metadata' => $this->metadata,
        ];
    }
}