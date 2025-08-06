<?php

namespace App\Casts\Import;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Safe JSON casting for import data
 * Handles JSON validation, sanitization, and structure validation
 */
class SafeJsonCast implements CastsAttributes
{
    private int $maxDepth;
    private int $maxSize;
    private array $allowedKeys;
    private bool $stripHtml;
    
    public function __construct(
        int $maxDepth = 10,
        int $maxSize = 65535,
        array $allowedKeys = [],
        bool $stripHtml = true
    ) {
        $this->maxDepth = $maxDepth;
        $this->maxSize = $maxSize;
        $this->allowedKeys = $allowedKeys;
        $this->stripHtml = $stripHtml;
    }
    
    /**
     * Cast the given value when retrieving from database
     */
    public function get(Model $model, string $key, $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }
        
        try {
            $decoded = json_decode($value, true, $this->maxDepth, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (\JsonException $e) {
            Log::error('Failed to decode JSON from database', [
                'model' => get_class($model),
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Cast the given value when storing to database
     */
    public function set(Model $model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }
        
        // Handle string input that might be JSON
        if (is_string($value)) {
            if (empty(trim($value))) {
                return null;
            }
            
            try {
                $value = json_decode($value, true, $this->maxDepth, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                Log::warning('Invalid JSON string provided, treating as plain text', [
                    'model' => get_class($model),
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
                
                // Convert plain text to simple array structure
                $value = ['text' => $value];
            }
        }
        
        // Ensure we have an array
        if (!is_array($value)) {
            $value = ['value' => $value];
        }
        
        // Sanitize the array
        $sanitized = $this->sanitizeArray($value, 0);
        
        // Filter allowed keys if specified
        if (!empty($this->allowedKeys)) {
            $sanitized = $this->filterAllowedKeys($sanitized);
        }
        
        try {
            $json = json_encode($sanitized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            
            // Check size limit
            if (strlen($json) > $this->maxSize) {
                Log::warning('JSON data truncated due to size limit', [
                    'model' => get_class($model),
                    'key' => $key,
                    'original_size' => strlen($json),
                    'max_size' => $this->maxSize
                ]);
                
                // Try to truncate intelligently
                $truncated = $this->truncateJson($sanitized);
                $json = json_encode($truncated, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            }
            
            return $json;
            
        } catch (\JsonException $e) {
            Log::error('Failed to encode JSON for storage', [
                'model' => get_class($model),
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Recursively sanitize array data
     */
    private function sanitizeArray(array $data, int $currentDepth): array
    {
        if ($currentDepth >= $this->maxDepth) {
            Log::warning('JSON depth limit reached, truncating', [
                'max_depth' => $this->maxDepth
            ]);
            return [];
        }
        
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            // Sanitize key
            $cleanKey = $this->sanitizeKey($key);
            if (empty($cleanKey)) {
                continue;
            }
            
            // Sanitize value based on type
            if (is_array($value)) {
                $sanitized[$cleanKey] = $this->sanitizeArray($value, $currentDepth + 1);
            } elseif (is_string($value)) {
                $sanitized[$cleanKey] = $this->sanitizeString($value);
            } elseif (is_numeric($value)) {
                // Ensure numeric values are reasonable
                if (abs($value) > PHP_INT_MAX / 2) {
                    $sanitized[$cleanKey] = 0;
                } else {
                    $sanitized[$cleanKey] = $value;
                }
            } elseif (is_bool($value)) {
                $sanitized[$cleanKey] = $value;
            } else {
                // Convert other types to string
                $sanitized[$cleanKey] = $this->sanitizeString((string) $value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize a JSON key
     */
    private function sanitizeKey($key): string
    {
        $cleanKey = (string) $key;
        
        // Remove null bytes and control characters
        $cleanKey = preg_replace('/[\x00-\x1F\x7F]/', '', $cleanKey);
        
        // Limit key length
        if (strlen($cleanKey) > 100) {
            $cleanKey = substr($cleanKey, 0, 100);
        }
        
        return trim($cleanKey);
    }
    
    /**
     * Sanitize a string value
     */
    private function sanitizeString(string $value): string
    {
        // Remove null bytes
        $value = str_replace("\0", '', $value);
        
        // Strip HTML if requested
        if ($this->stripHtml) {
            $value = strip_tags($value);
        }
        
        // Ensure valid UTF-8
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'auto');
        }
        
        // Limit string length
        if (mb_strlen($value) > 1000) {
            $value = mb_substr($value, 0, 1000);
        }
        
        return $value;
    }
    
    /**
     * Filter array to only include allowed keys
     */
    private function filterAllowedKeys(array $data): array
    {
        if (empty($this->allowedKeys)) {
            return $data;
        }
        
        $filtered = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $this->allowedKeys)) {
                if (is_array($value)) {
                    $filtered[$key] = $this->filterAllowedKeys($value);
                } else {
                    $filtered[$key] = $value;
                }
            }
        }
        
        return $filtered;
    }
    
    /**
     * Truncate JSON data to fit size limit
     */
    private function truncateJson(array $data): array
    {
        $truncated = [];
        $currentSize = 0;
        
        foreach ($data as $key => $value) {
            $itemJson = json_encode([$key => $value]);
            $itemSize = strlen($itemJson);
            
            if ($currentSize + $itemSize > $this->maxSize) {
                break;
            }
            
            $truncated[$key] = $value;
            $currentSize += $itemSize;
        }
        
        return $truncated;
    }
}