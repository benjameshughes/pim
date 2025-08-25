<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create sync_logs table for tracking sync operations
     */
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();

            // What was synced
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('sync_account_id')->constrained()->onDelete('cascade');

            // Sync details
            $table->string('channel'); // "shopify", "ebay", etc.
            $table->string('operation'); // "create", "update", "delete"
            $table->string('sync_type')->default('product'); // "product", "pricing", "inventory"
            $table->string('status'); // "pending", "processing", "completed", "failed"

            // Tracking
            $table->string('external_id')->nullable(); // ID on the marketplace
            $table->text('request_data')->nullable(); // Data sent
            $table->text('response_data')->nullable(); // Response received
            $table->text('error_message')->nullable(); // Error details if failed
            $table->integer('duration_ms')->nullable(); // How long it took
            $table->integer('retry_count')->default(0); // Number of retries

            // Metadata
            $table->json('metadata')->nullable(); // Additional context

            $table->timestamps();

            // Indexes
            $table->index(['product_id', 'channel']);
            $table->index(['sync_account_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('channel');
            $table->index('operation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
