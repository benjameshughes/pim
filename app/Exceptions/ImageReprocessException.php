<?php

namespace App\Exceptions;

class ImageReprocessException extends \Exception
{
    public static function invalidImage(): self
    {
        return new self('Image must have valid filename and URL to reprocess');
    }

    public static function storageRetrievalFailed(): self
    {
        return new self('Could not retrieve image from storage for reprocessing');
    }

    public static function dimensionExtractionFailed(): self
    {
        return new self('Failed to extract image dimensions during reprocessing');
    }
}
