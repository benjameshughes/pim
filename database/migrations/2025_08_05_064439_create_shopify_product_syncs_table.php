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
        Schema::create('shopify_product_syncs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id'); // Laravel product ID
            $table->string('color'); // Color variant (since we split by color)
            $table->string('shopify_product_id'); // Shopify product ID
            $table->string('shopify_handle')->nullable(); // Shopify product handle
            $table->string('sync_status')->default('synced'); // synced, failed, pending
            $table->json('last_sync_data')->nullable(); // Store last synced data for comparison
            $table->timestamp('last_synced_at');
            $table->timestamps();
            
            // Unique constraint to prevent duplicate syncs
            $table->unique(['product_id', 'color'], 'unique_product_color_sync');
            $table->index(['product_id', 'color']);
            $table->index('shopify_product_id');
            $table->index('sync_status');
            
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_product_syncs');
    }
};