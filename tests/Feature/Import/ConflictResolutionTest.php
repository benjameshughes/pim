<?php

use App\Services\Import\Conflicts\ConflictResolver;
use App\Services\Import\Conflicts\ConflictResolution;
use App\Services\Import\Conflicts\DuplicateSkuResolver;
use App\Services\Import\Conflicts\DuplicateBarcodeResolver;
use App\Services\Import\Conflicts\VariantConstraintResolver;
use App\Services\Import\Conflicts\UniqueConstraintResolver;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantBarcode;
use Illuminate\Database\QueryException;

describe('Conflict Resolution System', function () {
    beforeEach(function () {
        $this->actingAs(\App\Models\User::factory()->create());
    });

    describe('ConflictResolver', function () {
        it('identifies conflict types correctly', function () {
            $resolver = ConflictResolver::create();
            
            // SKU conflict
            $skuException = new QueryException(
                'default',
                'INSERT INTO product_variants...',
                [],
                new \Exception('SQLSTATE[23000]: Integrity constraint violation: Duplicate entry \'TEST-001\' for key \'product_variants_sku_unique\'')
            );
            
            $resolution = $resolver->resolve($skuException, ['variant_sku' => 'TEST-001']);
            expect($resolution)->toBeInstanceOf(ConflictResolution::class);
            
            $stats = $resolver->getStatistics();
            expect($stats['conflicts_detected'])->toBe(1);
        });

        it('routes conflicts to appropriate resolvers', function () {
            $resolver = ConflictResolver::create([
                'sku_resolution' => ['strategy' => 'skip'],
                'barcode_resolution' => ['strategy' => 'remove_barcode'],
            ]);

            // Test SKU conflict routing
            $skuException = new QueryException(
                'default',
                'INSERT INTO product_variants...',
                [],
                new \Exception('SQLSTATE[23000]: for key \'product_variants_sku_unique\'')
            );
            
            $resolution = $resolver->resolve($skuException, ['variant_sku' => 'TEST-001']);
            expect($resolution->shouldSkip())->toBeTrue();
        });

        it('tracks resolution statistics', function () {
            $resolver = ConflictResolver::create();
            
            $exception = new QueryException(
                'default',
                'INSERT INTO product_variants...',
                [],
                new \Exception('SQLSTATE[23000]: for key \'product_variants_sku_unique\'')
            );

            $resolver->resolve($exception, ['variant_sku' => 'TEST-001']);
            $resolver->resolve($exception, ['variant_sku' => 'TEST-002']);

            $stats = $resolver->getStatistics();
            expect($stats['conflicts_detected'])->toBe(2);
            expect($stats)->toHaveKey('resolution_strategies');
        });
    });

    describe('DuplicateSkuResolver', function () {
        beforeEach(function () {
            $this->existingVariant = ProductVariant::factory()->create(['sku' => 'EXISTING-001']);
        });

        it('skips in create_only mode by default', function () {
            $resolver = new DuplicateSkuResolver(['strategy' => 'use_existing']);
            
            $conflictData = [
                'conflicting_value' => 'EXISTING-001',
                'constraint' => 'product_variants_sku_unique',
            ];
            
            $context = ['import_mode' => 'create_only', 'variant_sku' => 'EXISTING-001'];
            
            $resolution = $resolver->resolve($conflictData, $context);
            
            expect($resolution->shouldSkip())->toBeTrue();
            expect($resolution->getMetadataValue('existing_variant_id'))->toBe($this->existingVariant->id);
        });

        it('generates unique SKU when configured', function () {
            $resolver = new DuplicateSkuResolver([
                'strategy' => 'generate_unique',
                'generate_unique_sku' => true,
            ]);
            
            $conflictData = ['conflicting_value' => 'EXISTING-001'];
            $context = ['import_mode' => 'create_only', 'variant_sku' => 'EXISTING-001'];
            
            $resolution = $resolver->resolve($conflictData, $context);
            
            expect($resolution->shouldRetry())->toBeTrue();
            expect($resolution->hasModifiedData())->toBeTrue();
            
            $newSku = $resolution->getModifiedValue('variant_sku');
            expect($newSku)->not->toBe('EXISTING-001');
            expect($newSku)->toStartWith('EXISTING-001');
        });

        it('updates existing variant when allowed', function () {
            $resolver = new DuplicateSkuResolver([
                'strategy' => 'update_existing',
                'allow_updates' => true,
            ]);
            
            $conflictData = ['conflicting_value' => 'EXISTING-001'];
            $context = [
                'variant_sku' => 'EXISTING-001',
                'stock_level' => 50,
                'package_weight' => 2.5,
            ];
            
            $resolution = $resolver->resolve($conflictData, $context);
            
            expect($resolution->shouldUpdate())->toBeTrue();
            expect($resolution->hasModifiedData())->toBeTrue();
            expect($resolution->getModifiedData())->toHaveKey('stock_level');
        });
    });

    describe('DuplicateBarcodeResolver', function () {
        beforeEach(function () {
            $this->variant = ProductVariant::factory()->create();
            $this->existingBarcode = VariantBarcode::factory()->create([
                'product_variant_id' => $this->variant->id,
                'barcode' => '1234567890123',
            ]);
        });

        it('skips by default', function () {
            $resolver = new DuplicateBarcodeResolver(['strategy' => 'skip']);
            
            $conflictData = [
                'conflicting_value' => '1234567890123',
                'constraint' => 'variant_barcodes_barcode_unique',
            ];
            
            $context = ['barcode' => '1234567890123', 'variant_sku' => 'NEW-001'];
            
            $resolution = $resolver->resolve($conflictData, $context);
            
            expect($resolution->shouldSkip())->toBeTrue();
            expect($resolution->getMetadataValue('existing_variant_id'))->toBe($this->variant->id);
        });

        it('removes barcode when configured', function () {
            $resolver = new DuplicateBarcodeResolver(['strategy' => 'remove_barcode']);
            
            $conflictData = ['conflicting_value' => '1234567890123'];
            $context = ['barcode' => '1234567890123'];
            
            $resolution = $resolver->resolve($conflictData, $context);
            
            expect($resolution->shouldRetry())->toBeTrue();
            expect($resolution->getModifiedValue('barcode'))->toBeNull();
        });

        it('handles reassignment when allowed', function () {
            $resolver = new DuplicateBarcodeResolver([
                'strategy' => 'reassign',
                'allow_reassignment' => true,
            ]);
            
            $conflictData = ['conflicting_value' => '1234567890123'];
            $context = [
                'barcode' => '1234567890123',
                'variant_sku' => 'DIFFERENT-001',
            ];
            
            $resolution = $resolver->resolve($conflictData, $context);
            
            expect($resolution->isResolved())->toBeTrue();
            expect($resolution->getMetadataValue('reassign_barcode'))->toBeTrue();
        });

        it('uses existing assignment for same variant', function () {
            $resolver = new DuplicateBarcodeResolver([
                'strategy' => 'reassign',
                'allow_reassignment' => true,
            ]);
            
            $conflictData = ['conflicting_value' => '1234567890123'];
            $context = [
                'barcode' => '1234567890123',
                'variant_sku' => $this->variant->sku,
            ];
            
            $resolution = $resolver->resolve($conflictData, $context);
            
            expect($resolution->shouldSkip())->toBeTrue();
            expect($resolution->getMetadataValue('variant_already_owns_barcode'))->toBeTrue();
        });
    });

    describe('VariantConstraintResolver', function () {
        beforeEach(function () {
            $this->product = Product::factory()->create();
            $this->existingVariant = ProductVariant::factory()->create([
                'product_id' => $this->product->id,
            ]);
            
            // Set color and size attributes
            $this->existingVariant->setVariantAttributeValue('color', 'red', 'string', 'core');
            $this->existingVariant->setVariantAttributeValue('size', 'large', 'string', 'core');
        });

        it('skips in create_only mode', function () {
            $resolver = new VariantConstraintResolver(['strategy' => 'use_existing']);
            
            $conflictData = ['constraint' => 'product_variants_product_id_color_size_unique'];
            $context = [
                'product_id' => $this->product->id,
                'variant_color' => 'red',
                'variant_size' => 'large',
                'import_mode' => 'create_only',
            ];
            
            $resolution = $resolver->resolve($conflictData, $context);
            
            expect($resolution->shouldSkip())->toBeTrue();
            expect($resolution->getMetadataValue('existing_variant_id'))->toBe($this->existingVariant->id);
        });

        it('merges data when configured', function () {
            $resolver = new VariantConstraintResolver([
                'strategy' => 'merge_data',
                'allow_merging' => true,
                'allow_dimension_updates' => true,
            ]);
            
            $conflictData = ['constraint' => 'product_variants_product_id_color_size_unique'];
            $context = [
                'product_id' => $this->product->id,
                'variant_color' => 'red',
                'variant_size' => 'large',
                'stock_level' => 100,
                'extracted_width' => 150,
                'made_to_measure' => true,
            ];
            
            $resolution = $resolver->resolve($conflictData, $context);
            
            expect($resolution->shouldUpdate())->toBeTrue();
            expect($resolution->hasModifiedData())->toBeTrue();
            
            $mergeData = $resolution->getModifiedData();
            expect($mergeData)->toHaveKey('stock_level');
            expect($mergeData)->toHaveKey('variant_attributes');
        });

        it('attempts attribute modification when configured', function () {
            $resolver = new VariantConstraintResolver(['strategy' => 'modify_attributes']);
            
            $conflictData = ['constraint' => 'product_variants_product_id_color_size_unique'];
            $context = [
                'product_id' => $this->product->id,
                'variant_color' => 'red',
                'variant_size' => 'large',
            ];
            
            $resolution = $resolver->resolve($conflictData, $context);
            
            // Should attempt to modify either color or size
            if ($resolution->shouldRetry()) {
                expect($resolution->hasModifiedData())->toBeTrue();
                $modified = $resolution->getModifiedData();
                expect($modified)->toHaveAnyKeys(['variant_color', 'variant_size']);
            } else {
                // If modification not possible, should skip
                expect($resolution->shouldSkip())->toBeTrue();
            }
        });
    });

    describe('UniqueConstraintResolver', function () {
        it('skips by default', function () {
            $resolver = new UniqueConstraintResolver(['default_strategy' => 'skip']);
            
            $conflictData = [
                'constraint' => 'some_table_field_unique',
                'conflicting_value' => 'duplicate-value',
            ];
            
            $context = [];
            
            $resolution = $resolver->resolve($conflictData, $context);
            
            expect($resolution->shouldSkip())->toBeTrue();
            expect($resolution->getReason())->toContain('Unique constraint violation');
        });

        it('generates unique values when configured', function () {
            $resolver = new UniqueConstraintResolver([
                'default_strategy' => 'generate_unique',
            ]);
            
            $conflictData = [
                'constraint' => 'products_slug_unique',
                'conflicting_value' => 'existing-slug',
            ];
            
            $context = [];
            
            $resolution = $resolver->resolve($conflictData, $context);
            
            expect($resolution->shouldRetry())->toBeTrue();
            expect($resolution->hasModifiedData())->toBeTrue();
            
            $newValue = $resolution->getModifiedValue('slug');
            expect($newValue)->not->toBe('existing-slug');
            expect($newValue)->toContain('existing-slug');
        });

        it('uses field-specific strategies', function () {
            $resolver = new UniqueConstraintResolver([
                'field_strategies' => [
                    'email' => 'generate_unique',
                    'name' => 'append_suffix',
                ]
            ]);
            
            // Test email strategy
            $emailConflictData = [
                'constraint' => 'users_email_unique',
                'conflicting_value' => 'test@example.com',
            ];
            
            $resolution = $resolver->resolve($emailConflictData, []);
            expect($resolution->shouldRetry())->toBeTrue();
            
            $newEmail = $resolution->getModifiedValue('email');
            expect($newEmail)->toContain('@example.com');
            expect($newEmail)->not->toBe('test@example.com');
        });

        it('removes field when configured', function () {
            $resolver = new UniqueConstraintResolver([
                'default_strategy' => 'remove_field',
            ]);
            
            $conflictData = [
                'constraint' => 'products_optional_field_unique',
                'conflicting_value' => 'duplicate',
            ];
            
            $resolution = $resolver->resolve($conflictData, []);
            
            expect($resolution->shouldRetry())->toBeTrue();
            expect($resolution->getModifiedValue('optional_field'))->toBeNull();
        });
    });

    describe('ConflictResolution', function () {
        it('creates different resolution types correctly', function () {
            $skip = ConflictResolution::skip('Duplicate found');
            expect($skip->shouldSkip())->toBeTrue();
            expect($skip->isResolved())->toBeTrue();
            
            $update = ConflictResolution::updateExisting(['field' => 'new_value'], 'Merge data');
            expect($update->shouldUpdate())->toBeTrue();
            expect($update->hasModifiedData())->toBeTrue();
            
            $retry = ConflictResolution::retryWithModifiedData(['sku' => 'new-sku'], 'Generated unique');
            expect($retry->shouldRetry())->toBeTrue();
            expect($retry->getModifiedValue('sku'))->toBe('new-sku');
            
            $failed = ConflictResolution::failed('Cannot resolve');
            expect($failed->shouldFail())->toBeTrue();
            expect($failed->isResolved())->toBeFalse();
        });

        it('handles metadata correctly', function () {
            $resolution = ConflictResolution::skip('Test')
                ->withMetadata('original_value', 'test')
                ->withMetadata('strategy_used', 'skip');
            
            expect($resolution->getMetadataValue('original_value'))->toBe('test');
            expect($resolution->getMetadataValue('strategy_used'))->toBe('skip');
            expect($resolution->getMetadata())->toHaveKey('original_value');
        });

        it('converts to array correctly', function () {
            $resolution = ConflictResolution::retryWithModifiedData(
                ['field' => 'value'],
                'Test reason',
                ['meta' => 'data']
            );
            
            $array = $resolution->toArray();
            
            expect($array)->toHaveKey('resolved');
            expect($array)->toHaveKey('action');
            expect($array)->toHaveKey('strategy');
            expect($array)->toHaveKey('reason');
            expect($array)->toHaveKey('modified_data');
            expect($array)->toHaveKey('metadata');
            expect($array['has_modified_data'])->toBeTrue();
        });
    });
});