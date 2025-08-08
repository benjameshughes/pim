<?php

namespace App\Exceptions\Import;

use Exception;

abstract class ImportException extends Exception
{
    protected string $userMessage;

    protected array $context;

    public function __construct(string $message = '', string $userMessage = '', array $context = [], int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->userMessage = $userMessage ?: $message;
        $this->context = $context;
    }

    public function getUserMessage(): string
    {
        return $this->userMessage;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getErrorType(): string
    {
        return 'import_error';
    }
}
