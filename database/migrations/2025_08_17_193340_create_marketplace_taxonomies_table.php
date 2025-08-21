<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 🏷️ CREATE MARKETPLACE TAXONOMIES CACHE TABLE
     *
     * This table caches taxonomy data (categories, attributes, values) from all marketplaces.
     * Data is synced monthly via scheduled job to keep forms fast and responsive.
     *
     * Supports hierarchical taxonomy structures and extensible metadata for any marketplace.
     */
    public function up(): void
    {
        Schema::create('marketplace_taxonomies', function (Blueprint $table) {
            $table->id();

            // Marketplace reference
            $table->foreignId('sync_account_id')->constrained()->onDelete('cascade');

            // Taxonomy classification
            $table->enum('taxonomy_type', [
                'category',      // Product categories (H11 equivalent)
                'attribute',     // Product attributes (PM11 equivalent)
                'value',         // Attribute values (VL11 equivalent)
                'collection',    // Smart collections/rules
                'tag',           // Product tags/labels
            ]);

            // External identifiers
            $table->string('external_id');                    // Marketplace's internal ID
            $table->string('external_parent_id')->nullable(); // Parent taxonomy item ID

            // Basic information
            $table->string('name');                          // Display name
            $table->string('key')->nullable();              // API key/handle (for attributes)
            $table->text('description')->nullable();        // Description/help text

            // Hierarchy support
            $table->unsignedInteger('level')->default(1);   // Depth in hierarchy (1 = root)
            $table->boolean('is_leaf')->default(false);     // Has no children
            $table->boolean('is_required')->default(false); // Required for products

            // Type information (for attributes)
            $table->string('data_type')->nullable();        // text, number, boolean, list, etc.
            $table->json('validation_rules')->nullable();   // Min/max, regex, choices, etc.

            // Rich metadata
            $table->json('metadata')->nullable();           // Full API response, properties, etc.
            $table->json('properties')->nullable();         // Parsed properties (use_cases, popularity, etc.)

            // Sync tracking
            $table->timestamp('last_synced_at');           // When this item was last synced
            $table->boolean('is_active')->default(true);   // Enabled for use
            $table->string('sync_version')->nullable();    // API version used for sync

            $table->timestamps();

            // Performance indexes
            $table->index(['sync_account_id', 'taxonomy_type']);
            $table->index(['sync_account_id', 'taxonomy_type', 'is_active']);
            $table->index(['taxonomy_type', 'external_id']);
            $table->index(['sync_account_id', 'external_parent_id']);
            $table->index(['level', 'is_leaf']);
            $table->index('last_synced_at');

            // Unique constraint per marketplace
            $table->unique(['sync_account_id', 'taxonomy_type', 'external_id'], 'unique_marketplace_taxonomy');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_taxonomies');
    }
};
