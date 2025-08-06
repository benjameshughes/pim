<?php

namespace App\Exceptions\Import;

class SecurityException extends ImportException
{
    public function __construct(string $reason = '', string $filename = '', ?Throwable $previous = null)
    {
        $message = "Security violation in import file '{$filename}': {$reason}";
        $userMessage = "The uploaded file failed security checks. Please ensure it's a clean Excel or CSV file.";
        $context = ['reason' => $reason, 'filename' => $filename];

        parent::__construct($message, $userMessage, $context, 403, $previous);
    }

    public function getErrorType(): string
    {
        return 'security_violation';
    }
}