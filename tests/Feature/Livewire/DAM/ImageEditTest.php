<?php

use App\Livewire\DAM\ImageEdit;
use App\Models\Image;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    
    $this->image = Image::factory()->create([
        'title' => 'Test Image',
        'alt_text' => 'Test alt text',
        'description' => 'Test description',
        'folder' => 'test-folder',
        'tags' => ['tag1', 'tag2'],
        'filename' => 'test-image.jpg',
        'path' => 'images/test-image.jpg',
        'url' => 'https://example.com/images/test-image.jpg',
        'file_size' => 1024,
        'width' => 800,
        'height' => 600,
    ]);
});

test('image edit component mounts with image data', function () {
    Livewire::test(ImageEdit::class, ['image' => $this->image])
        ->assertSet('title', 'Test Image')
        ->assertSet('alt_text', 'Test alt text')
        ->assertSet('description', 'Test description')
        ->assertSet('folder', 'test-folder')
        ->assertSet('tags', ['tag1', 'tag2'])
        ->assertSet('tagsString', 'tag1, tag2');
});

test('can update image metadata', function () {
    Livewire::test(ImageEdit::class, ['image' => $this->image])
        ->set('title', 'Updated Title')
        ->set('alt_text', 'Updated alt text')
        ->set('description', 'Updated description')
        ->set('folder', 'updated-folder')
        ->set('tagsString', 'newtag1, newtag2, newtag3')
        ->call('save')
        ->assertDispatched('notify', fn ($event) => 
            $event['type'] === 'success' && 
            str_contains($event['message'], 'Image updated successfully!')
        )
        ->assertRedirect(route('dam.index'));
        
    $this->image->refresh();
    expect($this->image->title)->toBe('Updated Title')
        ->and($this->image->alt_text)->toBe('Updated alt text')
        ->and($this->image->description)->toBe('Updated description')
        ->and($this->image->folder)->toBe('updated-folder')
        ->and($this->image->tags)->toBe(['newtag1', 'newtag2', 'newtag3']);
});

test('validates required fields', function () {
    Livewire::test(ImageEdit::class, ['image' => $this->image])
        ->set('folder', '') // folder is required
        ->call('save')
        ->assertHasErrors(['folder']);
});

test('validates field lengths', function () {
    Livewire::test(ImageEdit::class, ['image' => $this->image])
        ->set('title', str_repeat('a', 256)) // max 255
        ->set('alt_text', str_repeat('b', 256)) // max 255
        ->set('description', str_repeat('c', 1001)) // max 1000
        ->set('tagsString', str_repeat('d', 501)) // max 500
        ->call('save')
        ->assertHasErrors(['title', 'alt_text', 'description', 'tagsString']);
});

test('can cancel and redirect back', function () {
    Livewire::test(ImageEdit::class, ['image' => $this->image])
        ->call('cancel')
        ->assertRedirect(route('dam.index'));
});

test('can delete image', function () {
    Livewire::test(ImageEdit::class, ['image' => $this->image])
        ->call('delete')
        ->assertDispatched('notify', fn ($event) => 
            $event['type'] === 'success' && 
            str_contains($event['message'], 'Image deleted successfully!')
        )
        ->assertRedirect(route('dam.index'));
        
    expect(Image::find($this->image->id))->toBeNull();
});

test('loads existing product attachment', function () {
    $product = Product::factory()->create();
    $this->image->products()->attach($product->id);
    
    Livewire::test(ImageEdit::class, ['image' => $this->image])
        ->assertSet('attachmentType', 'product')
        ->assertSet('attachmentId', $product->id);
});

test('loads existing variant attachment', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    $this->image->variants()->attach($variant->id);
    
    Livewire::test(ImageEdit::class, ['image' => $this->image])
        ->assertSet('attachmentType', 'variant')
        ->assertSet('attachmentId', $variant->id);
});

test('can handle product selection from combobox', function () {
    $product = Product::factory()->create();
    
    Livewire::test(ImageEdit::class, ['image' => $this->image])
        ->call('handleItemSelected', [
            'type' => 'product',
            'id' => $product->id,
            'display' => $product->name,
        ])
        ->assertSet('attachmentType', 'product')
        ->assertSet('attachmentId', $product->id);
});

test('can handle variant selection from combobox', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    
    Livewire::test(ImageEdit::class, ['image' => $this->image])
        ->call('handleItemSelected', [
            'type' => 'variant',
            'id' => $variant->id,
            'display' => $variant->name,
        ])
        ->assertSet('attachmentType', 'variant')
        ->assertSet('attachmentId', $variant->id);
});

test('can clear selection from combobox', function () {
    Livewire::test(ImageEdit::class, ['image' => $this->image])
        ->set('attachmentType', 'product')
        ->set('attachmentId', 123)
        ->call('handleItemCleared')
        ->assertSet('attachmentType', '')
        ->assertSet('attachmentId', 0);
});

test('can attach to product', function () {
    $product = Product::factory()->create();
    
    Livewire::test(ImageEdit::class, ['image' => $this->image])
        ->set('attachmentType', 'product')
        ->set('attachmentId', $product->id)
        ->call('attachToItem')
        ->assertDispatched('notify', fn ($event) => 
            $event['type'] === 'success' && 
            str_contains($event['message'], 'Image attached to product:')
        );
        
    expect($this->image->products()->count())->toBe(1)
        ->and($this->image->products()->first()->id)->toBe($product->id);
});

test('can attach to variant', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    
    Livewire::test(ImageEdit::class, ['image' => $this->image])
        ->set('attachmentType', 'variant')
        ->set('attachmentId', $variant->id)
        ->call('attachToItem')
        ->assertDispatched('notify', fn ($event) => 
            $event['type'] === 'success' && 
            str_contains($event['message'], 'Image attached to variant:')
        );
        
    expect($this->image->variants()->count())->toBe(1)
        ->and($this->image->variants()->first()->id)->toBe($variant->id);
});

test('detaches from existing attachments when attaching to new item', function () {
    $product1 = Product::factory()->create();
    $product2 = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product1->id]);
    
    // Attach to product1 and variant first
    $this->image->products()->attach($product1->id);
    $this->image->variants()->attach($variant->id);
    
    expect($this->image->products()->count())->toBe(1)
        ->and($this->image->variants()->count())->toBe(1);
    
    // Attach to product2 - should detach from product1 and variant
    Livewire::test(ImageEdit::class, ['image' => $this->image])
        ->set('attachmentType', 'product')
        ->set('attachmentId', $product2->id)
        ->call('attachToItem');
        
    $this->image->refresh();
    expect($this->image->products()->count())->toBe(1)
        ->and($this->image->variants()->count())->toBe(0)
        ->and($this->image->products()->first()->id)->toBe($product2->id);
});

test('can detach from all products and variants', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    
    $this->image->products()->attach($product->id);
    $this->image->variants()->attach($variant->id);
    
    Livewire::test(ImageEdit::class, ['image' => $this->image])
        ->call('detachFromAll')
        ->assertDispatched('notify', fn ($event) => 
            $event['type'] === 'success' && 
            str_contains($event['message'], 'Image detached from all products and variants')
        )
        ->assertSet('attachmentType', '')
        ->assertSet('attachmentId', 0);
        
    $this->image->refresh();
    expect($this->image->products()->count())->toBe(0)
        ->and($this->image->variants()->count())->toBe(0);
});

test('getCurrentAttachments returns correct attachment data', function () {
    $product = Product::factory()->create(['name' => 'Test Product', 'parent_sku' => 'PROD001']);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'name' => 'Test Variant',
        'sku' => 'VAR001'
    ]);
    
    $this->image->products()->attach($product->id);
    $this->image->variants()->attach($variant->id);
    
    $component = Livewire::test(ImageEdit::class, ['image' => $this->image]);
    $attachments = $component->instance()->getCurrentAttachments();
    
    expect($attachments)->toHaveCount(2)
        ->and($attachments[0])->toMatchArray([
            'type' => 'product',
            'id' => $product->id,
            'name' => 'Test Product',
            'sku' => 'PROD001',
        ])
        ->and($attachments[1])->toMatchArray([
            'type' => 'variant',
            'id' => $variant->id,
            'name' => 'Test Product - Test Variant',
            'sku' => 'VAR001',
        ]);
});

test('saves and attaches in single operation', function () {
    $product = Product::factory()->create();
    
    Livewire::test(ImageEdit::class, ['image' => $this->image])
        ->set('title', 'Updated Title')
        ->set('attachmentType', 'product')
        ->set('attachmentId', $product->id)
        ->call('save')
        ->assertDispatched('notify', fn ($event) => 
            $event['type'] === 'success' && 
            str_contains($event['message'], 'Image updated successfully!')
        )
        ->assertRedirect(route('dam.index'));
        
    $this->image->refresh();
    expect($this->image->title)->toBe('Updated Title')
        ->and($this->image->products()->count())->toBe(1)
        ->and($this->image->products()->first()->id)->toBe($product->id);
});

test('folders property returns available folders', function () {
    Image::factory()->create(['folder' => 'folder1']);
    Image::factory()->create(['folder' => 'folder2']);
    Image::factory()->create(['folder' => 'folder1']); // duplicate
    Image::factory()->create(['folder' => null]); // should be excluded
    
    $component = Livewire::test(ImageEdit::class, ['image' => $this->image]);
    $folders = $component->instance()->getFoldersProperty();
    
    expect($folders)->toHaveCount(3) // test-folder + folder1 + folder2
        ->and($folders)->toContain('folder1', 'folder2', 'test-folder');
});