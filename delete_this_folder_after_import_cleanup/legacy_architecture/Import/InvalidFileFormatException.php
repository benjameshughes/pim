<?php

namespace App\Exceptions\Import;

class InvalidFileFormatException extends ImportException
{
    public function __construct(string $format = '', string $filename = '', ?Throwable $previous = null)
    {
        $message = "Invalid file format '{$format}' for import file: {$filename}";
        $userMessage = 'Invalid file format. Please upload an Excel (.xlsx, .xls) or CSV file.';
        $context = ['format' => $format, 'filename' => $filename];

        parent::__construct($message, $userMessage, $context, 422, $previous);
    }

    public function getErrorType(): string
    {
        return 'invalid_format';
    }
}
