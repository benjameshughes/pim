<?php

use App\Models\ImportSession;
use App\Services\Import\ImportBuilder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;

describe('ImportBuilder', function () {
    beforeEach(function () {
        $this->actingAs(\App\Models\User::factory()->create());
        Storage::fake('local');
        Queue::fake();
    });

    it('creates import session with basic configuration', function () {
        $file = UploadedFile::fake()->createWithContent('test.csv', "Product,SKU\nTest Product,TEST-001");
        
        $session = ImportBuilder::create()
            ->fromFile($file)
            ->execute();

        expect($session)->toBeInstanceOf(ImportSession::class);
        expect($session->original_filename)->toBe('test.csv');
        expect($session->user_id)->toBe(auth()->id());
        expect($session->status)->toBe('initializing');
        expect($session->file_type)->toBe('csv');
    });

    it('validates file type restrictions', function () {
        $invalidFile = UploadedFile::fake()->create('test.txt', 1024);
        
        expect(fn() => ImportBuilder::create()->fromFile($invalidFile))
            ->toThrow(InvalidArgumentException::class, 'Unsupported file type');
    });

    it('validates file size restrictions', function () {
        // Create file larger than 10MB
        $largeFile = UploadedFile::fake()->create('large.csv', 11 * 1024); // 11MB
        
        expect(fn() => ImportBuilder::create()->fromFile($largeFile))
            ->toThrow(InvalidArgumentException::class, 'File too large');
    });

    it('configures import mode correctly', function () {
        $file = UploadedFile::fake()->create('test.csv', 1024);
        
        $session = ImportBuilder::create()
            ->fromFile($file)
            ->withMode('create_only')
            ->execute();

        expect($session->configuration['import_mode'])->toBe('create_only');
    });

    it('validates import mode values', function () {
        $file = UploadedFile::fake()->create('test.csv', 1024);
        
        expect(fn() => ImportBuilder::create()
            ->fromFile($file)
            ->withMode('invalid_mode'))
            ->toThrow(InvalidArgumentException::class, 'Invalid import mode');
    });

    it('enables feature flags correctly', function () {
        $file = UploadedFile::fake()->create('test.csv', 1024);
        
        $session = ImportBuilder::create()
            ->fromFile($file)
            ->extractAttributes()
            ->detectMadeToMeasure()
            ->dimensionsDigitsOnly()
            ->groupBySku()
            ->execute();

        $config = $session->configuration;
        expect($config['smart_attribute_extraction'])->toBeTrue();
        expect($config['detect_made_to_measure'])->toBeTrue();
        expect($config['dimensions_digits_only'])->toBeTrue();
        expect($config['group_by_sku'])->toBeTrue();
    });

    it('sets default configuration values', function () {
        $file = UploadedFile::fake()->create('test.csv', 1024);
        
        $session = ImportBuilder::create()
            ->fromFile($file)
            ->execute();

        $config = $session->configuration;
        expect($config['import_mode'])->toBe('create_or_update');
        expect($config['chunk_size'])->toBe(50);
        expect($config['smart_attribute_extraction'])->toBeTrue();
    });

    it('validates chunk size boundaries', function () {
        $file = UploadedFile::fake()->create('test.csv', 1024);
        
        expect(fn() => ImportBuilder::create()
            ->fromFile($file)
            ->withChunkSize(5)) // Below minimum
            ->toThrow(InvalidArgumentException::class, 'Chunk size must be between');

        expect(fn() => ImportBuilder::create()
            ->fromFile($file)
            ->withChunkSize(1000)) // Above maximum
            ->toThrow(InvalidArgumentException::class, 'Chunk size must be between');
    });

    it('stores file correctly and generates hash', function () {
        $file = UploadedFile::fake()->createWithContent('test.csv', "Product,SKU\nTest Product,TEST-001");
        
        $session = ImportBuilder::create()
            ->fromFile($file)
            ->execute();

        expect($session->file_path)->toStartWith('imports/');
        expect($session->file_hash)->not->toBeNull();
        expect($session->file_size)->toBeGreaterThan(0);
        expect(Storage::exists($session->file_path))->toBeTrue();
    });

    it('dispatches analyze file job after creation', function () {
        $file = UploadedFile::fake()->create('test.csv', 1024);
        
        ImportBuilder::create()
            ->fromFile($file)
            ->execute();

        Queue::assertPushed(\App\Jobs\Import\AnalyzeFileJob::class);
    });

    it('generates unique session IDs', function () {
        $file1 = UploadedFile::fake()->create('test1.csv', 1024);
        $file2 = UploadedFile::fake()->create('test2.csv', 1024);
        
        $session1 = ImportBuilder::create()->fromFile($file1)->execute();
        $session2 = ImportBuilder::create()->fromFile($file2)->execute();

        expect($session1->session_id)->not->toBe($session2->session_id);
        expect(strlen($session1->session_id))->toBe(32);
        expect(strlen($session2->session_id))->toBe(32);
    });

    it('supports method chaining fluently', function () {
        $file = UploadedFile::fake()->create('test.csv', 1024);
        
        $session = ImportBuilder::create()
            ->fromFile($file)
            ->withMode('create_or_update')
            ->withChunkSize(100)
            ->extractAttributes()
            ->detectMadeToMeasure()
            ->dimensionsDigitsOnly()
            ->groupBySku()
            ->execute();

        expect($session)->toBeInstanceOf(ImportSession::class);
        expect($session->configuration['import_mode'])->toBe('create_or_update');
        expect($session->configuration['chunk_size'])->toBe(100);
    });

    it('handles Excel files correctly', function () {
        $file = UploadedFile::fake()->create('test.xlsx', 1024, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        
        $session = ImportBuilder::create()
            ->fromFile($file)
            ->execute();

        expect($session->file_type)->toBe('xlsx');
        expect($session->original_filename)->toBe('test.xlsx');
    });

    it('requires file before execution', function () {
        expect(fn() => ImportBuilder::create()->execute())
            ->toThrow(InvalidArgumentException::class, 'No file provided');
    });

    it('creates session with correct timestamps', function () {
        $file = UploadedFile::fake()->create('test.csv', 1024);
        
        $session = ImportBuilder::create()
            ->fromFile($file)
            ->execute();

        expect($session->created_at)->not->toBeNull();
        expect($session->updated_at)->not->toBeNull();
        expect($session->started_at)->toBeNull(); // Not started yet
        expect($session->completed_at)->toBeNull(); // Not completed yet
    });
});