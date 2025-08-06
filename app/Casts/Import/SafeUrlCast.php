<?php

namespace App\Casts\Import;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Safe URL casting for import data
 * Handles URL validation, normalization, and security checking
 */
class SafeUrlCast implements CastsAttributes
{
    private array $allowedSchemes;
    private bool $requireScheme;
    private int $maxLength;
    private array $blockedDomains;
    
    public function __construct(
        array $allowedSchemes = ['http', 'https'],
        bool $requireScheme = true,
        int $maxLength = 2048,
        array $blockedDomains = []
    ) {
        $this->allowedSchemes = $allowedSchemes;
        $this->requireScheme = $requireScheme;
        $this->maxLength = $maxLength;
        $this->blockedDomains = array_map('strtolower', $blockedDomains);
    }
    
    /**
     * Cast the given value when retrieving from database
     */
    public function get(Model $model, string $key, $value, array $attributes): ?string
    {
        return $value;
    }
    
    /**
     * Cast the given value when storing to database
     */
    public function set(Model $model, string $key, $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        $originalValue = (string) $value;
        $url = trim($originalValue);
        
        // Remove null bytes and control characters
        $url = preg_replace('/[\x00-\x1F\x7F]/', '', $url);
        
        // Handle multiple URLs separated by commas
        if (strpos($url, ',') !== false) {
            $urls = array_map('trim', explode(',', $url));
            $validUrls = [];
            
            foreach ($urls as $singleUrl) {
                $validatedUrl = $this->validateAndNormalizeUrl($singleUrl);
                if ($validatedUrl) {
                    $validUrls[] = $validatedUrl;
                }
            }
            
            $result = implode(',', $validUrls);
        } else {
            $result = $this->validateAndNormalizeUrl($url);
        }
        
        // Check total length
        if ($result && strlen($result) > $this->maxLength) {
            Log::warning('URL(s) truncated due to length limit', [
                'model' => get_class($model),
                'key' => $key,
                'original_length' => strlen($result),
                'max_length' => $this->maxLength
            ]);
            $result = substr($result, 0, $this->maxLength);
        }
        
        // Log transformation if value changed
        if ($originalValue !== $result) {
            Log::info('URL transformed during casting', [
                'model' => get_class($model),
                'key' => $key,
                'original' => $originalValue,
                'transformed' => $result
            ]);
        }
        
        return $result;
    }
    
    /**
     * Validate and normalize a single URL
     */
    private function validateAndNormalizeUrl(string $url): ?string
    {
        if (empty($url)) {
            return null;
        }
        
        // Add scheme if missing and required
        if ($this->requireScheme && !preg_match('/^[a-z][a-z0-9+.-]*:/i', $url)) {
            $url = 'https://' . $url;
        }
        
        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            Log::warning('Invalid URL format detected', [
                'url' => $url
            ]);
            return null;
        }
        
        // Parse URL components
        $parsed = parse_url($url);
        if (!$parsed) {
            return null;
        }
        
        // Check scheme
        if (isset($parsed['scheme']) && !in_array(strtolower($parsed['scheme']), $this->allowedSchemes)) {
            Log::warning('URL scheme not allowed', [
                'url' => $url,
                'scheme' => $parsed['scheme'],
                'allowed' => $this->allowedSchemes
            ]);
            return null;
        }
        
        // Check for blocked domains
        if (isset($parsed['host'])) {
            $host = strtolower($parsed['host']);
            foreach ($this->blockedDomains as $blockedDomain) {
                if ($host === $blockedDomain || str_ends_with($host, '.' . $blockedDomain)) {
                    Log::warning('URL domain is blocked', [
                        'url' => $url,
                        'host' => $host,
                        'blocked_domain' => $blockedDomain
                    ]);
                    return null;
                }
            }
        }
        
        // Normalize URL
        $normalizedUrl = $this->normalizeUrl($parsed);
        
        return $normalizedUrl;
    }
    
    /**
     * Normalize URL components
     */
    private function normalizeUrl(array $parsed): string
    {
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = $parsed['path'] ?? '/';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';
        
        // Normalize host to lowercase
        $host = strtolower($host);
        
        // Remove default ports
        if (($scheme === 'http' && $port === ':80') || ($scheme === 'https' && $port === ':443')) {
            $port = '';
        }
        
        // Ensure path starts with /
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        
        // Remove trailing slash for root path
        if ($path === '/' && empty($query) && empty($fragment)) {
            $path = '';
        }
        
        return $scheme . '://' . $host . $port . $path . $query . $fragment;
    }
}