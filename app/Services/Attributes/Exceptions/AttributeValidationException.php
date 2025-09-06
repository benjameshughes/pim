<?php

namespace App\Services\Attributes\Exceptions;

use Exception;

class AttributeValidationException extends Exception
{
    /** @var array<string, array<int,string>> */
    protected array $errors;

    /** @param array<string, array<int,string>> $errors */
    public function __construct(array $errors, string $message = 'Attribute validation failed')
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    /** @return array<string, array<int,string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<int,string> */
    public function messagesFor(string $key): array
    {
        return $this->errors[$key] ?? [];
    }
}

