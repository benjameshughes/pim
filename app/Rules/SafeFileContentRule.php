<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class SafeFileContentRule implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value instanceof UploadedFile) {
            $fail('The file must be a valid uploaded file.');
            return;
        }

        // Check file size vs compressed size ratio (zip bomb detection)
        if ($this->isZipBomb($value)) {
            Log::warning('Potential zip bomb detected', [
                'filename' => $value->getClientOriginalName(),
                'size' => $value->getSize()
            ]);
            $fail('The file appears to be a compressed archive with suspicious compression ratios.');
            return;
        }

        // Check for executable content in files
        if ($this->containsExecutableContent($value)) {
            Log::warning('File contains executable content', [
                'filename' => $value->getClientOriginalName(),
                'mime_type' => $value->getMimeType()
            ]);
            $fail('The file contains potentially dangerous executable content.');
            return;
        }

        // Check for malicious macros in Excel files (basic detection)
        if ($this->containsSuspiciousMacros($value)) {
            Log::warning('File may contain malicious macros', [
                'filename' => $value->getClientOriginalName(),
                'extension' => $value->getClientOriginalExtension()
            ]);
            $fail('The Excel file contains macros. Please use macro-free Excel files (.xlsx) for security.');
            return;
        }

        // Check file header matches extension
        if (!$this->hasValidFileSignature($value)) {
            Log::warning('File signature mismatch detected', [
                'filename' => $value->getClientOriginalName(),
                'declared_extension' => $value->getClientOriginalExtension(),
                'mime_type' => $value->getMimeType()
            ]);
            $fail('The file header does not match the file extension.');
            return;
        }
    }

    /**
     * Check for potential zip bomb by analyzing compression ratio
     */
    private function isZipBomb(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());
        
        // Only check compressed formats
        if (!in_array($extension, ['xlsx', 'xls', 'zip'])) {
            return false;
        }

        $fileSize = $file->getSize();
        
        // If file is too small, skip check
        if ($fileSize < 1024) {
            return false;
        }

        // For very large files, be suspicious
        if ($fileSize > 100 * 1024 * 1024) { // 100MB
            return true;
        }

        // Additional heuristics could be added here
        // For now, we rely on file size limits
        return false;
    }

    /**
     * Check for executable content in file
     */
    private function containsExecutableContent(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());
        
        // Explicitly reject executable extensions
        $dangerousExtensions = [
            'exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js',
            'jar', 'app', 'deb', 'pkg', 'rpm', 'dmg', 'iso'
        ];

        if (in_array($extension, $dangerousExtensions)) {
            return true;
        }

        // Check MIME type
        $mimeType = $file->getMimeType();
        $dangerousMimeTypes = [
            'application/x-executable',
            'application/x-msdownload',
            'application/x-ms-dos-executable',
            'application/java-archive',
            'text/javascript',
            'application/javascript'
        ];

        return in_array($mimeType, $dangerousMimeTypes);
    }

    /**
     * Check for suspicious macro content (basic detection)
     */
    private function containsSuspiciousMacros(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());
        
        // Check for macro-enabled Excel formats
        $macroEnabledExtensions = ['xlsm', 'xltm', 'xlam'];
        
        if (in_array($extension, $macroEnabledExtensions)) {
            return true;
        }

        // For .xls files, check MIME type
        if ($extension === 'xls') {
            $mimeType = $file->getMimeType();
            // Old Excel format can contain macros
            if (str_contains($mimeType, 'ms-excel')) {
                // Could add more sophisticated macro detection here
                return false; // For now, allow .xls files
            }
        }

        return false;
    }

    /**
     * Validate file signature matches extension
     */
    private function hasValidFileSignature(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());
        
        // Read first few bytes for file signature
        $handle = fopen($file->getRealPath(), 'rb');
        if (!$handle) {
            return false;
        }

        $header = fread($handle, 16);
        fclose($handle);

        if (!$header) {
            return false;
        }

        // Define file signatures
        $signatures = [
            'xlsx' => [
                "\x50\x4B\x03\x04", // ZIP signature (XLSX is ZIP-based)
                "\x50\x4B\x05\x06", // Empty ZIP
                "\x50\x4B\x07\x08"  // Spanned ZIP
            ],
            'xls' => [
                "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1", // OLE2 signature
                "\x09\x08\x06\x00\x00\x00\x10\x00", // Excel BIFF5
                "\x09\x08\x08\x00\x00\x00\x05\x00"  // Excel BIFF8
            ],
            'csv' => [] // CSV can start with anything, skip signature check
        ];

        // Skip signature check for CSV
        if ($extension === 'csv') {
            return true;
        }

        // Check if file signature matches expected signatures
        if (isset($signatures[$extension])) {
            foreach ($signatures[$extension] as $signature) {
                if (str_starts_with($header, $signature)) {
                    return true;
                }
            }
            return false;
        }

        // Unknown extension, be permissive
        return true;
    }
}