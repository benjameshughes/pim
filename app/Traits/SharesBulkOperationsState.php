<?php

namespace App\Traits;

trait SharesBulkOperationsState
{
    /**
     * Get selected variants from session
     */
    public function getSelectedVariants(): array
    {
        return session('bulk_operations.selected_variants', []);
    }

    /**
     * Set selected variants in session
     */
    protected function setSelectedVariants(array $variants): void
    {
        session(['bulk_operations.selected_variants' => $variants]);
    }

    /**
     * Get search state from session
     */
    protected function getSearchState(): array
    {
        return [
            'search' => session('bulk_operations.search', ''),
            'searchFilter' => session('bulk_operations.search_filter', 'all'),
        ];
    }

    /**
     * Set search state in session
     */
    protected function setSearchState(string $search, string $filter = 'all'): void
    {
        session([
            'bulk_operations.search' => $search,
            'bulk_operations.search_filter' => $filter,
        ]);
    }

    /**
     * Get expanded products from session
     */
    protected function getExpandedProducts(): array
    {
        return session('bulk_operations.expanded_products', []);
    }

    /**
     * Set expanded products in session
     */
    protected function setExpandedProducts(array $products): void
    {
        session(['bulk_operations.expanded_products' => $products]);
    }

    /**
     * Clear all bulk operations state
     */
    protected function clearBulkOperationsState(): void
    {
        session()->forget([
            'bulk_operations.selected_variants',
            'bulk_operations.search',
            'bulk_operations.search_filter',
            'bulk_operations.expanded_products',
        ]);
    }

    /**
     * Get state summary for debugging
     */
    protected function getStateSummary(): array
    {
        return [
            'selected_variants_count' => count($this->getSelectedVariants()),
            'search' => session('bulk_operations.search', ''),
            'search_filter' => session('bulk_operations.search_filter', 'all'),
            'expanded_products_count' => count($this->getExpandedProducts()),
        ];
    }
}
