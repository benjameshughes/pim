<?php

namespace App\Services\Import\Extraction;

interface AttributeExtractor
{
    /**
     * Extract attributes from the given text
     *
     * @param string $text The text to analyze
     * @param array $context Additional context for extraction (field name, row data, etc.)
     * @return array Extracted attributes with confidence scores
     */
    public function extract(string $text, array $context = []): array;
}