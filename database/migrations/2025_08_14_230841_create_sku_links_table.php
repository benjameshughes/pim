<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 🔗 SKU LINKING SYSTEM MIGRATION
     *
     * Creates tables for SKU-based marketplace product linking.
     * Integrates with existing sync system for unified management.
     */
    public function up(): void
    {
        // SKU Links - Core linking table
        Schema::create('sku_links', function (Blueprint $table) {
            $table->id();

            // Core relationships
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('sync_account_id')->constrained('sync_accounts')->onDelete('cascade');

            // SKU mapping
            $table->string('internal_sku'); // Our SKU
            $table->string('external_sku'); // Marketplace SKU
            $table->string('external_product_id')->nullable(); // Marketplace product ID

            // Link status and confidence
            $table->enum('link_status', ['linked', 'unlinked', 'pending', 'failed'])->default('pending');
            $table->decimal('confidence_score', 5, 2)->default(0.00); // 0.00 to 100.00
            $table->text('match_reason')->nullable(); // Why this was matched

            // Linking metadata
            $table->json('marketplace_data')->nullable(); // Store marketplace-specific data
            $table->timestamp('linked_at')->nullable();
            $table->string('linked_by')->nullable(); // User who created the link

            $table->timestamps();

            // Indexes for performance
            $table->index(['product_id', 'sync_account_id']);
            $table->index(['internal_sku', 'sync_account_id']);
            $table->index('external_sku');
            $table->index('link_status');
            $table->index('confidence_score');

            // Unique constraint - one link per product per marketplace
            $table->unique(['product_id', 'sync_account_id'], 'unique_product_marketplace_link');
        });

        // SKU Mapping Rules - Configuration for SKU transformations
        Schema::create('sku_mapping_rules', function (Blueprint $table) {
            $table->id();

            // Marketplace this rule applies to
            $table->string('marketplace'); // 'shopify', 'ebay', 'amazon'

            // Rule definition
            $table->string('pattern'); // Regex pattern to match
            $table->string('transformation')->nullable(); // How to transform the SKU
            $table->text('description')->nullable(); // Human-readable description

            // Rule priority and status
            $table->integer('priority')->default(100); // Lower number = higher priority
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Indexes
            $table->index(['marketplace', 'is_active']);
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sku_mapping_rules');
        Schema::dropIfExists('sku_links');
    }
};
