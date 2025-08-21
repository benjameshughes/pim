<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 🔗 CREATE FRESH MARKETPLACE LINKS TABLE
     *
     * Create a clean marketplace_links table with polymorphic support
     * for both product-level and variant-level linking with hierarchical relationships.
     */
    public function up(): void
    {
        // Only create if table doesn't exist
        if (! Schema::hasTable('marketplace_links')) {
            Schema::create('marketplace_links', function (Blueprint $table) {
                $table->id();

                // Polymorphic fields for linking both products and variants
                $table->string('linkable_type'); // Product or ProductVariant
                $table->unsignedBigInteger('linkable_id');

                // Marketplace account reference
                $table->foreignId('sync_account_id')->constrained()->onDelete('cascade');

                // Hierarchy support - variant links can reference their parent product link
                $table->foreignId('parent_link_id')->nullable()
                    ->constrained('marketplace_links')->onDelete('cascade');

                // SKU identifiers
                $table->string('internal_sku');
                $table->string('external_sku');

                // Enhanced marketplace identifiers
                $table->string('external_product_id')->nullable();
                $table->string('external_variant_id')->nullable();

                // Status tracking
                $table->enum('link_status', ['pending', 'linked', 'failed', 'unlinked'])
                    ->default('pending');
                $table->enum('link_level', ['product', 'variant'])->default('product');

                // Additional data
                $table->json('marketplace_data')->nullable();
                $table->timestamp('linked_at')->nullable();
                $table->string('linked_by')->nullable();

                $table->timestamps();

                // Performance indexes
                $table->index(['linkable_type', 'linkable_id']);
                $table->index('parent_link_id');
                $table->index(['sync_account_id', 'link_status']);
                $table->index(['sync_account_id', 'link_level']);
                $table->index('external_sku');
                $table->index(['internal_sku', 'sync_account_id']);
                $table->index('link_status');

                // Unique constraint for polymorphic marketplace links
                $table->unique(['linkable_type', 'linkable_id', 'sync_account_id'], 'unique_polymorphic_marketplace_link');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_links');
    }
};
