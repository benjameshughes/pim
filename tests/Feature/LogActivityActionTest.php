<?php

use App\Actions\Activity\LogActivityAction;
use App\Models\ActivityLog;
use App\Models\User;

it('can create activity log with basic data', function () {
    $action = app(LogActivityAction::class);
    $user = User::factory()->create();
    
    $log = $action->createLog(
        event: 'product.created',
        data: ['subject' => ['id' => 1, 'type' => 'Product', 'name' => 'Test Product']],
        userId: $user->id
    );
    
    expect($log)->toBeInstanceOf(ActivityLog::class)
        ->and($log->event)->toBe('product.created')
        ->and($log->user_id)->toBe($user->id)
        ->and($log->data)->toHaveKey('subject')
        ->and($log->occurred_at)->not->toBeNull();
});

it('can create activity log without user', function () {
    $action = app(LogActivityAction::class);
    
    $log = $action->createLog(
        event: 'system.maintenance',
        data: ['type' => 'automated']
    );
    
    expect($log->user_id)->toBeNull()
        ->and($log->event)->toBe('system.maintenance')
        ->and($log->data['type'])->toBe('automated');
});

it('can create activity log with custom timestamp', function () {
    $action = app(LogActivityAction::class);
    $customTime = now()->subHours(2);
    
    $log = $action->createLog(
        event: 'product.updated',
        data: ['changes' => ['name' => 'Updated Name']],
        occurredAt: $customTime
    );
    
    expect($log->occurred_at->format('Y-m-d H:i:s'))->toBe($customTime->format('Y-m-d H:i:s'));
});

it('handles complex data structures', function () {
    $action = app(LogActivityAction::class);
    $user = User::factory()->create();
    
    $complexData = [
        'subject' => [
            'id' => 1,
            'type' => 'Product',
            'name' => 'Complex Product'
        ],
        'changes' => [
            'old' => ['price' => 100, 'status' => 'draft'],
            'new' => ['price' => 150, 'status' => 'active']
        ],
        'metadata' => [
            'batch_id' => 'batch_123',
            'source' => 'api'
        ]
    ];
    
    $log = $action->createLog(
        event: 'product.bulk_updated',
        data: $complexData,
        userId: $user->id
    );
    
    expect($log->data['subject']['name'])->toBe('Complex Product')
        ->and($log->data['changes']['old']['price'])->toBe(100)
        ->and($log->data['metadata']['batch_id'])->toBe('batch_123');
});

it('uses current user when no userId provided', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    
    $action = app(LogActivityAction::class);
    
    $log = $action->createLog(
        event: 'user.login',
        data: ['ip' => '127.0.0.1']
    );
    
    expect($log->user_id)->toBe($user->id);
});

it('stores data as json in database', function () {
    $action = app(LogActivityAction::class);
    
    $data = [
        'subject' => ['id' => 1, 'name' => 'Test'],
        'array_data' => [1, 2, 3],
        'nested' => ['level1' => ['level2' => 'value']]
    ];
    
    $log = $action->createLog(
        event: 'test.event',
        data: $data
    );
    
    // Refresh from database to ensure JSON casting works
    $fresh = ActivityLog::find($log->id);
    
    expect($fresh->data)->toBe($data)
        ->and($fresh->data['array_data'])->toBe([1, 2, 3])
        ->and($fresh->data['nested']['level1']['level2'])->toBe('value');
});

it('handles empty data gracefully', function () {
    $action = app(LogActivityAction::class);
    
    $log = $action->createLog(
        event: 'simple.event',
        data: []
    );
    
    expect($log->data)->toBe([])
        ->and($log->event)->toBe('simple.event');
});

it('validates required parameters', function () {
    $action = app(LogActivityAction::class);
    
    expect(fn() => $action->createLog('', []))
        ->toThrow(InvalidArgumentException::class);
});