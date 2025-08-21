<?php

namespace App\Http\Requests\Import;

use App\Constants\ImportDefaults;
use App\Rules\ValidCsvStructure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 📁✨ IMPORT FILE REQUEST VALIDATION ✨📁
 *
 * Laravel Form Request for validating import file uploads,
 * replacing inline validation with proper Laravel conventions
 */
class ImportFileRequest extends FormRequest
{
    /**
     * 🔐 Determine if the user is authorized to make this request
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * 📋 Get the validation rules that apply to the request
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:'.implode(',', ImportDefaults::ALLOWED_FILE_EXTENSIONS),
                'max:'.ImportDefaults::MAX_FILE_SIZE_BYTES / 1024, // Laravel expects KB
                new ValidCsvStructure,
            ],
        ];
    }

    /**
     * 🎨 Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Please select a file to import.',
            'file.file' => 'The uploaded item must be a valid file.',
            'file.mimes' => 'The file must be one of the following types: '.
                           ImportDefaults::getAllowedExtensionsString().'.',
            'file.max' => 'The file size must not exceed '.
                         ImportDefaults::getMaxFileSizeFormatted().'.',
        ];
    }

    /**
     * 🏷️ Get custom attribute names for validation messages
     */
    public function attributes(): array
    {
        return [
            'file' => 'import file',
        ];
    }

    /**
     * 🛠️ Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Additional custom validation logic can be added here
            $file = $this->file('file');

            if ($file && ! $this->isValidFileStructure($file)) {
                $validator->errors()->add('file',
                    'The file appears to be empty or has an invalid structure.'
                );
            }
        });
    }

    /**
     * 🔍 Check if uploaded file has valid structure
     */
    private function isValidFileStructure($file): bool
    {
        try {
            // Basic file structure validation
            if ($file->getSize() === 0) {
                return false;
            }

            // For CSV files, check if we can read at least the first row
            if (in_array($file->getClientOriginalExtension(), ['csv', 'txt'])) {
                $handle = fopen($file->getPathname(), 'r');
                $firstRow = fgetcsv($handle);
                fclose($handle);

                return ! empty($firstRow) && count(array_filter($firstRow)) > 0;
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 📊 Get processed validation data
     */
    public function getValidatedFile()
    {
        $validated = $this->validated();

        return $validated['file'];
    }

    /**
     * 📋 Get file information for processing
     */
    public function getFileInfo(): array
    {
        $file = $this->getValidatedFile();

        return [
            'original_name' => $file->getClientOriginalName(),
            'extension' => $file->getClientOriginalExtension(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'size_formatted' => $this->formatFileSize($file->getSize()),
            'path' => $file->getPathname(),
        ];
    }

    /**
     * 📏 Format file size in human readable format
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2).' '.$units[$pow];
    }

    /**
     * 🔍 Check if file is Excel format
     */
    public function isExcelFile(): bool
    {
        return in_array(
            $this->getValidatedFile()->getClientOriginalExtension(),
            ['xlsx', 'xls']
        );
    }

    /**
     * 📄 Check if file is CSV format
     */
    public function isCsvFile(): bool
    {
        return $this->getValidatedFile()->getClientOriginalExtension() === 'csv';
    }

    /**
     * 🎯 Get suggested processing options based on file
     */
    public function getProcessingSuggestions(): array
    {
        $file = $this->getValidatedFile();
        $size = $file->getSize();

        $suggestions = [];

        // Suggest chunk size based on file size
        if ($size > 5 * 1024 * 1024) { // > 5MB
            $suggestions['chunk_size'] = ImportDefaults::MAX_CHUNK_SIZE;
            $suggestions['background_processing'] = true;
        } elseif ($size > 1 * 1024 * 1024) { // > 1MB
            $suggestions['chunk_size'] = 500;
            $suggestions['background_processing'] = false;
        } else {
            $suggestions['chunk_size'] = ImportDefaults::DEFAULT_CHUNK_SIZE;
            $suggestions['background_processing'] = false;
        }

        // Suggest timeout based on estimated rows
        $estimatedRows = max(1, $size / 100); // rough estimation
        $suggestions['timeout'] = min(300, max(60, $estimatedRows / 10));

        return $suggestions;
    }
}
