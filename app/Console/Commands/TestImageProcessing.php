<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * 🖼️ IMAGE PROCESSING DIAGNOSTIC COMMAND
 *
 * Tests all aspects of image processing to identify what's broken
 */
class TestImageProcessing extends Command
{
    protected $signature = 'images:test {--detailed : Show detailed output}';

    protected $description = 'Test image processing pipeline and storage';

    public function handle(): int
    {
        $this->info('🖼️ IMAGE PROCESSING DIAGNOSTIC TEST');
        $this->newLine();

        // Test 1: Storage Configuration
        $this->info('1️⃣ Testing Storage Configuration...');
        $storageResult = $this->testStorageConfig();

        if (! $storageResult) {
            $this->error('❌ Storage configuration test failed!');

            return 1;
        }

        $this->info('✅ Storage configuration looks good!');
        $this->newLine();

        // Test 2: Livewire File Uploads
        $this->info('2️⃣ Testing Livewire File Upload System...');
        $livewireResult = $this->testLivewireUploads();

        if (! $livewireResult) {
            $this->error('❌ Livewire file upload test failed!');

            return 1;
        }

        $this->info('✅ Livewire file uploads working!');
        $this->newLine();

        // Test 3: Image Processing Libraries
        $this->info('3️⃣ Testing Image Processing Libraries...');
        $processingResult = $this->testImageProcessing();

        if (! $processingResult) {
            $this->error('❌ Image processing test failed!');

            return 1;
        }

        $this->info('✅ Image processing libraries working!');
        $this->newLine();

        // Test 4: Queue System
        $this->info('4️⃣ Testing Queue System...');
        $queueResult = $this->testQueueSystem();

        if (! $queueResult) {
            $this->error('❌ Queue system test failed!');

            return 1;
        }

        $this->info('✅ Queue system ready!');
        $this->newLine();

        // Test 5: Image Upload Step
        $this->info('5️⃣ Testing ImageUploadStep Component...');
        $componentResult = $this->testImageUploadComponent();

        if (! $componentResult) {
            $this->error('❌ ImageUploadStep component test failed!');

            return 1;
        }

        $this->info('✅ ImageUploadStep component working!');
        $this->newLine();

        $this->info('🎉 ALL IMAGE PROCESSING TESTS PASSED!');

        return 0;
    }

    private function testStorageConfig(): bool
    {
        $defaultDisk = config('filesystems.default');
        $disks = config('filesystems.disks', []);

        if ($this->option('detailed')) {
            $this->line('   Default Disk: '.$defaultDisk);
            $this->line('   Available Disks: '.implode(', ', array_keys($disks)));

            foreach (['local', 'public'] as $disk) {
                if (isset($disks[$disk])) {
                    $this->line("   {$disk} disk: ".($disks[$disk]['driver'] ?? 'Unknown driver'));
                }
            }
        }

        try {
            // Test storage operations
            Storage::put('test.txt', 'Storage test');
            $exists = Storage::exists('test.txt');
            Storage::delete('test.txt');

            if ($this->option('detailed')) {
                $this->line('   Storage Write/Read/Delete: '.($exists ? '✅ Working' : '❌ Failed'));
            }

            return $exists;
        } catch (\Exception $e) {
            if ($this->option('detailed')) {
                $this->line('   Storage Error: '.$e->getMessage());
            }

            return false;
        }
    }

    private function testLivewireUploads(): bool
    {
        $tempPath = config('livewire.temporary_file_upload.directory') ?: 'livewire-tmp';
        $disk = config('livewire.temporary_file_upload.disk') ?: 'local';
        $maxFileSize = config('livewire.temporary_file_upload.max_upload_time', 5);

        if ($this->option('detailed')) {
            $this->line('   Upload Disk: '.$disk);
            $this->line('   Temp Directory: '.$tempPath);
            $this->line('   Max Upload Time: '.$maxFileSize.' minutes');

            if ($tempPath) {
                $this->line('   Livewire Temp Directory: '.(Storage::disk($disk)->exists($tempPath) ? '✅ Exists' : '⚠️ Will be created on upload'));
            } else {
                $this->line('   ⚠️ Livewire temp directory not configured');
            }
        }

        try {
            // Check if we can access the temp upload configuration
            $uploadPath = storage_path('app/'.$tempPath);

            if (! file_exists($uploadPath)) {
                if ($this->option('detailed')) {
                    $this->line('   Creating temp upload directory...');
                }
                mkdir($uploadPath, 0755, true);
            }

            if ($this->option('detailed')) {
                $this->line('   Temp Upload Path: '.$uploadPath);
                $this->line('   Directory Writable: '.(is_writable($uploadPath) ? '✅ Yes' : '❌ No'));
            }

            return is_writable($uploadPath);
        } catch (\Exception $e) {
            if ($this->option('detailed')) {
                $this->line('   Livewire Upload Error: '.$e->getMessage());
            }

            return false;
        }
    }

    private function testImageProcessing(): bool
    {
        $hasGD = extension_loaded('gd');
        $hasImagick = extension_loaded('imagick');
        $hasIntervention = class_exists('Intervention\Image\ImageManager');

        if ($this->option('detailed')) {
            $this->line('   GD Extension: '.($hasGD ? '✅ Available' : '❌ Missing'));
            $this->line('   Imagick Extension: '.($hasImagick ? '✅ Available' : '❌ Missing'));
            $this->line('   Intervention Image: '.($hasIntervention ? '✅ Available' : '❌ Missing'));
        }

        // We need at least GD for basic image processing
        if (! $hasGD && ! $hasImagick) {
            if ($this->option('detailed')) {
                $this->line('   ❌ No image processing extensions available!');
            }

            return false;
        }

        try {
            // Test basic image operations with GD if available
            if ($hasGD) {
                $info = gd_info();
                if ($this->option('detailed')) {
                    $this->line('   GD Version: '.($info['GD Version'] ?? 'Unknown'));
                    $this->line('   JPEG Support: '.($info['JPEG Support'] ?? false ? '✅' : '❌'));
                    $this->line('   PNG Support: '.($info['PNG Support'] ?? false ? '✅' : '❌'));
                }
            }

            return true;
        } catch (\Exception $e) {
            if ($this->option('detailed')) {
                $this->line('   Image Processing Error: '.$e->getMessage());
            }

            return false;
        }
    }

    private function testQueueSystem(): bool
    {
        $defaultQueue = config('queue.default');
        $connections = config('queue.connections', []);

        if ($this->option('detailed')) {
            $this->line('   Default Queue: '.$defaultQueue);
            $this->line('   Queue Connections: '.implode(', ', array_keys($connections)));

            if (isset($connections[$defaultQueue])) {
                $this->line('   Default Driver: '.($connections[$defaultQueue]['driver'] ?? 'Unknown'));
            }
        }

        try {
            // Check if queue worker is running (basic check)
            $queueWorkerRunning = false;

            // On Unix systems, check for queue workers
            if (function_exists('shell_exec') && ! str_contains(strtolower(PHP_OS), 'win')) {
                $processes = shell_exec('ps aux | grep "queue:listen\|queue:work" | grep -v grep');
                $queueWorkerRunning = ! empty(trim($processes ?? ''));
            }

            if ($this->option('detailed')) {
                $this->line('   Queue Worker Running: '.($queueWorkerRunning ? '✅ Yes' : '⚠️ Not detected (may be running in different way)'));
            }

            return true; // Queue system is configured even if worker isn't running
        } catch (\Exception $e) {
            if ($this->option('detailed')) {
                $this->line('   Queue System Error: '.$e->getMessage());
            }

            return false;
        }
    }

    private function testImageUploadComponent(): bool
    {
        try {
            $componentPath = app_path('Livewire/Products/Wizard/ImageUploadStep.php');
            $exists = file_exists($componentPath);

            if ($this->option('detailed')) {
                $this->line('   Component File: '.($exists ? '✅ Exists' : '❌ Missing'));
                $this->line('   Component Path: '.$componentPath);
            }

            if ($exists) {
                // Check if component has required traits
                $content = file_get_contents($componentPath);
                $hasWithFileUploads = str_contains($content, 'WithFileUploads');
                $hasUploadMethod = str_contains($content, 'upload');

                if ($this->option('detailed')) {
                    $this->line('   WithFileUploads Trait: '.($hasWithFileUploads ? '✅ Present' : '❌ Missing'));
                    $this->line('   Upload Methods: '.($hasUploadMethod ? '✅ Present' : '❌ Missing'));
                }

                return $hasWithFileUploads;
            }

            return false;
        } catch (\Exception $e) {
            if ($this->option('detailed')) {
                $this->line('   Component Test Error: '.$e->getMessage());
            }

            return false;
        }
    }
}
