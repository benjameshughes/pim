<?php

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\User;

it('can search activities by event', function () {
    ActivityLog::factory()->count(3)->create(['event' => 'product.created']);
    ActivityLog::factory()->count(2)->create(['event' => 'product.updated']);
    ActivityLog::factory()->create(['event' => 'user.created']);

    $results = ActivityLog::search(['event' => 'product.created']);

    expect($results)->toHaveCount(3)
        ->and($results->every(fn ($log) => $log->event === 'product.created'))->toBeTrue();
});

it('can search activities by user', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    ActivityLog::factory()->count(4)->create(['user_id' => $user1->id]);
    ActivityLog::factory()->count(2)->create(['user_id' => $user2->id]);
    ActivityLog::factory()->create(['user_id' => null]); // System activity

    $results = ActivityLog::search(['user_id' => $user1->id]);

    expect($results)->toHaveCount(4)
        ->and($results->every(fn ($log) => $log->user_id === $user1->id))->toBeTrue();
});

it('can search activities by time period', function () {
    // Recent activities
    ActivityLog::factory()->count(3)->create([
        'occurred_at' => now()->subHours(12),
    ]);

    // Old activities
    ActivityLog::factory()->count(2)->create([
        'occurred_at' => now()->subDays(2),
    ]);

    $results = ActivityLog::search(['hours' => 24]);

    expect($results)->toHaveCount(3);
});

it('can search activities by subject type and id', function () {
    $product1 = Product::factory()->create();
    $product2 = Product::factory()->create();

    ActivityLog::factory()->count(3)->create([
        'data' => [
            'subject' => [
                'type' => 'Product',
                'id' => $product1->id,
                'name' => $product1->name,
            ],
        ],
    ]);

    ActivityLog::factory()->count(2)->create([
        'data' => [
            'subject' => [
                'type' => 'Product',
                'id' => $product2->id,
                'name' => $product2->name,
            ],
        ],
    ]);

    ActivityLog::factory()->create([
        'data' => [
            'subject' => [
                'type' => 'User',
                'id' => 1,
                'name' => 'John Doe',
            ],
        ],
    ]);

    $results = ActivityLog::search([
        'subject_type' => 'Product',
        'subject_id' => $product1->id,
    ]);

    expect($results)->toHaveCount(3);
});

it('can search activities by text in multiple fields', function () {
    ActivityLog::factory()->create([
        'event' => 'product.created',
        'data' => [
            'subject' => ['name' => 'Special Product'],
            'description' => 'Created a new product',
        ],
    ]);

    ActivityLog::factory()->create([
        'event' => 'special.event',
        'data' => [
            'subject' => ['name' => 'Regular Product'],
            'description' => 'Regular activity',
        ],
    ]);

    ActivityLog::factory()->create([
        'event' => 'product.updated',
        'data' => [
            'subject' => ['name' => 'Another Product'],
            'description' => 'Special update performed',
        ],
    ]);

    ActivityLog::factory()->create([
        'event' => 'user.created',
        'data' => [
            'subject' => ['name' => 'Regular User'],
            'description' => 'Nothing special here',
        ],
    ]);

    $results = ActivityLog::search(['search' => 'special']);

    expect($results)->toHaveCount(3); // Found in event, subject name, and description
});

it('can search activities by date range', function () {
    $startDate = now()->subDays(5);
    $endDate = now()->subDays(2);

    // Activities within range
    ActivityLog::factory()->count(3)->create([
        'occurred_at' => now()->subDays(3),
    ]);

    // Activities outside range
    ActivityLog::factory()->create([
        'occurred_at' => now()->subDays(7), // Too old
    ]);

    ActivityLog::factory()->create([
        'occurred_at' => now()->subDays(1), // Too recent
    ]);

    $results = ActivityLog::search([
        'date_from' => $startDate,
        'date_to' => $endDate,
    ]);

    expect($results)->toHaveCount(3);
});

it('can limit search results', function () {
    ActivityLog::factory()->count(20)->create([
        'event' => 'product.created',
    ]);

    $results = ActivityLog::search([
        'event' => 'product.created',
        'limit' => 5,
    ]);

    expect($results)->toHaveCount(5);
});

it('can combine multiple search filters', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['name' => 'Test Product']);

    // Matching activity
    ActivityLog::factory()->create([
        'event' => 'product.updated',
        'user_id' => $user->id,
        'occurred_at' => now()->subHours(12),
        'data' => [
            'subject' => [
                'type' => 'Product',
                'id' => $product->id,
                'name' => 'Test Product',
            ],
            'description' => 'Updated test product',
        ],
    ]);

    // Non-matching activities
    ActivityLog::factory()->create([
        'event' => 'product.created', // Different event
        'user_id' => $user->id,
        'occurred_at' => now()->subHours(12),
        'data' => [
            'subject' => [
                'type' => 'Product',
                'id' => $product->id,
                'name' => 'Test Product',
            ],
        ],
    ]);

    ActivityLog::factory()->create([
        'event' => 'product.updated',
        'user_id' => $user->id,
        'occurred_at' => now()->subDays(2), // Too old
        'data' => [
            'subject' => [
                'type' => 'Product',
                'id' => $product->id,
                'name' => 'Test Product',
            ],
        ],
    ]);

    $results = ActivityLog::search([
        'event' => 'product.updated',
        'user_id' => $user->id,
        'hours' => 24,
        'search' => 'test',
    ]);

    expect($results)->toHaveCount(1)
        ->and($results->first()->event)->toBe('product.updated');
});

it('returns empty collection when no matches found', function () {
    ActivityLog::factory()->count(5)->create([
        'event' => 'product.created',
    ]);

    $results = ActivityLog::search([
        'event' => 'nonexistent.event',
    ]);

    expect($results)->toHaveCount(0)
        ->and($results)->toBeInstanceOf(Illuminate\Support\Collection::class);
});

it('searches are case insensitive for text search', function () {
    ActivityLog::factory()->create([
        'event' => 'product.created',
        'data' => [
            'subject' => ['name' => 'UPPERCASE PRODUCT'],
            'description' => 'Created UPPERCASE product',
        ],
    ]);

    ActivityLog::factory()->create([
        'event' => 'product.created',
        'data' => [
            'subject' => ['name' => 'lowercase product'],
            'description' => 'Created lowercase product',
        ],
    ]);

    $results = ActivityLog::search(['search' => 'PRODUCT']);

    expect($results)->toHaveCount(2);
});

it('can get activity stats for specific time period', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    // Create activities within 24 hours
    ActivityLog::factory()->count(5)->create([
        'event' => 'product.created',
        'user_id' => $user1->id,
        'occurred_at' => now()->subHours(12),
    ]);

    ActivityLog::factory()->count(3)->create([
        'event' => 'product.updated',
        'user_id' => $user2->id,
        'occurred_at' => now()->subHours(6),
    ]);

    ActivityLog::factory()->count(2)->create([
        'event' => 'user.created',
        'user_id' => $user1->id,
        'occurred_at' => now()->subHours(3),
    ]);

    // Create activities outside 24 hours
    ActivityLog::factory()->count(10)->create([
        'occurred_at' => now()->subDays(2),
    ]);

    $stats = ActivityLog::getActivityStats(24);

    expect($stats['total_activities'])->toBe(10) // Only recent ones
        ->and($stats['unique_users'])->toBe(2)
        ->and($stats['top_events']['product.created'])->toBe(5)
        ->and($stats['top_events']['product.updated'])->toBe(3)
        ->and($stats['top_events']['user.created'])->toBe(2);
});

it('can get recent activities by specific user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    ActivityLog::factory()->count(15)->create(['user_id' => $user->id]);
    ActivityLog::factory()->count(5)->create(['user_id' => $otherUser->id]);
    ActivityLog::factory()->count(3)->create(['user_id' => null]); // System

    $userActivities = ActivityLog::getRecentByUser($user->id, 10);

    expect($userActivities)->toHaveCount(10) // Limited to 10
        ->and($userActivities->every(fn ($log) => $log->user_id === $user->id))->toBeTrue();
});

it('orders search results by occurred_at descending by default', function () {
    ActivityLog::factory()->create([
        'event' => 'product.created',
        'occurred_at' => now()->subDays(3),
    ]);

    ActivityLog::factory()->create([
        'event' => 'product.created',
        'occurred_at' => now()->subDays(1), // Most recent
    ]);

    ActivityLog::factory()->create([
        'event' => 'product.created',
        'occurred_at' => now()->subDays(2),
    ]);

    $results = ActivityLog::search(['event' => 'product.created']);

    expect($results->first()->occurred_at->diffInDays())->toBe(1) // Most recent first
        ->and($results->last()->occurred_at->diffInDays())->toBe(3); // Oldest last
});
