<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 🦋 SHOPIFY_SYNC_STATUS TABLE - THE PHOENIX WINGS
     *
     * Simple sync tracking - know what's been pushed to Shopify
     * No complex webhooks, no over-engineering, just sync status
     */
    public function up(): void
    {
        Schema::create('shopify_sync_status', function (Blueprint $table) {
            $table->id();

            // What we're syncing - both product and variant level tracking
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained()->onDelete('cascade');

            // Shopify IDs - what Shopify assigned to our items
            $table->string('shopify_product_id')->nullable(); // Shopify's product ID
            $table->string('shopify_variant_id')->nullable(); // Shopify's variant ID

            // Sync tracking - simple and focused
            $table->string('sync_status')->default('pending'); // pending, synced, failed
            $table->timestamp('last_synced_at')->nullable(); // When did we last sync
            $table->text('error_message')->nullable(); // If sync failed, why?

            $table->timestamps();

            // Indexes for performance
            $table->index('product_id');
            $table->index('product_variant_id');
            $table->index('sync_status');
            $table->index('shopify_product_id');
            $table->index('shopify_variant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_sync_status');
    }
};
