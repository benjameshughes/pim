<?php

namespace App\Livewire\Pim\Products\Variants;

use App\Models\ProductVariant;
use App\Models\Product;
use App\Contracts\HasStackedList;
use App\Concerns\HasStackedListBehavior;
use App\StackedList\StackedListBuilder;
use App\StackedList\Columns\Column;
use App\StackedList\Columns\Badge;
use App\StackedList\Actions\BulkAction;
use App\StackedList\Actions\Action;
use App\StackedList\Actions\HeaderAction;
use App\StackedList\Filters\Filter;
use App\StackedList\EmptyStateAction;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class VariantIndex extends Component implements HasStackedList
{
    use HasStackedListBehavior;

    public $showDeleteModal = false;
    public $variantToDelete = null;

    protected $listeners = [
        'refreshList' => '$refresh'
    ];

    public function mount()
    {
        $this->initializeStackedList(ProductVariant::class, $this->getList());
    }

    public function getList(): StackedListBuilder
    {
        return $this->stackedList()
            ->model(ProductVariant::class)
            ->title('Variant Management')
            ->subtitle('Manage individual product variants across your catalog')
            ->searchPlaceholder('Search variants by SKU, color, size...')
            ->searchable(['sku', 'color', 'size', 'product.name'])
            ->sortableColumns(['sku', 'color', 'size', 'status', 'barcodes_count'])
            ->with(['product'])
            ->withCount(['barcodes'])
            ->perPageOptions([10, 25, 50, 100])
            ->export()
            ->defaultSort('created_at', 'desc')
            ->columns([
                Column::make('sku')
                    ->label('Variant SKU')
                    ->font('font-mono text-sm')
                    ->sortable(),

                Column::make('product.name')
                    ->label('Product Name')
                    ->font('font-medium'),

                Column::make('color')
                    ->label('Color')
                    ->sortable(),

                Column::make('size')
                    ->label('Size')
                    ->sortable(),

                Column::make('barcodes_count')
                    ->label('# Barcodes')
                    ->sortable(),

                Column::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable()
                    ->addBadge('active', Badge::make()
                        ->class('bg-green-100 text-green-800 border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800')
                        ->icon('check-circle')
                    )
                    ->addBadge('inactive', Badge::make()
                        ->class('bg-zinc-100 text-zinc-800 border-zinc-200 dark:bg-zinc-700 dark:text-zinc-300 dark:border-zinc-600')
                        ->icon('pause-circle')
                    )
                    ->addBadge('discontinued', Badge::make()
                        ->class('bg-red-100 text-red-800 border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800')
                        ->icon('x-circle')
                    ),
            ])
            ->bulkActions([
                BulkAction::make('delete')
                    ->label('Delete Selected')
                    ->icon('trash-2')
                    ->danger()
                    ->action(fn($selectedIds) => $this->deleteVariants($selectedIds)),

                BulkAction::make('activate')
                    ->label('Activate')
                    ->icon('check-circle')
                    ->outline()
                    ->action(fn($selectedIds) => $this->updateVariantStatus($selectedIds, 'active')),
            ])
            ->actions([
                Action::view()->route('products.variants.view'),
                Action::edit()->route('products.variants.edit'),
            ])
            ->filters([
                Filter::select('product_id')
                    ->label('Product')
                    ->column('product_id')
                    ->optionsFromModel(Product::class, 'name', 'id', ['name']),

                Filter::select('status')
                    ->option('active', 'Active')
                    ->option('inactive', 'Inactive')
                    ->option('discontinued', 'Discontinued'),
            ])
            ->headerActions([
                HeaderAction::make('create')
                    ->label('Create Variant')
                    ->route('products.variants.create')
                    ->primary(),
            ])
            ->emptyState(
                'No variants found',
                'Create your first variant to get started.',
                EmptyStateAction::make('Create Variant')
                    ->href(route('products.variants.create'))
                    ->primary()
            );
    }

    public function handleBulkAction(string $action, array $selectedIds): void
    {
        // Actions are now self-contained via closures - this method is no longer needed
        // but required by HasStackedList interface for backward compatibility
    }

    private function deleteVariants(array $ids): void
    {
        ProductVariant::whereIn('id', $ids)->delete();
        session()->flash('message', count($ids) . ' variants deleted.');
    }

    private function updateVariantStatus(array $ids, string $status): void
    {
        ProductVariant::whereIn('id', $ids)->update(['status' => $status]);
        session()->flash('message', count($ids) . ' variants updated.');
    }

    public function confirmDelete($variantId)
    {
        $this->variantToDelete = $variantId;
        $this->showDeleteModal = true;
    }

    public function deleteVariant()
    {
        $variant = ProductVariant::find($this->variantToDelete);
        
        if ($variant) {
            $variant->delete();
            session()->flash('message', 'Variant deleted successfully.');
        }

        $this->showDeleteModal = false;
        $this->variantToDelete = null;
    }

    public function render()
    {
        return view('livewire.pim.products.variants.variant-index');
    }
}