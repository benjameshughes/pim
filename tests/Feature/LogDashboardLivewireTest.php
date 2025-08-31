<?php

use App\Livewire\LogDashboard;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

it('requires view-system-logs permission to mount', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user);
    
    expect(fn() => Livewire::test(LogDashboard::class))
        ->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
});

it('mounts successfully with proper permissions', function () {
    $user = User::factory()->withPermissions(['view-system-logs'])->create();
    
    $this->actingAs($user);
    
    Livewire::test(LogDashboard::class)
        ->assertSet('activeTab', 'overview')
        ->assertSet('activityFilter', 'all')
        ->assertSet('autoRefresh', false)
        ->assertOk();
});

it('can change active tab', function () {
    $user = User::factory()->withPermissions(['view-system-logs'])->create();
    
    $this->actingAs($user);
    
    Livewire::test(LogDashboard::class)
        ->call('setActiveTab', 'activity')
        ->assertSet('activeTab', 'activity');
});

it('can change activity filter', function () {
    $user = User::factory()->withPermissions(['view-system-logs'])->create();
    
    $this->actingAs($user);
    
    Livewire::test(LogDashboard::class)
        ->call('setActivityFilter', 'product')
        ->assertSet('activityFilter', 'product');
});

it('can toggle auto refresh', function () {
    $user = User::factory()->withPermissions(['view-system-logs'])->create();
    
    $this->actingAs($user);
    
    Livewire::test(LogDashboard::class)
        ->assertSet('autoRefresh', false)
        ->call('toggleAutoRefresh')
        ->assertSet('autoRefresh', true)
        ->call('toggleAutoRefresh')
        ->assertSet('autoRefresh', false);
});

it('can refresh data', function () {
    $user = User::factory()->withPermissions(['view-system-logs'])->create();
    
    $this->actingAs($user);
    
    Livewire::test(LogDashboard::class)
        ->call('refreshData')
        ->assertDispatched('$refresh');
});

it('gets recent activity correctly', function () {
    $user = User::factory()->withPermissions(['view-system-logs'])->create();
    $product = Product::factory()->create();
    
    // Create some activity logs
    ActivityLog::factory()->count(3)->create([
        'event' => 'product.created',
        'occurred_at' => now()->subHours(2)
    ]);
    
    ActivityLog::factory()->count(2)->create([
        'event' => 'user.updated',
        'occurred_at' => now()->subHours(1)
    ]);
    
    $this->actingAs($user);
    
    $component = Livewire::test(LogDashboard::class);
    
    $recentActivity = $component->get('recentActivity');
    
    expect($recentActivity)->toHaveCount(5);
});

it('filters recent activity by type', function () {
    $user = User::factory()->withPermissions(['view-system-logs'])->create();
    
    // Create activity logs
    ActivityLog::factory()->count(3)->create([
        'event' => 'product.created',
        'occurred_at' => now()->subHours(2)
    ]);
    
    ActivityLog::factory()->count(2)->create([
        'event' => 'user.updated',
        'occurred_at' => now()->subHours(1)
    ]);
    
    $this->actingAs($user);
    
    $component = Livewire::test(LogDashboard::class)
        ->set('activityFilter', 'product');
    
    $recentActivity = $component->get('recentActivity');
    
    expect($recentActivity)->toHaveCount(3)
        ->and($recentActivity->every(fn($log) => str_starts_with($log->event, 'product')))->toBeTrue();
});

it('gets activity stats correctly', function () {
    $user1 = User::factory()->withPermissions(['view-system-logs'])->create();
    $user2 = User::factory()->create();
    
    // Create various activities
    ActivityLog::factory()->count(3)->create([
        'event' => 'product.created',
        'user_id' => $user1->id,
        'occurred_at' => now()->subHours(12)
    ]);
    
    ActivityLog::factory()->count(2)->create([
        'event' => 'variant.updated',
        'user_id' => $user2->id,
        'occurred_at' => now()->subHours(6)
    ]);
    
    ActivityLog::factory()->create([
        'event' => 'user.created',
        'user_id' => $user1->id,
        'occurred_at' => now()->subHours(3)
    ]);
    
    $this->actingAs($user1);
    
    $component = Livewire::test(LogDashboard::class);
    $stats = $component->get('activityStats');
    
    expect($stats['total'])->toBe(6)
        ->and($stats['product_activities'])->toBe(3)
        ->and($stats['variant_activities'])->toBe(2)
        ->and($stats['user_activities'])->toBe(1)
        ->and($stats['unique_users'])->toBe(2);
});

it('gets top users correctly', function () {
    $user1 = User::factory()->withPermissions(['view-system-logs'])->create(['name' => 'John Doe']);
    $user2 = User::factory()->create(['name' => 'Jane Smith']);
    
    // User1 has more activities
    ActivityLog::factory()->count(5)->create([
        'user_id' => $user1->id,
        'occurred_at' => now()->subHours(12)
    ]);
    
    // User2 has fewer activities
    ActivityLog::factory()->count(2)->create([
        'user_id' => $user2->id,
        'occurred_at' => now()->subHours(6)
    ]);
    
    $this->actingAs($user1);
    
    $component = Livewire::test(LogDashboard::class);
    $topUsers = $component->get('topUsers');
    
    expect($topUsers)->toHaveCount(2)
        ->and($topUsers->first()['name'])->toBe('John Doe')
        ->and($topUsers->first()['count'])->toBe(5)
        ->and($topUsers->last()['name'])->toBe('Jane Smith')
        ->and($topUsers->last()['count'])->toBe(2);
});

it('handles system activities without user', function () {
    $user = User::factory()->withPermissions(['view-system-logs'])->create();
    
    // Create system activities (no user)
    ActivityLog::factory()->count(3)->create([
        'user_id' => null,
        'event' => 'system.maintenance',
        'occurred_at' => now()->subHours(2)
    ]);
    
    $this->actingAs($user);
    
    $component = Livewire::test(LogDashboard::class);
    $topUsers = $component->get('topUsers');
    
    expect($topUsers->first()['name'])->toBe('System')
        ->and($topUsers->first()['count'])->toBe(3);
});

it('limits recent activity to 50 items', function () {
    $user = User::factory()->withPermissions(['view-system-logs'])->create();
    
    // Create more than 50 activities
    ActivityLog::factory()->count(75)->create([
        'occurred_at' => now()->subHours(2)
    ]);
    
    $this->actingAs($user);
    
    $component = Livewire::test(LogDashboard::class);
    $recentActivity = $component->get('recentActivity');
    
    expect($recentActivity)->toHaveCount(50);
});

it('includes both technical and activity data in render', function () {
    $user = User::factory()->withPermissions(['view-system-logs'])->create();
    
    $this->actingAs($user);
    
    Livewire::test(LogDashboard::class)
        ->assertViewHas('metrics')
        ->assertViewHas('recentRequests')
        ->assertViewHas('slowestEndpoints')
        ->assertViewHas('recentErrors')
        ->assertViewHas('logSizes')
        ->assertViewHas('recentActivity')
        ->assertViewHas('activityStats')
        ->assertViewHas('topUsers');
});

it('shows correct activity count for different time periods', function () {
    $user = User::factory()->withPermissions(['view-system-logs'])->create();
    
    // Activities from different time periods
    ActivityLog::factory()->count(3)->create([
        'occurred_at' => now()->subHours(2) // Within 24h
    ]);
    
    ActivityLog::factory()->count(2)->create([
        'occurred_at' => now()->subDays(2) // Outside 24h
    ]);
    
    $this->actingAs($user);
    
    $component = Livewire::test(LogDashboard::class);
    $recentActivity = $component->get('recentActivity');
    
    expect($recentActivity)->toHaveCount(3); // Only recent ones
});

it('handles empty activity gracefully', function () {
    $user = User::factory()->withPermissions(['view-system-logs'])->create();
    
    $this->actingAs($user);
    
    $component = Livewire::test(LogDashboard::class);
    
    expect($component->get('recentActivity'))->toHaveCount(0)
        ->and($component->get('activityStats')['total'])->toBe(0)
        ->and($component->get('topUsers'))->toHaveCount(0);
});