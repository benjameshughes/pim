<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create marketplace_links table for linking products/variants to marketplace listings
     */
    public function up(): void
    {
        Schema::create('marketplace_links', function (Blueprint $table) {
            $table->id();
            
            // Polymorphic relationship (can link to products or variants)
            $table->string('linkable_type'); // "App\Models\Product" or "App\Models\ProductVariant"
            $table->unsignedBigInteger('linkable_id');
            
            // Marketplace info
            $table->foreignId('sync_account_id')->constrained()->onDelete('cascade');
            $table->string('channel'); // "shopify", "ebay", "amazon"
            $table->string('external_id'); // ID on the marketplace
            $table->string('external_sku')->nullable(); // SKU on the marketplace
            
            // Link metadata
            $table->string('link_type')->default('automatic'); // "manual", "automatic", "suggested"
            $table->string('link_level'); // "product", "variant"
            $table->string('status')->default('active'); // "active", "inactive", "broken"
            
            // Marketplace-specific data
            $table->string('marketplace_url')->nullable(); // Direct URL to listing
            $table->json('marketplace_data')->nullable(); // Additional marketplace-specific data
            
            // Tracking
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->text('last_error')->nullable();
            
            // User tracking
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            
            // Indexes
            $table->index(['linkable_type', 'linkable_id']);
            $table->index(['sync_account_id', 'external_id']);
            $table->index(['channel', 'status']);
            $table->unique(['linkable_type', 'linkable_id', 'sync_account_id'], 'unique_marketplace_link');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_links');
    }
};