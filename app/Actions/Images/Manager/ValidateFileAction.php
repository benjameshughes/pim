<?php

namespace App\Actions\Images\Manager;

use App\Actions\Images\ValidateImageFileAction;
use Illuminate\Http\UploadedFile;

/**
 * ✅ VALIDATE FILE ACTION - FACADE INTEGRATION
 *
 * Facade-friendly wrapper for ValidateImageFileAction
 * Maintains full backwards compatibility while adding fluent API
 * 
 * Usage:
 * Images::validate($file)->size('5MB')->types(['jpg', 'png'])
 * Images::validate($file)->strict()->check()
 */
class ValidateFileAction implements ImageActionInterface
{
    use ImageActionTrait;

    protected ValidateImageFileAction $legacyAction;
    protected $file = null; // Can be single file or array of files
    protected int $maxFileSize = 10 * 1024 * 1024; // 10MB
    protected int $maxFiles = 50; // Max number of files
    protected array $allowedMimeTypes = [
        'image/jpeg',
        'image/jpg', 
        'image/png',
        'image/webp',
        'image/gif',
    ];
    protected bool $strictMode = false;
    protected bool $imagesOnly = false;
    protected bool $documentsAllowed = false;
    protected array $documentMimeTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
        'text/csv',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public function __construct()
    {
        $this->legacyAction = new ValidateImageFileAction();
    }

    /**
     * ✅ Execute file validation
     *
     * @param mixed ...$parameters - [file(s)]
     */
    public function execute(...$parameters): mixed
    {
        [$files] = $parameters + [null];

        if (!$this->canExecute($files)) {
            return $this->handleReturn(false);
        }

        // Store for fluent API
        $this->file = $files;

        // Handle single file or array of files
        $filesToValidate = is_array($files) ? $files : [$files];
        
        try {
            // Check file count limit first
            if (count($filesToValidate) > $this->maxFiles) {
                $this->errors[] = "Too many files. Maximum {$this->maxFiles} files allowed, got " . count($filesToValidate);
                return $this->handleReturn(false);
            }

            $validationResults = [];
            $allValid = true;

            foreach ($filesToValidate as $index => $file) {
                $fileResult = $this->validateSingleFile($file, $index);
                $validationResults[] = $fileResult;
                
                if (!$fileResult['valid']) {
                    $allValid = false;
                }
            }

            $this->logAction('validate_files', [
                'success' => $allValid,
                'file_count' => count($filesToValidate),
                'max_files' => $this->maxFiles,
                'max_file_size' => $this->maxFileSize,
                'images_only' => $this->imagesOnly,
                'strict_mode' => $this->strictMode,
            ]);

            $result = [
                'valid' => $allValid,
                'file_count' => count($filesToValidate),
                'results' => $validationResults,
                'errors' => $this->errors,
            ];
            
            return $this->handleReturn($result);

        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
            
            $this->logAction('validate_files_failed', [
                'file_count' => count($filesToValidate),
                'error' => $e->getMessage(),
            ]);

            return $this->handleReturn(false);
        }
    }

    /**
     * ✅ Validate a single file with enhanced rules
     */
    private function validateSingleFile($file, int $index = 0): array
    {
        $filename = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $size = $file->getSize();
        $errors = [];

        // File size validation
        if ($size > $this->maxFileSize) {
            $maxSizeMB = round($this->maxFileSize / 1024 / 1024, 2);
            $fileSizeMB = round($size / 1024 / 1024, 2);
            $errors[] = "File '{$filename}' is too large ({$fileSizeMB}MB). Maximum {$maxSizeMB}MB allowed.";
        }

        // MIME type validation
        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            $errors[] = "File '{$filename}' has unsupported type ({$mimeType}). Allowed types: " . implode(', ', $this->allowedMimeTypes);
        }

        // Images only validation
        if ($this->imagesOnly && !str_starts_with($mimeType, 'image/')) {
            $errors[] = "File '{$filename}' is not an image. Only image files are allowed.";
        }

        // Store errors for later retrieval
        $this->errors = array_merge($this->errors, $errors);

        return [
            'index' => $index,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'size' => $size,
            'size_mb' => round($size / 1024 / 1024, 2),
            'valid' => empty($errors),
            'errors' => $errors,
            'is_image' => str_starts_with($mimeType, 'image/'),
            'is_document' => in_array($mimeType, $this->documentMimeTypes),
        ];
    }

    /**
     * ✅ Validate file parameter
     */
    public function canExecute(...$parameters): bool
    {
        [$file] = $parameters + [null];

        // Handle single file or array of files
        if (is_array($file)) {
            foreach ($file as $singleFile) {
                if (!($singleFile instanceof UploadedFile)) {
                    $this->errors[] = "All files must be UploadedFile instances";
                    return false;
                }
            }
        } elseif (!($file instanceof UploadedFile)) {
            $this->errors[] = "File must be an UploadedFile instance";
            return false;
        }

        return true;
    }

    /**
     * 🎯 FLUENT API METHODS (when in fluent mode)
     */

    /**
     * 📏 Set maximum file size
     */
    public function size(string $maxSize): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("size() requires fluent mode");
        }
        
        // Parse human-readable size (e.g., '5MB', '500KB')
        $maxSize = strtoupper($maxSize);
        
        if (preg_match('/^(\d+(?:\.\d+)?)(MB|KB|B)$/', $maxSize, $matches)) {
            $value = floatval($matches[1]);
            $unit = $matches[2];
            
            $this->maxFileSize = match($unit) {
                'MB' => intval($value * 1024 * 1024),
                'KB' => intval($value * 1024),
                'B' => intval($value),
                default => $this->maxFileSize
            };
        }
        
        return $this;
    }

    /**
     * 🎨 Set allowed file types
     */
    public function types(array $types): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("types() requires fluent mode");
        }
        
        // Convert extensions to MIME types
        $mimeTypes = [];
        foreach ($types as $type) {
            $type = strtolower($type);
            $mimeTypes[] = match($type) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                default => $type // Assume it's already a MIME type
            };
        }
        
        $this->allowedMimeTypes = array_unique($mimeTypes);
        return $this;
    }

    /**
     * 🔒 Enable strict validation mode
     */
    public function strict(): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("strict() requires fluent mode");
        }
        
        $this->strictMode = true;
        return $this;
    }

    /**
     * 🖼️ Validate images only (no documents)
     */
    public function imageOnly(): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("imageOnly() requires fluent mode");
        }
        
        $this->imagesOnly = true;
        $this->documentsAllowed = false;
        
        // Set strict image MIME types
        $this->allowedMimeTypes = [
            'image/jpeg',
            'image/jpg', 
            'image/png',
            'image/webp',
            'image/gif',
            'image/svg+xml',
        ];
        
        return $this;
    }

    /**
     * 🔢 Set maximum number of files allowed
     */
    public function maxFiles(int $maxFiles): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("maxFiles() requires fluent mode");
        }
        
        $this->maxFiles = $maxFiles;
        return $this;
    }

    /**
     * 📏 Set maximum file size (supports fluent chaining)
     */
    public function maxFileSize(string $size): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("maxFileSize() requires fluent mode");
        }
        
        return $this->size($size); // Delegate to existing size() method
    }

    /**
     * 🎯 Start allowed file types configuration
     */
    public function allowed(): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("allowed() requires fluent mode");
        }
        
        // Reset allowed types for explicit configuration
        $this->allowedMimeTypes = [];
        return $this;
    }

    /**
     * 🖼️ Allow image file types
     */
    public function images(): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("images() requires fluent mode");
        }
        
        $this->allowedMimeTypes = array_merge($this->allowedMimeTypes, [
            'image/jpeg',
            'image/jpg', 
            'image/png',
            'image/webp',
            'image/gif',
            'image/svg+xml',
            'image/bmp',
            'image/tiff',
        ]);
        
        return $this;
    }

    /**
     * 📄 Allow document file types
     */
    public function documents(): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("documents() requires fluent mode");
        }
        
        $this->documentsAllowed = true;
        $this->allowedMimeTypes = array_merge($this->allowedMimeTypes, $this->documentMimeTypes);
        
        return $this;
    }

    /**
     * 🎵 Allow audio file types
     */
    public function audio(): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("audio() requires fluent mode");
        }
        
        $this->allowedMimeTypes = array_merge($this->allowedMimeTypes, [
            'audio/mpeg',
            'audio/mp3',
            'audio/wav',
            'audio/ogg',
            'audio/aac',
        ]);
        
        return $this;
    }

    /**
     * 🎥 Allow video file types
     */
    public function video(): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("video() requires fluent mode");
        }
        
        $this->allowedMimeTypes = array_merge($this->allowedMimeTypes, [
            'video/mp4',
            'video/avi',
            'video/quicktime',
            'video/x-msvideo',
            'video/webm',
        ]);
        
        return $this;
    }

    /**
     * ✅ Perform validation check
     */
    public function check(): bool
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("check() requires fluent mode");
        }
        
        if (!$this->file) {
            return false;
        }
        
        $result = $this->execute($this->file);
        return $result !== false;
    }

    /**
     * 📊 Get validation result data
     */
    public function getData(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("getData() requires fluent mode");
        }
        
        return is_array($this->result) ? $this->result : [];
    }

    /**
     * ❌ Get validation errors
     */
    public function getValidationErrors(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("getValidationErrors() requires fluent mode");
        }
        
        return $this->errors;
    }

    /**
     * ✅ Check if validation passed
     */
    public function passed(): bool
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("passed() requires fluent mode");
        }
        
        return $this->result !== false && empty($this->errors);
    }

    /**
     * ❌ Check if validation failed
     */
    public function failed(): bool
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("failed() requires fluent mode");
        }
        
        return !$this->passed();
    }
}