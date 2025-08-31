<?php

use App\Services\Marketplace\ValueObjects\SyncResult;

describe('SyncResult Value Object', function () {

    it('can create successful result', function () {
        $result = SyncResult::success('Operation completed successfully');

        expect($result->isSuccess())->toBeTrue();
        expect($result->getMessage())->toBe('Operation completed successfully');
        expect($result->getErrors())->toBeEmpty();
        expect($result->getData())->toBeEmpty();
    });

    it('can create successful result with data', function () {
        $data = ['products_created' => 2, 'variants_updated' => 4];
        $result = SyncResult::success('Products created', $data);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getMessage())->toBe('Products created');
        expect($result->getData())->toBe($data);
    });

    it('can create failure result', function () {
        $result = SyncResult::failure('Operation failed');

        expect($result->isSuccess())->toBeFalse();
        expect($result->getMessage())->toBe('Operation failed');
        expect($result->getErrors())->toBeEmpty();
    });

    it('can create failure result with errors', function () {
        $errors = ['Invalid SKU', 'Price cannot be negative'];
        $result = SyncResult::failure('Validation failed', $errors);

        expect($result->isSuccess())->toBeFalse();
        expect($result->getMessage())->toBe('Validation failed');
        expect($result->getErrors())->toBe($errors);
    });

    it('can create result with all parameters', function () {
        $data = ['processed' => 10];
        $errors = ['Warning: Low stock'];
        $metadata = ['sync_duration' => 1.5];

        $result = new SyncResult(
            success: true,
            message: 'Partial success',
            data: $data,
            errors: $errors,
            metadata: $metadata
        );

        expect($result->isSuccess())->toBeTrue();
        expect($result->getMessage())->toBe('Partial success');
        expect($result->getData())->toBe($data);
        expect($result->getErrors())->toBe($errors);
        expect($result->getMetadata())->toBe($metadata);
    });

    it('can add data to existing result', function () {
        $result = SyncResult::success('Initial success');
        $result->addData('new_key', 'new_value');

        expect($result->getData())->toHaveKey('new_key', 'new_value');
    });

    it('can add multiple data items', function () {
        $result = SyncResult::success('Success');
        $result->addData('key1', 'value1');
        $result->addData('key2', 'value2');

        expect($result->getData())->toBe([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);
    });

    it('can add errors to existing result', function () {
        $result = SyncResult::failure('Initial error', ['first_error']);
        $result->addError('second_error');

        expect($result->getErrors())->toBe(['first_error', 'second_error']);
    });

    it('can merge data from another result', function () {
        $result1 = SyncResult::success('First', ['key1' => 'value1']);
        $result2 = SyncResult::success('Second', ['key2' => 'value2']);

        $result1->mergeData($result2->getData());

        expect($result1->getData())->toBe([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);
    });

    it('can be converted to array', function () {
        $result = SyncResult::success(
            'Test message',
            ['data_key' => 'data_value'],
            ['error1'],
            ['meta_key' => 'meta_value']
        );

        $array = $result->toArray();

        expect($array)->toBe([
            'success' => true,
            'message' => 'Test message',
            'data' => ['data_key' => 'data_value'],
            'errors' => ['error1'],
            'metadata' => ['meta_key' => 'meta_value'],
        ]);
    });

    it('can be converted to JSON', function () {
        $result = SyncResult::success('JSON test', ['key' => 'value']);

        $json = $result->toJson();

        expect($json)->toBeString();

        $decoded = json_decode($json, true);
        expect($decoded)->toHaveKey('success', true);
        expect($decoded)->toHaveKey('message', 'JSON test');
        expect($decoded)->toHaveKey('data', ['key' => 'value']);
    });

    it('handles empty data gracefully', function () {
        $result = SyncResult::success('Empty data test');

        expect($result->getData())->toBeArray();
        expect($result->getData())->toBeEmpty();
    });

    it('handles null values in constructor', function () {
        $result = new SyncResult(
            success: true,
            message: 'Null test',
            data: null,
            errors: null,
            metadata: null
        );

        expect($result->getData())->toBeArray();
        expect($result->getErrors())->toBeArray();
        expect($result->getMetadata())->toBeArray();
    });

    it('can chain method calls', function () {
        $result = SyncResult::success('Chaining test')
            ->addData('key1', 'value1')
            ->addData('key2', 'value2')
            ->addError('warning');

        expect($result->getData())->toHaveKey('key1', 'value1');
        expect($result->getData())->toHaveKey('key2', 'value2');
        expect($result->getErrors())->toContain('warning');
    });
});
