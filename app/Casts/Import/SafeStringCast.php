<?php

namespace App\Casts\Import;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Safe string casting for import data
 * Handles encoding, sanitization, and length validation
 */
class SafeStringCast implements CastsAttributes
{
    private int $maxLength;
    private bool $allowHtml;
    private bool $trimWhitespace;
    
    public function __construct(int $maxLength = 255, bool $allowHtml = false, bool $trimWhitespace = true)
    {
        $this->maxLength = $maxLength;
        $this->allowHtml = $allowHtml;
        $this->trimWhitespace = $trimWhitespace;
    }
    
    /**
     * Cast the given value when retrieving from database
     */
    public function get(Model $model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }
        
        return (string) $value;
    }
    
    /**
     * Cast the given value when storing to database
     */
    public function set(Model $model, string $key, $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        $stringValue = (string) $value;
        
        // Trim whitespace if requested
        if ($this->trimWhitespace) {
            $stringValue = trim($stringValue);
        }
        
        // Remove null bytes for security
        $stringValue = str_replace("\0", '', $stringValue);
        
        // Handle HTML content
        if (!$this->allowHtml) {
            $stringValue = strip_tags($stringValue);
        } else {
            // Sanitize HTML if allowed
            $stringValue = $this->sanitizeHtml($stringValue);
        }
        
        // Ensure valid UTF-8 encoding
        if (!mb_check_encoding($stringValue, 'UTF-8')) {
            Log::warning('Invalid UTF-8 encoding detected, converting', [
                'model' => get_class($model),
                'key' => $key,
                'original_length' => strlen($value)
            ]);
            $stringValue = mb_convert_encoding($stringValue, 'UTF-8', 'auto');
        }
        
        // Truncate if too long
        if (mb_strlen($stringValue) > $this->maxLength) {
            Log::info('String truncated due to length limit', [
                'model' => get_class($model),
                'key' => $key,
                'original_length' => mb_strlen($stringValue),
                'max_length' => $this->maxLength
            ]);
            $stringValue = mb_substr($stringValue, 0, $this->maxLength);
        }
        
        return $stringValue;
    }
    
    /**
     * Sanitize HTML content
     */
    private function sanitizeHtml(string $value): string
    {
        // Allow only safe HTML tags
        $allowedTags = '<p><br><strong><em><u><ul><ol><li>';
        return strip_tags($value, $allowedTags);
    }
}