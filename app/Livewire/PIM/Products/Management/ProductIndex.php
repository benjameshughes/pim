<?php

namespace App\Livewire\Pim\Products\Management;

use App\Models\Product;
use App\StackedList\Concerns\InteractsWithStackedList;
use App\StackedList\Table;
use App\StackedList\Columns\Column;
use App\StackedList\Columns\Badge;
use App\StackedList\Actions\BulkAction;
use App\StackedList\Actions\Action;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ProductIndex extends Component
{
    use InteractsWithStackedList;

    protected $listeners = [
        'refreshList' => '$refresh'
    ];

    /**
     * Configure the StackedList table (FilamentPHP-style).
     */
    public function stackedList(Table $table): Table
    {
        return $table
            ->model(Product::class)
            ->title('Product Catalog')
            ->subtitle('Manage your product catalog with advanced filtering and bulk operations')
            ->searchable(['name', 'parent_sku', 'description'])
            ->columns([
                Column::make('name')
                    ->label('Product Name')
                    ->sortable(),

                Column::make('parent_sku')
                    ->label('SKU')
                    ->sortable(),

                Column::make('status')
                    ->label('Status')
                    ->sortable(),
            ])
            ->bulkActions([
                BulkAction::make('update_pricing')
                    ->label('Update Pricing')
                    ->icon('dollar-sign')
                    ->outline()
                    ->action(function($selectedIds, $livewire) {
                        return $livewire->handleUpdatePricing($selectedIds);
                    }),

                BulkAction::export(),

                BulkAction::make('toggle_status')
                    ->label('Toggle Status')
                    ->icon('refresh-cw')
                    ->outline()
                    ->action(function($selectedIds, $livewire) {
                        return $livewire->toggleProductStatus($selectedIds);
                    }),

                BulkAction::delete(),
            ])
            ->actions([
                Action::view()->route('products.view'),
                Action::edit()->route('products.product.edit'),
            ]);
    }

    private function toggleProductStatus(array $selectedIds): bool
    {
        foreach ($selectedIds as $id) {
            $product = Product::find($id);
            if ($product) {
                $product->status = $product->status === 'active' ? 'inactive' : 'active';
                $product->save();
            }
        }

        return true;
    }

    public function handleBulkAction(string $action, array $selectedIds): void
    {
        // Handle all actions without flash messages
        match($action) {
            'update_pricing' => $this->handleUpdatePricing($selectedIds),
            'toggle_status' => $this->toggleProductStatus($selectedIds),
            'export' => $this->handleExport($selectedIds),
            'delete' => $this->handleDelete($selectedIds),
            default => null
        };
    }

    private function handleUpdatePricing(array $selectedIds): bool
    {
        // TODO: Implement pricing update functionality
        return true;
    }

    private function handleExport(array $selectedIds): bool
    {
        // TODO: Implement export functionality
        return true;
    }

    private function handleDelete(array $selectedIds): bool
    {
        // TODO: Implement delete functionality
        return true;
    }


    public function viewProduct($productId)
    {
        return $this->redirect(route('products.view', $productId));
    }

    public function render()
    {
        return view('livewire.pim.products.management.product-index');
    }
}