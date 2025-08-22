<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create sync_statuses table for current sync state
     */
    public function up(): void
    {
        Schema::create('sync_statuses', function (Blueprint $table) {
            $table->id();
            
            // What's synced
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('sync_account_id')->constrained()->onDelete('cascade');
            
            // Current status
            $table->string('channel'); // "shopify", "ebay", etc.
            $table->string('status'); // "never_synced", "synced", "needs_update", "sync_failed"
            $table->string('external_id')->nullable(); // ID on the marketplace
            
            // Sync tracking
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->text('last_error')->nullable();
            $table->integer('error_count')->default(0);
            
            // Data checksums for change detection
            $table->string('product_checksum')->nullable(); // Hash of product data
            $table->string('pricing_checksum')->nullable(); // Hash of pricing data
            $table->string('inventory_checksum')->nullable(); // Hash of inventory data
            
            // Metadata
            $table->json('sync_metadata')->nullable(); // Channel-specific data
            
            $table->timestamps();
            
            // Unique constraint - one status per product per channel
            $table->unique(['product_id', 'sync_account_id']);
            
            // Indexes
            $table->index(['channel', 'status']);
            $table->index('last_synced_at');
            $table->index('external_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_statuses');
    }
};