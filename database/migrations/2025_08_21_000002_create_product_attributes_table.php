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
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            
            // Core relationships
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('attribute_definition_id')->constrained()->onDelete('cascade');
            
            // Attribute value storage
            $table->text('value')->nullable(); // Flexible storage for any data type
            $table->string('display_value')->nullable(); // Formatted value for display
            $table->json('value_metadata')->nullable(); // Additional context about the value
            
            // Validation and quality
            $table->boolean('is_valid')->default(true); // Whether value passes validation
            $table->json('validation_errors')->nullable(); // Validation error details
            $table->timestamp('last_validated_at')->nullable(); // When validation was last run
            
            // Source and audit trail
            $table->string('source')->default('manual'); // manual, import, api, inheritance
            $table->timestamp('assigned_at')->nullable(); // When value was assigned
            $table->string('assigned_by')->nullable(); // Who/what assigned this value
            $table->json('assignment_metadata')->nullable(); // Additional assignment context
            
            // Marketplace sync tracking
            $table->json('sync_status')->nullable(); // Status per marketplace
            $table->timestamp('last_synced_at')->nullable(); // When last synced to any marketplace
            $table->json('sync_metadata')->nullable(); // Marketplace-specific data
            
            // Change tracking
            $table->text('previous_value')->nullable(); // Previous value for change tracking
            $table->timestamp('value_changed_at')->nullable(); // When value was last changed
            $table->integer('version')->default(1); // Value version for history
            
            $table->timestamps();
            
            // Indexes for performance
            $table->unique(['product_id', 'attribute_definition_id']); // One value per attribute per product
            $table->index(['product_id', 'is_valid']); // For getting valid attributes
            $table->index(['attribute_definition_id', 'value']); // For searching by attribute value
            $table->index(['source'], 'pa_source_idx'); // For filtering by source
            $table->index(['last_synced_at'], 'pa_synced_at_idx'); // For sync operations
            $table->index(['is_valid', 'last_validated_at']); // For validation operations
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_attributes');
    }
};