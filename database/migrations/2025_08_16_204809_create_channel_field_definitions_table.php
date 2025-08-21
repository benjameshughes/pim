<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_field_definitions', function (Blueprint $table) {
            $table->id();

            // Channel identification
            $table->string('channel_type'); // 'mirakl', 'shopify', 'ebay', 'amazon'
            $table->string('channel_subtype')->nullable(); // 'freemans', 'debenhams' for Mirakl
            $table->string('category')->nullable(); // Category-specific fields

            // Field definition
            $table->string('field_code'); // API field name
            $table->string('field_label'); // Human-readable label
            $table->string('field_type'); // TEXT, LIST, MEDIA, MEASUREMENT, etc.
            $table->boolean('is_required')->default(false);
            $table->text('description')->nullable();
            $table->json('field_metadata')->nullable(); // Extra field properties

            // Validation and values
            $table->json('validation_rules')->nullable(); // Field validation rules
            $table->json('allowed_values')->nullable(); // For LIST fields
            $table->string('value_list_code')->nullable(); // Reference to value list

            // Discovery metadata
            $table->timestamp('discovered_at'); // When field was discovered
            $table->timestamp('last_verified_at')->nullable(); // Last API verification
            $table->string('api_version')->nullable(); // API version when discovered
            $table->boolean('is_active')->default(true); // Field still exists

            $table->timestamps();

            // Indexes for performance
            $table->index(['channel_type', 'channel_subtype', 'category']);
            $table->index(['field_code', 'channel_type']);
            $table->index(['is_required', 'is_active']);

            // Unique constraint
            $table->unique(['channel_type', 'channel_subtype', 'category', 'field_code'], 'channel_field_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_field_definitions');
    }
};
