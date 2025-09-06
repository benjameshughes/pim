<?php

namespace App\Services\Attributes\Concerns;

trait ConfiguresLogging
{
    /** Optional source string. */
    protected ?string $source = null;

    /** Activity logging configuration. */
    protected bool $shouldLog = false;
    protected array $logData = [];
    protected ?string $logDescription = null;

    public function source(string $source): self
    {
        $this->source = $source;
        return $this;
    }

    /**
     * Enable activity logging with optional custom data and description
     * 
     * @param array<string,mixed> $data Custom data to log
     * @param string|null $description Custom description (auto-generated if null)
     */
    public function log(array $data = [], ?string $description = null): self
    {
        $this->shouldLog = true;
        $this->logData = array_merge([
            'user_id' => auth()->id(),
            'component' => 'AttributesSystem',
            'timestamp' => now()->toISOString(),
        ], $data);
        $this->logDescription = $description;
        return $this;
    }
}