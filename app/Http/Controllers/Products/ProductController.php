<?php

namespace App\Http\Controllers\Products;

use App\Actions\Products\DeleteProductAction;
use App\Builders\Products\ProductBuilder;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

/**
 * Product Controller
 * 
 * RESTful controller for product management using Builder Pattern + Actions Pattern.
 * Demonstrates clean separation of concerns and readable business logic.
 * 
 * @package App\Http\Controllers\Products
 */
class ProductController extends Controller
{
    /**
     * Display a listing of products
     * 
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $query = Product::query()
            ->with(['variants', 'productImages'])
            ->withCount('variants');
        
        // Handle search
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('parent_sku', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        // Handle status filter
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        
        // Handle sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        
        $allowedSortFields = ['name', 'parent_sku', 'status', 'created_at', 'updated_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        }
        
        $products = $query->paginate(15)->withQueryString();
        
        return view('products.index', compact('products'));
    }
    
    /**
     * Show the form for creating a new product
     * 
     * @return View
     */
    public function create(): View
    {
        return view('products.create');
    }
    
    /**
     * Store a newly created product
     * 
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:products,slug',
            'parent_sku' => 'nullable|string|max:100|unique:products,parent_sku',
            'description' => 'nullable|string',
            'status' => 'required|in:draft,active,inactive,archived',
            'features' => 'nullable|array|max:5',
            'features.*' => 'nullable|string|max:255',
            'details' => 'nullable|array|max:5',
            'details.*' => 'nullable|string|max:255',
            'categories' => 'nullable|array',
            'categories.*' => 'exists:categories,id',
        ]);
        
        try {
            $builder = ProductBuilder::create()
                ->name($validated['name'])
                ->status($validated['status']);
            
            // Optional fields
            if (!empty($validated['slug'])) {
                $builder->slug($validated['slug']);
            }
            
            if (!empty($validated['parent_sku'])) {
                $builder->sku($validated['parent_sku']);
            }
            
            if (!empty($validated['description'])) {
                $builder->description($validated['description']);
            }
            
            if (!empty($validated['features'])) {
                $builder->features(array_filter($validated['features']));
            }
            
            if (!empty($validated['details'])) {
                $builder->details(array_filter($validated['details']));
            }
            
            if (!empty($validated['categories'])) {
                $categories = [];
                foreach ($validated['categories'] as $categoryId) {
                    $categories[$categoryId] = ['is_primary' => false];
                }
                $builder->categories($categories);
            }
            
            $product = $builder->execute();
            
            return redirect()->route('products.show', $product)
                ->with('success', "Product '{$product->name}' created successfully.");
                
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create product: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Display the specified product
     * 
     * @param Product $product
     * @return View
     */
    public function show(Product $product): View
    {
        $product->load([
            'variants' => function ($query) {
                $query->with(['barcodes', 'pricing', 'variantImages']);
            },
            'productImages',
            'categories',
            'attributes',
            'metadata'
        ]);
        
        return view('products.show', compact('product'));
    }
    
    /**
     * Show the form for editing the specified product
     * 
     * @param Product $product
     * @return View
     */
    public function edit(Product $product): View
    {
        $product->load(['categories', 'attributes', 'metadata']);
        
        return view('products.edit', compact('product'));
    }
    
    /**
     * Update the specified product
     * 
     * @param Request $request
     * @param Product $product
     * @return RedirectResponse
     */
    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'slug')->ignore($product->id)
            ],
            'parent_sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'parent_sku')->ignore($product->id)
            ],
            'description' => 'nullable|string',
            'status' => 'required|in:draft,active,inactive,archived',
            'features' => 'nullable|array|max:5',
            'features.*' => 'nullable|string|max:255',
            'details' => 'nullable|array|max:5',
            'details.*' => 'nullable|string|max:255',
            'categories' => 'nullable|array',
            'categories.*' => 'exists:categories,id',
        ]);
        
        try {
            $builder = ProductBuilder::update($product)
                ->name($validated['name'])
                ->status($validated['status']);
            
            // Optional fields
            if (array_key_exists('slug', $validated)) {
                $builder->slug($validated['slug']);
            }
            
            if (array_key_exists('parent_sku', $validated)) {
                $builder->sku($validated['parent_sku']);
            }
            
            if (array_key_exists('description', $validated)) {
                $builder->description($validated['description']);
            }
            
            if (!empty($validated['features'])) {
                $builder->features(array_filter($validated['features']));
            }
            
            if (!empty($validated['details'])) {
                $builder->details(array_filter($validated['details']));
            }
            
            if (array_key_exists('categories', $validated)) {
                if (!empty($validated['categories'])) {
                    $categories = [];
                    foreach ($validated['categories'] as $categoryId) {
                        $categories[$categoryId] = ['is_primary' => false];
                    }
                    $builder->categories($categories);
                } else {
                    $builder->categories([]);
                }
            }
            
            $product = $builder->execute();
            
            return redirect()->route('products.show', $product)
                ->with('success', "Product '{$product->name}' updated successfully.");
                
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update product: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Remove the specified product
     * 
     * @param Product $product
     * @return RedirectResponse
     */
    public function destroy(Product $product): RedirectResponse
    {
        try {
            $productName = $product->name;
            
            $deleteAction = app(DeleteProductAction::class);
            $deleteAction->execute($product);
            
            return redirect()->route('products.index')
                ->with('success', "Product '{$productName}' deleted successfully.");
                
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to delete product: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Restore a soft-deleted product (if soft deletes are implemented)
     * 
     * @param int $productId
     * @return RedirectResponse
     */
    public function restore(int $productId): RedirectResponse
    {
        try {
            $product = Product::onlyTrashed()->findOrFail($productId);
            $product->restore();
            
            return redirect()->route('products.show', $product)
                ->with('success', "Product '{$product->name}' restored successfully.");
                
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to restore product: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Force delete a product (permanently delete)
     * 
     * @param int $productId
     * @return RedirectResponse
     */
    public function forceDestroy(int $productId): RedirectResponse
    {
        try {
            $product = Product::withTrashed()->findOrFail($productId);
            $productName = $product->name;
            
            $deleteAction = app(DeleteProductAction::class);
            $deleteAction->execute($product, true); // Force delete
            
            return redirect()->route('products.index')
                ->with('success', "Product '{$productName}' permanently deleted.");
                
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to permanently delete product: ' . $e->getMessage()]);
        }
    }
}