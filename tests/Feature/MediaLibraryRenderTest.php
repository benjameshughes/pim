<?php

use App\Livewire\Media\MediaLibrary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('MediaLibrary component can be rendered', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user);
    
    $component = Livewire::test(MediaLibrary::class);
    
    $component->assertSuccessful();
});

test('MediaLibrary route can be accessed', function () {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)->get(route('images.index'));
    
    $response->assertSuccessful();
    $response->assertSeeLivewire('media.media-library');
});

test('MediaLibrary has correct initial state', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user);
    
    $component = Livewire::test(MediaLibrary::class);
    
    $component
        ->assertSet('activeTab', 'library')
        ->assertSet('viewMode', 'grid')
        ->assertSet('bulkMode', false)
        ->assertSet('assignmentMode', false);
});

test('MediaLibrary can switch tabs', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user);
    
    $component = Livewire::test(MediaLibrary::class);
    
    $component->set('activeTab', 'upload')
        ->assertSet('activeTab', 'upload');
        
    $component->set('activeTab', 'library')
        ->assertSet('activeTab', 'library');
});

test('MediaLibrary can toggle bulk mode', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user);
    
    $component = Livewire::test(MediaLibrary::class);
    
    $component->call('toggleBulkMode')
        ->assertSet('bulkMode', true)
        ->assertSet('selectedImages', []);
        
    $component->call('toggleBulkMode')
        ->assertSet('bulkMode', false);
});

test('MediaLibrary can toggle assignment mode', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user);
    
    $component = Livewire::test(MediaLibrary::class);
    
    $component->call('toggleAssignmentMode')
        ->assertSet('assignmentMode', true)
        ->assertSet('selectedProductId', '')
        ->assertSet('selectedVariantId', '');
        
    $component->call('toggleAssignmentMode')
        ->assertSet('assignmentMode', false);
});

test('MediaLibrary updates stats correctly', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user);
    
    $component = Livewire::test(MediaLibrary::class);
    
    // Check that stats are loaded
    expect($component->get('stats'))->toBeArray();
    expect($component->get('stats'))->toHaveKeys([
        'total', 'unassigned', 'products', 'variants',
        'pending', 'processing', 'completed', 'failed'
    ]);
});