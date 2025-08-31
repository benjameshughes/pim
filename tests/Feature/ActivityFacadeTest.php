<?php

use App\Facades\Activity;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\User;

it('can log activity using facade', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    
    $this->actingAs($user);
    
    $log = Activity::log()->created($product);
    
    expect($log)->toBeInstanceOf(ActivityLog::class)
        ->and($log->event)->toBe('product.created')
        ->and($log->user_id)->toBe($user->id)
        ->and($log->getSubjectId())->toBe($product->id)
        ->and($log->getSubjectType())->toBe('Product');
});

it('can use fluent api for complex logging', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    
    $this->actingAs($user);
    
    $log = Activity::log()
        ->by($user->id)
        ->event($product, 'updated')
        ->with(['custom' => 'data'])
        ->description('Custom description')
        ->changes(['name' => ['old' => 'Old Name', 'new' => 'New Name']])
        ->save();
    
    expect($log->event)->toBe('product.updated')
        ->and($log->user_id)->toBe($user->id)
        ->and($log->description)->toBe('Custom description')
        ->and($log->changes)->toBe(['name' => ['old' => 'Old Name', 'new' => 'New Name']])
        ->and($log->getContextData()->get('custom'))->toBe('data');
});

it('can log different model types', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    
    $this->actingAs($user);
    
    $productLog = Activity::log()->created($product);
    $userLog = Activity::log()->updated($user);
    
    expect($productLog->event)->toBe('product.created')
        ->and($userLog->event)->toBe('user.updated');
});

it('can log with batch operations', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    
    $this->actingAs($user);
    
    $batchId = 'batch_123';
    
    $log = Activity::log()
        ->event($product, 'imported')
        ->batch($batchId)
        ->with(['import_details' => ['count' => 5]])
        ->save();
    
    expect($log->getContextData()->get('batch_id'))->toBe($batchId)
        ->and($log->getContextData()->get('import_details'))->toBe(['count' => 5]);
});

it('captures context automatically', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    
    $this->actingAs($user);
    
    $log = Activity::log()->created($product);
    
    expect($log->getContextData())->toHaveKey('ip')
        ->and($log->getContextData())->toHaveKey('user_agent');
});

it('can search recent activities', function () {
    $user = User::factory()->create();
    Product::factory()->count(5)->create();
    
    $this->actingAs($user);
    
    Product::all()->each(fn($product) => Activity::log()->created($product));
    
    $recent = Activity::recent(24);
    
    expect($recent)->toHaveCount(5)
        ->and($recent->first()->event)->toBe('product.created');
});

it('can search activities for specific subject', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();
    
    $this->actingAs($user);
    
    Activity::log()->created($product);
    Activity::log()->updated($product);
    Activity::log()->created($otherProduct);
    
    $logs = Activity::forSubject($product);
    
    expect($logs)->toHaveCount(2)
        ->and($logs->pluck('event')->toArray())->toBe(['product.updated', 'product.created']);
});

it('handles anonymous users gracefully', function () {
    $product = Product::factory()->create();
    
    $log = Activity::log()->created($product);
    
    expect($log->user_id)->toBeNull()
        ->and($log->event)->toBe('product.created');
});

it('can log sync operations', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    
    $this->actingAs($user);
    
    $log = Activity::log()->synced($product, 'shopify', [
        'sync_id' => 'sync_123',
        'status' => 'success'
    ]);
    
    expect($log->event)->toBe('product.synced')
        ->and($log->getContextData()->get('channel'))->toBe('shopify')
        ->and($log->getContextData()->get('sync_id'))->toBe('sync_123');
});