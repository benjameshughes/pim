<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add performance indexes based on common query patterns
        
        // Products table - commonly filtered by status and parent_sku
        Schema::table('products', function (Blueprint $table) {
            $table->index('status');
            $table->index('auto_generated');
            $table->index(['status', 'auto_generated']); // For filtering active products
            $table->index('created_at'); // For ordering by creation date
        });

        // Product variants table - commonly queried by various combinations
        Schema::table('product_variants', function (Blueprint $table) {
            $table->index('status');
            $table->index(['product_id', 'status']); // For getting active variants of a product
            $table->index('created_at'); // For ordering
            $table->index(['status', 'created_at']); // For dashboard queries
        });

        // Barcodes table - commonly searched by barcode value
        Schema::table('barcodes', function (Blueprint $table) {
            $table->index('barcode'); // For barcode lookup queries
            $table->index(['product_variant_id', 'is_primary']); // For finding primary barcode
            $table->index('is_primary'); // For primary barcode queries
        });

        // Product images table - commonly filtered by type and status
        Schema::table('product_images', function (Blueprint $table) {
            $table->index('created_at'); // For ordering by upload date
            $table->index(['processing_status', 'created_at']); // For processing queues
            $table->index(['image_type', 'sort_order']); // For getting images by type in order
        });

        // Pricing table - commonly queried by marketplace and currency
        Schema::table('pricing', function (Blueprint $table) {
            $table->index('currency');
            $table->index(['marketplace', 'currency']); // For marketplace-specific pricing
            $table->index('updated_at'); // For tracking price changes
        });

        // Product metadata table - commonly queried by key
        if (Schema::hasTable('product_metadata')) {
            Schema::table('product_metadata', function (Blueprint $table) {
                $table->index('key'); // For metadata lookups across all products
                $table->index('created_at'); // For audit trails
            });
        }

        // Marketplace variants table - enhance existing indexes
        Schema::table('marketplace_variants', function (Blueprint $table) {
            $table->index('status');
            $table->index(['status', 'last_synced_at']); // For sync operations
            $table->index('created_at'); // For tracking when items were added
        });

        // Categories table - if it exists and doesn't have proper indexes
        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                if (!$this->indexExists('categories', 'categories_name_index')) {
                    $table->index('name');
                }
                if (!$this->indexExists('categories', 'categories_parent_id_index')) {
                    $table->index('parent_id'); // For hierarchical queries
                }
                if (!$this->indexExists('categories', 'categories_status_index')) {
                    $table->index('status'); // If status column exists
                }
            });
        }

        // Sales channels table - if it exists
        if (Schema::hasTable('sales_channels')) {
            Schema::table('sales_channels', function (Blueprint $table) {
                if (!$this->indexExists('sales_channels', 'sales_channels_code_index')) {
                    $table->index('code'); // For code-based lookups
                }
                if (!$this->indexExists('sales_channels', 'sales_channels_status_index')) {
                    $table->index('status'); // If status column exists
                }
            });
        }

        // File processing progress table - commonly queried by status
        if (Schema::hasTable('file_processing_progress')) {
            Schema::table('file_processing_progress', function (Blueprint $table) {
                if (!$this->indexExists('file_processing_progress', 'file_processing_progress_status_index')) {
                    $table->index('status');
                }
                if (!$this->indexExists('file_processing_progress', 'file_processing_progress_created_at_index')) {
                    $table->index('created_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop performance indexes
        
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['auto_generated']);
            $table->dropIndex(['status', 'auto_generated']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['product_id', 'status']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('barcodes', function (Blueprint $table) {
            $table->dropIndex(['barcode']);
            $table->dropIndex(['product_variant_id', 'is_primary']);
            $table->dropIndex(['is_primary']);
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['processing_status', 'created_at']);
            $table->dropIndex(['image_type', 'sort_order']);
        });

        Schema::table('pricing', function (Blueprint $table) {
            $table->dropIndex(['currency']);
            $table->dropIndex(['marketplace', 'currency']);
            $table->dropIndex(['updated_at']);
        });

        if (Schema::hasTable('product_metadata')) {
            Schema::table('product_metadata', function (Blueprint $table) {
                $table->dropIndex(['key']);
                $table->dropIndex(['created_at']);
            });
        }

        Schema::table('marketplace_variants', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['status', 'last_synced_at']);
            $table->dropIndex(['created_at']);
        });

        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                if ($this->indexExists('categories', 'categories_name_index')) {
                    $table->dropIndex(['name']);
                }
                if ($this->indexExists('categories', 'categories_parent_id_index')) {
                    $table->dropIndex(['parent_id']);
                }
                if ($this->indexExists('categories', 'categories_status_index')) {
                    $table->dropIndex(['status']);
                }
            });
        }

        if (Schema::hasTable('sales_channels')) {
            Schema::table('sales_channels', function (Blueprint $table) {
                if ($this->indexExists('sales_channels', 'sales_channels_code_index')) {
                    $table->dropIndex(['code']);
                }
                if ($this->indexExists('sales_channels', 'sales_channels_status_index')) {
                    $table->dropIndex(['status']);
                }
            });
        }

        if (Schema::hasTable('file_processing_progress')) {
            Schema::table('file_processing_progress', function (Blueprint $table) {
                if ($this->indexExists('file_processing_progress', 'file_processing_progress_status_index')) {
                    $table->dropIndex(['status']);
                }
                if ($this->indexExists('file_processing_progress', 'file_processing_progress_created_at_index')) {
                    $table->dropIndex(['created_at']);
                }
            });
        }
    }

    /**
     * Helper method to check if index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        $indexes = collect(DB::select("PRAGMA index_list($table)"));
        return $indexes->pluck('name')->contains($index);
    }
};