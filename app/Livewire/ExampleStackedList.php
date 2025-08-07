<?php

namespace App\Livewire;

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
class ExampleStackedList extends Component implements HasStackedList
{
    use HasStackedListBehavior;

    public function mount()
    {
        $this->initializeStackedList(Product::class, $this->getList());
    }

    public function getList(): StackedListBuilder
    {
        return $this->stackedList()
            ->model(Product::class)
            ->title('Example Product List')
            ->subtitle('This is an example of the fluent StackedList API')
            ->searchable(['name', 'description'])
            ->columns([
                Column::make('name')
                    ->label('Product Name')
                    ->sortable(),

                Column::make('status')
                    ->label('Status')
                    ->badge()
                    ->addBadge('active', Badge::make()
                        ->class('bg-green-100 text-green-800')
                        ->icon('check-circle')
                    )
                    ->addBadge('inactive', Badge::make()
                        ->class('bg-zinc-100 text-zinc-800') 
                        ->icon('pause-circle')
                    ),
            ])
            ->bulkActions([
                BulkAction::make('example_action')
                    ->label('Example Action')
                    ->icon('star')
                    ->outline()
                    ->action(fn($selectedIds) => session()->flash('message', 'Processed ' . count($selectedIds) . ' items!')),

                BulkAction::export(),
                BulkAction::delete(),
            ])
            ->actions([
                Action::view()->route('products.view'),
                Action::edit()->route('products.edit'),
            ])
            ->headerActions([
                HeaderAction::create()->route('products.create'),
            ])
            ->emptyState(
                'No products found',
                'Create your first product to get started.',
                EmptyStateAction::make('Create Product')
                    ->href(route('products.create'))
                    ->primary()
            );
    }

    public function handleBulkAction(string $action, array $selectedIds): void
    {
        // Actions are self-contained via closures - this is just for interface compliance
    }

    public function render()
    {
        return view('livewire.example-stacked-list');
    }
}
