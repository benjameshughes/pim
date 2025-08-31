<?php

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\User;
use App\Traits\WithActivityLogs;

// Create a test class that uses the trait
class TestActionWithLogs
{
    use WithActivityLogs;

    public function testLogCreated($model, $details = [])
    {
        return $this->logCreated($model, $details);
    }

    public function testLogUpdated($model, $changes = [], $description = null)
    {
        return $this->logUpdated($model, $changes, $description);
    }

    public function testLogDeleted($model, $reason = null)
    {
        return $this->logDeleted($model, $reason);
    }

    public function testLogCustom($event, $model, $data = [], $description = null)
    {
        return $this->logCustom($event, $model, $data, $description);
    }

    public function testLogBulkOperation($operation, $models, $details = [])
    {
        return $this->logBulkOperation($operation, $models, $details);
    }
}

beforeEach(function () {
    $this->testAction = new TestActionWithLogs;
});

it('can log created activity', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    $this->actingAs($user);

    $log = $this->testAction->testLogCreated($product);

    expect($log)->toBeInstanceOf(ActivityLog::class)
        ->and($log->event)->toBe('product.created')
        ->and($log->user_id)->toBe($user->id)
        ->and($log->getSubjectId())->toBe($product->id);
});

it('can log created activity with details', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    $this->actingAs($user);

    $details = ['import_batch' => 'batch_123', 'source' => 'csv'];
    $log = $this->testAction->testLogCreated($product, $details);

    expect($log->getContextData()->get('import_batch'))->toBe('batch_123')
        ->and($log->getContextData()->get('source'))->toBe('csv');
});

it('can log updated activity with changes', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['name' => 'Old Name']);

    $this->actingAs($user);

    $changes = [
        'old' => ['name' => 'Old Name', 'status' => 'draft'],
        'new' => ['name' => 'New Name', 'status' => 'active'],
    ];

    $log = $this->testAction->testLogUpdated($product, $changes);

    expect($log->event)->toBe('product.updated')
        ->and($log->changes)->toBe($changes);
});

it('can log updated activity with description', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    $this->actingAs($user);

    $description = 'Updated product pricing and status';
    $log = $this->testAction->testLogUpdated($product, [], $description);

    expect($log->description)->toBe($description);
});

it('can log deleted activity with reason', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    $this->actingAs($user);

    $reason = 'Product discontinued';
    $log = $this->testAction->testLogDeleted($product, $reason);

    expect($log->event)->toBe('product.deleted')
        ->and($log->getContextData()->get('reason'))->toBe($reason);
});

it('can log custom activities', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    $this->actingAs($user);

    $data = ['sync_id' => 'sync_456', 'marketplace' => 'amazon'];
    $description = 'Synced product to Amazon marketplace';

    $log = $this->testAction->testLogCustom('synced', $product, $data, $description);

    expect($log->event)->toBe('product.synced')
        ->and($log->description)->toBe($description)
        ->and($log->getContextData()->get('sync_id'))->toBe('sync_456')
        ->and($log->getContextData()->get('marketplace'))->toBe('amazon');
});

it('can log bulk operations', function () {
    $user = User::factory()->create();
    $products = Product::factory()->count(3)->create();

    $this->actingAs($user);

    $details = ['import_file' => 'products.csv'];
    $log = $this->testAction->testLogBulkOperation('imported', $products->toArray(), $details);

    // Should create individual logs for each product plus a bulk summary
    $allLogs = ActivityLog::all();

    expect($allLogs)->toHaveCount(4) // 3 individual + 1 bulk summary
        ->and($allLogs->where('event', 'product.imported'))->toHaveCount(3)
        ->and($allLogs->where('event', 'product.bulk_imported'))->toHaveCount(1);

    $bulkLog = $allLogs->where('event', 'product.bulk_imported')->first();
    expect($bulkLog->getContextData()->get('count'))->toBe(3)
        ->and($bulkLog->getContextData()->get('details')['import_file'])->toBe('products.csv');
});

it('handles different model types', function () {
    $user = User::factory()->create();
    $anotherUser = User::factory()->create();

    $this->actingAs($user);

    $log = $this->testAction->testLogCreated($anotherUser);

    expect($log->event)->toBe('user.created')
        ->and($log->getSubjectType())->toBe('User');
});

it('works without authenticated user', function () {
    $product = Product::factory()->create();

    // No user authenticated
    $log = $this->testAction->testLogCreated($product);

    expect($log->user_id)->toBeNull()
        ->and($log->event)->toBe('product.created');
});

it('logActivity returns configured logger', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $reflection = new ReflectionClass($this->testAction);
    $method = $reflection->getMethod('logActivity');
    $method->setAccessible(true);

    $logger = $method->invoke($this->testAction);

    expect($logger)->toBeInstanceOf(\App\Services\ActivityLogger::class);
});

it('can log imported activities', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    $this->actingAs($user);

    $importDetails = [
        'batch_id' => 'import_789',
        'source_file' => 'products.xlsx',
        'row_number' => 15,
    ];

    $reflection = new ReflectionClass($this->testAction);
    $method = $reflection->getMethod('logImported');
    $method->setAccessible(true);

    $log = $method->invoke($this->testAction, $product, $importDetails);

    expect($log->event)->toBe('product.imported')
        ->and($log->getContextData()->get('batch_id'))->toBe('import_789')
        ->and($log->getContextData()->get('source_file'))->toBe('products.xlsx');
});

it('can log synced activities', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    $this->actingAs($user);

    $syncDetails = [
        'external_id' => 'shopify_123',
        'status' => 'success',
        'response_time' => 250,
    ];

    $reflection = new ReflectionClass($this->testAction);
    $method = $reflection->getMethod('logSynced');
    $method->setAccessible(true);

    $log = $method->invoke($this->testAction, $product, 'shopify', $syncDetails);

    expect($log->event)->toBe('product.synced')
        ->and($log->getContextData()->get('channel'))->toBe('shopify')
        ->and($log->getContextData()->get('external_id'))->toBe('shopify_123');
});
