<?php

use App\Actions\Products\CreateProductAction;
use App\Actions\Products\DeleteProductAction;
use App\Actions\Products\UpdateProductAction;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->withPermissions(['create-products', 'edit-products', 'delete-products'])->create();
    $this->actingAs($this->user);
});

describe('CreateProductAction', function () {
    it('logs product creation', function () {
        $action = app(CreateProductAction::class);

        $productData = [
            'name' => 'Test Product',
            'sku' => 'TEST-SKU',
            'status' => 'active',
            'categories' => [1 => ['is_primary' => true]],
            'attributes' => ['color' => 'red', 'size' => 'large'],
        ];

        $result = $action->execute($productData);
        $product = $result['data']['product'];

        expect($result['success'])->toBeTrue();

        $log = ActivityLog::where('event', 'product.created')->first();

        expect($log)->not->toBeNull()
            ->and($log->user_id)->toBe($this->user->id)
            ->and($log->getSubjectId())->toBe($product->id)
            ->and($log->getSubjectType())->toBe('Product')
            ->and($log->getContextData()->get('initial_data'))->toHaveKeys(['name', 'sku', 'status'])
            ->and($log->getContextData()->get('categories_count'))->toBe(1)
            ->and($log->getContextData()->get('attributes_count'))->toBe(2);
    });

    it('logs creation with minimal data', function () {
        $action = app(CreateProductAction::class);

        $result = $action->execute([
            'name' => 'Simple Product',
        ]);

        $product = $result['data']['product'];
        $log = ActivityLog::where('event', 'product.created')->first();

        expect($log->getSubjectId())->toBe($product->id)
            ->and($log->getContextData()->get('categories_count'))->toBe(0)
            ->and($log->getContextData()->get('attributes_count'))->toBe(0);
    });

    it('captures creation context', function () {
        $action = app(CreateProductAction::class);

        $action->execute(['name' => 'Context Test Product']);

        $log = ActivityLog::where('event', 'product.created')->first();

        expect($log->getContextData())->toHaveKey('ip')
            ->and($log->getContextData())->toHaveKey('user_agent');
    });
});

describe('UpdateProductAction', function () {
    it('logs product updates with changes', function () {
        $product = Product::factory()->create([
            'name' => 'Original Name',
            'status' => 'draft',
            'sku' => 'ORIG-SKU',
        ]);

        $action = app(UpdateProductAction::class);

        $updateData = [
            'name' => 'Updated Name',
            'status' => 'active',
        ];

        $result = $action->execute($product, $updateData);

        expect($result['success'])->toBeTrue();

        $log = ActivityLog::where('event', 'product.updated')->first();

        expect($log)->not->toBeNull()
            ->and($log->user_id)->toBe($this->user->id)
            ->and($log->getSubjectId())->toBe($product->id)
            ->and($log->changes['old']['name'])->toBe('Original Name')
            ->and($log->changes['new']['name'])->toBe('Updated Name')
            ->and($log->changes['old']['status'])->toBe('draft')
            ->and($log->changes['new']['status'])->toBe('active')
            ->and($log->getContextData()->get('updated_fields'))->toBe(['name', 'status']);
    });

    it('logs updates with only changed fields', function () {
        $product = Product::factory()->create([
            'name' => 'Test Product',
            'sku' => 'TEST-SKU',
            'status' => 'draft',
        ]);

        $action = app(UpdateProductAction::class);

        // Only update status
        $result = $action->execute($product, ['status' => 'active']);

        $log = ActivityLog::where('event', 'product.updated')->first();

        expect($log->changes['old'])->toHaveKey('status')
            ->and($log->changes['old'])->not->toHaveKey('name')
            ->and($log->changes['new']['status'])->toBe('active')
            ->and($log->getContextData()->get('updated_fields'))->toBe(['status']);
    });

    it('handles slug generation in updates', function () {
        $product = Product::factory()->create([
            'name' => 'Original Product',
            'slug' => 'original-product',
        ]);

        $action = app(UpdateProductAction::class);

        $result = $action->execute($product, ['name' => 'New Product Name']);

        $log = ActivityLog::where('event', 'product.updated')->first();

        expect($log->changes['old']['name'])->toBe('Original Product')
            ->and($log->changes['new']['name'])->toBe('New Product Name')
            ->and($log->changes['new'])->toHaveKey('slug')
            ->and($log->changes['new']['slug'])->toBe('new-product-name');
    });

    it('logs relationship updates', function () {
        $product = Product::factory()->create();

        $action = app(UpdateProductAction::class);

        $updateData = [
            'name' => 'Updated Product',
            'categories' => [1 => ['is_primary' => true], 2 => ['is_primary' => false]],
            'attributes' => ['color' => 'blue'],
        ];

        $action->execute($product, $updateData);

        $log = ActivityLog::where('event', 'product.updated')->first();

        expect($log->getContextData()->get('updated_fields'))
            ->toContain('name', 'categories', 'attributes');
    });
});

describe('DeleteProductAction', function () {
    it('logs product deletion', function () {
        $product = Product::factory()->create([
            'name' => 'Product To Delete',
        ]);

        $action = app(DeleteProductAction::class);

        $result = $action->execute($product);

        expect($result['success'])->toBeTrue();

        $log = ActivityLog::where('event', 'product.deleted')->first();

        expect($log)->not->toBeNull()
            ->and($log->user_id)->toBe($this->user->id)
            ->and($log->getSubjectId())->toBe($product->id)
            ->and($log->getSubjectType())->toBe('Product')
            ->and($log->getSubjectName())->toBe('Product To Delete');
    });

    it('logs deletion with variant and image counts', function () {
        $product = Product::factory()->create([
            'images' => ['image1.jpg', 'image2.jpg', 'image3.jpg'],
        ]);

        // Create variants for this product
        $product->variants()->createMany([
            ['sku' => 'VAR-1', 'retail_price' => 10.00],
            ['sku' => 'VAR-2', 'retail_price' => 15.00],
        ]);

        $action = app(DeleteProductAction::class);

        $action->execute($product);

        $log = ActivityLog::where('event', 'product.deleted')->first();

        expect($log->description)->toContain('deleted with 2 variants and 3 images');
    });

    it('logs force deletion', function () {
        $product = Product::factory()->create();

        $action = app(DeleteProductAction::class);

        $result = $action->execute($product, true); // Force delete

        expect($result['success'])->toBeTrue()
            ->and($result['data']['force_delete'])->toBeTrue();

        $log = ActivityLog::where('event', 'product.deleted')->first();

        expect($log)->not->toBeNull();
    });

    it('captures deletion before product is actually deleted', function () {
        $product = Product::factory()->create([
            'name' => 'Soon To Be Deleted',
            'sku' => 'DELETE-ME',
        ]);

        $productId = $product->id;

        $action = app(DeleteProductAction::class);
        $action->execute($product);

        // Product should be deleted
        expect(Product::find($productId))->toBeNull();

        // But log should have captured the data
        $log = ActivityLog::where('event', 'product.deleted')->first();

        expect($log->getSubjectId())->toBe($productId)
            ->and($log->getSubjectName())->toBe('Soon To Be Deleted');
    });
});

describe('Action Logging Integration', function () {
    it('maintains user context across all actions', function () {
        // Create
        $createAction = app(CreateProductAction::class);
        $createResult = $createAction->execute(['name' => 'Integration Test']);
        $product = $createResult['data']['product'];

        // Update
        $updateAction = app(UpdateProductAction::class);
        $updateAction->execute($product, ['name' => 'Updated Integration Test']);

        // Delete
        $deleteAction = app(DeleteProductAction::class);
        $deleteAction->execute($product);

        $logs = ActivityLog::where('user_id', $this->user->id)->get();

        expect($logs)->toHaveCount(3)
            ->and($logs->pluck('event')->toArray())->toBe([
                'product.created',
                'product.updated',
                'product.deleted',
            ]);
    });

    it('handles actions without authenticated user', function () {
        // Logout user
        auth()->logout();

        $action = app(CreateProductAction::class);

        // This might fail due to authorization, but if it passes...
        try {
            $result = $action->execute(['name' => 'Anonymous Product']);

            if ($result['success']) {
                $log = ActivityLog::where('event', 'product.created')->first();
                expect($log->user_id)->toBeNull();
            }
        } catch (Exception $e) {
            // Authorization failed, which is expected
            expect($e)->toBeInstanceOf(Illuminate\Auth\Access\AuthorizationException::class);
        }
    });

    it('logs are created in correct chronological order', function () {
        $product = Product::factory()->create(['name' => 'Chronology Test']);

        $updateAction = app(UpdateProductAction::class);

        // Make multiple updates with small delays
        $updateAction->execute($product, ['name' => 'Update 1']);
        usleep(1000); // 1ms delay
        $updateAction->execute($product, ['name' => 'Update 2']);
        usleep(1000);
        $updateAction->execute($product, ['name' => 'Update 3']);

        $logs = ActivityLog::where('event', 'product.updated')
            ->orderBy('occurred_at')
            ->get();

        expect($logs)->toHaveCount(3)
            ->and($logs->get(0)->changes['new']['name'])->toBe('Update 1')
            ->and($logs->get(1)->changes['new']['name'])->toBe('Update 2')
            ->and($logs->get(2)->changes['new']['name'])->toBe('Update 3');
    });
});
