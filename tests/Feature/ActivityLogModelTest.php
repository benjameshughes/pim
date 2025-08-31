<?php

use App\Models\ActivityLog;
use App\Models\User;

it('has correct fillable attributes', function () {
    $log = new ActivityLog;

    expect($log->getFillable())->toEqual([
        'event',
        'user_id',
        'occurred_at',
        'data',
    ]);
});

it('casts attributes correctly', function () {
    $log = ActivityLog::create([
        'event' => 'product.created',
        'occurred_at' => now(),
        'data' => ['test' => 'data'],
    ]);

    expect($log->occurred_at)->toBeInstanceOf(Carbon\Carbon::class)
        ->and($log->data)->toBeArray()
        ->and($log->created_at)->toBeInstanceOf(Carbon\Carbon::class);
});

it('belongs to user', function () {
    $user = User::factory()->create();
    $log = ActivityLog::factory()->create(['user_id' => $user->id]);

    expect($log->user)->toBeInstanceOf(User::class)
        ->and($log->user->id)->toBe($user->id);
});

it('can have null user', function () {
    $log = ActivityLog::factory()->create(['user_id' => null]);

    expect($log->user)->toBeNull();
});

it('gets user name attribute', function () {
    $user = User::factory()->create(['name' => 'John Doe']);
    $log = ActivityLog::factory()->create(['user_id' => $user->id]);

    expect($log->user_name)->toBe('John Doe');
});

it('gets subject name from data', function () {
    $log = ActivityLog::factory()->create([
        'data' => [
            'subject' => [
                'id' => 1,
                'type' => 'Product',
                'name' => 'Test Product',
            ],
        ],
    ]);

    expect($log->subject_name)->toBe('Test Product');
});

it('gets subject title when no name available', function () {
    $log = ActivityLog::factory()->create([
        'data' => [
            'subject' => [
                'id' => 1,
                'type' => 'Product',
                'title' => 'Test Title',
            ],
        ],
    ]);

    expect($log->subject_name)->toBe('Test Title');
});

it('falls back to id when no name or title', function () {
    $log = ActivityLog::factory()->create([
        'data' => [
            'subject' => [
                'id' => 123,
                'type' => 'Product',
            ],
        ],
    ]);

    expect($log->subject_name)->toBe('#123');
});

it('gets subject id and type', function () {
    $log = ActivityLog::factory()->create([
        'data' => [
            'subject' => [
                'id' => 456,
                'type' => 'Product',
            ],
        ],
    ]);

    expect($log->getSubjectId())->toBe(456)
        ->and($log->getSubjectType())->toBe('Product');
});

it('gets context data excluding subject', function () {
    $log = ActivityLog::factory()->create([
        'data' => [
            'subject' => ['id' => 1],
            'changes' => ['name' => 'New Name'],
            'metadata' => ['batch_id' => 'batch_123'],
        ],
    ]);

    $context = $log->getContextData();

    expect($context->has('subject'))->toBeFalse()
        ->and($context->get('changes'))->toBe(['name' => 'New Name'])
        ->and($context->get('metadata'))->toBe(['batch_id' => 'batch_123']);
});

it('gets changes attribute', function () {
    $changes = ['old' => ['status' => 'draft'], 'new' => ['status' => 'active']];
    $log = ActivityLog::factory()->create([
        'data' => ['changes' => $changes],
    ]);

    expect($log->changes)->toBe($changes);
});

it('gets description attribute', function () {
    $log = ActivityLog::factory()->create([
        'data' => ['description' => 'Custom description'],
    ]);

    expect($log->description)->toBe('Custom description');
});

it('generates description when none provided', function () {
    $log = ActivityLog::factory()->create([
        'event' => 'product.created',
        'data' => [
            'subject' => [
                'id' => 1,
                'type' => 'Product',
                'name' => 'Test Product',
            ],
        ],
    ]);

    expect($log->description)->toBe('Product Created Test Product');
});

it('scopes by event', function () {
    ActivityLog::factory()->create(['event' => 'product.created']);
    ActivityLog::factory()->create(['event' => 'product.updated']);
    ActivityLog::factory()->create(['event' => 'user.created']);

    $productCreated = ActivityLog::byEvent('product.created')->get();

    expect($productCreated)->toHaveCount(1)
        ->and($productCreated->first()->event)->toBe('product.created');
});

it('scopes by user', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    ActivityLog::factory()->count(2)->create(['user_id' => $user1->id]);
    ActivityLog::factory()->create(['user_id' => $user2->id]);

    $user1Logs = ActivityLog::byUser($user1->id)->get();

    expect($user1Logs)->toHaveCount(2);
});

it('scopes for subject', function () {
    ActivityLog::factory()->create([
        'data' => ['subject' => ['type' => 'Product', 'id' => 1]],
    ]);
    ActivityLog::factory()->create([
        'data' => ['subject' => ['type' => 'Product', 'id' => 2]],
    ]);
    ActivityLog::factory()->create([
        'data' => ['subject' => ['type' => 'User', 'id' => 1]],
    ]);

    $productLogs = ActivityLog::forSubject('Product', 1)->get();

    expect($productLogs)->toHaveCount(1);
});

it('scopes recent activities', function () {
    // Old log
    ActivityLog::factory()->create([
        'occurred_at' => now()->subDays(2),
    ]);

    // Recent logs
    ActivityLog::factory()->count(2)->create([
        'occurred_at' => now()->subHours(12),
    ]);

    $recent = ActivityLog::recent(24)->get();

    expect($recent)->toHaveCount(2);
});

it('can search with multiple filters', function () {
    $user = User::factory()->create();

    ActivityLog::factory()->create([
        'event' => 'product.created',
        'user_id' => $user->id,
        'occurred_at' => now()->subHours(12),
        'data' => ['subject' => ['type' => 'Product', 'id' => 1]],
    ]);

    ActivityLog::factory()->create([
        'event' => 'product.updated',
        'user_id' => $user->id,
        'occurred_at' => now()->subDays(2),
    ]);

    $results = ActivityLog::search([
        'event' => 'product.created',
        'user_id' => $user->id,
        'hours' => 24,
    ]);

    expect($results)->toHaveCount(1)
        ->and($results->first()->event)->toBe('product.created');
});

it('can search by text in various fields', function () {
    ActivityLog::factory()->create([
        'event' => 'product.created',
        'data' => [
            'subject' => ['name' => 'Special Product'],
            'description' => 'Created a special item',
        ],
    ]);

    ActivityLog::factory()->create([
        'event' => 'user.updated',
        'data' => ['subject' => ['name' => 'Regular User']],
    ]);

    $results = ActivityLog::search(['search' => 'special']);

    expect($results)->toHaveCount(1)
        ->and($results->first()->event)->toBe('product.created');
});

it('can get activity stats', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    // Create various activities
    ActivityLog::factory()->count(3)->create([
        'event' => 'product.created',
        'user_id' => $user1->id,
        'occurred_at' => now()->subHours(12),
    ]);

    ActivityLog::factory()->count(2)->create([
        'event' => 'product.updated',
        'user_id' => $user2->id,
        'occurred_at' => now()->subHours(6),
    ]);

    $stats = ActivityLog::getActivityStats(24);

    expect($stats['total_activities'])->toBe(5)
        ->and($stats['unique_users'])->toBe(2)
        ->and($stats['top_events']['product.created'])->toBe(3)
        ->and($stats['top_events']['product.updated'])->toBe(2);
});

it('can get recent activities by user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    ActivityLog::factory()->count(3)->create(['user_id' => $user->id]);
    ActivityLog::factory()->create(['user_id' => $otherUser->id]);

    $userLogs = ActivityLog::getRecentByUser($user->id, 10);

    expect($userLogs)->toHaveCount(3);
});
