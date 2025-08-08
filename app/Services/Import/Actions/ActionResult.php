<?php

namespace App\Services\Import\Actions;

class ActionResult
{
    public bool $success;
    public string $message = '';
    public array $errors = [];
    public ActionContext $context;
    private array $data = [];
    private array $contextUpdates = [];

    public function __construct(bool $success, string $messageOrError = '', array $data = [], array $errors = [])
    {
        $this->success = $success;
        if ($success) {
            $this->message = $messageOrError;
        } else {
            $this->message = $messageOrError;
            if (!empty($messageOrError)) {
                $this->errors[] = $messageOrError;
            }
        }
        $this->errors = array_merge($this->errors, $errors);
        $this->data = $data;
    }

    public static function success(ActionContext $context = null, string $message = ''): self
    {
        $result = new self(true, $message);
        if ($context) {
            $result->context = $context;
        }
        return $result;
    }

    public static function failure(ActionContext $context, string $message = '', array $errors = []): self
    {
        $result = new self(false, $message, [], $errors);
        $result->context = $context;
        return $result;
    }

    // Keep legacy method for backward compatibility
    public static function failed(string $error, array $data = []): self
    {
        return new self(false, $error, $data);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isFailure(): bool
    {
        return !$this->success;
    }


    public function getData(): array
    {
        return $this->data;
    }

    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function withData(array $data): self
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    public function withContextUpdate(string $key, $value): self
    {
        $this->contextUpdates[$key] = $value;
        return $this;
    }

    public function withContextUpdates(array $updates): self
    {
        $this->contextUpdates = array_merge($this->contextUpdates, $updates);
        return $this;
    }

    public function hasContextUpdates(): bool
    {
        return !empty($this->contextUpdates);
    }

    public function getContextUpdates(): array
    {
        return $this->contextUpdates;
    }

    public function updateContext(ActionContext $context): void
    {
        $this->context = $context;
    }

    public function getError(): string
    {
        return $this->message;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'errors' => $this->errors,
            'error' => $this->message, // Keep for backward compatibility
            'data' => $this->data,
            'context_updates' => $this->contextUpdates,
        ];
    }
}