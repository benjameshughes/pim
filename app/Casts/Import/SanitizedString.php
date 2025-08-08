<?php

namespace App\Casts\Import;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Laravel attribute cast for sanitized string values
 * Automatically sanitizes data when retrieving from and storing to database
 */
class SanitizedString implements CastsAttributes
{
    private array $sanitizers;

    private ?int $maxLength;

    public function __construct(array $sanitizers = [], ?int $maxLength = null)
    {
        $this->sanitizers = $sanitizers;
        $this->maxLength = $maxLength;
    }

    /**
     * Cast the given value for storage
     */
    public function set(Model $model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $sanitized = $this->sanitizeValue((string) $value);

        // Apply length restriction if specified
        if ($this->maxLength && mb_strlen($sanitized) > $this->maxLength) {
            $sanitized = mb_substr($sanitized, 0, $this->maxLength);
        }

        return $sanitized;
    }

    /**
     * Cast the given value for retrieval
     */
    public function get(Model $model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        // Additional sanitization on retrieval if needed
        return $this->sanitizeValue((string) $value);
    }

    /**
     * Apply sanitization rules
     */
    private function sanitizeValue(string $value): string
    {
        // Remove invisible characters
        $value = $this->removeInvisibleCharacters($value);

        // Normalize whitespace
        $value = preg_replace('/\s+/', ' ', trim($value));

        // Apply specific sanitizers
        foreach ($this->sanitizers as $sanitizer) {
            $value = $this->applySanitizer($value, $sanitizer);
        }

        return $value;
    }

    /**
     * Remove invisible characters
     */
    private function removeInvisibleCharacters(string $value): string
    {
        $invisibleChars = [
            "\xE2\x80\x8B", // Zero-width space
            "\xE2\x80\x8C", // Zero-width non-joiner
            "\xE2\x80\x8D", // Zero-width joiner
            "\xEF\xBB\xBF", // Byte order mark
            "\xC2\xA0",     // Non-breaking space
        ];

        return str_replace($invisibleChars, '', $value);
    }

    /**
     * Apply specific sanitizer
     */
    private function applySanitizer(string $value, string $sanitizer): string
    {
        return match ($sanitizer) {
            'alphanumeric' => preg_replace('/[^a-zA-Z0-9\s]/', '', $value),
            'alpha_only' => preg_replace('/[^a-zA-Z\s]/', '', $value),
            'uppercase' => strtoupper($value),
            'lowercase' => strtolower($value),
            'title_case' => ucwords(strtolower($value)),
            'sku_format' => strtoupper(preg_replace('/[^a-zA-Z0-9\-]/', '', $value)),
            default => $value
        };
    }
}
