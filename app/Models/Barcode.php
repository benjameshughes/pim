<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Milon\Barcode\DNS1D;
use Milon\Barcode\DNS2D;

class Barcode extends Model
{
    protected $fillable = [
        'product_variant_id',
        'barcode',
        'barcode_type',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public const BARCODE_TYPES = [
        'EAN13' => 'EAN-13',
        'EAN8' => 'EAN-8',
        'UPC' => 'UPC-A',
        'CODE128' => 'Code 128',
        'CODE39' => 'Code 39',
        'CODABAR' => 'Codabar',
        'QRCODE' => 'QR Code',
    ];

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Generate barcode image as HTML
     */
    public function generateBarcodeHtml($width = 2, $height = 30): string
    {
        if ($this->barcode_type === 'QRCODE') {
            return (new DNS2D)->getBarcodeHTML($this->barcode, 'QRCODE', $width, $height);
        }

        return (new DNS1D)->getBarcodeHTML($this->barcode, $this->barcode_type ?? 'CODE128', $width, $height);
    }

    /**
     * Generate barcode image as PNG
     */
    public function generateBarcodePNG($width = 2, $height = 30): string
    {
        if ($this->barcode_type === 'QRCODE') {
            return (new DNS2D)->getBarcodePNG($this->barcode, 'QRCODE', $width, $height);
        }

        return (new DNS1D)->getBarcodePNG($this->barcode, $this->barcode_type ?? 'CODE128', $width, $height);
    }

    /**
     * Generate barcode image as SVG
     */
    public function generateBarcodeSVG($width = 2, $height = 30): string
    {
        if ($this->barcode_type === 'QRCODE') {
            return (new DNS2D)->getBarcodeSVG($this->barcode, 'QRCODE', $width, $height);
        }

        return (new DNS1D)->getBarcodeSVG($this->barcode, $this->barcode_type ?? 'CODE128', $width, $height);
    }

    /**
     * Get the barcode type label
     */
    public function getBarcodeTypeLabel(): string
    {
        return self::BARCODE_TYPES[$this->barcode_type] ?? $this->barcode_type;
    }

    /**
     * Generate a random barcode for testing
     */
    public static function generateRandomBarcode(string $type = 'CODE128'): string
    {
        switch ($type) {
            case 'EAN13':
                return str_pad(mt_rand(100000000000, 999999999999), 13, '0', STR_PAD_LEFT);
            case 'EAN8':
                return str_pad(mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
            case 'UPC':
                return str_pad(mt_rand(100000000000, 999999999999), 12, '0', STR_PAD_LEFT);
            case 'CODE128':
            case 'CODE39':
            case 'CODABAR':
            default:
                return strtoupper(substr(md5(uniqid()), 0, 12));
        }
    }
}
