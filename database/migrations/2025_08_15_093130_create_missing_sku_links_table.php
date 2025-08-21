<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 🔗 CREATE MISSING SKU LINKS TABLE
     *
     * Create the sku_links table for legacy SKU linking support.
     * This table works alongside marketplace_links for backward compatibility.
     */
    public function up(): void
    {
        // Only create if table doesn't exist
        if (! Schema::hasTable('sku_links')) {
            Schema::create('sku_links', function (Blueprint $table) {
                $table->id();

                // Product and sync account references
                $table->foreignId('product_id')->constrained()->onDelete('cascade');
                $table->foreignId('sync_account_id')->constrained()->onDelete('cascade');

                // SKU identifiers
                $table->string('internal_sku');
                $table->string('external_sku');
                $table->string('external_product_id')->nullable();

                // Status tracking
                $table->enum('link_status', ['pending', 'linked', 'failed', 'unlinked'])
                    ->default('pending');

                // Additional data
                $table->json('marketplace_data')->nullable();
                $table->timestamp('linked_at')->nullable();
                $table->string('linked_by')->nullable();

                $table->timestamps();

                // Indexes for performance
                $table->index(['product_id', 'sync_account_id']);
                $table->index('external_sku');
                $table->index('link_status');

                // Unique constraint to prevent duplicate links
                $table->unique(['product_id', 'sync_account_id'], 'unique_product_sync_account');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sku_links');
    }
};
