<?php

namespace App\Exceptions\Import;

class FileNotFoundException extends ImportException
{
    public function __construct(string $filename = '', ?Throwable $previous = null)
    {
        $message = "Import file not found or inaccessible: {$filename}";
        $userMessage = 'The uploaded file could not be found or is corrupted. Please try uploading again.';
        $context = ['filename' => $filename];

        parent::__construct($message, $userMessage, $context, 404, $previous);
    }

    public function getErrorType(): string
    {
        return 'file_not_found';
    }
}
