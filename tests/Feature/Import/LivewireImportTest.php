<?php

use App\Livewire\Import\CreateImport;
use App\Models\ImportSession;
use App\Services\Import\ImportBuilder;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;

describe('Livewire Import Components', function () {
    beforeEach(function () {
        $this->actingAs(\App\Models\User::factory()->create());
        Storage::fake('local');
        Queue::fake();
    });

    describe('CreateImport Component', function () {
        it('renders correctly with default state', function () {
            Livewire::test(CreateImport::class)
                ->assertSee('Import Configuration')
                ->assertSee('Upload a file')
                ->assertSee('Import Mode')
                ->assertSee('Processing Options')
                ->assertSet('file', null)
                ->assertSet('import_mode', 'create_or_update')
                ->assertSet('extract_attributes', true)
                ->assertSet('detect_made_to_measure', false)
                ->assertSet('dimensions_digits_only', false)
                ->assertSet('group_by_sku', false)
                ->assertSet('chunk_size', 50);
        });

        it('handles file upload correctly', function () {
            $file = UploadedFile::fake()->createWithContent('test.csv', "Product,SKU\nTest Product,TEST-001");
            
            Livewire::test(CreateImport::class)
                ->set('file', $file)
                ->assertSet('file', $file)
                ->assertSee($file->getClientOriginalName());
        });

        it('validates file upload requirements', function () {
            // Test invalid file type
            $invalidFile = UploadedFile::fake()->create('test.txt', 1024);
            
            Livewire::test(CreateImport::class)
                ->set('file', $invalidFile)
                ->assertHasErrors(['file']);
            
            // Test file too large (over 10MB)
            $largeFile = UploadedFile::fake()->create('large.csv', 11 * 1024); // 11MB
            
            Livewire::test(CreateImport::class)
                ->set('file', $largeFile)
                ->assertHasErrors(['file']);
        });

        it('updates import mode correctly', function () {
            Livewire::test(CreateImport::class)
                ->set('import_mode', 'create_only')
                ->assertSet('import_mode', 'create_only');
        });

        it('toggles processing options correctly', function () {
            $component = Livewire::test(CreateImport::class);
            
            $component
                ->set('extract_attributes', false)
                ->assertSet('extract_attributes', false)
                ->set('detect_made_to_measure', true)
                ->assertSet('detect_made_to_measure', true)
                ->set('dimensions_digits_only', true)
                ->assertSet('dimensions_digits_only', true)
                ->set('group_by_sku', true)
                ->assertSet('group_by_sku', true);
        });

        it('validates chunk size boundaries', function () {
            Livewire::test(CreateImport::class)
                ->set('chunk_size', 5) // Below minimum
                ->assertHasErrors(['chunk_size'])
                ->set('chunk_size', 1000) // Above maximum
                ->assertHasErrors(['chunk_size'])
                ->set('chunk_size', 100) // Valid
                ->assertHasNoErrors(['chunk_size']);
        });

        it('submits form successfully with valid data', function () {
            $file = UploadedFile::fake()->createWithContent('test.csv', "Product,SKU\nTest Product,TEST-001");
            
            $component = Livewire::test(CreateImport::class)
                ->set('file', $file)
                ->set('import_mode', 'create_or_update')
                ->set('extract_attributes', true)
                ->set('detect_made_to_measure', true)
                ->set('dimensions_digits_only', false)
                ->set('group_by_sku', true)
                ->set('chunk_size', 75);
            
            $component->call('submit');
            
            // Should create import session
            expect(ImportSession::count())->toBe(1);
            
            $session = ImportSession::first();
            expect($session->user_id)->toBe(auth()->id());
            expect($session->original_filename)->toBe('test.csv');
            expect($session->configuration['import_mode'])->toBe('create_or_update');
            expect($session->configuration['smart_attribute_extraction'])->toBeTrue();
            expect($session->configuration['detect_made_to_measure'])->toBeTrue();
            expect($session->configuration['group_by_sku'])->toBeTrue();
            expect($session->configuration['chunk_size'])->toBe(75);
        });

        it('requires file before submission', function () {
            Livewire::test(CreateImport::class)
                ->call('submit')
                ->assertHasErrors(['file']);
        });

        it('shows upload progress during file processing', function () {
            $file = UploadedFile::fake()->create('test.csv', 1024);
            
            $component = Livewire::test(CreateImport::class)
                ->set('file', $file);
            
            // Simulate upload progress
            $component->set('uploading', true)
                ->set('uploadProgress', 50)
                ->assertSee('Uploading File')
                ->assertSee('50% complete');
        });

        it('emits import-created event after successful submission', function () {
            $file = UploadedFile::fake()->createWithContent('test.csv', "Product,SKU\nTest Product,TEST-001");
            
            Livewire::test(CreateImport::class)
                ->set('file', $file)
                ->call('submit')
                ->assertDispatched('import-created');
        });

        it('handles submission errors gracefully', function () {
            $file = UploadedFile::fake()->create('test.csv', 1024);
            
            // Mock ImportBuilder to throw exception
            $this->mock(ImportBuilder::class, function ($mock) {
                $mock->shouldReceive('create')->andThrow(new \Exception('Test error'));
            });
            
            Livewire::test(CreateImport::class)
                ->set('file', $file)
                ->call('submit')
                ->assertHasErrors()
                ->assertSee('An error occurred');
        });

        it('displays import modes with descriptions', function () {
            Livewire::test(CreateImport::class)
                ->assertSee('Create Only')
                ->assertSee('Skip existing records')
                ->assertSee('Update Existing')
                ->assertSee('Only update existing records')
                ->assertSee('Create or Update')
                ->assertSee('Create new and update existing');
        });

        it('shows processing option descriptions', function () {
            Livewire::test(CreateImport::class)
                ->assertSee('Smart Attribute Extraction')
                ->assertSee('Automatically extract colors, sizes')
                ->assertSee('Made-to-Measure Detection')
                ->assertSee('Detect MTM, bespoke, and custom products')
                ->assertSee('Digits-Only Dimensions')
                ->assertSee('Extract dimensions as pure numbers')
                ->assertSee('SKU-Based Product Grouping')
                ->assertSee('Group variants by SKU pattern');
        });

        it('updates configuration based on selected options', function () {
            $file = UploadedFile::fake()->createWithContent('test.csv', "Product,SKU\nTest Product,TEST-001");
            
            Livewire::test(CreateImport::class)
                ->set('file', $file)
                ->set('import_mode', 'update_existing')
                ->set('extract_attributes', false)
                ->set('detect_made_to_measure', true)
                ->set('dimensions_digits_only', true)
                ->set('group_by_sku', false)
                ->set('chunk_size', 25)
                ->call('submit');
            
            $session = ImportSession::first();
            $config = $session->configuration;
            
            expect($config['import_mode'])->toBe('update_existing');
            expect($config['smart_attribute_extraction'])->toBeFalse();
            expect($config['detect_made_to_measure'])->toBeTrue();
            expect($config['dimensions_digits_only'])->toBeTrue();
            expect($config['group_by_sku'])->toBeFalse();
            expect($config['chunk_size'])->toBe(25);
        });

        it('displays file information after upload', function () {
            $file = UploadedFile::fake()->createWithContent('test-file.csv', str_repeat('data,', 1000));
            
            Livewire::test(CreateImport::class)
                ->set('file', $file)
                ->assertSee('test-file.csv')
                ->assertSee('KB'); // Should show file size
        });

        it('disables submit button during upload', function () {
            $file = UploadedFile::fake()->create('test.csv', 1024);
            
            $component = Livewire::test(CreateImport::class)
                ->set('file', $file)
                ->set('uploading', true);
            
            // Should show loading state
            $component->assertSee('Creating Import...')
                ->assertSee('animate-spin'); // Loading spinner
        });

        it('supports different file formats', function () {
            $csvFile = UploadedFile::fake()->createWithContent('test.csv', "Product,SKU\nTest,001");
            $xlsxFile = UploadedFile::fake()->create('test.xlsx', 1024, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $xlsFile = UploadedFile::fake()->create('test.xls', 1024, 'application/vnd.ms-excel');
            
            // CSV
            Livewire::test(CreateImport::class)
                ->set('file', $csvFile)
                ->call('submit')
                ->assertHasNoErrors();
            
            // XLSX
            Livewire::test(CreateImport::class)
                ->set('file', $xlsxFile)
                ->call('submit')
                ->assertHasNoErrors();
            
            // XLS
            Livewire::test(CreateImport::class)
                ->set('file', $xlsFile)
                ->call('submit')
                ->assertHasNoErrors();
        });

        it('provides drag and drop interface', function () {
            Livewire::test(CreateImport::class)
                ->assertSee('drag and drop')
                ->assertSee('CSV, XLSX, XLS up to 10MB');
        });

        it('shows different states for file upload area', function () {
            $component = Livewire::test(CreateImport::class);
            
            // Initial state - no file
            $component->assertSee('Upload a file')
                ->assertSee('cloud-arrow-up'); // Upload icon
            
            // With file
            $file = UploadedFile::fake()->createWithContent('uploaded.csv', "Data");
            $component->set('file', $file)
                ->assertSee('uploaded.csv')
                ->assertSee('document-text'); // Document icon
        });

        it('handles component lifecycle correctly', function () {
            $component = Livewire::test(CreateImport::class);
            
            // Initial mount
            expect($component->get('importModes'))->toHaveCount(3);
            expect($component->get('importModes'))->toHaveKeys(['create_only', 'update_existing', 'create_or_update']);
            
            // Each mode should have name and description
            foreach ($component->get('importModes') as $mode) {
                expect($mode)->toHaveKey('name');
                expect($mode)->toHaveKey('description');
            }
        });
    });

    describe('Component Integration', function () {
        it('integrates with ImportBuilder service correctly', function () {
            $file = UploadedFile::fake()->createWithContent('integration.csv', "Product,SKU\nTest Product,TEST-001");
            
            // Spy on ImportBuilder to verify integration
            $this->spy(ImportBuilder::class, function ($spy) {
                $spy->shouldReceive('create')->once()->andReturnSelf();
                $spy->shouldReceive('fromFile')->once()->andReturnSelf(); 
                $spy->shouldReceive('withMode')->once()->with('create_or_update')->andReturnSelf();
                $spy->shouldReceive('extractAttributes')->once()->andReturnSelf();
                $spy->shouldReceive('execute')->once()->andReturn(ImportSession::factory()->make());
            });
            
            Livewire::test(CreateImport::class)
                ->set('file', $file)
                ->set('import_mode', 'create_or_update')
                ->set('extract_attributes', true)
                ->call('submit');
        });

        it('creates proper import session via builder', function () {
            $file = UploadedFile::fake()->createWithContent('builder.csv', "Product,SKU\nTest Product,TEST-001");
            
            Livewire::test(CreateImport::class)
                ->set('file', $file)
                ->set('import_mode', 'create_only')
                ->set('extract_attributes', true)
                ->set('detect_made_to_measure', true)
                ->set('dimensions_digits_only', false)
                ->set('group_by_sku', true)
                ->set('chunk_size', 100)
                ->call('submit');
            
            // Verify session was created with correct configuration
            $session = ImportSession::first();
            expect($session)->not->toBeNull();
            expect($session->configuration['import_mode'])->toBe('create_only');
            expect($session->configuration['smart_attribute_extraction'])->toBeTrue();
            expect($session->configuration['detect_made_to_measure'])->toBeTrue();
            expect($session->configuration['dimensions_digits_only'])->toBeFalse();
            expect($session->configuration['group_by_sku'])->toBeTrue();
            expect($session->configuration['chunk_size'])->toBe(100);
        });

        it('dispatches correct events for navigation', function () {
            $file = UploadedFile::fake()->createWithContent('navigation.csv', "Product,SKU\nTest Product,TEST-001");
            
            Livewire::test(CreateImport::class)
                ->set('file', $file)
                ->call('submit')
                ->assertDispatched('import-created', function ($event) {
                    return isset($event['redirect_url']) && str_contains($event['redirect_url'], '/import/');
                });
        });
    });
});