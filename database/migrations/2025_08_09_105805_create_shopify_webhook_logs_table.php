<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * 📡 WEBHOOK EVENT LOGGING TABLE 📡
     * For tracking all incoming Shopify webhook events like a notification historian!
     */
    public function up(): void
    {
        Schema::create('shopify_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('topic'); // products/create, products/update, etc.
            $table->string('shopify_product_id')->nullable();
            $table->string('shopify_variant_id')->nullable();
            $table->json('payload'); // Full webhook payload
            $table->json('headers')->nullable(); // Request headers
            $table->timestamp('processed_at')->nullable();
            $table->string('processing_status')->default('pending'); // pending, success, failed, error
            $table->json('processing_result')->nullable(); // Result of processing
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('related_product_id')->nullable(); // Link to our Product
            $table->timestamp('event_timestamp')->nullable(); // When event happened in Shopify
            $table->string('webhook_id')->nullable(); // X-Shopify-Webhook-Id header
            $table->boolean('signature_verified')->default(false);
            $table->timestamps();
            
            // Indexes for performance
            $table->index('topic');
            $table->index('shopify_product_id');
            $table->index('processing_status');
            $table->index('processed_at');
            $table->index('event_timestamp');
            $table->index(['topic', 'processing_status'], 'topic_status_index');
            $table->index(['created_at', 'processing_status'], 'created_status_index');
            
            // Foreign key
            $table->foreign('related_product_id')->references('id')->on('products')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_webhook_logs');
    }
};