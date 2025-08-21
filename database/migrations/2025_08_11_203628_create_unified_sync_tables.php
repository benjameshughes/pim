<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 🔄 UNIFIED SYNC SYSTEM TABLES
     *
     * Creates a comprehensive sync tracking system that works across
     * all integrations: Shopify, eBay, Amazon, Mirakl, etc.
     */
    public function up(): void
    {
        // 1. Sync Accounts - Multi-account support (eBay UK/US, multiple Shopify stores)
        Schema::create('sync_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // 'main_shopify', 'ebay_uk', 'ebay_us'
            $table->string('channel'); // 'shopify', 'ebay', 'amazon'
            $table->string('display_name'); // 'Main Shopify Store', 'eBay UK'
            $table->boolean('is_active')->default(true);
            $table->json('credentials')->nullable(); // Encrypted API keys, tokens
            $table->json('settings')->nullable(); // Channel-specific settings
            $table->timestamps();

            $table->unique(['name', 'channel']);
            $table->index('channel');
            $table->index('is_active');
        });

        // 2. Unified Sync Status - The single source of truth
        Schema::create('sync_statuses', function (Blueprint $table) {
            $table->id();

            // What we're syncing
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('sync_account_id')->constrained('sync_accounts')->onDelete('cascade');

            // External system identifiers
            $table->string('external_product_id')->nullable(); // Shopify/eBay product ID
            $table->string('external_variant_id')->nullable(); // Shopify/eBay variant ID
            $table->string('external_handle')->nullable(); // Shopify handle, eBay listing ID

            // Sync tracking
            $table->enum('sync_status', ['pending', 'synced', 'failed', 'out_of_sync'])->default('pending');
            $table->timestamp('last_synced_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable(); // Channel-specific data

            // Color-specific sync (for Shopify color separation)
            $table->string('color')->nullable(); // Which color this sync record represents
            $table->string('sync_type')->default('standard'); // 'standard', 'color_separated'

            $table->timestamps();

            // Indexes for performance
            $table->index(['product_id', 'sync_account_id']);
            $table->index(['sync_account_id', 'sync_status']);
            $table->index('external_product_id');
            $table->index('color');
            $table->index('sync_type');
        });

        // 3. Comprehensive Sync Logs - Audit trail for all sync operations
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();

            // What was synced
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('sync_account_id')->constrained('sync_accounts')->onDelete('cascade');
            $table->foreignId('sync_status_id')->nullable()->constrained('sync_statuses')->onDelete('set null');

            // Sync operation details
            $table->string('action'); // 'push', 'pull', 'create', 'update', 'delete'
            $table->enum('status', ['started', 'success', 'failed', 'warning']);
            $table->text('message')->nullable();
            $table->json('details')->nullable(); // Full sync operation details

            // Performance tracking
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable(); // Milliseconds

            // Batch operations
            $table->string('batch_id')->nullable(); // For bulk operations
            $table->integer('items_processed')->default(1);
            $table->integer('items_successful')->default(0);
            $table->integer('items_failed')->default(0);

            $table->timestamps();

            // Indexes for performance and reporting
            $table->index(['sync_account_id', 'created_at']);
            $table->index(['product_id', 'action']);
            $table->index(['status', 'created_at']);
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
        Schema::dropIfExists('sync_statuses');
        Schema::dropIfExists('sync_accounts');
    }
};
