<?php

namespace App\Exceptions\Import;

class FileSizeException extends ImportException
{
    public function __construct(int $actualSize = 0, int $maxSize = 0, ?Throwable $previous = null)
    {
        $actualSizeMB = round($actualSize / 1024 / 1024, 2);
        $maxSizeMB = round($maxSize / 1024 / 1024, 2);
        
        $message = "File size {$actualSizeMB}MB exceeds maximum allowed size of {$maxSizeMB}MB";
        $userMessage = "File too large. Maximum size is {$maxSizeMB}MB.";
        $context = ['actual_size' => $actualSize, 'max_size' => $maxSize];

        parent::__construct($message, $userMessage, $context, 413, $previous);
    }

    public function getErrorType(): string
    {
        return 'file_too_large';
    }
}