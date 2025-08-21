<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_value_lists', function (Blueprint $table) {
            $table->id();

            // Channel identification
            $table->string('channel_type', 50); // 'mirakl', 'shopify', 'ebay', 'amazon'
            $table->string('channel_subtype', 100)->nullable(); // 'freemans', 'debenhams' for Mirakl

            // Value list identification
            $table->string('list_code', 100); // The value list identifier
            $table->string('list_name')->nullable(); // Human-readable name
            $table->text('list_description')->nullable();

            // Values data
            $table->json('allowed_values'); // Array of valid values
            $table->json('value_metadata')->nullable(); // Additional value properties
            $table->integer('values_count'); // Count for quick reference

            // Discovery metadata
            $table->timestamp('discovered_at'); // When list was discovered
            $table->timestamp('last_synced_at')->nullable(); // Last sync with API
            $table->string('api_version')->nullable();
            $table->boolean('is_active')->default(true);

            // Sync status
            $table->string('sync_status')->default('pending'); // 'pending', 'synced', 'failed'
            $table->text('sync_error')->nullable(); // Last sync error
            $table->json('sync_metadata')->nullable(); // Sync details

            $table->timestamps();

            // Indexes
            $table->index(['channel_type', 'channel_subtype']);
            $table->index(['list_code', 'channel_type']);
            $table->index(['is_active', 'sync_status']);
            $table->index('last_synced_at');

            // Unique constraint
            $table->unique(['channel_type', 'channel_subtype', 'list_code'], 'channel_value_list_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_value_lists');
    }
};
