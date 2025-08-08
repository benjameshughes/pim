<?php

namespace App\Services;

class BarcodeDetector
{
    /**
     * Detect barcode type based on the barcode string format
     */
    public static function detectBarcodeType(string $barcode): string
    {
        // Remove any whitespace and non-numeric characters for analysis
        $cleanBarcode = preg_replace('/[^0-9]/', '', $barcode);
        $length = strlen($cleanBarcode);

        // EAN-13: 13 digits, starts with specific country codes
        if ($length === 13) {
            if (self::isValidEAN13($cleanBarcode)) {
                return 'EAN13';
            }
        }

        // UPC-A: 12 digits
        if ($length === 12) {
            if (self::isValidUPCA($cleanBarcode)) {
                return 'UPC-A';
            }
        }

        // EAN-8: 8 digits
        if ($length === 8) {
            if (self::isValidEAN8($cleanBarcode)) {
                return 'EAN8';
            }
        }

        // UPC-E: 6 or 8 digits (compressed UPC)
        if ($length === 6 || ($length === 8 && substr($cleanBarcode, 0, 1) === '0')) {
            return 'UPC-E';
        }

        // ISBN-13: 13 digits starting with 978 or 979
        if ($length === 13 && (substr($cleanBarcode, 0, 3) === '978' || substr($cleanBarcode, 0, 3) === '979')) {
            if (self::isValidEAN13($cleanBarcode)) {
                return 'ISBN13';
            }
        }

        // ISBN-10: 10 digits
        if ($length === 10) {
            if (self::isValidISBN10($cleanBarcode)) {
                return 'ISBN10';
            }
        }

        // Code 128: Variable length (usually 6-40 characters, can contain letters)
        if ($length >= 6 && $length <= 40) {
            return 'CODE128';
        }

        // Default fallback based on length
        switch ($length) {
            case 13:
                return 'EAN13';
            case 12:
                return 'UPC-A';
            case 8:
                return 'EAN8';
            default:
                return 'CODE128'; // Generic fallback
        }
    }

    /**
     * Validate EAN-13 barcode using checksum
     */
    private static function isValidEAN13(string $barcode): bool
    {
        if (strlen($barcode) !== 13) {
            return false;
        }

        $checksum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int) $barcode[$i];
            $checksum += ($i % 2 === 0) ? $digit : $digit * 3;
        }

        $calculatedCheck = (10 - ($checksum % 10)) % 10;
        $providedCheck = (int) $barcode[12];

        return $calculatedCheck === $providedCheck;
    }

    /**
     * Validate UPC-A barcode using checksum
     */
    private static function isValidUPCA(string $barcode): bool
    {
        if (strlen($barcode) !== 12) {
            return false;
        }

        $checksum = 0;
        for ($i = 0; $i < 11; $i++) {
            $digit = (int) $barcode[$i];
            $checksum += ($i % 2 === 0) ? $digit * 3 : $digit;
        }

        $calculatedCheck = (10 - ($checksum % 10)) % 10;
        $providedCheck = (int) $barcode[11];

        return $calculatedCheck === $providedCheck;
    }

    /**
     * Validate EAN-8 barcode using checksum
     */
    private static function isValidEAN8(string $barcode): bool
    {
        if (strlen($barcode) !== 8) {
            return false;
        }

        $checksum = 0;
        for ($i = 0; $i < 7; $i++) {
            $digit = (int) $barcode[$i];
            $checksum += ($i % 2 === 0) ? $digit * 3 : $digit;
        }

        $calculatedCheck = (10 - ($checksum % 10)) % 10;
        $providedCheck = (int) $barcode[7];

        return $calculatedCheck === $providedCheck;
    }

    /**
     * Validate ISBN-10 using checksum
     */
    private static function isValidISBN10(string $isbn): bool
    {
        if (strlen($isbn) !== 10) {
            return false;
        }

        $checksum = 0;
        for ($i = 0; $i < 9; $i++) {
            $checksum += (int) $isbn[$i] * (10 - $i);
        }

        $calculatedCheck = (11 - ($checksum % 11)) % 11;
        $providedCheck = ($isbn[9] === 'X') ? 10 : (int) $isbn[9];

        return $calculatedCheck === $providedCheck;
    }

    /**
     * Get barcode type information
     */
    public static function getBarcodeInfo(string $barcode): array
    {
        $type = self::detectBarcodeType($barcode);
        $cleanBarcode = preg_replace('/[^0-9]/', '', $barcode);

        $info = [
            'original' => $barcode,
            'clean' => $cleanBarcode,
            'type' => $type,
            'length' => strlen($cleanBarcode),
            'is_valid' => false,
            'description' => '',
        ];

        switch ($type) {
            case 'EAN13':
                $info['is_valid'] = self::isValidEAN13($cleanBarcode);
                $info['description'] = 'European Article Number (13 digits)';
                break;
            case 'UPC-A':
                $info['is_valid'] = self::isValidUPCA($cleanBarcode);
                $info['description'] = 'Universal Product Code (12 digits)';
                break;
            case 'EAN8':
                $info['is_valid'] = self::isValidEAN8($cleanBarcode);
                $info['description'] = 'European Article Number (8 digits)';
                break;
            case 'UPC-E':
                $info['is_valid'] = true; // Basic validation for UPC-E is complex
                $info['description'] = 'Universal Product Code (compressed)';
                break;
            case 'ISBN13':
                $info['is_valid'] = self::isValidEAN13($cleanBarcode);
                $info['description'] = 'International Standard Book Number (13 digits)';
                break;
            case 'ISBN10':
                $info['is_valid'] = self::isValidISBN10($cleanBarcode);
                $info['description'] = 'International Standard Book Number (10 digits)';
                break;
            case 'CODE128':
                $info['is_valid'] = true; // CODE128 validation is complex and varies
                $info['description'] = 'Code 128 (variable length)';
                break;
        }

        return $info;
    }

    /**
     * Test barcode detection with sample barcodes
     */
    public static function test(): array
    {
        $testBarcodes = [
            '5059032276858', // EAN13 from your CSV
            '5059032120847', // Another EAN13 from your CSV
            '123456789012',  // UPC-A
            '12345678',      // EAN8
            '9781234567890', // ISBN13
            '0123456789',    // ISBN10
            'ABC12345',      // CODE128
        ];

        $results = [];
        foreach ($testBarcodes as $barcode) {
            $results[$barcode] = self::getBarcodeInfo($barcode);
        }

        return $results;
    }
}
