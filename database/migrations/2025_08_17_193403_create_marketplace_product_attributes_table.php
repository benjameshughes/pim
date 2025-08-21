<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 🔗 CREATE MARKETPLACE PRODUCT ATTRIBUTES TABLE
     *
     * This table stores the assignments of marketplace-specific attributes to products.
     * Links products to their marketplace taxonomy attributes without touching core product data.
     *
     * Supports both single values and complex multi-value attributes with full metadata.
     */
    public function up(): void
    {
        Schema::create('marketplace_product_attributes', function (Blueprint $table) {
            $table->id();

            // Product reference (can extend to variants later)
            $table->foreignId('product_id')->constrained()->onDelete('cascade');

            // Marketplace reference
            $table->foreignId('sync_account_id')->constrained()->onDelete('cascade');

            // Attribute reference (from marketplace_taxonomies)
            $table->foreignId('marketplace_taxonomy_id')->constrained()->onDelete('cascade');

            // Attribute identification
            $table->string('attribute_key');              // e.g., 'material', 'color', 'size'
            $table->string('attribute_name');            // e.g., 'Material', 'Color', 'Size'

            // Value storage (flexible for different data types)
            $table->text('attribute_value');             // Main value (JSON for complex types)
            $table->string('display_value')->nullable(); // Human-readable display value

            // Type information
            $table->string('data_type')->default('text'); // text, number, boolean, list, dimension
            $table->boolean('is_required')->default(false);

            // Rich metadata
            $table->json('value_metadata')->nullable();   // Properties, validation results, etc.
            $table->json('sync_metadata')->nullable();    // Sync status, last updated, etc.

            // Tracking
            $table->timestamp('assigned_at');            // When attribute was assigned
            $table->string('assigned_by')->nullable();   // User who assigned it
            $table->timestamp('last_validated_at')->nullable(); // Last validation check
            $table->boolean('is_valid')->default(true);  // Passes current validation rules

            $table->timestamps();

            // Performance indexes
            $table->index(['product_id', 'sync_account_id']);
            $table->index(['sync_account_id', 'attribute_key']);
            $table->index(['marketplace_taxonomy_id']);
            $table->index(['product_id', 'attribute_key']);
            $table->index(['is_valid', 'is_required']);
            $table->index('assigned_at');

            // Unique constraint - one attribute per product per marketplace
            $table->unique(['product_id', 'sync_account_id', 'attribute_key'], 'unique_product_marketplace_attribute');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_product_attributes');
    }
};
