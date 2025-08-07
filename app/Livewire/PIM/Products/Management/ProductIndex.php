<?php

namespace App\Livewire\Pim\Products\Management;

use App\Models\Product;
use App\Table\Table;
use App\Table\Column;
use App\Table\Concerns\InteractsWithTable;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ProductIndex extends Component
{
    use InteractsWithTable;
    
    protected $listeners = [
        'refreshList' => '$refresh'
    ];
    
    /**
     * Configure the table (FilamentPHP-style).
     */
    public function table(Table $table): Table
    {
        return $table
            ->model(Product::class)
            ->title('Product Catalog')
            ->subtitle('Manage your product catalog with advanced filtering and bulk operations')
            ->columns([
                Column::make('name')
                    ->label('Product Name')
                    ->sortable(),

                Column::make('parent_sku')
                    ->label('SKU')
                    ->sortable()
                    ->font('font-mono'),

                Column::make('status')
                    ->label('Status')
                    ->badge([
                        'active' => 'bg-green-100 text-green-800',
                        'inactive' => 'bg-red-100 text-red-800',
                        'draft' => 'bg-yellow-100 text-yellow-800',
                    ])
                    ->sortable(),
            ]);
    }

    public function render()
    {
        return view('livewire.pim.products.management.product-index');
    }
}