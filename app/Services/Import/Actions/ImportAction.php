<?php

namespace App\Services\Import\Actions;

abstract class ImportAction
{
    protected bool $optional = false;
    protected array $config = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    abstract public function execute(ActionContext $context): ActionResult;

    public function isOptional(): bool
    {
        return $this->optional;
    }

    public function setOptional(bool $optional = true): self
    {
        $this->optional = $optional;
        return $this;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    protected function logAction(string $message, array $data = []): void
    {
        \Illuminate\Support\Facades\Log::debug(
            get_class($this) . ': ' . $message, 
            array_merge(['action_class' => get_class($this)], $data)
        );
    }
}